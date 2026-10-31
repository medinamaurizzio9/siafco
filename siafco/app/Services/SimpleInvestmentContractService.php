<?php

namespace App\Services;

use App\Models\SimpleInvestmentContract;
use App\Models\SimpleInvestmentSetting;
use App\Models\SimpleInvestmentUnit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SimpleInvestmentContractService
{
    public function __construct(private readonly SimpleInvestmentSequenceService $sequences) {}

    public function create(array $data, User $actor): SimpleInvestmentContract
    {
        return DB::transaction(function () use ($data, $actor) {
            $settings = SimpleInvestmentSetting::query()->lockForUpdate()->firstOrFail();
            $quantity = (int) $data['quantity'];
            if (! $settings->is_active || $quantity < $settings->minimum_units || $quantity > $settings->maximum_units) {
                throw ValidationException::withMessages(['quantity' => 'La cantidad no está permitida por la configuración vigente.']);
            }
            $unitCents = $this->toCents($settings->unit_price);
            $contract = SimpleInvestmentContract::create([
                'contract_number' => $this->sequences->next('contract', 'CON'),
                'investor_id' => $data['investor_id'],
                'product_name_snapshot' => $settings->product_name,
                'quantity' => $quantity,
                'unit_price_snapshot' => $this->fromCents($unitCents),
                'total_amount' => $this->fromCents($unitCents * $quantity),
                'currency' => $settings->currency,
                'registered_on' => $data['registered_on'],
                'status' => SimpleInvestmentContract::DRAFT,
                'responsible_user_id' => $actor->id,
                'notes' => $data['notes'] ?? null,
            ]);
            AuditService::record('simple_investment_contract_created', $contract, ['contract_number' => $contract->contract_number]);

            return $contract;
        });
    }

    public function markPending(SimpleInvestmentContract $contract): SimpleInvestmentContract
    {
        return DB::transaction(function () use ($contract) {
            // Serialize every state transition on the same current contract row.
            $locked = SimpleInvestmentContract::query()->lockForUpdate()->findOrFail($contract->id);
            if ($locked->status !== SimpleInvestmentContract::DRAFT) {
                throw ValidationException::withMessages(['status' => 'Solo un contrato en borrador puede pasar a pendiente de firma.']);
            }
            $locked->update(['status' => SimpleInvestmentContract::PENDING_SIGNATURE]);
            AuditService::record('simple_investment_contract_status_changed', $locked, ['status' => $locked->status]);

            return $locked;
        });
    }

    public function sign(SimpleInvestmentContract $contract, User $actor, string $signedOn): SimpleInvestmentContract
    {
        return DB::transaction(function () use ($contract, $actor, $signedOn) {
            $locked = SimpleInvestmentContract::query()->lockForUpdate()->findOrFail($contract->id);
            if ($locked->status === SimpleInvestmentContract::SIGNED) {
                return $locked->load('units');
            }
            if ($locked->status !== SimpleInvestmentContract::PENDING_SIGNATURE) {
                throw ValidationException::withMessages(['status' => 'El contrato debe estar pendiente de firma.']);
            }
            $settings = SimpleInvestmentSetting::query()->lockForUpdate()->firstOrFail();
            for ($position = 1; $position <= $locked->quantity; $position++) {
                SimpleInvestmentUnit::create([
                    'code' => $this->sequences->next('unit', 'ACC'), 'contract_id' => $locked->id,
                    'investor_id' => $locked->investor_id, 'position' => $position, 'issued_at' => now(),
                ]);
            }
            $locked->update([
                'status' => SimpleInvestmentContract::SIGNED,
                'signed_on' => $signedOn,
                'first_schedule_days_snapshot' => $settings->first_schedule_days,
                'schedule_interval_days_snapshot' => $settings->schedule_interval_days,
                'schedule_projection_count_snapshot' => max(1, (int) config('simple_investments.projection_count', 12)),
                'schedule_timezone_snapshot' => config('simple_investments.schedule_timezone', 'America/La_Paz'),
                'signed_by' => $actor->id,
                'signed_at' => now(),
            ]);
            AuditService::record('simple_investment_contract_signed', $locked, ['contract_number' => $locked->contract_number, 'unit_count' => $locked->quantity]);

            return $locked->load('units');
        });
    }

    public function void(SimpleInvestmentContract $contract, User $actor, string $reason): SimpleInvestmentContract
    {
        return DB::transaction(function () use ($contract, $actor, $reason) {
            $locked = SimpleInvestmentContract::query()->lockForUpdate()->findOrFail($contract->id);
            if ($locked->status === SimpleInvestmentContract::VOID) {
                return $locked;
            }
            if (! in_array($locked->status, [SimpleInvestmentContract::DRAFT, SimpleInvestmentContract::PENDING_SIGNATURE, SimpleInvestmentContract::SIGNED], true)) {
                throw ValidationException::withMessages(['status' => 'El estado actual del contrato no permite la anulación.']);
            }
            $locked->update(['status' => SimpleInvestmentContract::VOID, 'voided_by' => $actor->id, 'voided_at' => now(), 'void_reason' => $reason]);
            AuditService::record('simple_investment_contract_voided', $locked, ['contract_number' => $locked->contract_number, 'reason' => $reason]);

            return $locked;
        });
    }

    private function toCents(string $amount): int
    {
        [$whole, $decimal] = array_pad(explode('.', $amount, 2), 2, '');

        return ((int) $whole * 100) + (int) str_pad(substr($decimal, 0, 2), 2, '0');
    }

    private function fromCents(int $cents): string
    {
        return sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
    }
}
