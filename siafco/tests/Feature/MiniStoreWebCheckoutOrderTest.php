<?php

namespace Tests\Feature;

use App\Models\Affiliate;
use App\Models\AffiliationPlan;
use App\Models\Person;
use App\Models\Sector;
use App\Models\StoreCoupon;
use App\Models\StoreOrder;
use App\Models\StoreProduct;
use App\Models\StoreSetting;
use App\Models\StoreShippingRate;
use App\Models\User;
use App\Support\StoreAvailabilityStatus;
use App\Support\StoreDeliveryMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MiniStoreWebCheckoutOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_creates_pickup_order_recalculating_manipulated_payload_and_clears_cart(): void
    {
        $affiliate = $this->affiliate();
        $product = $this->product(['affiliate_price' => 90]);
        StoreCoupon::create(['code_encrypted' => 'WEB10', 'type' => StoreCoupon::TYPE_FIXED, 'value' => 10, 'minimum_amount' => 0, 'active' => true]);

        $this->actingAs($affiliate->user)->post(route('store.cart.store'), ['product_public_code' => $product->public_code, 'quantity' => 2]);
        $this->actingAs($affiliate->user)->get(route('store.checkout.show'))->assertOk()->assertSee('180.00');
        $key = session('store_checkout.idempotency_key');

        $this->actingAs($affiliate->user)->post(route('store.orders.store'), [
            'idempotency_key' => $key,
            'delivery_method' => StoreDeliveryMethod::PICKUP,
            'coupon_code' => 'web10',
            'total' => '0.01',
            'shipping_total' => '0.01',
        ])->assertRedirect();

        $order = StoreOrder::firstOrFail();
        $this->assertSame('180.00', $order->subtotal);
        $this->assertSame('10.00', $order->discount_total);
        $this->assertSame('170.00', $order->total);
        $this->assertNull(session('store_cart.lines'));
        $this->assertNotSame($key, session('store_checkout.idempotency_key'));
    }

    public function test_checkout_supports_shipping_precedence_and_idempotent_double_submit(): void
    {
        $affiliate = $this->affiliate();
        $product = $this->product(['affiliate_price' => 50]);
        StoreSetting::current()->update(['shipping_enabled' => true]);
        StoreShippingRate::create(['scope' => 'national', 'amount' => 40, 'currency' => 'BOB', 'active' => true]);
        StoreShippingRate::create(['scope' => 'zone', 'department' => 'LA PAZ', 'city' => 'EL ALTO', 'zone' => 'CENTRO', 'amount' => 9, 'currency' => 'BOB', 'active' => true, 'priority' => 1]);

        $this->actingAs($affiliate->user)->post(route('store.cart.store'), ['product_public_code' => $product->public_code, 'quantity' => 1]);
        $this->actingAs($affiliate->user)->get(route('store.checkout.show'));
        $key = session('store_checkout.idempotency_key');
        $payload = [
            'idempotency_key' => $key,
            'delivery_method' => StoreDeliveryMethod::SHIPPING,
            'department' => 'la paz',
            'city' => 'el alto',
            'zone' => 'centro',
            'delivery_address' => 'Direccion  privada',
        ];

        $this->actingAs($affiliate->user)->post(route('store.orders.store'), $payload)->assertRedirect();
        $order = StoreOrder::firstOrFail();
        $this->assertSame('9.00', $order->shipping_total);
        $this->assertSame('59.00', $order->total);
        $this->assertSame('zone', $order->shipping_snapshot['scope']);
        $this->assertSame('LA PAZ', $order->department);
        $this->assertSame('EL ALTO', $order->city);
        $this->assertSame('CENTRO', $order->zone);
        $this->assertSame('DIRECCION PRIVADA', $order->delivery_address);

        session(['store_cart.lines' => [['line_key' => 'x', 'product_public_code' => $product->public_code, 'variant_public_code' => null, 'quantity' => 1]]]);
        session(['store_checkout.idempotency_key' => $key]);
        $this->actingAs($affiliate->user)->post(route('store.orders.store'), $payload)->assertRedirect(route('store.orders.show', $order));
        $this->assertSame(1, StoreOrder::count());
    }

    public function test_affiliate_can_only_see_own_orders(): void
    {
        $owner = $this->affiliate();
        $other = $this->affiliate();
        $product = $this->product();
        $this->actingAs($owner->user)->post(route('store.cart.store'), ['product_public_code' => $product->public_code, 'quantity' => 1]);
        $this->actingAs($owner->user)->get(route('store.checkout.show'));
        $this->actingAs($owner->user)->post(route('store.orders.store'), [
            'idempotency_key' => session('store_checkout.idempotency_key'),
            'delivery_method' => StoreDeliveryMethod::PICKUP,
        ]);
        $order = StoreOrder::firstOrFail();

        $this->actingAs($owner->user)->get(route('store.orders.index'))->assertOk()->assertSee($order->code);
        $this->actingAs($owner->user)->get(route('store.orders.show', $order))->assertOk()->assertSee($order->code);
        $this->actingAs($other->user)->get(route('store.orders.show', $order))->assertNotFound();
    }

    private function affiliate(): Affiliate
    {
        $sector = Sector::create(['name' => fake()->unique()->word(), 'code' => fake()->unique()->bothify('SEC-###'), 'is_active' => true]);
        $plan = AffiliationPlan::create(['name' => fake()->unique()->word(), 'affiliation_fee' => 100, 'credential_fee' => 20, 'is_active' => true]);
        $person = Person::create(['full_name' => 'Afiliado Checkout', 'ci' => fake()->unique()->numerify('#######'), 'email' => fake()->unique()->safeEmail()]);
        $user = User::create(['person_id' => $person->id, 'name' => $person->full_name, 'email' => $person->email, 'role' => 'afiliado', 'user_type' => 'affiliate', 'password' => Hash::make('secret'), 'is_active' => true]);

        return Affiliate::create(['person_id' => $person->id, 'user_id' => $user->id, 'sector_id' => $sector->id, 'affiliation_plan_id' => $plan->id, 'full_name' => $person->full_name, 'ci' => $person->ci, 'email' => $person->email, 'registration_number' => fake()->unique()->bothify('REG-#####'), 'verification_token' => fake()->uuid(), 'status' => 'activo']);
    }

    private function product(array $overrides = []): StoreProduct
    {
        $category = \App\Models\StoreCategory::create(['name' => fake()->unique()->word(), 'slug' => fake()->unique()->slug(), 'active' => true]);

        return StoreProduct::create(array_merge(['store_category_id' => $category->id, 'sku' => fake()->unique()->bothify('SKU-###'), 'slug' => fake()->unique()->slug(), 'name' => 'Producto', 'regular_price' => 100, 'affiliate_price' => 90, 'availability_status' => StoreAvailabilityStatus::AVAILABLE, 'delivery_modes' => [StoreDeliveryMethod::PICKUP, StoreDeliveryMethod::SHIPPING], 'max_quantity_per_order' => 10, 'active' => true], $overrides));
    }
}
