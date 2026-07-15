<?php

namespace Database\Seeders;

use App\Models\Affiliate;
use App\Models\AffiliationPayment;
use App\Models\AffiliationPlan;
use App\Models\InvestmentLot;
use App\Models\InvestmentReceipt;
use App\Models\InvestmentReturnPeriod;
use App\Models\InvestmentSetting;
use App\Models\Investor;
use App\Models\InvestorType;
use App\Models\Person;
use App\Models\Sector;
use App\Models\ShareReservation;
use App\Models\User;
use App\Services\CredentialService;
use App\Services\InvestmentService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $setting = InvestmentSetting::current();
        $plan = AffiliationPlan::firstOrCreate(
            ['name' => 'Afiliacion completa demo'],
            ['affiliation_fee' => 120, 'credential_fee' => 30, 'description' => 'Plan demo para pruebas integrales.', 'is_active' => true]
        );

        $sectors = [
            'MAG-RUR' => Sector::updateOrCreate(['code' => 'MAG-RUR'], ['name' => 'Magisterio Rural', 'regional' => 'La Paz', 'institution' => 'Tierra Bendita', 'is_active' => true]),
            'MIN-ORU' => Sector::updateOrCreate(['code' => 'MIN-ORU'], ['name' => 'Mineria Oruro', 'regional' => 'Oruro', 'institution' => 'Cooperativa Minera', 'is_active' => true]),
            'SAL-POT' => Sector::updateOrCreate(['code' => 'SAL-POT'], ['name' => 'Salud Potosi', 'regional' => 'Potosi', 'institution' => 'Red de Salud', 'is_active' => true]),
        ];

        $people = [
            ['name' => 'Horacio Saire Mamani', 'ci' => '49951553', 'email' => 'horacio.demo@siafco.test', 'phone' => '72000111', 'sector' => 'MAG-RUR', 'status' => 'activo', 'registration' => 'MAG-RUR-000001', 'investor' => true, 'shares' => 2],
            ['name' => 'Daniela Pabon Maldonado', 'ci' => '16482531', 'email' => 'daniela.demo@siafco.test', 'phone' => '72000112', 'sector' => 'MIN-ORU', 'status' => 'activo', 'registration' => 'MIN-ORU-000001', 'investor' => true, 'shares' => 4],
            ['name' => 'Roxana Quispe Flores', 'ci' => '73004567', 'email' => 'roxana.demo@siafco.test', 'phone' => '72000113', 'sector' => 'SAL-POT', 'status' => 'pendiente_pago', 'registration' => 'SAL-POT-000001', 'investor' => false, 'shares' => 0],
            ['name' => 'Javier Condori Choque', 'ci' => '61007890', 'email' => 'javier.demo@siafco.test', 'phone' => '72000114', 'sector' => 'MAG-RUR', 'status' => 'observado', 'registration' => 'MAG-RUR-000002', 'investor' => false, 'shares' => 0],
            ['name' => 'Miriam Vargas Rojas', 'ci' => '84001234', 'email' => 'miriam.demo@siafco.test', 'phone' => '72000115', 'sector' => 'MIN-ORU', 'status' => 'inactivo', 'registration' => 'MIN-ORU-000002', 'investor' => false, 'shares' => 0],
        ];

        DB::transaction(function () use ($people, $sectors, $plan, $setting) {
            foreach ($people as $index => $demo) {
                $person = $this->person($demo);
                $user = $this->user($person, 'afiliado');
                $sector = $sectors[$demo['sector']];

                $affiliate = Affiliate::updateOrCreate(
                    ['registration_number' => $demo['registration']],
                    [
                        'person_id' => $person->id,
                        'user_id' => $user->id,
                        'sector_id' => $sector->id,
                        'affiliation_plan_id' => $plan->id,
                        'full_name' => $person->full_name,
                        'ci' => $person->ci,
                        'phone' => $person->phone,
                        'email' => $person->email,
                        'address' => $person->address,
                        'regional' => $sector->regional,
                        'institution' => $sector->institution,
                        'position' => $index % 2 === 0 ? 'Docente' : 'Trabajador minero',
                        'birth_date' => now()->subYears(30 + $index)->subMonths($index)->toDateString(),
                        'marital_status' => $index % 2 === 0 ? 'Soltero' : 'Casado',
                        'status' => $demo['status'],
                        'verification_token' => Affiliate::where('registration_number', $demo['registration'])->value('verification_token') ?: Str::uuid()->toString(),
                    ]
                );

                $this->payment($affiliate, $demo['status'], $plan);

                if ($demo['status'] === 'activo') {
                    app(CredentialService::class)->generate($affiliate);
                }

                if ($demo['investor']) {
                    $this->investorWithLot($person, $demo['shares'], $setting, now()->subMonths(8 + $index), "INV-DEMO-00{$index}");
                }
            }

            $soloInvestors = [
                ['name' => 'Carlos Aruquipa Lima', 'ci' => '90010001', 'email' => 'carlos.inversion@siafco.test', 'phone' => '73000001', 'shares' => 1, 'purchase' => now()->subMonths(2), 'number' => 'INV-DEMO-010'],
                ['name' => 'Patricia Flores Nina', 'ci' => '90010002', 'email' => 'patricia.inversion@siafco.test', 'phone' => '73000002', 'shares' => 6, 'purchase' => now()->subMonths(13), 'number' => 'INV-DEMO-011'],
                ['name' => 'Victor Hugo Colque', 'ci' => '90010003', 'email' => 'victor.inversion@siafco.test', 'phone' => '73000003', 'shares' => 3, 'purchase' => now()->subMonths(5), 'number' => 'INV-DEMO-012'],
            ];

            foreach ($soloInvestors as $demo) {
                $person = $this->person($demo);
                $this->user($person, 'accionista');
                $this->investorWithLot($person, $demo['shares'], $setting, Carbon::parse($demo['purchase']), $demo['number']);
            }

            $reservationInvestor = Investor::with('person')->whereHas('person', fn ($query) => $query->where('ci', '90010003'))->first();
            if ($reservationInvestor) {
                ShareReservation::updateOrCreate(
                    ['investor_id' => $reservationInvestor->id, 'reservation_date' => now()->subDays(8)->toDateString()],
                    [
                        'shares_quantity' => 2,
                        'share_unit_price' => $setting->share_unit_price,
                        'total_amount' => 2 * (float) $setting->share_unit_price,
                        'expiration_date' => now()->addDays(22)->toDateString(),
                        'amount_paid' => 5000,
                        'payment_reference' => 'RES-DEMO-001',
                        'payment_method' => 'Transferencia',
                        'status' => 'active',
                        'notes' => 'Reserva demo vigente.',
                        'created_by' => User::where('role', 'caja')->value('id'),
                    ]
                );

                ShareReservation::updateOrCreate(
                    ['investor_id' => $reservationInvestor->id, 'reservation_date' => now()->subDays(45)->toDateString()],
                    [
                        'shares_quantity' => 1,
                        'share_unit_price' => $setting->share_unit_price,
                        'total_amount' => (float) $setting->share_unit_price,
                        'expiration_date' => now()->subDays(15)->toDateString(),
                        'amount_paid' => 1000,
                        'payment_reference' => 'RES-DEMO-EXP',
                        'payment_method' => 'Caja',
                        'status' => 'expired',
                        'closure_reason' => 'Reserva demo vencida con respaldo administrativo.',
                        'notes' => 'No genera rendimientos.',
                        'created_by' => User::where('role', 'caja')->value('id'),
                    ]
                );
            }

            foreach (Sector::all() as $sector) {
                $max = Affiliate::where('sector_id', $sector->id)
                    ->where('registration_number', 'like', strtoupper($sector->code).'-%')
                    ->get()
                    ->map(fn ($affiliate) => (int) Str::afterLast($affiliate->registration_number, '-'))
                    ->max() ?: 0;
                $sector->update(['current_sequence' => max($sector->current_sequence, $max)]);
            }
        });
    }

    private function person(array $demo): Person
    {
        return Person::updateOrCreate(
            ['ci' => $demo['ci']],
            [
                'full_name' => $demo['name'],
                'phone' => $demo['phone'] ?? null,
                'email' => $demo['email'] ?? null,
                'address' => 'Zona Central, calle demo #'.substr($demo['ci'], -3),
                'birth_date' => now()->subYears(35)->toDateString(),
                'marital_status' => 'Soltero',
            ]
        );
    }

    private function user(Person $person, string $role): User
    {
        return User::updateOrCreate(
            ['email' => $person->email],
            [
                'person_id' => $person->id,
                'name' => $person->full_name,
                'role' => $role,
                'password' => Hash::make('demo123456'),
            ]
        );
    }

    private function payment(Affiliate $affiliate, string $affiliateStatus, AffiliationPlan $plan): void
    {
        $status = match ($affiliateStatus) {
            'activo' => 'confirmado',
            'observado' => 'rechazado',
            default => 'pendiente',
        };

        AffiliationPayment::updateOrCreate(
            ['affiliate_id' => $affiliate->id],
            [
                'amount' => $plan->total_amount,
                'institutional_qr_path' => 'institutional/payment-qr.png',
                'transaction_number' => $status === 'pendiente' ? null : 'TRX-AFI-'.$affiliate->id,
                'status' => $status,
                'rejection_reason' => $status === 'rechazado' ? 'Comprobante no legible demo.' : null,
                'confirmed_by' => $status === 'pendiente' ? null : User::where('role', 'cajero')->value('id'),
                'confirmed_at' => $status === 'pendiente' ? null : now()->subDays(3),
            ]
        );
    }

    private function investorWithLot(Person $person, int $shares, InvestmentSetting $setting, Carbon $purchaseDate, string $purchaseNumber): void
    {
        $investor = Investor::updateOrCreate(
            ['person_id' => $person->id],
            [
                'investor_number' => Investor::where('person_id', $person->id)->value('investor_number') ?: sprintf('ACC-%06d', (Investor::max('id') ?? 0) + 1),
                'status' => 'active',
                'start_date' => $purchaseDate->toDateString(),
                'created_by' => User::where('role', 'administrador')->value('id'),
            ]
        );

        $maturity = $purchaseDate->copy()->addMonthsNoOverflow((int) $setting->waiting_months);
        $contractEnd = $maturity->copy()->addYears((int) $setting->contract_years);
        $capital = $shares * (float) $setting->share_unit_price;

        $lot = InvestmentLot::updateOrCreate(
            ['purchase_number' => $purchaseNumber],
            [
                'investor_id' => $investor->id,
                'purchase_date' => $purchaseDate->toDateString(),
                'shares_quantity' => $shares,
                'share_unit_price' => $setting->share_unit_price,
                'invested_capital' => $capital,
                'return_percentage' => $setting->monthly_return_percentage,
                'waiting_months' => $setting->waiting_months,
                'contract_years' => $setting->contract_years,
                'maturity_date' => $maturity->toDateString(),
                'contract_end_date' => $contractEnd->toDateString(),
                'renewal_status' => 'pending_decision',
                'status' => 'pending_approval',
                'payment_method' => 'Transferencia',
                'payment_reference' => 'TRX-'.$purchaseNumber,
                'settings_snapshot' => $setting->only(['share_unit_price', 'monthly_return_percentage', 'waiting_months', 'contract_years']),
                'created_by' => User::where('role', 'caja')->value('id'),
            ]
        );

        app(InvestmentService::class)->approveLot($lot);
        $lot->refresh();

        if ($lot->maturity_date->isPast()) {
            $lot->update(['status' => 'active_earning']);
        }

        app(InvestmentService::class)->refreshInvestorType($investor);

        $period = $lot->periods()->orderBy('period_number')->first();
        if ($period && ! $period->receipt_id && $period->due_date->isPast()) {
            app(InvestmentService::class)->preparePeriod($period, [
                'production_bonus_amount' => $shares * 120,
                'extra_concept' => 'Incentivo demo',
                'extra_amount' => 0,
                'deductions_amount' => 0,
                'notes' => 'Rendimiento demo preparado.',
            ]);
            app(InvestmentService::class)->approvePeriod($period->refresh());

            if (! InvestmentReceipt::where('return_period_id', $period->id)->exists()) {
                app(InvestmentService::class)->issueReceipt($period->refresh(), [
                    'payment_method' => 'Caja',
                    'payment_reference' => 'PAGO-'.$purchaseNumber,
                    'notes' => 'Recibo demo emitido.',
                ]);
            }
        }

        InvestmentReturnPeriod::where('investment_lot_id', $lot->id)
            ->whereDate('due_date', '<=', now())
            ->where('status', 'upcoming')
            ->update(['status' => 'pending']);
    }
}
