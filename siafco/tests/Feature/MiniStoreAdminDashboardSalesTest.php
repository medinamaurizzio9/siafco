<?php

namespace Tests\Feature;

use App\Models\Affiliate;
use App\Models\AffiliationPlan;
use App\Models\Person;
use App\Models\Sector;
use App\Models\StoreCategory;
use App\Models\StoreOrder;
use App\Models\StoreProduct;
use App\Models\User;
use App\Services\Store\StoreAdminReportService;
use App\Services\Store\StoreOrderService;
use App\Services\Store\StoreOrderStatusService;
use App\Support\StoreAvailabilityStatus;
use App\Support\StoreDeliveryMethod;
use App\Support\StoreOrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MiniStoreAdminDashboardSalesTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_admin_sees_store_dashboard_with_real_metrics(): void
    {
        $admin = $this->admin();
        $pending = $this->order('PENDIENTE DASH', 80);
        $confirmed = $this->confirmedOrder('VENTA DASH', 120);

        $response = $this->actingAs($admin)->get(route('admin.store.dashboard'));

        $response->assertOk()
            ->assertSee('Dashboard de Mini Tienda')
            ->assertSee('Ventas confirmadas')
            ->assertSee('Bs 120.00')
            ->assertSee('Pedidos pendientes')
            ->assertSee($pending->code)
            ->assertSee($confirmed->code);
    }

    public function test_sales_list_contains_only_economically_confirmed_orders(): void
    {
        $admin = $this->admin();
        $pending = $this->order('PENDIENTE VENTA', 70);
        $review = $this->order('REVISION VENTA', 90);
        app(StoreOrderStatusService::class)->transition($review, StoreOrderStatus::PAYMENT_REVIEW, $admin);
        $confirmed = $this->confirmedOrder('VENTA CONFIRMADA', 150);

        $response = $this->actingAs($admin)->get(route('admin.store.sales.index'));

        $response->assertOk()
            ->assertSee('Ventas')
            ->assertSee($confirmed->code)
            ->assertSee('Bs 150.00')
            ->assertDontSee($pending->code)
            ->assertDontSee($review->code);
    }

    public function test_sales_summary_totals_are_calculated_from_confirmed_orders(): void
    {
        $this->confirmedOrder('VENTA UNO', 100);
        $this->confirmedOrder('VENTA DOS', 60);
        $this->order('PENDIENTE TOTAL', 40);

        $summary = app(StoreAdminReportService::class)->salesSummary();

        $this->assertSame(2, $summary['registered_sales']);
        $this->assertSame(160.0, $summary['total_amount']);
    }

    public function test_orders_keep_pending_records_and_store_permission_is_required(): void
    {
        $admin = $this->admin();
        $pending = $this->order('PENDIENTE OPERATIVO', 55);
        $withoutPermission = User::factory()->create(['role' => 'afiliado', 'user_type' => 'affiliate']);

        $this->actingAs($admin)->get(route('admin.store.orders.index'))
            ->assertOk()
            ->assertSee($pending->code);

        $this->actingAs($withoutPermission)->get(route('admin.store.dashboard'))->assertForbidden();
        $this->actingAs($withoutPermission)->get(route('admin.store.sales.index'))->assertForbidden();
    }

    private function confirmedOrder(string $name, float $price): StoreOrder
    {
        $admin = $this->admin();
        $order = $this->order($name, $price);

        app(StoreOrderStatusService::class)->transition($order, StoreOrderStatus::PAYMENT_REVIEW, $admin);
        app(StoreOrderStatusService::class)->transition($order->fresh(), StoreOrderStatus::CONFIRMED, $admin);

        return $order->fresh();
    }

    private function order(string $name, float $price): StoreOrder
    {
        $affiliate = $this->affiliate();
        $product = $this->product($name, $price);

        return app(StoreOrderService::class)->create($affiliate, [
            'items' => [['product_public_code' => $product->public_code, 'quantity' => 1]],
            'delivery' => ['method' => StoreDeliveryMethod::PICKUP],
        ]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'administrador', 'user_type' => 'internal']);
    }

    private function affiliate(): Affiliate
    {
        $sector = Sector::create(['name' => fake()->unique()->word(), 'code' => fake()->unique()->bothify('SEC-###'), 'is_active' => true]);
        $plan = AffiliationPlan::create(['name' => fake()->unique()->word(), 'affiliation_fee' => 100, 'credential_fee' => 20, 'is_active' => true]);
        $person = Person::create(['full_name' => 'Afiliado Tienda Admin', 'ci' => fake()->unique()->numerify('#######'), 'email' => fake()->unique()->safeEmail()]);
        $user = User::create([
            'person_id' => $person->id,
            'name' => $person->full_name,
            'email' => $person->email,
            'role' => 'afiliado',
            'user_type' => 'affiliate',
            'password' => Hash::make('secret'),
            'is_active' => true,
        ]);

        return Affiliate::create([
            'person_id' => $person->id,
            'user_id' => $user->id,
            'sector_id' => $sector->id,
            'affiliation_plan_id' => $plan->id,
            'full_name' => $person->full_name,
            'ci' => $person->ci,
            'email' => $person->email,
            'registration_number' => fake()->unique()->bothify('REG-#####'),
            'verification_token' => fake()->uuid(),
            'status' => 'activo',
        ]);
    }

    private function product(string $name, float $price): StoreProduct
    {
        $category = StoreCategory::create(['name' => fake()->unique()->word(), 'slug' => fake()->unique()->slug(), 'active' => true]);

        return StoreProduct::create([
            'store_category_id' => $category->id,
            'sku' => fake()->unique()->bothify('SKU-###'),
            'slug' => fake()->unique()->slug(),
            'name' => $name,
            'regular_price' => $price,
            'affiliate_price' => $price,
            'availability_status' => StoreAvailabilityStatus::AVAILABLE,
            'delivery_modes' => [StoreDeliveryMethod::PICKUP],
            'max_quantity_per_order' => 10,
            'active' => true,
        ]);
    }
}
