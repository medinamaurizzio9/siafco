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
use App\Models\StoreProductImage;
use App\Models\User;
use App\Support\StoreAvailabilityStatus;
use App\Support\StoreDeliveryMethod;
use App\Support\StoreOrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
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

    public function test_order_detail_items_include_nullable_primary_image_url(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('store/products/joya.jpg', 'fake-image');

        $affiliate = $this->affiliate();
        $withImage = $this->product(['name' => 'JOYA CONVENIO']);
        $withoutImage = $this->product(['name' => 'JOUA JUVENIL']);
        StoreProductImage::create([
            'store_product_id' => $withImage->id,
            'path' => 'store/products/joya.jpg',
            'is_primary' => true,
            'order' => 1,
        ]);

        Sanctum::actingAs($affiliate->user);

        $code = $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/mobile/v1/store/orders', [
            'items' => [
                ['product_public_code' => $withImage->public_code, 'quantity' => 1],
                ['product_public_code' => $withoutImage->public_code, 'quantity' => 1],
            ],
            'delivery_method' => StoreDeliveryMethod::PICKUP,
        ])->assertCreated()->json('data.order.code');

        $response = $this->getJson("/api/mobile/v1/store/orders/{$code}");

        $response->assertOk()
            ->assertJsonPath('data.order.items.0.name', 'JOYA CONVENIO')
            ->assertJsonPath('data.order.items.1.name', 'JOUA JUVENIL')
            ->assertJsonPath('data.order.items.1.primary_image_url', null)
            ->assertJsonMissingPath('data.order.items.0.id')
            ->assertJsonMissingPath('data.order.items.0.store_product_id');

        $this->assertStringContainsString(
            '/storage/store/products/joya.jpg?v=',
            $response->json('data.order.items.0.primary_image_url')
        );
    }

    public function test_attention_only_returns_old_pending_orders_before_pagination(): void
    {
        $owner = $this->affiliate();
        $other = $this->affiliate();
        Sanctum::actingAs($owner->user);

        foreach (range(1, 16) as $index) {
            $this->orderFor($owner, StoreOrderStatus::DELIVERED, now()->subMinutes($index));
        }

        $attentionOrders = collect([
            StoreOrderStatus::PENDING,
            StoreOrderStatus::RESERVED,
            StoreOrderStatus::WAITING_PAYMENT,
            StoreOrderStatus::PAYMENT_REVIEW,
        ])->map(fn (string $status, int $index) => $this->orderFor($owner, $status, now()->subDays(2)->subMinutes($index)));

        $otherOrder = $this->orderFor($other, StoreOrderStatus::PENDING, now()->subDay());

        $response = $this->getJson('/api/mobile/v1/store/orders?attention_only=true');

        $response->assertOk()
            ->assertJsonPath('data.pagination.total', 4)
            ->assertJsonMissing(['code' => $otherOrder->code]);

        $this->assertEqualsCanonicalizing(
            $attentionOrders->pluck('code')->all(),
            collect($response->json('data.orders'))->pluck('code')->all()
        );
    }

    public function test_attention_only_excludes_closed_and_unrelated_orders(): void
    {
        $owner = $this->affiliate();
        $other = $this->affiliate();
        Sanctum::actingAs($owner->user);

        $included = $this->orderFor($owner, StoreOrderStatus::PAYMENT_REVIEW, now());
        $excludedStatuses = [
            StoreOrderStatus::CONFIRMED,
            StoreOrderStatus::CANCELLED,
            StoreOrderStatus::SHIPPED,
            StoreOrderStatus::DELIVERED,
        ];

        foreach ($excludedStatuses as $index => $status) {
            $this->orderFor($owner, $status, now()->subMinutes($index + 1));
        }

        $otherPending = $this->orderFor($other, StoreOrderStatus::PENDING, now());

        $this->getJson('/api/mobile/v1/store/orders?attention_only=true')
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.orders.0.code', $included->code)
            ->assertJsonMissing(['code' => $otherPending->code]);
    }

    public function test_orders_without_attention_only_keep_default_listing(): void
    {
        $owner = $this->affiliate();
        Sanctum::actingAs($owner->user);

        $oldPending = $this->orderFor($owner, StoreOrderStatus::PENDING, now()->subDays(3));
        $recentDelivered = $this->orderFor($owner, StoreOrderStatus::DELIVERED, now());

        $this->getJson('/api/mobile/v1/store/orders')
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 2)
            ->assertJsonPath('data.orders.0.code', $recentDelivered->code)
            ->assertJsonFragment(['code' => $oldPending->code]);
    }

    public function test_attention_only_rejects_invalid_value(): void
    {
        $affiliate = $this->affiliate();
        Sanctum::actingAs($affiliate->user);

        $this->getJson('/api/mobile/v1/store/orders?attention_only=maybe')
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['attention_only']]);
    }

    private function affiliate(): Affiliate
    {
        $sector = Sector::create(['name' => fake()->unique()->word(), 'code' => fake()->unique()->bothify('SEC-###'), 'is_active' => true]);
        $plan = AffiliationPlan::create(['name' => fake()->unique()->word(), 'affiliation_fee' => 100, 'credential_fee' => 20, 'is_active' => true]);
        $person = Person::create(['full_name' => 'Afiliado Pedido', 'ci' => fake()->unique()->numerify('#######'), 'email' => fake()->unique()->safeEmail()]);
        $user = User::create(['person_id' => $person->id, 'name' => $person->full_name, 'email' => $person->email, 'role' => 'afiliado', 'user_type' => 'affiliate', 'password' => Hash::make('secret'), 'is_active' => true]);

        return Affiliate::create(['person_id' => $person->id, 'user_id' => $user->id, 'sector_id' => $sector->id, 'affiliation_plan_id' => $plan->id, 'full_name' => $person->full_name, 'ci' => $person->ci, 'email' => $person->email, 'registration_number' => fake()->unique()->bothify('REG-#####'), 'verification_token' => fake()->uuid(), 'status' => 'activo']);
    }

    private function product(array $overrides = []): StoreProduct
    {
        $category = StoreCategory::create(['name' => fake()->unique()->word(), 'slug' => fake()->unique()->slug(), 'active' => true]);

        return StoreProduct::create(array_merge(['store_category_id' => $category->id, 'sku' => fake()->unique()->bothify('SKU-###'), 'slug' => fake()->unique()->slug(), 'name' => 'Producto Pedido', 'regular_price' => 100, 'affiliate_price' => 80, 'availability_status' => StoreAvailabilityStatus::AVAILABLE, 'delivery_modes' => [StoreDeliveryMethod::PICKUP], 'max_quantity_per_order' => 10, 'active' => true], $overrides));
    }

    private function orderFor(Affiliate $affiliate, string $status, \DateTimeInterface $createdAt): StoreOrder
    {
        $order = StoreOrder::create([
            'affiliate_id' => $affiliate->id,
            'status' => $status,
            'delivery_method' => StoreDeliveryMethod::PICKUP,
            'subtotal' => 10,
            'discount_total' => 0,
            'shipping_total' => 0,
            'total' => 10,
            'currency' => 'BOB',
        ]);

        $order->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->save();

        return $order->refresh();
    }
}
