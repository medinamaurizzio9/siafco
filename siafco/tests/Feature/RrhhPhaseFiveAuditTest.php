<?php

namespace Tests\Feature;

use App\Models\RolePermissionOverride;
use App\Models\RrhhAdjustment;
use App\Models\RrhhProduct;
use App\Models\RrhhSale;
use App\Models\RrhhWorker;
use App\Models\User;
use App\Services\RolePermissionService;
use App\Services\RrhhReportingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RrhhPhaseFiveAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_rrhh_forms_use_visible_shared_controls_sections_and_dynamic_labels(): void
    {
        $rrhh = User::factory()->create(['role' => 'rrhh', 'user_type' => 'internal', 'is_active' => true]);

        $workerCreate = $this->actingAs($rrhh)->get(route('rrhh.workers.create'));
        $workerCreate->assertOk()
            ->assertSee('Información personal')
            ->assertSee('Información laboral')
            ->assertSee('Remuneración')
            ->assertSee('form-control', false)
            ->assertSee('data-scheduled-fields', false);

        $worker = $this->worker('scheduled');
        $this->actingAs($rrhh)->get(route('rrhh.workers.edit', $worker))
            ->assertOk()
            ->assertSee('Editar trabajador')
            ->assertSee($worker->full_name);

        $productCreate = $this->actingAs($rrhh)->get(route('rrhh.products.create'));
        $productCreate->assertOk()
            ->assertSee('Información del producto')
            ->assertSee('Configuración de comisión')
            ->assertSee('Comisión por unidad (Bs)')
            ->assertSee('data-commission-label', false);

        $product = $this->product();
        $this->actingAs($rrhh)->get(route('rrhh.products.edit', $product))
            ->assertOk()
            ->assertSee('Editar producto')
            ->assertSee($product->name);

        $css = file_get_contents(resource_path('css/app.css'));
        $this->assertStringContainsString('.form-control', $css);
        $this->assertStringContainsString('.form-control:focus', $css);
        $this->assertStringContainsString('.form-control[aria-invalid="true"]', $css);
        $this->assertStringContainsString('.form-control:disabled', $css);
    }

    public function test_superadministrator_keeps_every_rrhh_permission_and_real_route_access(): void
    {
        $super = User::factory()->create(['role' => 'superadministrador', 'user_type' => 'internal', 'is_active' => true]);
        RolePermissionOverride::create([
            'role' => 'superadministrador',
            'permissions' => ['dashboard.view', 'roles.view', 'roles.update', 'audit.view'],
            'updated_by' => $super->id,
        ]);

        $service = app(RolePermissionService::class);
        $rrhhPermissions = array_values(array_filter(
            $service->catalog(),
            fn (string $permission) => str_starts_with($permission, 'rrhh.') && $permission !== 'rrhh.self.view'
        ));

        foreach ($rrhhPermissions as $permission) {
            $this->assertTrue($super->fresh()->hasPermission($permission), $permission);
        }

        $this->actingAs($super)->get(route('rrhh.dashboard'))->assertOk();
        $this->actingAs($super)->get(route('rrhh.workers.create'))->assertOk();
        $this->actingAs($super)->get(route('rrhh.products.create'))->assertOk();
        $this->actingAs($super)->get(route('rrhh.sales.create'))->assertOk();
        $this->actingAs($super)->get(route('rrhh.reports.periods.create'))->assertOk();
        $this->actingAs($super)->get(route('rrhh.reports.adjustments.index'))->assertOk();
        $this->actingAs($super)->get(route('rrhh.reports.pdf'))->assertOk();
        $this->actingAs($super)->get(route('rrhh.reports.excel'))->assertOk();
    }

    public function test_management_totals_honor_worker_type_worker_and_date_filters(): void
    {
        $scheduled = $this->worker('scheduled');
        $virtual = $this->worker('virtual');
        $product = $this->product();
        $this->sale($scheduled, $product, '2026-10-10', '10.25');
        $this->sale($virtual, $product, '2026-10-10', '20.50');
        $this->correction($scheduled, '2026-10-10');
        $this->correction($virtual, '2026-09-15');

        $report = app(RrhhReportingService::class)->report([
            'type' => 'management',
            'worker_type' => 'scheduled',
            'from' => '2026-10-01',
            'to' => '2026-10-31',
        ], false);

        $this->assertSame('10.25', $report['totals']['Comisiones sin liquidar']);
        $this->assertSame('1', $report['totals']['Correcciones pendientes']);

        $workerReport = app(RrhhReportingService::class)->report([
            'type' => 'management',
            'worker_id' => $virtual->id,
            'from' => '2026-10-01',
            'to' => '2026-10-31',
        ], false);

        $this->assertSame('20.50', $workerReport['totals']['Comisiones sin liquidar']);
        $this->assertSame('0', $workerReport['totals']['Correcciones pendientes']);
    }

    public function test_pdf_and_excel_limits_return_controlled_422_responses(): void
    {
        $rrhh = User::factory()->create(['role' => 'rrhh', 'user_type' => 'internal', 'is_active' => true]);
        $now = now();
        $rows = [];
        for ($i = 1; $i <= 1001; $i++) {
            $rows[] = [
                'code' => 'TRA-LIM-'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'type' => 'scheduled',
                'full_name' => 'Trabajador '.$i,
                'ci' => 'LIM-'.$i,
                'phone' => '70000000',
                'joined_on' => '2026-01-01',
                'compensation_mode' => 'monthly',
                'agreed_amount' => '1000.00',
                'commissions_enabled' => false,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        RrhhWorker::insert($rows);

        $this->actingAs($rrhh)->get(route('rrhh.reports.pdf', ['type' => 'personal']))
            ->assertStatus(422)
            ->assertSee('límite operativo');

        $additional = [];
        for ($i = 1002; $i <= 5001; $i++) {
            $additional[] = [
                'code' => 'TRA-LIM-'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'type' => 'scheduled',
                'full_name' => 'Trabajador '.$i,
                'ci' => 'LIM-'.$i,
                'phone' => '70000000',
                'joined_on' => '2026-01-01',
                'compensation_mode' => 'monthly',
                'agreed_amount' => '1000.00',
                'commissions_enabled' => false,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            if (count($additional) === 500) {
                RrhhWorker::insert($additional);
                $additional = [];
            }
        }
        if ($additional !== []) {
            RrhhWorker::insert($additional);
        }

        $this->actingAs($rrhh)->get(route('rrhh.reports.excel', ['type' => 'personal']))
            ->assertStatus(422)
            ->assertSee('límite operativo');
    }

    private function worker(string $type): RrhhWorker
    {
        return RrhhWorker::create([
            'code' => 'TRA-'.Str::upper(Str::random(8)),
            'type' => $type,
            'full_name' => fake()->name(),
            'ci' => Str::upper(Str::random(10)),
            'phone' => '70000000',
            'joined_on' => '2026-01-01',
            'compensation_mode' => $type === 'virtual' ? 'commission_only' : 'monthly',
            'agreed_amount' => $type === 'virtual' ? null : '1000.00',
            'commissions_enabled' => true,
            'is_active' => true,
        ]);
    }

    private function product(): RrhhProduct
    {
        return RrhhProduct::create([
            'code' => 'PRO-'.Str::upper(Str::random(8)),
            'name' => 'Producto',
            'reference_price' => '100.00',
            'currency' => 'BOB',
            'commission_type' => 'fixed',
            'commission_value' => '10.0000',
            'is_active' => true,
        ]);
    }

    private function sale(RrhhWorker $worker, RrhhProduct $product, string $date, string $commission): void
    {
        $actor = User::factory()->create();
        $sale = RrhhSale::create([
            'number' => 'VEN-'.Str::upper(Str::random(8)),
            'sold_on' => $date,
            'worker_id' => $worker->id,
            'status' => 'confirmed',
            'subtotal' => '100.00',
            'total' => '100.00',
            'commission_total' => $commission,
            'created_by' => $actor->id,
            'confirmed_by' => $actor->id,
            'confirmed_at' => now(),
        ]);
        $sale->items()->create([
            'product_id' => $product->id,
            'product_code_snapshot' => $product->code,
            'product_name_snapshot' => $product->name,
            'quantity' => 1,
            'unit_price' => '100.00',
            'line_total' => '100.00',
            'commission_type_snapshot' => 'fixed',
            'commission_value_snapshot' => $commission,
            'commission_amount' => $commission,
        ]);
    }

    private function correction(RrhhWorker $worker, string $date): void
    {
        RrhhAdjustment::create([
            'worker_id' => $worker->id,
            'applies_on' => $date,
            'direction' => 'deduction',
            'concept' => 'Corrección de auditoría',
            'amount' => '1.00',
            'status' => 'pending',
            'origin' => 'voided_sale',
            'review_status' => 'pending',
            'submission_key' => (string) Str::uuid(),
            'created_by' => User::factory()->create()->id,
        ]);
    }
}
