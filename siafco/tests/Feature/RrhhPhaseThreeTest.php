<?php

namespace Tests\Feature;

use App\Models\RrhhAdjustment;
use App\Models\RrhhPayLine;
use App\Models\RrhhPayPeriod;
use App\Models\RrhhProduct;
use App\Models\RrhhSale;
use App\Models\RrhhWorker;
use App\Models\User;
use App\Services\RrhhAdjustmentService;
use App\Services\RrhhPayrollService;
use App\Services\RrhhSaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RrhhPhaseThreeTest extends TestCase
{
    use RefreshDatabase;

    public function test_monthly_daily_and_virtual_remuneration_use_exact_decimal_rules(): void
    {
        $actor = $this->user('rrhh');
        $service = app(RrhhPayrollService::class);

        $monthly = $this->worker('monthly', '2500.00');
        $monthlyPeriod = $service->calculate($this->periodData($monthly), $actor);
        $this->assertSame('2500.00', $monthlyPeriod->base_amount);

        $daily = $this->worker('daily', '120.25');
        $dailyPeriod = $service->calculate($this->periodData($daily, ['recognized_days' => 7]), $actor);
        $this->assertSame('841.75', $dailyPeriod->base_amount);

        $virtual = $this->worker('commission_only', null, 'virtual');
        $virtualPeriod = $service->calculate($this->periodData($virtual), $actor);
        $this->assertSame('0.00', $virtualPeriod->base_amount);
        $this->assertSame('0.00', $virtualPeriod->calculated_total);
    }

    public function test_adjustments_are_idempotent_explicit_and_signed_in_total(): void
    {
        $actor = $this->user('rrhh');
        $worker = $this->worker('monthly', '1000.00');
        $adjustments = app(RrhhAdjustmentService::class);
        $key = (string) Str::uuid();
        $bonus = $adjustments->create($this->adjustmentData($worker, 'earning', '100.10', $key), $actor);
        $this->assertSame($bonus->id, $adjustments->create($this->adjustmentData($worker, 'earning', '100.10', $key), $actor)->id);
        $deduction = $adjustments->create($this->adjustmentData($worker, 'deduction', '25.05'), $actor);

        $withoutExplicitAdjustments = app(RrhhPayrollService::class)->calculate($this->periodData($worker), $actor);
        $this->assertSame('1000.00', $withoutExplicitAdjustments->calculated_total);
        app(RrhhPayrollService::class)->void($withoutExplicitAdjustments, $actor, 'Recalcular con ajustes explícitos');

        $period = app(RrhhPayrollService::class)->calculate($this->periodData($worker, ['adjustment_ids' => [$bonus->id, $deduction->id]]), $actor);
        $this->assertSame('75.05', $period->adjustment_total);
        $this->assertSame('1075.05', $period->calculated_total);
        $this->assertCount(2, $period->lines->where('type', 'adjustment'));
    }

    public function test_confirmed_commissions_are_snapshots_and_cannot_be_liquidated_twice(): void
    {
        $actor = $this->user('rrhh');
        $worker = $this->worker('commission_only', null, 'virtual');
        $sale = $this->confirmedSale($worker, '33.33');
        $payroll = app(RrhhPayrollService::class);

        $first = $payroll->calculate($this->periodData($worker), $actor);
        $this->assertSame('33.33', $first->commission_total);
        $this->assertSame($sale->items->first()->id, $first->lines->firstWhere('type', 'commission')->sale_item_id);

        $second = $payroll->calculate($this->periodData($worker, ['date_from' => '2026-10-16', 'date_to' => '2026-10-31']), $actor);
        $this->assertSame('0.00', $second->commission_total);
        $this->assertSame(1, RrhhPayLine::whereNotNull('sale_item_id')->count());
    }

    public function test_monthly_base_overlap_and_invalid_daily_days_are_rejected(): void
    {
        $actor = $this->user('rrhh');
        $monthly = $this->worker('monthly', '2000.00');
        $service = app(RrhhPayrollService::class);
        $service->calculate($this->periodData($monthly), $actor);

        $this->expectException(ValidationException::class);
        $service->calculate($this->periodData($monthly, ['date_from' => '2026-10-15', 'date_to' => '2026-11-15']), $actor);
    }

    public function test_closing_and_manual_payment_are_single_exact_and_immutable(): void
    {
        $actor = $this->user('rrhh');
        $worker = $this->worker('monthly', '1500.00');
        $service = app(RrhhPayrollService::class);
        $period = $service->calculate($this->periodData($worker), $actor);
        $service->close($period, $actor);
        $paid = $service->pay($period, ['paid_at' => '2026-10-31', 'paid_amount' => '1500.00', 'payment_reference' => 'PAGO-1'], $actor);

        $this->assertSame(RrhhPayPeriod::STATUS_PAID, $paid->status);
        $this->assertSame('1500.00', $paid->paid_amount);
        $this->assertSame('PAGO-1', $paid->payment_reference);
        $this->assertDatabaseHas('audit_logs', ['action' => 'rrhh_pay_period_paid', 'auditable_id' => $period->id]);

        foreach (['pay', 'void', 'calculate'] as $operation) {
            try {
                match ($operation) {
                    'pay' => $service->pay($paid, ['paid_at' => '2026-11-01', 'paid_amount' => '1500.00'], $actor),
                    'void' => $service->void($paid, $actor, 'No debe borrar un pago'),
                    'calculate' => $service->calculate($this->periodData($worker), $actor, $paid),
                };
                $this->fail("La operación {$operation} debía ser rechazada.");
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }
        $this->assertSame('PAGO-1', $paid->fresh()->payment_reference);
    }

    public function test_voiding_paid_sale_creates_one_unapplied_correction_and_keeps_payment(): void
    {
        $actor = $this->user('rrhh');
        $worker = $this->worker('commission_only', null, 'virtual');
        $sale = $this->confirmedSale($worker, '80.00');
        $payroll = app(RrhhPayrollService::class);
        $period = $payroll->calculate($this->periodData($worker), $actor);
        $payroll->close($period, $actor);
        $payroll->pay($period, ['paid_at' => '2026-10-31', 'paid_amount' => '80.00'], $actor);

        app(RrhhSaleService::class)->void($sale, $actor, 'Venta revertida después del pago');
        $correction = RrhhAdjustment::where('source_sale_id', $sale->id)->firstOrFail();
        $this->assertSame(RrhhAdjustment::REVIEW_PENDING, $correction->review_status);
        $this->assertSame(RrhhAdjustment::STATUS_PENDING, $correction->status);
        $this->assertSame('80.00', $correction->amount);
        $this->assertSame(RrhhPayPeriod::STATUS_PAID, $period->fresh()->status);
        $this->assertSame('80.00', $period->fresh()->paid_amount);
        $this->assertSame(1, RrhhAdjustment::where('source_sale_id', $sale->id)->count());

        $future = $payroll->calculate($this->periodData($worker, ['date_from' => '2026-10-30', 'date_to' => '2026-10-31']), $actor);
        $this->assertSame('0.00', $future->adjustment_total);
        $payroll->void($future, $actor, 'Preparar corrección revisada');
        app(RrhhAdjustmentService::class)->review($correction, $actor, 'approved', 'Corresponde recuperar la comisión');
        $applied = $payroll->calculate($this->periodData($worker, ['date_from' => '2026-10-30', 'date_to' => '2026-10-31', 'adjustment_ids' => [$correction->id]]), $actor);
        $this->assertSame('-80.00', $applied->calculated_total);
        $this->assertSame(RrhhAdjustment::STATUS_PENDING, $correction->fresh()->status);
    }

    public function test_void_sale_releases_draft_blocks_closed_and_never_creates_unearned_correction(): void
    {
        $actor = $this->user('rrhh');
        $worker = $this->worker('commission_only', null, 'virtual');
        $draftSale = $this->confirmedSale($worker, '20.00');
        $period = app(RrhhPayrollService::class)->calculate($this->periodData($worker), $actor);
        app(RrhhSaleService::class)->void($draftSale, $actor, 'Anulada antes de cerrar liquidación');
        $this->assertSame('0.00', $period->fresh()->commission_total);
        $this->assertDatabaseMissing('rrhh_adjustments', ['source_sale_id' => $draftSale->id]);

        $closedSale = $this->confirmedSale($worker, '25.00', '2026-11-02');
        $closed = app(RrhhPayrollService::class)->calculate($this->periodData($worker, ['date_from' => '2026-11-01', 'date_to' => '2026-11-30']), $actor);
        app(RrhhPayrollService::class)->close($closed, $actor);
        $this->expectException(ValidationException::class);
        app(RrhhSaleService::class)->void($closedSale, $actor, 'No se puede alterar cierre');
    }

    public function test_permissions_and_worker_scope_are_enforced_without_reducing_management_access(): void
    {
        $rrhh = $this->user('rrhh');
        $manager = $this->user('gerente');
        $workerUser = $this->user('trabajador_rrhh');
        $worker = $this->worker('monthly', '1000.00', 'scheduled', $workerUser);
        $other = $this->worker('monthly', '900.00');
        $own = app(RrhhPayrollService::class)->calculate($this->periodData($worker), $rrhh);
        $foreign = app(RrhhPayrollService::class)->calculate($this->periodData($other), $rrhh);

        $this->actingAs($rrhh)->get(route('rrhh.reports.index'))->assertOk();
        $this->actingAs($manager)->get(route('rrhh.reports.index'))->assertOk();
        $this->actingAs($manager)->get(route('rrhh.reports.periods.create'))->assertForbidden();
        $this->actingAs($workerUser)->get(route('rrhh.self.periods.show', $own))->assertOk();
        $this->actingAs($workerUser)->get(route('rrhh.self.periods.show', $foreign))->assertNotFound();
        $this->actingAs($this->user('consulta'))->get(route('rrhh.reports.index'))->assertForbidden();
        $this->assertTrue($manager->hasPermission('affiliates.view'));
    }

    public function test_rejected_correction_cannot_be_liquidated_and_wrong_payment_amount_is_rejected(): void
    {
        $actor = $this->user('rrhh');
        $worker = $this->worker('monthly', '500.00');
        $adjustment = RrhhAdjustment::create([
            'worker_id' => $worker->id, 'applies_on' => '2026-10-10', 'direction' => 'deduction',
            'concept' => 'Corrección propuesta', 'amount' => '20.00', 'status' => 'pending',
            'origin' => 'voided_sale', 'review_status' => 'pending', 'submission_key' => (string) Str::uuid(),
            'created_by' => $actor->id,
        ]);
        app(RrhhAdjustmentService::class)->review($adjustment, $actor, 'rejected', 'No corresponde');
        $this->assertSame(RrhhAdjustment::STATUS_VOIDED, $adjustment->fresh()->status);

        $period = app(RrhhPayrollService::class)->calculate($this->periodData($worker), $actor);
        app(RrhhPayrollService::class)->close($period, $actor);
        try {
            app(RrhhPayrollService::class)->pay($period, ['paid_at' => '2026-10-31', 'paid_amount' => '499.99'], $actor);
            $this->fail('El pago con importe distinto debía rechazarse.');
        } catch (ValidationException) {
            $this->assertSame(RrhhPayPeriod::STATUS_CLOSED, $period->fresh()->status);
            $this->assertNull($period->fresh()->paid_amount);
        }
    }

    public function test_rrhh_navigation_groups_the_seven_module_links_and_keeps_the_current_option_active(): void
    {
        $rrhh = $this->user('rrhh');
        $response = $this->actingAs($rrhh)->get(route('rrhh.reports.periods.index'));

        $response->assertOk()
            ->assertSee('PERSONAL Y COMISIONES')
            ->assertSee('Resumen')
            ->assertSee('Personal')
            ->assertSee('Productos')
            ->assertSee('Ventas')
            ->assertSee('Liquidaciones')
            ->assertSee('Ajustes y correcciones')
            ->assertSee('Informes')
            ->assertDontSee('Informes (próximamente)')
            ->assertSee('aria-expanded="true"', false)
            ->assertSee('nav-link nav-link-active gap-3', false);

        $this->assertSame(1, substr_count($response->getContent(), 'data-accordion-module="rrhh"'));
    }

    public function test_management_and_read_only_routes_follow_rrhh_and_manager_permissions(): void
    {
        $rrhh = $this->user('rrhh');
        $manager = $this->user('gerente');

        foreach (['rrhh.dashboard', 'rrhh.workers.index', 'rrhh.products.index', 'rrhh.sales.index', 'rrhh.reports.periods.index', 'rrhh.reports.adjustments.index', 'rrhh.reports.index'] as $route) {
            $this->actingAs($rrhh)->get(route($route))->assertOk();
            $this->actingAs($manager)->get(route($route))->assertOk();
        }

        $managerMenu = $this->actingAs($manager)->get(route('rrhh.dashboard'));
        $managerMenu->assertSee('Liquidaciones')->assertSee('Ajustes y correcciones');
        $this->actingAs($manager)->get(route('rrhh.reports.periods.create'))->assertForbidden();
    }

    public function test_worker_portal_does_not_render_the_administrative_rrhh_menu(): void
    {
        $workerUser = $this->user('trabajador_rrhh');
        $this->worker('monthly', '1000.00', 'scheduled', $workerUser);

        $this->actingAs($workerUser)->get(route('rrhh.self.dashboard'))
            ->assertOk()
            ->assertSee('Mi trabajo')
            ->assertDontSee('PERSONAL Y COMISIONES');
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role, 'user_type' => 'internal', 'is_active' => true]);
    }

    private function worker(string $mode, ?string $amount, string $type = 'scheduled', ?User $user = null): RrhhWorker
    {
        return RrhhWorker::create(['code' => 'TRA-'.Str::upper(Str::random(8)), 'user_id' => $user?->id, 'type' => $type, 'full_name' => fake()->name(), 'ci' => Str::upper(Str::random(10)), 'phone' => '70000000', 'email' => fake()->unique()->safeEmail(), 'position' => $type === 'virtual' ? null : 'OPERADOR', 'joined_on' => '2026-01-01', 'schedule_description' => $type === 'virtual' ? null : '08:00-16:00', 'compensation_mode' => $mode, 'agreed_amount' => $amount, 'commissions_enabled' => true, 'is_active' => true]);
    }

    private function confirmedSale(RrhhWorker $worker, string $commission, string $date = '2026-10-15'): RrhhSale
    {
        $actor = User::factory()->create(['role' => 'rrhh', 'user_type' => 'internal']);
        $product = RrhhProduct::create(['code' => 'PRO-'.Str::upper(Str::random(8)), 'name' => 'Producto', 'reference_price' => '100.00', 'currency' => 'BOB', 'commission_type' => 'fixed', 'commission_value' => $commission, 'is_active' => true]);
        $sale = RrhhSale::create(['number' => 'VEN-'.Str::upper(Str::random(8)), 'sold_on' => $date, 'worker_id' => $worker->id, 'status' => 'confirmed', 'subtotal' => '100.00', 'total' => '100.00', 'commission_total' => $commission, 'created_by' => $actor->id, 'confirmed_by' => $actor->id, 'confirmed_at' => now()]);
        $sale->items()->create(['product_id' => $product->id, 'product_code_snapshot' => $product->code, 'product_name_snapshot' => $product->name, 'quantity' => 1, 'unit_price' => '100.00', 'line_total' => '100.00', 'commission_type_snapshot' => 'fixed', 'commission_value_snapshot' => $commission, 'commission_amount' => $commission]);

        return $sale->fresh('items');
    }

    private function periodData(RrhhWorker $worker, array $overrides = []): array
    {
        return array_merge(['worker_id' => $worker->id, 'date_from' => '2026-10-01', 'date_to' => '2026-10-31', 'recognized_days' => null, 'adjustment_ids' => []], $overrides);
    }

    private function adjustmentData(RrhhWorker $worker, string $direction, string $amount, ?string $key = null): array
    {
        return ['worker_id' => $worker->id, 'applies_on' => '2026-10-10', 'direction' => $direction, 'concept' => 'Ajuste de prueba', 'amount' => $amount, 'notes' => null, 'submission_key' => $key ?? (string) Str::uuid()];
    }
}
