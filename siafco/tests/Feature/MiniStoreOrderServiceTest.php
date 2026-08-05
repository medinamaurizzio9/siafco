<?php

namespace Tests\Feature;

use App\Exceptions\StoreIdempotencyConflictException;
use App\Models\Affiliate;
use App\Models\AffiliationPlan;
use App\Models\AuditLog;
use App\Models\MobileApiIdempotencyKey;
use App\Models\Person;
use App\Models\Sector;
use App\Models\StoreCategory;
use App\Models\StoreCoupon;
use App\Models\StoreOrder;
use App\Models\StoreProduct;
use App\Models\StoreProductVariant;
use App\Models\StoreSetting;
use App\Models\User;
use App\Services\Store\StoreOrderService;
use App\Support\StoreAvailabilityStatus;
use App\Support\StoreDeliveryMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MiniStoreOrderServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_affiliate_creates_multi_item_order_with_safe_snapshots_and_coupon_usage(): void
    {
        $affiliate = $this->affiliate();
        StoreSetting::current()->update(['whatsapp_enabled' => true, 'whatsapp_number_encrypted' => '59170000000']);
        $first = $this->product(['sku' => 'A-1', 'name' => 'Producto A', 'affiliate_price' => 100]);
        $variant = StoreProductVariant::create(['store_product_id' => $first->id, 'type' => 'TALLA', 'name' => 'M', 'sku_suffix' => 'M', 'price_delta' => 10, 'active' => true]);
        $second = $this->product(['sku' => 'B-1', 'name' => 'Producto B', 'affiliate_price' => 50]);
        StoreCoupon::create(['code_encrypted' => 'TODO10', 'type' => StoreCoupon::TYPE_PERCENTAGE, 'value' => 10, 'minimum_amount' => 0, 'active' => true]);

        $order = app(StoreOrderService::class)->create($affiliate, [
            'items' => [
                ['product_public_code' => $first->public_code, 'variant_public_code' => $variant->public_code, 'quantity' => 1, 'unit_price' => '0.01'],
                ['product_public_code' => $second->public_code, 'quantity' => 2, 'subtotal' => '1.00'],
            ],
            'delivery' => ['method' => StoreDeliveryMethod::PICKUP],
            'coupon_code' => 'todo 10',
        ], 'order-key-1');

        $this->assertMatchesRegularExpression('/^PED-\d{6}-[A-Z0-9]{8}$/', $order->code);
        $this->assertSame('210.00', $order->subtotal);
        $this->assertSame('21.00', $order->discount_total);
        $this->assertSame('189.00', $order->total);
        $this->assertSame('TO**10', $order->coupon_snapshot['code_hint']);
        $this->assertSame('59170000000', $order->whatsapp_number_snapshot);
        $this->assertCount(2, $order->items);
        $this->assertSame('A-1-M', $order->items[0]->sku_snapshot);
        $this->assertSame('TALLA M', $order->items[0]->variant_snapshot);
        $this->assertDatabaseHas('store_coupon_usages', ['store_order_id' => $order->id, 'affiliate_id' => $affiliate->id]);
        $this->assertDatabaseHas('mobile_api_idempotency_keys', ['scope' => StoreOrderService::IDEMPOTENCY_SCOPE, 'status' => 'completed']);

        $metadata = json_encode(AuditLog::firstWhere('action', 'mini_tienda.pedido_creado')->metadata);
        $this->assertStringContainsString($order->code, $metadata);
        $this->assertStringNotContainsString('todo 10', $metadata);
        $this->assertStringNotContainsString('59170000000', $metadata);
    }

    public function test_idempotency_returns_same_order_and_conflicts_on_different_payload(): void
    {
        $affiliate = $this->affiliate();
        $product = $this->product(['affiliate_price' => 25]);
        $payload = [
            'items' => [['product_public_code' => $product->public_code, 'quantity' => 1]],
            'delivery' => ['method' => StoreDeliveryMethod::PICKUP],
        ];

        $first = app(StoreOrderService::class)->create($affiliate, $payload, 'same-key');
        $second = app(StoreOrderService::class)->create($affiliate, $payload, 'same-key');

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, StoreOrder::count());
        $this->assertSame(1, MobileApiIdempotencyKey::count());

        $this->expectException(StoreIdempotencyConflictException::class);
        app(StoreOrderService::class)->create($affiliate, [
            'items' => [['product_public_code' => $product->public_code, 'quantity' => 2]],
            'delivery' => ['method' => StoreDeliveryMethod::PICKUP],
        ], 'same-key');
    }

    public function test_idempotency_key_is_scoped_per_user_and_affiliates_are_validated(): void
    {
        $firstAffiliate = $this->affiliate();
        $secondAffiliate = $this->affiliate();
        $blocked = $this->affiliate('suspendido');
        $internal = $this->affiliate('activo', 'internal');
        $product = $this->product(['affiliate_price' => 25]);
        $payload = [
            'items' => [['product_public_code' => $product->public_code, 'quantity' => 1]],
            'delivery' => ['method' => StoreDeliveryMethod::PICKUP],
        ];

        app(StoreOrderService::class)->create($firstAffiliate, $payload, 'shared-key');
        app(StoreOrderService::class)->create($secondAffiliate, $payload, 'shared-key');
        $this->assertSame(2, StoreOrder::count());

        $this->expectException(ValidationException::class);
        app(StoreOrderService::class)->create($blocked, $payload, 'blocked-key');

        try {
            app(StoreOrderService::class)->create($internal, $payload, 'internal-key');
        } catch (ValidationException) {
            $this->assertSame(2, StoreOrder::count());
            return;
        }

        $this->fail('Internal users must not create store orders.');
    }

    private function affiliate(string $status = 'activo', string $userType = 'affiliate'): Affiliate
    {
        $sector = Sector::create(['name' => fake()->unique()->word(), 'code' => fake()->unique()->bothify('SEC-###'), 'is_active' => true]);
        $plan = AffiliationPlan::create(['name' => fake()->unique()->word(), 'affiliation_fee' => 100, 'credential_fee' => 20, 'is_active' => true]);
        $person = Person::create(['full_name' => 'Afiliado Pedido', 'ci' => fake()->unique()->numerify('#######'), 'email' => fake()->unique()->safeEmail()]);
        $user = User::create([
            'person_id' => $person->id,
            'name' => $person->full_name,
            'email' => $person->email,
            'role' => $userType === 'affiliate' ? 'afiliado' : 'administrador',
            'user_type' => $userType,
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
            'status' => $status,
        ]);
    }

    private function product(array $overrides = []): StoreProduct
    {
        $category = StoreCategory::create(['name' => fake()->unique()->word(), 'slug' => fake()->unique()->slug(), 'active' => true]);

        return StoreProduct::create(array_merge([
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
        ], $overrides));
    }
}
