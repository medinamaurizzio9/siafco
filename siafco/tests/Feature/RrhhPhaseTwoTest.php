<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\RrhhProduct;
use App\Models\RrhhSale;
use App\Models\RrhhWorker;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RrhhPhaseTwoTest extends TestCase
{
    use RefreshDatabase;

    public function test_interactive_command_creates_restricted_rrhh_account_with_forced_password_change(): void
    {
        $user148 = $this->user('gerente', ['id' => 148, 'email' => 'gerencia@example.test']);
        $password = 'Temporal123';

        $this->artisan('rrhh:create-admin')
            ->expectsQuestion('Nombre completo', 'Responsable RRHH')
            ->expectsQuestion('Correo electrónico', 'rrhh@example.test')
            ->expectsQuestion('Contraseña temporal', $password)
            ->expectsQuestion('Confirma la contraseña temporal', $password)
            ->expectsOutputToContain('Cuenta RR. HH. creada')
            ->doesntExpectOutputToContain($password)
            ->assertSuccessful();

        $account = User::where('email', 'rrhh@example.test')->firstOrFail();
        $this->assertSame('rrhh', $account->role);
        $this->assertTrue($account->must_change_password);
        $this->assertTrue(Hash::check($password, $account->password));
        $this->assertFalse($account->hasPermission('users.view'));
        $this->assertFalse($account->hasPermission('affiliates.view'));
        $this->assertFalse($account->hasPermission('investment_prospects.view'));
        $this->assertSame('gerente', $user148->fresh()->role);
        $this->assertDatabaseHas('audit_logs', ['action' => 'rrhh_admin_account_created', 'auditable_id' => $account->id]);
    }

    public function test_command_rejects_duplicate_email_without_changing_existing_account(): void
    {
        $existing = $this->user('gerente', ['email' => 'existing@example.test']);

        $this->artisan('rrhh:create-admin')
            ->expectsQuestion('Nombre completo', 'Duplicado')
            ->expectsQuestion('Correo electrónico', 'existing@example.test')
            ->expectsQuestion('Contraseña temporal', 'Temporal123')
            ->expectsQuestion('Confirma la contraseña temporal', 'Temporal123')
            ->assertFailed();

        $this->assertSame('gerente', $existing->fresh()->role);
        $this->assertSame(1, User::where('email', 'existing@example.test')->count());
    }

    public function test_rrhh_account_is_isolated_from_unrelated_modules(): void
    {
        $rrhh = $this->user('rrhh');

        $this->actingAs($rrhh)->get(route('rrhh.dashboard'))->assertOk();
        $this->actingAs($rrhh)->get(route('affiliates.index'))->assertForbidden();
        $this->actingAs($rrhh)->get(route('cash-deposits.dashboard'))->assertForbidden();
        $this->actingAs($rrhh)->get(route('investments.crm.dashboard'))->assertForbidden();
        $this->actingAs($rrhh)->get(route('admin.users.index'))->assertForbidden();
    }

    public function test_products_support_fixed_and_percentage_commissions_and_validate_percentage(): void
    {
        $rrhh = $this->user('rrhh');
        $this->actingAs($rrhh)->post(route('rrhh.products.store'), $this->productPayload())->assertRedirect();
        $this->actingAs($rrhh)->post(route('rrhh.products.store'), $this->productPayload(['code' => 'PRO-002', 'commission_type' => 'percentage', 'commission_value' => '12.5000']))->assertRedirect();

        $this->assertDatabaseHas('rrhh_products', ['code' => 'PRO-001', 'commission_type' => 'fixed', 'commission_value' => '10.0000']);
        $this->assertDatabaseHas('rrhh_products', ['code' => 'PRO-002', 'commission_type' => 'percentage', 'commission_value' => '12.5000']);
        $this->actingAs($rrhh)->post(route('rrhh.products.store'), $this->productPayload(['code' => 'PRO-003', 'commission_type' => 'percentage', 'commission_value' => '100.0001']))->assertSessionHasErrors('commission_value');
    }

    public function test_manager_reads_products_and_sales_but_cannot_operate_them(): void
    {
        $manager = $this->user('gerente');
        $product = $this->product();

        $this->actingAs($manager)->get(route('rrhh.products.index'))->assertOk()->assertSee($product->code);
        $this->actingAs($manager)->get(route('rrhh.sales.index'))->assertOk();
        $this->actingAs($manager)->get(route('rrhh.products.create'))->assertForbidden();
        $this->actingAs($manager)->post(route('rrhh.products.store'), $this->productPayload(['code' => 'PRO-NEW']))->assertForbidden();
        $this->actingAs($manager)->get(route('rrhh.sales.create'))->assertForbidden();
    }

    public function test_server_calculates_multi_item_sale_with_exact_snapshots(): void
    {
        $rrhh = $this->user('rrhh');
        $worker = $this->worker();
        $fixed = $this->product();
        $percentage = $this->product(['code' => 'PRO-002', 'name' => 'PORCENTUAL', 'reference_price' => '19.99', 'commission_type' => 'percentage', 'commission_value' => '12.5000']);

        $response = $this->actingAs($rrhh)->post(route('rrhh.sales.store'), [
            'sold_on' => '2026-10-30', 'worker_id' => $worker->id, 'total' => '0.01', 'commission_total' => '99999',
            'items' => [['product_id' => $fixed->id, 'quantity' => 2], ['product_id' => $percentage->id, 'quantity' => 3]],
        ]);
        $response->assertRedirect();

        $sale = RrhhSale::with('items')->firstOrFail();
        $this->assertSame(RrhhSale::STATUS_PENDING, $sale->status);
        $this->assertSame('259.97', $sale->total);
        $this->assertSame('27.50', $sale->commission_total);
        $this->assertSame(['PRO-001', 'PRO-002'], $sale->items->pluck('product_code_snapshot')->all());
        $this->assertSame(['20.00', '7.50'], $sale->items->pluck('commission_amount')->all());
    }

    public function test_sale_rejects_decimal_quantity_inactive_product_and_ineligible_worker(): void
    {
        $rrhh = $this->user('rrhh');
        $worker = $this->worker();
        $product = $this->product();

        $this->actingAs($rrhh)->post(route('rrhh.sales.store'), $this->salePayload($worker, $product, '1.5'))->assertSessionHasErrors('items.0.quantity');
        $product->update(['is_active' => false]);
        $this->actingAs($rrhh)->post(route('rrhh.sales.store'), $this->salePayload($worker, $product))->assertSessionHasErrors('items');
        $product->update(['is_active' => true]);
        $worker->update(['commissions_enabled' => false]);
        $this->actingAs($rrhh)->post(route('rrhh.sales.store'), $this->salePayload($worker, $product))->assertSessionHasErrors('worker_id');
        $this->assertDatabaseCount('rrhh_sales', 0);
    }

    public function test_product_edits_do_not_change_sale_snapshots(): void
    {
        $rrhh = $this->user('rrhh');
        $worker = $this->worker();
        $product = $this->product();
        $this->actingAs($rrhh)->post(route('rrhh.sales.store'), $this->salePayload($worker, $product))->assertRedirect();
        $sale = RrhhSale::firstOrFail();
        $this->actingAs($rrhh)->post(route('rrhh.sales.confirm', $sale))->assertRedirect();
        $item = $sale->items()->firstOrFail();

        $this->actingAs($rrhh)->patch(route('rrhh.products.update', $product), $this->productPayload(['name' => 'NUEVO NOMBRE', 'reference_price' => '999.00', 'commission_value' => '99.00']))->assertRedirect();

        $this->assertSame('PRODUCTO UNO', $item->fresh()->product_name_snapshot);
        $this->assertSame('100.00', $item->fresh()->unit_price);
        $this->assertSame('10.0000', $item->fresh()->commission_value_snapshot);
    }

    public function test_confirmation_is_unique_recalculates_and_void_requires_reason(): void
    {
        $rrhh = $this->user('rrhh');
        $worker = $this->worker();
        $product = $this->product();
        $this->actingAs($rrhh)->post(route('rrhh.sales.store'), $this->salePayload($worker, $product, 2))->assertRedirect();
        $sale = RrhhSale::firstOrFail();
        $sale->update(['total' => '0.01', 'commission_total' => '0.01']);
        $sale->items()->firstOrFail()->update(['line_total' => '0.01', 'commission_amount' => '0.01']);

        $this->actingAs($rrhh)->post(route('rrhh.sales.confirm', $sale))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('200.00', $sale->fresh()->total);
        $this->assertSame('20.00', $sale->fresh()->commission_total);
        $this->assertSame('200.00', $sale->items()->firstOrFail()->line_total);
        $this->assertSame('20.00', $sale->items()->firstOrFail()->commission_amount);
        $this->assertSame($rrhh->id, $sale->fresh()->confirmed_by);
        $this->actingAs($rrhh)->post(route('rrhh.sales.confirm', $sale))->assertSessionHasErrors('sale');
        $this->assertSame(1, AuditLog::where('action', 'rrhh_sale_confirmed')->count());

        $this->actingAs($rrhh)->post(route('rrhh.sales.void', $sale), ['void_reason' => 'x'])->assertSessionHasErrors('void_reason');
        $this->actingAs($rrhh)->post(route('rrhh.sales.void', $sale), ['void_reason' => 'Error comercial confirmado'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(RrhhSale::STATUS_VOIDED, $sale->fresh()->status);
        $this->assertSame('Error comercial confirmado', $sale->fresh()->void_reason);
    }

    public function test_worker_portal_is_read_only_and_scoped_to_own_sales(): void
    {
        $rrhh = $this->user('rrhh');
        $firstUser = $this->user('trabajador_rrhh');
        $secondUser = $this->user('trabajador_rrhh');
        $first = $this->worker(['code' => 'TRA-000001', 'ci' => 'W-1', 'email' => $firstUser->email, 'user_id' => $firstUser->id]);
        $second = $this->worker(['code' => 'TRA-000002', 'ci' => 'W-2', 'email' => $secondUser->email, 'user_id' => $secondUser->id]);
        $product = $this->product();
        $this->actingAs($rrhh)->post(route('rrhh.sales.store'), $this->salePayload($first, $product))->assertRedirect();
        $firstSale = RrhhSale::firstOrFail();
        $this->actingAs($rrhh)->post(route('rrhh.sales.confirm', $firstSale))->assertRedirect();
        $this->actingAs($rrhh)->post(route('rrhh.sales.store'), $this->salePayload($second, $product))->assertRedirect();
        $secondSale = RrhhSale::latest('id')->firstOrFail();

        $this->actingAs($firstUser)->get(route('rrhh.self.dashboard'))->assertOk()->assertSee($firstSale->number)->assertDontSee($secondSale->number);
        $this->actingAs($firstUser)->get(route('rrhh.self.sales.show', $firstSale))->assertOk()->assertSee($firstSale->number);
        $this->actingAs($firstUser)->get(route('rrhh.self.sales.show', $secondSale))->assertNotFound();
        $this->actingAs($firstUser)->post(route('rrhh.sales.store'), $this->salePayload($first, $product))->assertForbidden();
        $this->actingAs($firstUser)->post(route('rrhh.sales.confirm', $firstSale))->assertForbidden();
    }

    private function user(string $role, array $overrides = []): User
    {
        return User::factory()->create(array_merge(['role' => $role, 'user_type' => 'internal', 'is_active' => true, 'must_change_password' => false], $overrides));
    }

    private function worker(array $overrides = []): RrhhWorker
    {
        return RrhhWorker::create(array_merge(['code' => 'TRA-000001', 'type' => 'virtual', 'full_name' => 'TRABAJADOR UNO', 'ci' => 'CI-ONE', 'phone' => '70000000', 'email' => 'worker@example.test', 'position' => null, 'joined_on' => '2026-10-01', 'schedule_description' => null, 'compensation_mode' => 'commission_only', 'agreed_amount' => null, 'commissions_enabled' => true, 'is_active' => true, 'created_by' => null, 'updated_by' => null], $overrides));
    }

    private function product(array $overrides = []): RrhhProduct
    {
        return RrhhProduct::create(array_merge($this->productPayload(), ['currency' => 'BOB', 'created_by' => null, 'updated_by' => null], $overrides));
    }

    private function productPayload(array $overrides = []): array
    {
        return array_merge(['code' => 'PRO-001', 'name' => 'PRODUCTO UNO', 'description' => null, 'reference_price' => '100.00', 'commission_type' => 'fixed', 'commission_value' => '10.0000', 'is_active' => 1], $overrides);
    }

    private function salePayload(RrhhWorker $worker, RrhhProduct $product, int|string $quantity = 1): array
    {
        return ['sold_on' => '2026-10-30', 'worker_id' => $worker->id, 'items' => [['product_id' => $product->id, 'quantity' => $quantity]]];
    }
}
