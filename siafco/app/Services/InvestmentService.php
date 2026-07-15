<?php

namespace App\Services;

use App\Models\InvestmentLot;
use App\Models\InvestmentReceipt;
use App\Models\InvestmentReturnPeriod;
use App\Models\InvestmentSetting;
use App\Models\Investor;
use App\Models\InvestorType;
use App\Models\Person;
use App\Models\ShareReservation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InvestmentService
{
    public function firstOrCreatePerson(array $data): Person
    {
        $person = Person::where('ci', $data['ci'])->first();

        if ($person) {
            $person->fill(array_filter([
                'full_name' => $data['full_name'] ?? null,
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
                'address' => $data['address'] ?? null,
                'birth_date' => $data['birth_date'] ?? null,
                'marital_status' => $data['marital_status'] ?? null,
                'photo' => $data['photo'] ?? null,
            ], fn ($value) => filled($value)))->save();

            return $person;
        }

        return Person::create($data);
    }

    public function createInvestor(array $personData, array $investorData): Investor
    {
        return DB::transaction(function () use ($personData, $investorData) {
            $person = $this->firstOrCreatePerson($personData);

            if ($person->investor) {
                throw ValidationException::withMessages([
                    'ci' => 'Esta persona ya esta registrada como accionista.',
                ]);
            }

            $investor = Investor::create([
                'person_id' => $person->id,
                'investor_number' => $this->nextInvestorNumber(),
                'status' => $investorData['status'] ?? 'prospect',
                'start_date' => $investorData['start_date'] ?? now()->toDateString(),
                'notes' => $investorData['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            AuditService::record('accionista.creado', $investor);

            return $investor;
        });
    }

    public function createReservation(Investor $investor, array $data): ShareReservation
    {
        $setting = InvestmentSetting::current();
        $this->validateShareQuantity($investor, (int) $data['shares_quantity'], $setting);

        return DB::transaction(function () use ($investor, $data, $setting) {
            $reservationDate = CarbonImmutable::parse($data['reservation_date'] ?? now());
            $quantity = (int) $data['shares_quantity'];
            $unitPrice = (float) $setting->share_unit_price;

            $reservation = ShareReservation::create([
                'investor_id' => $investor->id,
                'shares_quantity' => $quantity,
                'share_unit_price' => $unitPrice,
                'total_amount' => $quantity * $unitPrice,
                'reservation_date' => $reservationDate->toDateString(),
                'expiration_date' => $reservationDate->addDays((int) $setting->reservation_days)->toDateString(),
                'amount_paid' => $data['amount_paid'] ?? 0,
                'payment_reference' => $data['payment_reference'] ?? null,
                'payment_method' => $data['payment_method'] ?? null,
                'status' => $data['status'] ?? 'active',
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            $investor->update(['status' => 'reserved']);
            AuditService::record('reserva.creada', $reservation);

            return $reservation;
        });
    }

    public function createLot(Investor $investor, array $data, ?ShareReservation $reservation = null): InvestmentLot
    {
        $setting = InvestmentSetting::current();
        $quantity = (int) ($data['shares_quantity'] ?? $reservation?->shares_quantity);
        $this->validateShareQuantity($investor, $quantity, $setting);

        if ($reservation && (float) $reservation->amount_paid < (float) $reservation->total_amount) {
            throw ValidationException::withMessages(['amount_paid' => 'La reserva debe estar pagada completamente para convertirse en inversion.']);
        }

        return DB::transaction(function () use ($investor, $data, $reservation, $setting, $quantity) {
            $purchaseDate = CarbonImmutable::parse($data['purchase_date'] ?? now());
            $unitPrice = (float) ($reservation?->share_unit_price ?? $setting->share_unit_price);
            $capital = $quantity * $unitPrice;
            $maturity = $purchaseDate->addMonthsNoOverflow((int) $setting->waiting_months);
            $contractEnd = $maturity->addYears((int) $setting->contract_years);

            $lot = InvestmentLot::create([
                'investor_id' => $investor->id,
                'reservation_id' => $reservation?->id,
                'purchase_number' => $this->nextPurchaseNumber(),
                'purchase_date' => $purchaseDate->toDateString(),
                'shares_quantity' => $quantity,
                'share_unit_price' => $unitPrice,
                'invested_capital' => $capital,
                'return_percentage' => $setting->monthly_return_percentage,
                'waiting_months' => $setting->waiting_months,
                'contract_years' => $setting->contract_years,
                'maturity_date' => $maturity->toDateString(),
                'contract_end_date' => $contractEnd->toDateString(),
                'status' => 'pending_approval',
                'payment_method' => $data['payment_method'],
                'payment_reference' => $data['payment_reference'] ?? null,
                'payment_receipt' => $data['payment_receipt'] ?? null,
                'settings_snapshot' => $setting->only([
                    'share_unit_price',
                    'monthly_return_percentage',
                    'waiting_months',
                    'contract_years',
                    'reservation_days',
                ]),
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            if ($reservation) {
                $reservation->update(['status' => 'converted']);
            }

            AuditService::record('inversion.creada', $lot);

            return $lot;
        });
    }

    public function approveLot(InvestmentLot $lot): InvestmentLot
    {
        return DB::transaction(function () use ($lot) {
            $lot = InvestmentLot::lockForUpdate()->findOrFail($lot->id);

            if ($lot->status !== 'pending_approval') {
                return $lot;
            }

            $lot->update([
                'status' => $lot->maturity_date->isFuture() ? 'active_waiting' : 'active_earning',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);

            $this->generateReturnPeriods($lot);
            $this->refreshInvestorType($lot->investor);
            $lot->investor->update(['status' => 'active']);
            AuditService::record('inversion.aprobada', $lot);

            return $lot;
        });
    }

    public function generateReturnPeriods(InvestmentLot $lot): void
    {
        $start = CarbonImmutable::parse($lot->maturity_date);
        $baseReturn = round((float) $lot->invested_capital * (float) $lot->return_percentage / 100, 2);

        for ($i = 1; $i <= 36; $i++) {
            $dueDate = $start->addMonthsNoOverflow($i - 1);

            InvestmentReturnPeriod::firstOrCreate(
                [
                    'investment_lot_id' => $lot->id,
                    'period_year' => (int) $dueDate->format('Y'),
                    'period_month' => (int) $dueDate->format('m'),
                ],
                [
                    'period_number' => $i,
                    'due_date' => $dueDate->toDateString(),
                    'invested_capital_snapshot' => $lot->invested_capital,
                    'return_percentage_snapshot' => $lot->return_percentage,
                    'base_return_amount' => $baseReturn,
                    'total_amount' => $baseReturn,
                    'status' => $dueDate->isPast() || $dueDate->isToday() ? 'pending' : 'upcoming',
                ]
            );
        }
    }

    public function preparePeriod(InvestmentReturnPeriod $period, array $data): InvestmentReturnPeriod
    {
        if (in_array($period->status, ['approved', 'paid'], true)) {
            throw ValidationException::withMessages(['period' => 'No se puede modificar un rendimiento aprobado o pagado.']);
        }

        $bonus = (float) ($data['production_bonus_amount'] ?? 0);
        $extra = (float) ($data['extra_amount'] ?? 0);
        $deductions = (float) ($data['deductions_amount'] ?? 0);

        $period->update([
            'production_bonus_amount' => $bonus,
            'extra_concept' => $data['extra_concept'] ?? null,
            'extra_amount' => $extra,
            'deductions_amount' => $deductions,
            'total_amount' => (float) $period->base_return_amount + $bonus + $extra - $deductions,
            'status' => 'pending_approval',
            'prepared_by' => auth()->id(),
            'prepared_at' => now(),
            'notes' => $data['notes'] ?? $period->notes,
        ]);

        AuditService::record('rendimiento.preparado', $period);

        return $period;
    }

    public function approvePeriod(InvestmentReturnPeriod $period): InvestmentReturnPeriod
    {
        if ($period->status !== 'pending_approval') {
            throw ValidationException::withMessages(['period' => 'El rendimiento debe estar pendiente de aprobacion.']);
        }

        $period->update([
            'status' => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        AuditService::record('rendimiento.aprobado', $period);

        return $period;
    }

    public function issueReceipt(InvestmentReturnPeriod $period, array $data): InvestmentReceipt
    {
        return DB::transaction(function () use ($period, $data) {
            $period = InvestmentReturnPeriod::with('lot.investor.person')->lockForUpdate()->findOrFail($period->id);

            if ($period->status !== 'approved') {
                throw ValidationException::withMessages(['period' => 'Debe aprobar el rendimiento antes de emitir recibo.']);
            }

            if ($period->receipt_id) {
                throw ValidationException::withMessages(['period' => 'Este periodo ya tiene recibo emitido.']);
            }

            $setting = InvestmentSetting::query()->where('active', true)->lockForUpdate()->latest('id')->firstOrFail();
            $receiptNumber = sprintf('%s-%s-%06d', $setting->receipt_prefix, now()->format('Y'), $setting->next_receipt_number);
            $setting->increment('next_receipt_number');
            InvestmentSetting::clearCurrentCache();

            $lot = $period->lot;
            $investor = $lot->investor;
            $person = $investor->person;

            $receipt = InvestmentReceipt::create([
                'receipt_number' => $receiptNumber,
                'investor_id' => $investor->id,
                'investment_lot_id' => $lot->id,
                'return_period_id' => $period->id,
                'issue_date' => now()->toDateString(),
                'company_name_snapshot' => $setting->company_name,
                'company_nit_snapshot' => $setting->nit,
                'company_address_snapshot' => $setting->address,
                'company_phone_snapshot' => $setting->phone,
                'company_email_snapshot' => $setting->email,
                'logo_path_snapshot' => $setting->receipt_logo,
                'investor_name_snapshot' => $person->full_name,
                'investor_ci_snapshot' => $person->ci,
                'investor_number_snapshot' => $investor->investor_number,
                'purchase_number_snapshot' => $lot->purchase_number,
                'shares_quantity_snapshot' => $lot->shares_quantity,
                'share_unit_price_snapshot' => $lot->share_unit_price,
                'invested_capital_snapshot' => $lot->invested_capital,
                'return_percentage_snapshot' => $lot->return_percentage,
                'base_return_amount' => $period->base_return_amount,
                'production_bonus_amount' => $period->production_bonus_amount,
                'extra_concept' => $period->extra_concept,
                'extra_amount' => $period->extra_amount,
                'deductions_amount' => $period->deductions_amount,
                'total_amount' => $period->total_amount,
                'payment_method' => $data['payment_method'],
                'payment_reference' => $data['payment_reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'verification_token' => Str::uuid()->toString(),
                'status' => 'paid',
                'approved_by' => $period->approved_by,
                'approved_at' => $period->approved_at,
                'issued_by' => auth()->id(),
                'issued_at' => now(),
            ]);

            $period->update([
                'status' => 'paid',
                'paid_by' => auth()->id(),
                'paid_at' => now(),
                'receipt_id' => $receipt->id,
            ]);

            AuditService::record('recibo.emitido', $receipt);

            return $receipt;
        });
    }

    public function refreshInvestorType(Investor $investor): void
    {
        $shares = $investor->activeShares();
        $type = InvestorType::where('shares_quantity', $shares)->where('active', true)->first();
        $investor->update(['investor_type_id' => $type?->id]);
    }

    private function validateShareQuantity(Investor $investor, int $quantity, InvestmentSetting $setting): void
    {
        if ($quantity < $setting->minimum_shares || $quantity > $setting->maximum_shares) {
            throw ValidationException::withMessages([
                'shares_quantity' => "La cantidad debe estar entre {$setting->minimum_shares} y {$setting->maximum_shares} acciones.",
            ]);
        }

        if ($setting->maximum_shares_per_person && ($investor->activeShares() + $quantity) > $setting->maximum_shares) {
            throw ValidationException::withMessages([
                'shares_quantity' => "El maximo activo por persona es de {$setting->maximum_shares} acciones.",
            ]);
        }
    }

    private function nextInvestorNumber(): string
    {
        $next = (int) Investor::query()->lockForUpdate()->selectRaw('COALESCE(MAX(id), 0) + 1 as next_number')->value('next_number');

        return sprintf('ACC-%06d', $next);
    }

    private function nextPurchaseNumber(): string
    {
        $next = (int) InvestmentLot::query()->lockForUpdate()->selectRaw('COALESCE(MAX(id), 0) + 1 as next_number')->value('next_number');

        return sprintf('INV-%06d', $next);
    }
}
