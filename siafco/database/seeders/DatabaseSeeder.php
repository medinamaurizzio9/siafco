<?php

namespace Database\Seeders;

use App\Models\AffiliationPlan;
use App\Models\AffiliateBenefit;
use App\Models\InstitutionalSetting;
use App\Models\InvestmentSetting;
use App\Models\InvestorType;
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
            ['email' => env('SIAFCO_ADMIN_EMAIL', 'admin@siafco.test')],
            [
                'name' => 'Administrador SIAFCO',
                'role' => 'administrador',
                'password' => Hash::make(env('SIAFCO_ADMIN_PASSWORD', 'admin123456')),
            ]
        );

        if ((bool) env('SIAFCO_SEED_SUPPORT_USERS', app()->environment(['local', 'testing']))) {
            foreach ([
                'administrador_sector' => 'Admin Sector',
                'secretaria' => 'Secretaria',
                'cajero' => 'Cajero',
                'caja' => 'Caja Inversiones',
                'contabilidad' => 'Contabilidad',
                'accionista' => 'Accionista',
                'consulta' => 'Consulta',
            ] as $role => $name) {
                User::updateOrCreate(
                    ['email' => "{$role}@siafco.test"],
                    ['name' => $name, 'role' => $role, 'password' => Hash::make('admin123456')]
                );
            }
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

        foreach ([
            ['CREDENCIAL DIGITAL', 'Descarga tu credencial institucional.', 'card', 'affiliate.credential.preview'],
            ['SOLICITUD DE CRÉDITO', 'Inicia una solicitud de crédito.', 'credit', null],
            ['SIMULADOR DE CRÉDITO', 'Consulta cuotas y plazos estimados.', 'calculator', null],
            ['HISTORIAL DE PAGOS', 'Revisa tus pagos de afiliación.', 'history', null],
            ['BENEFICIOS POR CONVENIO', 'Consulta beneficios institucionales vigentes.', 'gift', null],
            ['NOTICIAS Y COMUNICADOS', 'Información institucional para afiliados.', 'news', null],
            ['SOPORTE Y ASESORÍA', 'Canales de atención y orientación.', 'support', null],
        ] as $order => [$title, $description, $icon, $route]) {
            AffiliateBenefit::updateOrCreate(['title' => $title], [
                'description' => $description, 'icon' => $icon, 'route_name' => $route,
                'active' => true, 'visible_when_pending' => true, 'order' => $order + 1,
            ]);
        }

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

        InvestmentSetting::updateOrCreate(
            ['id' => 1],
            [
                'company_name' => 'Cooperativa Tierra Bendita',
                'company_legal_name' => 'Cooperativa Minera Tierra Bendita R.L.',
                'currency' => 'BOB',
                'share_unit_price' => 14000,
                'minimum_shares' => 1,
                'maximum_shares' => 10,
                'monthly_return_percentage' => 5,
                'waiting_months' => 4,
                'contract_years' => 3,
                'reservation_days' => 30,
                'maximum_shares_per_person' => true,
                'renewal_enabled' => true,
                'production_bonus_enabled' => true,
                'extra_amount_enabled' => true,
                'receipt_prefix' => 'REC-INV',
                'next_receipt_number' => 1,
                'alert_days_before_maturity' => 15,
                'active' => true,
            ]
        );

        foreach (range(1, 10) as $shares) {
            InvestorType::updateOrCreate(
                ['shares_quantity' => $shares],
                [
                    'name' => "Inversionista {$shares} ".($shares === 1 ? 'accion' : 'acciones'),
                    'description' => "Clasificacion automatica para {$shares} ".($shares === 1 ? 'accion activa' : 'acciones activas').'.',
                    'active' => true,
                    'order' => $shares,
                ]
            );
        }

        if ((bool) env('SEED_DEMO_DATA', env('SIAFCO_SEED_DEMO_DATA', app()->environment(['local', 'testing'])))) {
            $this->call(DemoDataSeeder::class);
        }
    }
}
