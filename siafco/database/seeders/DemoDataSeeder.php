<?php

namespace Database\Seeders;

use App\Models\Affiliate;
use App\Models\AffiliationPayment;
use App\Models\AffiliationPlan;
use App\Models\Person;
use App\Models\Sector;
use App\Models\User;
use App\Services\CredentialService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
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
            ['name' => 'Horacio Saire Mamani', 'ci' => '49951553', 'email' => 'horacio.demo@siafco.test', 'phone' => '72000111', 'sector' => 'MAG-RUR', 'status' => 'activo', 'registration' => 'MAG-RUR-000001'],
            ['name' => 'Daniela Pabon Maldonado', 'ci' => '16482531', 'email' => 'daniela.demo@siafco.test', 'phone' => '72000112', 'sector' => 'MIN-ORU', 'status' => 'activo', 'registration' => 'MIN-ORU-000001'],
            ['name' => 'Roxana Quispe Flores', 'ci' => '73004567', 'email' => 'roxana.demo@siafco.test', 'phone' => '72000113', 'sector' => 'SAL-POT', 'status' => 'pendiente_pago', 'registration' => 'SAL-POT-000001'],
            ['name' => 'Javier Condori Choque', 'ci' => '61007890', 'email' => 'javier.demo@siafco.test', 'phone' => '72000114', 'sector' => 'MAG-RUR', 'status' => 'observado', 'registration' => 'MAG-RUR-000002'],
            ['name' => 'Miriam Vargas Rojas', 'ci' => '84001234', 'email' => 'miriam.demo@siafco.test', 'phone' => '72000115', 'sector' => 'MIN-ORU', 'status' => 'inactivo', 'registration' => 'MIN-ORU-000002'],
        ];

        DB::transaction(function () use ($people, $sectors, $plan) {
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
}
