<?php

namespace Tests\Feature;

use App\Models\InvestmentLot;
use App\Models\InvestmentReceipt;
use App\Models\InvestmentReturnPeriod;
use App\Models\Investor;
use App\Models\InvestorType;
use App\Models\ShareReservation;
use App\Services\RolePermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FormalInvestmentRemovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_formal_investment_surface_is_removed_while_history_is_preserved(): void
    {
        foreach ([
            'investments.panel',
            'investments.dashboard',
            'investments.investors.index',
            'investments.investor-types.index',
            'investments.reservations.index',
            'investments.lots.index',
            'investments.returns.index',
            'investments.receipts.index',
            'investments.approvals.index',
            'investments.reports.index',
            'investments.settings.edit',
        ] as $route) {
            $this->assertFalse(Route::has($route), $route);
        }

        foreach (['investors.view', 'investors.create', 'investors.update'] as $permission) {
            $this->assertNotContains($permission, app(RolePermissionService::class)->catalog());
        }

        foreach ([
            'investor_types',
            'investors',
            'share_reservations',
            'investment_lots',
            'investment_return_periods',
            'investment_receipts',
            'investment_settings',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), $table);
        }

        foreach ([
            Investor::class,
            InvestorType::class,
            ShareReservation::class,
            InvestmentLot::class,
            InvestmentReturnPeriod::class,
            InvestmentReceipt::class,
        ] as $model) {
            $this->assertTrue(class_exists($model), $model);
        }
    }

    public function test_investment_crm_and_unrelated_modules_remain_registered(): void
    {
        foreach ([
            'investments.advisors.public',
            'investment-crm.advisor.dashboard',
            'investments.advisors.index',
            'investments.prospects.index',
            'investments.crm.dashboard',
            'investments.crm.kanban',
            'affiliates.index',
            'payments.index',
            'credits.placeholder',
            'admin.store.dashboard',
            'rrhh.dashboard',
        ] as $route) {
            $this->assertTrue(Route::has($route), $route);
        }

        $catalog = app(RolePermissionService::class)->catalog();
        foreach ([
            'investment_advisors.view',
            'investment_prospects.view',
            'investment_prospect_interactions.view',
            'investment_crm_settings.view',
        ] as $permission) {
            $this->assertContains($permission, $catalog);
        }
    }

    public function test_no_scheduler_or_demo_seeder_can_operate_formal_investments(): void
    {
        $this->assertArrayNotHasKey('investments:process-periods', Artisan::all());

        $schedule = file_get_contents(base_path('routes/console.php'));
        $seeder = file_get_contents(base_path('database/seeders/DemoDataSeeder.php'));

        $this->assertStringNotContainsString('investments:process-periods', $schedule);
        $this->assertStringNotContainsString('InvestmentService', $seeder);
        $this->assertStringNotContainsString('investorWithLot', $seeder);
    }

    public function test_formal_investment_urls_are_not_accessible_directly(): void
    {
        foreach ([
            '/panel-accionista',
            '/inversiones/dashboard',
            '/inversiones/accionistas',
            '/inversiones/tipos-inversionista',
            '/inversiones/reservas',
            '/inversiones/lotes',
            '/inversiones/rendimientos',
            '/inversiones/recibos',
            '/inversiones/aprobaciones',
            '/inversiones/reportes',
            '/inversiones/configuracion',
        ] as $url) {
            $this->get($url)->assertNotFound();
        }
    }
}
