<?php

namespace Tests\Feature;

use App\Models\Affiliate;
use App\Models\AffiliationPlan;
use App\Models\MobileApiIdempotencyKey;
use App\Models\Person;
use App\Models\Sector;
use App\Models\StoreCategory;
use App\Models\StoreCoupon;
use App\Models\StoreCouponUsage;
use App\Models\StoreOrder;
use App\Models\StoreProduct;
use App\Models\User;
use App\Support\StoreAvailabilityStatus;
use App\Support\StoreDeliveryMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileStoreOrderApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_affiliate_creates_order_with_snapshots_and_idempotency(): void
    {
        $affiliate = $this->affiliate();
        $product = $this->product();
        StoreCoupon::create(['code_encrypted' => 'APP10', 'type' => StoreCoupon::TYPE_FIXED, 'value' => 10, 'minimum_amount' => 0, 'active' => true]);
        Sanctum::actingAs($affiliate->user);
        $key = (string) Str::uuid();
        $payload = [
            'items' => [['product_public_code' => $product->public_code, 'quantity' => 2]],
            'delivery_method' => StoreDeliveryMethod::PICKUP,
            'coupon_code' => 'APP10',
        ];

        $first = $this->withHeader('Idempotency-Key', $key)->postJson('/api/mobile/v1/store/orders', $payload);
        $first->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.order.items.0.name', $product->name)
            ->assertJsonPath('data.order.subtotal', '160.00')
            ->assertJsonPath('data.order.discount_total', '10.00')
            ->assertJsonPath('data.order.total', '150.00')
            ->assertJsonPath('data.order.capabilities.can_upload_receipt', true)
            ->assertJsonMissingPath('data.order.id')
            ->assertJsonMissingPath('data.order.affiliate_id');

        $second = $this->withHeader('Idempotency-Key', $key)->postJson('/api/mobile/v1/store/orders', $payload);
        $second->assertOk()
            ->assertJsonPath('data.order.code', $first->json('data.order.code'));

        $this->assertSame(1, StoreOrder::count());
        $this->assertSame(1, StoreCouponUsage::count());
        $this->assertSame(1, MobileApiIdempotencyKey::count());

        $conflict = $this->withHeader('Idempotency-Key', $key)->postJson('/api/mobile/v1/store/orders', [
            'items' => [['product_public_code' => $product->public_code, 'quantity' => 1]],
            'delivery_method' => StoreDeliveryMethod::PICKUP,
        ]);
        $conflict->assertStatus(409)->assertJsonPath('success', false);
    }

    public function test_order_creation_rejects_missing_key_and_price_manipulation(): void
    {
        $affiliate = $this->affiliate();
        $product = $this->product();
        Sanctum::actingAs($affiliate->user);

        $payload = [
            'items' => [['product_public_code' => $product->public_code, 'quantity' => 1]],
            'delivery_method' => StoreDeliveryMethod::PICKUP,
        ];

        $this->postJson('/api/mobile/v1/store/orders', $payload)
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['Idempotency-Key']]);

        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/mobile/v1/store/orders', $payload + ['subtotal' => '1.00'])
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['subtotal']]);
    }

    public function test_affiliate_lists_and_views_only_own_orders(): void
    {
        $owner = $this->affiliate();
        $other = $this->affiliate();
        $product = $this->product();
        Sanctum::actingAs($owner->user);

        $own = $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/mobile/v1/store/orders', [
            'items' => [['product_public_code' => $product->public_code, 'quantity' => 1]],
            'delivery_method' => StoreDeliveryMethod::PICKUP,
        ])->json('data.order.code');

        Sanctum::actingAs($other->user);
        $otherCode = $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/mobile/v1/store/orders', [
            'items' => [['product_public_code' => $product->public_code, 'quantity' => 1]],
            'delivery_method' => StoreDeliveryMethod::PICKUP,
        ])->json('data.order.code');

        Sanctum::actingAs($owner->user);
        $this->getJson('/api/mobile/v1/store/orders')
            ->assertOk()
            ->assertJsonPath('data.orders.0.code', $own)
            ->assertJsonMissing(['code' => $otherCode]);

        $this->getJson("/api/mobile/v1/store/orders/{$own}")
            ->assertOk()
            ->assertJsonPath('data.order.code', $own)
            ->assertJsonMissingPath('data.order.receipts.0.path');

        $this->getJson("/api/mobile/v1/store/orders/{$otherCode}")
            ->assertNotFound()
            ->assertJsonPath('success', false);
    }

    private function affiliate(): Affiliate
    {
        $sector = Sector::create(['name' => fake()->unique()->word(), 'code' => fake()->unique()->bothify('SEC-###'), 'is_active' => true]);
        $plan = AffiliationPlan::create(['name' => fake()->unique()->word(), 'affiliation_fee' => 100, 'credential_fee' => 20, 'is_active' => true]);
        $person = Person::create(['full_name' => 'Afiliado Pedido', 'ci' => fake()->unique()->numerify('#######'), 'email' => fake()->unique()->safeEmail()]);
        $user = User::create(['person_id' => $person->id, 'name' => $person->full_name, 'email' => $person->email, 'role' => 'afiliado', 'user_type' => 'affiliate', 'password' => Hash::make('secret'), 'is_active' => true]);

        return Affiliate::create(['person_id' => $person->id, 'user_id' => $user->id, 'sector_id' => $sector->id, 'affiliation_plan_id' => $plan->id, 'full_name' => $person->full_name, 'ci' => $person->ci, 'email' => $person->email, 'registration_number' => fake()->unique()->bothify('REG-#####'), 'verification_token' => fake()->uuid(), 'status' => 'activo']);
    }

    private function product(): StoreProduct
    {
        $category = StoreCategory::create(['name' => fake()->unique()->word(), 'slug' => fake()->unique()->slug(), 'active' => true]);

        return StoreProduct::create(['store_category_id' => $category->id, 'sku' => fake()->unique()->bothify('SKU-###'), 'slug' => fake()->unique()->slug(), 'name' => 'Producto Pedido', 'regular_price' => 100, 'affiliate_price' => 80, 'availability_status' => StoreAvailabilityStatus::AVAILABLE, 'delivery_modes' => [StoreDeliveryMethod::PICKUP], 'max_quantity_per_order' => 10, 'active' => true]);
    }
}
