<?php

namespace Database\Seeders;

use App\Models\AffiliationPlan;
use App\Models\InstitutionalSetting;
use App\Models\Sector;
use App\Models\User;
use App\Services\QrCodeService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@siafco.test'],
            ['name' => 'Administrador SIAFCO', 'role' => 'administrador', 'password' => Hash::make('admin123456')]
        );

        foreach ([
            'administrador_sector' => 'Admin Sector',
            'secretaria' => 'Secretaria',
            'cajero' => 'Cajero',
            'consulta' => 'Consulta',
        ] as $role => $name) {
            User::updateOrCreate(
                ['email' => "{$role}@siafco.test"],
                ['name' => $name, 'role' => $role, 'password' => Hash::make('admin123456')]
            );
        }

        Sector::updateOrCreate(
            ['code' => 'MAG-RUR'],
            [
                'name' => 'Magisterio Rural',
                'regional' => 'La Paz',
                'institution' => 'Cooperativa Tierra Bendita',
                'is_active' => true,
            ]
        );

        AffiliationPlan::updateOrCreate(
            ['name' => 'Afiliacion inicial'],
            [
                'affiliation_fee' => 100,
                'credential_fee' => 30,
                'description' => 'Pago inicial de afiliacion y credencial digital.',
                'is_active' => true,
            ]
        );

        InstitutionalSetting::firstOrCreate([], [
            'institution_name' => 'Cooperativa Tierra Bendita',
            'primary_color' => '#0b1f3a',
            'secondary_color' => '#d4af37',
            'email' => 'no-reply@siafco.test',
            'phone' => '',
            'address' => '',
            'payment_qr_path' => 'institutional/payment-qr.png',
        ]);

        app(QrCodeService::class)->png(
            'SIAFCO TIERRA BENDITA - PAGO AFILIACION/CREDENCIAL',
            'institutional/payment-qr.png'
        );
    }
}
