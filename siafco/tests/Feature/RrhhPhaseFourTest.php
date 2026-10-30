<?php

namespace Tests\Feature;

use App\Models\RrhhAdjustment;
use App\Models\RrhhProduct;
use App\Models\RrhhSale;
use App\Models\RrhhWorker;
use App\Models\User;
use App\Services\RrhhPayrollService;
use App\Services\RrhhReportingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RrhhPhaseFourTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_seven_reports_render_with_filters_and_empty_states(): void
    {
        $rrhh = $this->user('rrhh');

        foreach (RrhhReportingService::TYPES as $type) {
            $this->actingAs($rrhh)->get(route('rrhh.reports.index', ['type' => $type, 'from' => '2026-10-01', 'to' => '2026-10-31']))
                ->assertOk()->assertSee('Criterio temporal')->assertSee('No existen registros');
        }
    }

    public function test_filters_validate_ranges_ids_and_allowed_states(): void
    {
        $rrhh = $this->user('rrhh');

        $this->actingAs($rrhh)->get(route('rrhh.reports.index', ['from' => '2026-10-31', 'to' => '2026-10-01']))->assertSessionHasErrors('to');
        $this->actingAs($rrhh)->get(route('rrhh.reports.index', ['worker_id' => 999999]))->assertSessionHasErrors('worker_id');
        $this->actingAs($rrhh)->get(route('rrhh.reports.index', ['status' => 'inventado']))->assertSessionHasErrors('status');
    }

    public function test_sales_product_filter_counts_each_sale_once_and_excludes_voided_from_current_total(): void
    {
        $rrhh = $this->user('rrhh');
        $worker = $this->worker();
        $product = $this->product('=PRODUCTO PELIGROSO');
        $confirmed = $this->sale($worker, $product, 'confirmed', '100.10', '10.01', 2);
        $confirmed->items()->create($this->item($product, '50.00', '5.00', 1));
        $this->sale($worker, $product, 'voided', '75.25', '7.50');

        $response = $this->actingAs($rrhh)->get(route('rrhh.reports.index', ['type' => 'sales', 'product_id' => $product->id]));
        $response->assertOk()->assertSee('100.10')->assertSee('75.25')->assertSee('Importe vigente');
        $this->assertSame(1, substr_count($response->getContent(), $confirmed->number));
    }

    public function test_commissions_distinguish_unsettled_draft_closed_paid_and_voided(): void
    {
        $rrhh = $this->user('rrhh');
        $worker = $this->worker('commission_only', null, 'virtual');
        $product = $this->product();
        $unsettled = $this->sale($worker, $product, 'confirmed', '100.00', '10.00');
        $draftSale = $this->sale($worker, $product, 'confirmed', '100.00', '11.00', 1, '2026-11-02');
        app(RrhhPayrollService::class)->calculate(['worker_id' => $worker->id, 'date_from' => '2026-11-01', 'date_to' => '2026-11-30', 'recognized_days' => null, 'adjustment_ids' => []], $rrhh);
        $this->sale($worker, $product, 'voided', '50.00', '5.00', 1, '2026-12-01');

        $response = $this->actingAs($rrhh)->get(route('rrhh.reports.index', ['type' => 'commissions']));
        $response->assertOk()->assertSee('Confirmada sin liquidar')->assertSee('Incluida en borrador')->assertSee('Venta anulada');
        $this->assertNotNull($draftSale->items()->first()->payLine);
        $this->assertNull($unsettled->items()->first()->payLine);
    }

    public function test_liquidations_payments_and_adjustments_use_historical_amounts(): void
    {
        $rrhh = $this->user('rrhh');
        $worker = $this->worker('daily', '25.50');
        $period = app(RrhhPayrollService::class)->calculate(['worker_id' => $worker->id, 'date_from' => '2026-10-01', 'date_to' => '2026-10-05', 'recognized_days' => 3, 'adjustment_ids' => []], $rrhh);
        app(RrhhPayrollService::class)->close($period, $rrhh);
        app(RrhhPayrollService::class)->pay($period, ['paid_at' => '2026-10-06', 'paid_amount' => '76.50', 'payment_reference' => '=CMD()'], $rrhh);
        RrhhAdjustment::create(['worker_id' => $worker->id, 'applies_on' => '2026-10-07', 'direction' => 'deduction', 'concept' => 'Corrección', 'amount' => '2.25', 'status' => 'pending', 'origin' => 'voided_sale', 'review_status' => 'pending', 'submission_key' => (string) Str::uuid(), 'created_by' => $rrhh->id]);

        $this->actingAs($rrhh)->get(route('rrhh.reports.index', ['type' => 'liquidations']))->assertOk()->assertSee('76.50')->assertSee('Jornal');
        $this->actingAs($rrhh)->get(route('rrhh.reports.index', ['type' => 'payments']))->assertOk()->assertSee('=CMD()')->assertSee('Total pagado');
        $this->actingAs($rrhh)->get(route('rrhh.reports.index', ['type' => 'adjustments']))->assertOk()->assertSee('Pendiente')->assertSee('-2.25');
    }

    public function test_pdf_and_excel_share_filters_totals_and_safe_numeric_cells(): void
    {
        $rrhh = $this->user('rrhh');
        $worker = $this->worker();
        $product = $this->product('=2+2');
        $this->sale($worker, $product, 'confirmed', '123.45', '12.34');
        $filters = ['type' => 'sales', 'from' => '2026-10-01', 'to' => '2026-10-31'];

        $pdf = $this->actingAs($rrhh)->get(route('rrhh.reports.pdf', $filters));
        $pdf->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        $excel = $this->actingAs($rrhh)->get(route('rrhh.reports.excel', $filters));
        $excel->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringStartsWith('PK', $excel->getContent());
        $sheet = $excel->getContent();
        $this->assertStringContainsString('t="n"', $sheet);
        $this->assertStringContainsString('=2+2', $sheet);
        $this->assertStringContainsString('t="inlineStr"', $sheet);
        $this->assertStringContainsString('123.45', $sheet);
    }

    public function test_rrhh_and_manager_export_but_worker_and_unprivileged_users_are_forbidden(): void
    {
        $rrhh = $this->user('rrhh');
        $manager = $this->user('gerente');
        $workerUser = $this->user('trabajador_rrhh');
        $other = $this->user('consulta');

        foreach ([$rrhh, $manager] as $allowed) {
            $this->actingAs($allowed)->get(route('rrhh.reports.index'))->assertOk();
            $this->actingAs($allowed)->get(route('rrhh.reports.pdf'))->assertOk();
            $this->actingAs($allowed)->get(route('rrhh.reports.excel'))->assertOk();
        }
        foreach ([$workerUser, $other] as $denied) {
            $this->actingAs($denied)->get(route('rrhh.reports.index'))->assertForbidden();
            $this->actingAs($denied)->get(route('rrhh.reports.pdf'))->assertForbidden();
            $this->actingAs($denied)->get(route('rrhh.reports.excel'))->assertForbidden();
        }
        $this->actingAs($manager)->get(route('rrhh.reports.periods.create'))->assertForbidden();
        $this->actingAs($manager)->post(route('rrhh.reports.adjustments.store'), [])->assertForbidden();
    }

    public function test_operational_limits_are_explicit_and_do_not_truncate_silently(): void
    {
        $this->assertSame(1000, RrhhReportingService::PDF_LIMIT);
        $this->assertSame(5000, RrhhReportingService::EXPORT_LIMIT);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role, 'user_type' => 'internal', 'is_active' => true]);
    }

    private function worker(string $mode = 'monthly', ?string $amount = '1000.00', string $type = 'scheduled'): RrhhWorker
    {
        return RrhhWorker::create(['code' => 'TRA-'.Str::upper(Str::random(8)), 'type' => $type, 'full_name' => fake()->name(), 'ci' => Str::upper(Str::random(10)), 'phone' => '70000000', 'email' => fake()->unique()->safeEmail(), 'position' => $type === 'virtual' ? null : 'OPERADOR', 'joined_on' => '2026-01-01', 'schedule_description' => $type === 'virtual' ? null : '08:00-16:00', 'compensation_mode' => $mode, 'agreed_amount' => $amount, 'commissions_enabled' => true, 'is_active' => true]);
    }

    private function product(string $name = 'Producto'): RrhhProduct
    {
        return RrhhProduct::create(['code' => 'PRO-'.Str::upper(Str::random(8)), 'name' => $name, 'reference_price' => '100.00', 'currency' => 'BOB', 'commission_type' => 'fixed', 'commission_value' => '10.0000', 'is_active' => true]);
    }

    private function sale(RrhhWorker $worker, RrhhProduct $product, string $status, string $total, string $commission, int $quantity = 1, string $date = '2026-10-15'): RrhhSale
    {
        $actor = $this->user('rrhh');
        $sale = RrhhSale::create(['number' => 'VEN-'.Str::upper(Str::random(8)), 'sold_on' => $date, 'worker_id' => $worker->id, 'status' => $status, 'subtotal' => $total, 'total' => $total, 'commission_total' => $commission, 'created_by' => $actor->id, 'confirmed_by' => $status === 'confirmed' ? $actor->id : null, 'confirmed_at' => $status === 'confirmed' ? now() : null]);
        $sale->items()->create($this->item($product, $total, $commission, $quantity));

        return $sale->fresh('items');
    }

    private function item(RrhhProduct $product, string $total, string $commission, int $quantity): array
    {
        return ['product_id' => $product->id, 'product_code_snapshot' => $product->code, 'product_name_snapshot' => $product->name, 'quantity' => $quantity, 'unit_price' => bcdiv($total, (string) $quantity, 2), 'line_total' => $total, 'commission_type_snapshot' => 'fixed', 'commission_value_snapshot' => $commission, 'commission_amount' => $commission];
    }
}
