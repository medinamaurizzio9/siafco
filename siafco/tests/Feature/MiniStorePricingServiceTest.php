<?php

namespace Tests\Feature;

use App\Models\Affiliate;
use App\Models\AffiliationPlan;
use App\Models\Person;
use App\Models\Sector;
use App\Models\StoreCategory;
use App\Models\StoreCoupon;
use App\Models\StoreCouponTarget;
use App\Models\StoreCouponUsage;
use App\Models\StoreOrder;
use App\Models\StoreProduct;
use App\Models\StoreProductVariant;
use App\Models\StoreSetting;
use App\Models\StoreShippingRate;
use App\Models\User;
use App\Services\Store\StorePricingService;
use App\Support\StoreAvailabilityStatus;
use App\Support\StoreDeliveryMethod;
use App\Support\StoreOrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MiniStorePricingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_prices_use_lowest_valid_affiliate_or_promo_price_and_variant_delta(): void
    {
        Carbon::setTestNow('2026-08-02 12:00:00');
        $affiliate = $this->affiliate();
        $product = $this->product([
            'regular_price' => 100,
            'affiliate_price' => 80,
            'promo_price' => 70,
            'promo_starts_at' => now()->subDay(),
            'promo_ends_at' => now()->addDay(),
        ]);
        $variant = StoreProductVariant::create([
            'store_product_id' => $product->id,
            'type' => 'COLOR',
            'name' => 'DORADO',
            'price_delta' => 5,
            'active' => true,
        ]);

        $quote = app(StorePricingService::class)->quote($affiliate, [[
            'product_public_code' => $product->public_code,
            'variant_public_code' => $variant->public_code,
            'quantity' => 2,
            'unit_price' => '0.01',
        ]], ['method' => StoreDeliveryMethod::PICKUP]);

        $this->assertSame('75.00', $quote['lines'][0]['unit_price']);
        $this->assertSame('150.00', $quote['subtotal']);
        $this->assertSame('150.00', $quote['total']);
        $this->assertSame('promo', $quote['lines'][0]['price_reason']);
    }

    public function test_expired_promo_product_rules_and_quantity_are_rejected_or_ignored(): void
    {
        Carbon::setTestNow('2026-08-02 12:00:00');
        $affiliate = $this->affiliate();
        $expired = $this->product([
            'regular_price' => 100,
            'affiliate_price' => 90,
            'promo_price' => 50,
            'promo_starts_at' => now()->subDays(3),
            'promo_ends_at' => now()->subDay(),
            'max_quantity_per_order' => 2,
        ]);

        $quote = app(StorePricingService::class)->quote($affiliate, [[
            'product_public_code' => $expired->public_code,
            'quantity' => 1,
        ]], ['method' => StoreDeliveryMethod::PICKUP]);
        $this->assertSame('90.00', $quote['lines'][0]['unit_price']);

        $this->expectException(ValidationException::class);
        app(StorePricingService::class)->quote($affiliate, [[
            'product_public_code' => $expired->public_code,
            'quantity' => 3,
        ]], ['method' => StoreDeliveryMethod::PICKUP]);
    }

    public function test_unavailable_hidden_inactive_category_and_negative_variant_are_controlled(): void
    {
        $affiliate = $this->affiliate();
        $product = $this->product(['affiliate_price' => 10]);
        $variant = StoreProductVariant::create([
            'store_product_id' => $product->id,
            'type' => 'PROMO',
            'name' => 'BONO',
            'price_delta' => -20,
            'active' => true,
        ]);

        $quote = app(StorePricingService::class)->quote($affiliate, [[
            'product_public_code' => $product->public_code,
            'variant_public_code' => $variant->public_code,
            'quantity' => 1,
        ]], ['method' => StoreDeliveryMethod::PICKUP]);
        $this->assertSame('0.00', $quote['lines'][0]['unit_price']);

        $product->update(['availability_status' => StoreAvailabilityStatus::SOLD_OUT]);
        $this->expectException(ValidationException::class);
        app(StorePricingService::class)->quote($affiliate, [['product_public_code' => $product->public_code, 'quantity' => 1]], ['method' => StoreDeliveryMethod::PICKUP]);
    }

    public function test_coupons_apply_percentage_fixed_minimum_limits_and_targets(): void
    {
        $affiliate = $this->affiliate();
        $category = StoreCategory::create(['name' => 'Objetivo', 'slug' => 'objetivo', 'active' => true]);
        $eligible = $this->product(['store_category_id' => $category->id, 'affiliate_price' => 100]);
        $other = $this->product(['affiliate_price' => 50]);
        $coupon = StoreCoupon::create([
            'code_encrypted' => 'CAT20',
            'type' => StoreCoupon::TYPE_PERCENTAGE,
            'value' => 20,
            'minimum_amount' => 100,
            'active' => true,
            'global_limit' => 2,
            'per_affiliate_limit' => 1,
        ]);
        StoreCouponTarget::create(['store_coupon_id' => $coupon->id, 'store_category_id' => $category->id]);

        $quote = app(StorePricingService::class)->quote($affiliate, [
            ['product_public_code' => $eligible->public_code, 'quantity' => 1],
            ['product_public_code' => $other->public_code, 'quantity' => 1],
        ], ['method' => StoreDeliveryMethod::PICKUP], ' cat 20 ');

        $this->assertSame('150.00', $quote['subtotal']);
        $this->assertSame('20.00', $quote['discount_total']);
        $this->assertSame('130.00', $quote['total']);

        $order = StoreOrder::create([
            'affiliate_id' => $affiliate->id,
            'status' => StoreOrderStatus::PENDING,
            'delivery_method' => StoreDeliveryMethod::PICKUP,
            'subtotal' => 100,
            'discount_total' => 20,
            'shipping_total' => 0,
            'total' => 80,
            'currency' => 'BOB',
        ]);
        StoreCouponUsage::create(['store_coupon_id' => $coupon->id, 'store_order_id' => $order->id, 'affiliate_id' => $affiliate->id, 'amount' => 20, 'used_at' => now()]);

        $this->expectException(ValidationException::class);
        app(StorePricingService::class)->quote($affiliate, [
            ['product_public_code' => $eligible->public_code, 'quantity' => 1],
        ], ['method' => StoreDeliveryMethod::PICKUP], 'CAT20');
    }

    public function test_fixed_coupon_never_exceeds_eligible_subtotal(): void
    {
        $affiliate = $this->affiliate();
        $product = $this->product(['affiliate_price' => 50]);
        StoreCoupon::create([
            'code_encrypted' => 'TODO100',
            'type' => StoreCoupon::TYPE_FIXED,
            'value' => 100,
            'minimum_amount' => 0,
            'active' => true,
        ]);

        $quote = app(StorePricingService::class)->quote($affiliate, [
            ['product_public_code' => $product->public_code, 'quantity' => 1],
        ], ['method' => StoreDeliveryMethod::PICKUP], 'TODO100');

        $this->assertSame('50.00', $quote['discount_total']);
        $this->assertSame('0.00', $quote['total']);
    }

    public function test_shipping_uses_precedence_and_rejects_disabled_or_missing_rates(): void
    {
        $affiliate = $this->affiliate();
        $product = $this->product();
        StoreSetting::current()->update(['pickup_enabled' => true, 'shipping_enabled' => true]);
        $this->shipping(StoreShippingRate::SCOPE_NATIONAL, null, null, null, 40, 1);
        $this->shipping(StoreShippingRate::SCOPE_DEPARTMENT, 'LA PAZ', null, null, 30, 1);
        $this->shipping(StoreShippingRate::SCOPE_CITY, 'LA PAZ', 'EL ALTO', null, 20, 1);
        $this->shipping(StoreShippingRate::SCOPE_ZONE, 'LA PAZ', 'EL ALTO', 'CENTRO', 10, 1);

        $quote = app(StorePricingService::class)->quote($affiliate, [
            ['product_public_code' => $product->public_code, 'quantity' => 1],
        ], [
            'method' => StoreDeliveryMethod::SHIPPING,
            'department' => 'la paz',
            'city' => 'el alto',
            'zone' => 'centro',
            'address' => 'Direccion privada',
        ]);

        $this->assertSame('10.00', $quote['shipping_total']);
        $this->assertSame(StoreShippingRate::SCOPE_ZONE, $quote['shipping']['snapshot']['scope']);

        StoreSetting::current()->update(['shipping_enabled' => false]);
        $this->expectException(ValidationException::class);
        app(StorePricingService::class)->quote($affiliate, [
            ['product_public_code' => $product->public_code, 'quantity' => 1],
        ], ['method' => StoreDeliveryMethod::SHIPPING, 'department' => 'LA PAZ', 'city' => 'EL ALTO', 'address' => 'Direccion']);
    }

    public function test_only_active_affiliates_can_quote_orders(): void
    {
        $product = $this->product();
        $affiliate = $this->affiliate('pendiente_pago');

        $this->expectException(ValidationException::class);
        app(StorePricingService::class)->quote($affiliate, [
            ['product_public_code' => $product->public_code, 'quantity' => 1],
        ], ['method' => StoreDeliveryMethod::PICKUP]);
    }

    private function affiliate(string $status = 'activo', string $userType = 'affiliate', bool $isActive = true): Affiliate
    {
        $sector = Sector::create(['name' => fake()->unique()->word(), 'code' => fake()->unique()->bothify('SEC-###'), 'is_active' => true]);
        $plan = AffiliationPlan::create(['name' => fake()->unique()->word(), 'affiliation_fee' => 100, 'credential_fee' => 20, 'is_active' => true]);
        $person = Person::create(['full_name' => 'Afiliado Tienda', 'ci' => fake()->unique()->numerify('#######'), 'email' => fake()->unique()->safeEmail()]);
        $user = User::create([
            'person_id' => $person->id,
            'name' => $person->full_name,
            'email' => $person->email,
            'role' => $userType === 'affiliate' ? 'afiliado' : 'administrador',
            'user_type' => $userType,
            'password' => Hash::make('secret'),
            'is_active' => $isActive,
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
            'status' => $status,
        ]);
    }

    private function product(array $overrides = []): StoreProduct
    {
        $category = isset($overrides['store_category_id'])
            ? StoreCategory::find($overrides['store_category_id'])
            : StoreCategory::create(['name' => fake()->unique()->word(), 'slug' => fake()->unique()->slug(), 'active' => true]);

        return StoreProduct::create(array_merge([
            'store_category_id' => $category->id,
            'sku' => fake()->unique()->bothify('SKU-###'),
            'slug' => fake()->unique()->slug(),
            'name' => 'Producto',
            'regular_price' => 100,
            'affiliate_price' => 90,
            'availability_status' => StoreAvailabilityStatus::AVAILABLE,
            'delivery_modes' => [StoreDeliveryMethod::PICKUP, StoreDeliveryMethod::SHIPPING],
            'max_quantity_per_order' => 10,
            'active' => true,
        ], $overrides));
    }

    private function shipping(string $scope, ?string $department, ?string $city, ?string $zone, int $amount, int $priority): StoreShippingRate
    {
        return StoreShippingRate::create([
            'scope' => $scope,
            'department' => $department,
            'city' => $city,
            'zone' => $zone,
            'amount' => $amount,
            'currency' => 'BOB',
            'active' => true,
            'priority' => $priority,
        ]);
    }
}
