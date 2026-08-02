<?php

namespace Tests\Feature;

use App\Models\Affiliate;
use App\Models\AffiliationPlan;
use App\Models\Person;
use App\Models\Sector;
use App\Models\StoreCategory;
use App\Models\StoreCoupon;
use App\Models\StoreCouponUsage;
use App\Models\StoreProduct;
use App\Models\StoreProductVariant;
use App\Models\StoreShippingRate;
use App\Models\StoreSetting;
use App\Models\User;
use App\Support\StoreAvailabilityStatus;
use App\Support\StoreDeliveryMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileStoreQuoteApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_quote_recalculates_pickup_variant_coupon_and_never_reserves_coupon(): void
    {
        $affiliate = $this->affiliate();
        $product = $this->product();
        $variant = StoreProductVariant::create(['store_product_id' => $product->id, 'type' => 'Color', 'name' => 'Azul', 'price_delta' => 10, 'active' => true]);
        StoreCoupon::create(['code_encrypted' => 'MOVIL10', 'type' => StoreCoupon::TYPE_PERCENTAGE, 'value' => 10, 'minimum_amount' => 0, 'active' => true]);
        Sanctum::actingAs($affiliate->user);

        $response = $this->postJson('/api/mobile/v1/store/quote', [
            'items' => [[
                'product_public_code' => $product->public_code,
                'variant_public_code' => $variant->public_code,
                'quantity' => 2,
            ]],
            'delivery_method' => StoreDeliveryMethod::PICKUP,
            'coupon_code' => 'MOVIL10',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.quote.items.0.unit_price', '90.00')
            ->assertJsonPath('data.quote.subtotal', '180.00')
            ->assertJsonPath('data.quote.discount_total', '18.00')
            ->assertJsonPath('data.quote.total', '162.00')
            ->assertJsonPath('data.quote.coupon.applied', true)
            ->assertJsonMissingPath('data.quote.coupon.code');

        $this->assertSame(0, StoreCouponUsage::count());
    }

    public function test_quote_supports_shipping_precedence_and_rejects_protected_fields(): void
    {
        $affiliate = $this->affiliate();
        $product = $this->product();
        StoreSetting::current()->update(['shipping_enabled' => true]);
        StoreShippingRate::create(['scope' => StoreShippingRate::SCOPE_NATIONAL, 'amount' => 40, 'currency' => 'BOB', 'active' => true, 'priority' => 1]);
        StoreShippingRate::create(['scope' => StoreShippingRate::SCOPE_ZONE, 'department' => 'LA PAZ', 'city' => 'EL ALTO', 'zone' => 'CENTRO', 'amount' => 9, 'currency' => 'BOB', 'active' => true, 'priority' => 10]);
        Sanctum::actingAs($affiliate->user);

        $payload = [
            'items' => [['product_public_code' => $product->public_code, 'quantity' => 1]],
            'delivery_method' => StoreDeliveryMethod::SHIPPING,
            'department' => 'La Paz',
            'city' => 'El Alto',
            'zone' => 'Centro',
            'delivery_address' => 'Calle ficticia 123',
        ];

        $this->postJson('/api/mobile/v1/store/quote', $payload)
            ->assertOk()
            ->assertJsonPath('data.quote.shipping.amount', '9.00')
            ->assertJsonPath('data.quote.total', '89.00');

        $this->postJson('/api/mobile/v1/store/quote', $payload + ['total' => '1.00'])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['total']]);
    }

    private function affiliate(): Affiliate
    {
        $sector = Sector::create(['name' => fake()->unique()->word(), 'code' => fake()->unique()->bothify('SEC-###'), 'is_active' => true]);
        $plan = AffiliationPlan::create(['name' => fake()->unique()->word(), 'affiliation_fee' => 100, 'credential_fee' => 20, 'is_active' => true]);
        $person = Person::create(['full_name' => 'Afiliado Quote', 'ci' => fake()->unique()->numerify('#######'), 'email' => fake()->unique()->safeEmail()]);
        $user = User::create(['person_id' => $person->id, 'name' => $person->full_name, 'email' => $person->email, 'role' => 'afiliado', 'user_type' => 'affiliate', 'password' => Hash::make('secret'), 'is_active' => true]);

        return Affiliate::create(['person_id' => $person->id, 'user_id' => $user->id, 'sector_id' => $sector->id, 'affiliation_plan_id' => $plan->id, 'full_name' => $person->full_name, 'ci' => $person->ci, 'email' => $person->email, 'registration_number' => fake()->unique()->bothify('REG-#####'), 'verification_token' => fake()->uuid(), 'status' => 'activo']);
    }

    private function product(): StoreProduct
    {
        $category = StoreCategory::create(['name' => fake()->unique()->word(), 'slug' => fake()->unique()->slug(), 'active' => true]);

        return StoreProduct::create(['store_category_id' => $category->id, 'sku' => fake()->unique()->bothify('SKU-###'), 'slug' => fake()->unique()->slug(), 'name' => 'Producto', 'regular_price' => 100, 'affiliate_price' => 80, 'availability_status' => StoreAvailabilityStatus::AVAILABLE, 'delivery_modes' => [StoreDeliveryMethod::PICKUP, StoreDeliveryMethod::SHIPPING], 'max_quantity_per_order' => 10, 'active' => true]);
    }
}
