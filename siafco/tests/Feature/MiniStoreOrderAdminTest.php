<?php

namespace Tests\Feature;

use App\Models\Affiliate;
use App\Models\AffiliationPlan;
use App\Models\AuditLog;
use App\Models\Person;
use App\Models\Sector;
use App\Models\StoreCoupon;
use App\Models\StoreOrder;
use App\Models\StoreProduct;
use App\Models\User;
use App\Services\Store\StoreOrderService;
use App\Services\Store\StoreOrderStatusService;
use App\Support\StoreAvailabilityStatus;
use App\Support\StoreDeliveryMethod;
use App\Support\StoreOrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MiniStoreOrderAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_lists_views_and_filters_orders_by_public_code(): void
    {
        $admin = User::factory()->create(['role' => 'administrador', 'user_type' => 'internal']);
        $consulta = User::factory()->create(['role' => 'consulta', 'user_type' => 'internal']);
        $order = $this->orderWithCoupon();

        $this->actingAs($consulta)->get(route('admin.store.orders.index', ['search' => $order->code]))
            ->assertOk()
            ->assertSee($order->code)
            ->assertSee($order->affiliate->full_name);

        $this->actingAs($admin)->get(route('admin.store.orders.show', $order))
            ->assertOk()
            ->assertSee($order->code)
            ->assertSee('Comprobante');
    }

    public function test_status_transitions_create_history_audit_and_release_coupon_on_cancel(): void
    {
        $admin = User::factory()->create(['role' => 'administrador', 'user_type' => 'internal']);
        $order = $this->orderWithCoupon();

        $this->actingAs($admin)->patch(route('admin.store.orders.status', $order), [
            'status' => StoreOrderStatus::WAITING_PAYMENT,
            'admin_note' => 'Listo para pagar',
        ])->assertRedirect();

        $order->refresh();
        $this->assertSame(StoreOrderStatus::WAITING_PAYMENT, $order->status);
        $this->assertDatabaseHas('store_order_status_histories', [
            'store_order_id' => $order->id,
            'from_status' => StoreOrderStatus::PENDING,
            'to_status' => StoreOrderStatus::WAITING_PAYMENT,
        ]);

        $this->actingAs($admin)->patch(route('admin.store.orders.status', $order), [
            'status' => StoreOrderStatus::CANCELLED,
            'admin_note' => 'Cancelado por prueba',
        ])->assertRedirect();

        $order->refresh();
        $this->assertSame(StoreOrderStatus::CANCELLED, $order->status);
        $this->assertNotNull($order->cancelled_at);
        $this->assertNotNull($order->couponUsage->fresh()->released_at);

        app(StoreOrderStatusService::class)->transition($this->orderWithCoupon('ORD20'), StoreOrderStatus::CANCELLED, $admin);
        $metadata = json_encode(AuditLog::query()->pluck('metadata'));
        $this->assertStringContainsString('order_cancelled', $metadata);
        $this->assertStringNotContainsString('Direccion privada', $metadata);
    }

    public function test_invalid_transitions_and_readonly_users_are_rejected(): void
    {
        $consulta = User::factory()->create(['role' => 'consulta', 'user_type' => 'internal']);
        $order = $this->orderWithCoupon();

        $this->actingAs($consulta)->patch(route('admin.store.orders.status', $order), [
            'status' => StoreOrderStatus::CONFIRMED,
        ])->assertForbidden();

        $this->expectException(ValidationException::class);
        app(StoreOrderStatusService::class)->transition($order, StoreOrderStatus::DELIVERED, $consulta);
    }

    private function orderWithCoupon(string $code = 'ORD10'): StoreOrder
    {
        $affiliate = $this->affiliate();
        $product = $this->product();
        StoreCoupon::create(['code_encrypted' => $code, 'type' => StoreCoupon::TYPE_FIXED, 'value' => 10, 'minimum_amount' => 0, 'active' => true]);

        return app(StoreOrderService::class)->create($affiliate, [
            'items' => [['product_public_code' => $product->public_code, 'quantity' => 1]],
            'delivery' => ['method' => StoreDeliveryMethod::PICKUP, 'address' => 'Direccion privada'],
            'coupon_code' => $code,
        ]);
    }

    private function affiliate(): Affiliate
    {
        $sector = Sector::create(['name' => fake()->unique()->word(), 'code' => fake()->unique()->bothify('SEC-###'), 'is_active' => true]);
        $plan = AffiliationPlan::create(['name' => fake()->unique()->word(), 'affiliation_fee' => 100, 'credential_fee' => 20, 'is_active' => true]);
        $person = Person::create(['full_name' => 'Afiliado Admin Pedido', 'ci' => fake()->unique()->numerify('#######'), 'email' => fake()->unique()->safeEmail()]);
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

    private function product(): StoreProduct
    {
        $category = \App\Models\StoreCategory::create(['name' => fake()->unique()->word(), 'slug' => fake()->unique()->slug(), 'active' => true]);

        return StoreProduct::create([
            'store_category_id' => $category->id,
            'sku' => fake()->unique()->bothify('SKU-###'),
            'slug' => fake()->unique()->slug(),
            'name' => 'Producto',
            'regular_price' => 100,
            'affiliate_price' => 90,
            'availability_status' => StoreAvailabilityStatus::AVAILABLE,
            'delivery_modes' => [StoreDeliveryMethod::PICKUP],
            'max_quantity_per_order' => 10,
            'active' => true,
        ]);
    }
}
