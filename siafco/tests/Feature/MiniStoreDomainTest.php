<?php

namespace Tests\Feature;

use App\Models\Affiliate;
use App\Models\AffiliateBenefit;
use App\Models\AffiliateBenefitRedemption;
use App\Models\AffiliationPlan;
use App\Models\Person;
use App\Models\Sector;
use App\Models\StoreCategory;
use App\Models\StoreCoupon;
use App\Models\StoreCouponTarget;
use App\Models\StoreCouponUsage;
use App\Models\StoreOrder;
use App\Models\StoreOrderItem;
use App\Models\StoreOrderReceipt;
use App\Models\StoreProduct;
use App\Models\StoreProductImage;
use App\Models\StoreProductVariant;
use App\Models\StoreSetting;
use App\Models\StoreShippingRate;
use App\Models\User;
use App\Services\StoreCouponCodeService;
use App\Services\StoreShippingRateResolver;
use App\Support\BenefitRedemptionStatus;
use App\Support\StoreAvailabilityStatus;
use App\Support\StoreDeliveryMethod;
use App\Support\StoreOrderStatus;
use App\Support\StoreReceiptStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MiniStoreDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_models_keep_relationships_casts_public_codes_and_sensitive_fields_private(): void
    {
        $affiliate = $this->affiliate();
        $category = StoreCategory::create([
            'slug' => 'joyas',
            'name' => 'Joyas',
            'active' => true,
            'order' => 1,
        ]);
        $product = StoreProduct::create([
            'store_category_id' => $category->id,
            'slug' => 'anillo-plata',
            'sku' => 'JOY-001',
            'name' => 'Anillo de plata',
            'regular_price' => 150,
            'affiliate_price' => 120,
            'promo_price' => 100,
            'promo_starts_at' => now()->subDay(),
            'promo_ends_at' => now()->addDay(),
            'availability_status' => StoreAvailabilityStatus::AVAILABLE,
            'delivery_modes' => [StoreDeliveryMethod::PICKUP, StoreDeliveryMethod::SHIPPING],
            'featured' => true,
            'active' => true,
            'order' => 1,
        ]);
        $variant = StoreProductVariant::create([
            'store_product_id' => $product->id,
            'type' => 'talla',
            'name' => '18',
            'price_delta' => 5,
            'active' => true,
        ]);
        $image = StoreProductImage::create([
            'store_product_id' => $product->id,
            'path' => 'store/products/private-name.jpg',
            'is_primary' => true,
        ]);
        $order = StoreOrder::create([
            'affiliate_id' => $affiliate->id,
            'status' => StoreOrderStatus::PENDING,
            'delivery_method' => StoreDeliveryMethod::SHIPPING,
            'department' => 'LA PAZ',
            'city' => 'EL ALTO',
            'delivery_address' => 'Direccion privada',
            'subtotal' => 125,
            'discount_total' => 10,
            'shipping_total' => 15,
            'total' => 130,
            'currency' => 'BOB',
            'coupon_snapshot' => ['code_hint' => 'PR**10'],
            'shipping_snapshot' => ['scope' => 'city', 'amount' => 15],
            'payment_snapshot' => ['bank' => 'Banco'],
            'whatsapp_number_snapshot' => '59170000000',
        ]);
        $item = StoreOrderItem::create([
            'store_order_id' => $order->id,
            'store_product_id' => $product->id,
            'store_product_variant_id' => $variant->id,
            'sku_snapshot' => 'JOY-001-18',
            'name_snapshot' => 'Anillo de plata',
            'variant_snapshot' => 'Talla 18',
            'unit_price' => 125,
            'quantity' => 1,
            'discount_total' => 10,
            'line_total' => 115,
        ]);
        $receipt = StoreOrderReceipt::create([
            'store_order_id' => $order->id,
            'uploaded_by_user_id' => $affiliate->user_id,
            'path' => 'store-receipts/'.$order->code.'/receipt.pdf',
            'mime' => 'application/pdf',
            'size_bytes' => 1234,
            'sha256' => hash('sha256', 'receipt'),
            'status' => StoreReceiptStatus::PENDING,
            'submitted_at' => now(),
        ]);

        $this->assertSame($category->id, $product->category->id);
        $this->assertSame($product->id, $variant->product->id);
        $this->assertSame($affiliate->id, $order->affiliate->id);
        $this->assertSame($order->id, $item->order->id);
        $this->assertSame($receipt->id, $order->receipts()->first()->id);
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $variant->public_code);
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $order->public_id);
        $this->assertMatchesRegularExpression('/^PED-\d{6}-[A-Z0-9]{8}$/', $order->code);
        $this->assertSame('120.00', $product->affiliate_price);
        $this->assertSame([StoreDeliveryMethod::PICKUP, StoreDeliveryMethod::SHIPPING], $product->delivery_modes);
        $this->assertTrue($product->featured);
        $this->assertSame('59170000000', $order->whatsapp_number_snapshot);
        $this->assertArrayNotHasKey('path', $image->toArray());
        $this->assertArrayNotHasKey('path', $receipt->toArray());
        $this->assertArrayNotHasKey('sha256', $receipt->toArray());
        $this->assertArrayNotHasKey('delivery_address', $order->toArray());
        $this->assertArrayNotHasKey('whatsapp_number_snapshot', $order->toArray());
    }

    public function test_store_settings_singleton_and_coupon_code_are_encrypted_hashed_and_hidden(): void
    {
        $settings = StoreSetting::current();
        $settings->update([
            'whatsapp_number_encrypted' => '59170000000',
            'whatsapp_number_hash' => hash('sha256', '59170000000'),
            'whatsapp_number_hint' => '591****000',
            'whatsapp_enabled' => true,
        ]);

        $coupon = StoreCoupon::create([
            'code_encrypted' => ' promo 10 ',
            'type' => StoreCoupon::TYPE_PERCENTAGE,
            'value' => 10,
            'minimum_amount' => 100,
            'active' => true,
        ]);

        $service = app(StoreCouponCodeService::class);

        $this->assertSame($settings->id, StoreSetting::current()->id);
        $this->assertSame('59170000000', $settings->fresh()->whatsapp_number_encrypted);
        $this->assertArrayNotHasKey('whatsapp_number_encrypted', $settings->fresh()->toArray());
        $this->assertSame('PROMO10', $coupon->fresh()->code_encrypted);
        $this->assertSame($service->hash('PROMO10'), $coupon->fresh()->code_hash);
        $this->assertSame('PR***10', $coupon->fresh()->code_hint);
        $this->assertArrayNotHasKey('code_encrypted', $coupon->fresh()->toArray());
        $this->assertArrayNotHasKey('code_hash', $coupon->fresh()->toArray());
    }

    public function test_shipping_rate_resolver_uses_zone_city_department_national_then_priority_and_null_when_missing(): void
    {
        $resolver = app(StoreShippingRateResolver::class);

        $national = $this->shippingRate(StoreShippingRate::SCOPE_NATIONAL, null, null, null, 40, 0);
        $department = $this->shippingRate(StoreShippingRate::SCOPE_DEPARTMENT, 'LA PAZ', null, null, 30, 0);
        $city = $this->shippingRate(StoreShippingRate::SCOPE_CITY, 'LA PAZ', 'EL ALTO', null, 20, 0);
        $this->shippingRate(StoreShippingRate::SCOPE_ZONE, 'LA PAZ', 'EL ALTO', 'CENTRO', 15, 1);
        $zoneWinner = $this->shippingRate(StoreShippingRate::SCOPE_ZONE, 'LA PAZ', 'EL ALTO', 'CENTRO', 12, 10);

        $this->assertSame($zoneWinner->id, $resolver->resolve('la paz', 'el alto', 'centro')->id);
        $this->assertSame($city->id, $resolver->resolve('LA PAZ', 'EL ALTO', 'NORTE')->id);
        $this->assertSame($department->id, $resolver->resolve('LA PAZ', 'ACHOCALLA')->id);
        $this->assertSame($national->id, $resolver->resolve('ORURO')->id);

        StoreShippingRate::query()->delete();
        $this->assertNull($resolver->resolve('LA PAZ', 'EL ALTO', 'CENTRO'));
    }

    public function test_status_transitions_accept_valid_paths_and_reject_invalid_paths(): void
    {
        $order = StoreOrder::create([
            'affiliate_id' => $this->affiliate()->id,
            'status' => StoreOrderStatus::PENDING,
            'delivery_method' => StoreDeliveryMethod::PICKUP,
            'subtotal' => 100,
            'discount_total' => 0,
            'shipping_total' => 0,
            'total' => 100,
            'currency' => 'BOB',
        ]);

        $order->transitionTo(StoreOrderStatus::WAITING_PAYMENT);
        $order->transitionTo(StoreOrderStatus::PAYMENT_REVIEW);
        $order->transitionTo(StoreOrderStatus::CONFIRMED);

        $this->assertNotNull($order->fresh()->confirmed_at);

        $this->expectException(ValidationException::class);
        $order->fresh()->transitionTo(StoreOrderStatus::PENDING);
    }

    public function test_benefits_are_backward_compatible_and_redemptions_have_transitions(): void
    {
        $benefit = AffiliateBenefit::create([
            'title' => 'Noticias',
            'description' => 'Informacion',
            'icon' => 'news',
            'active' => true,
            'visible_when_pending' => true,
            'order' => 1,
        ]);
        $affiliate = $this->affiliate();
        $redemption = AffiliateBenefitRedemption::create([
            'affiliate_benefit_id' => $benefit->id,
            'affiliate_id' => $affiliate->id,
            'status' => BenefitRedemptionStatus::PENDING,
        ]);

        $this->assertSame('informational', $benefit->benefit_type);
        $this->assertFalse((bool) $benefit->redeemable);
        $this->assertSame($benefit->id, $redemption->benefit->id);
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $redemption->public_id);
        $this->assertMatchesRegularExpression('/^CAN-\d{6}-[A-Z0-9]{8}$/', $redemption->code);

        $redemption->transitionTo(BenefitRedemptionStatus::APPROVED);
        $redemption->transitionTo(BenefitRedemptionStatus::USED);
        $this->assertNotNull($redemption->fresh()->used_at);

        $this->expectException(ValidationException::class);
        $redemption->fresh()->transitionTo(BenefitRedemptionStatus::CANCELLED);
    }

    public function test_coupon_target_requires_product_or_category_and_usage_links_coupon_order_affiliate(): void
    {
        $affiliate = $this->affiliate();
        $category = StoreCategory::create(['slug' => 'beneficios', 'name' => 'Beneficios']);
        $coupon = StoreCoupon::create([
            'code_encrypted' => 'todo20',
            'type' => StoreCoupon::TYPE_FIXED,
            'value' => 20,
            'active' => true,
        ]);
        $target = StoreCouponTarget::create([
            'store_coupon_id' => $coupon->id,
            'store_category_id' => $category->id,
        ]);
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
        $usage = StoreCouponUsage::create([
            'store_coupon_id' => $coupon->id,
            'store_order_id' => $order->id,
            'affiliate_id' => $affiliate->id,
            'amount' => 20,
            'used_at' => now(),
        ]);

        $this->assertSame($category->id, $target->category->id);
        $this->assertSame($order->id, $usage->order->id);
        $this->assertFalse($coupon->appliesToAllProducts());

        $this->expectException(ValidationException::class);
        StoreCouponTarget::create(['store_coupon_id' => $coupon->id]);
    }

    public function test_order_items_keep_snapshots_when_product_and_variant_are_deleted_and_no_stock_column_exists(): void
    {
        $affiliate = $this->affiliate();
        $category = StoreCategory::create(['slug' => 'cooperativa', 'name' => 'Cooperativa']);
        $product = StoreProduct::create([
            'store_category_id' => $category->id,
            'slug' => 'cafe',
            'sku' => 'CAF-001',
            'name' => 'Cafe',
            'regular_price' => 50,
            'affiliate_price' => 45,
            'availability_status' => StoreAvailabilityStatus::AVAILABLE,
            'delivery_modes' => [StoreDeliveryMethod::PICKUP],
            'active' => true,
        ]);
        $variant = StoreProductVariant::create([
            'store_product_id' => $product->id,
            'type' => 'modelo',
            'name' => 'Molido',
            'active' => true,
        ]);
        $order = StoreOrder::create([
            'affiliate_id' => $affiliate->id,
            'status' => StoreOrderStatus::PENDING,
            'delivery_method' => StoreDeliveryMethod::PICKUP,
            'subtotal' => 45,
            'discount_total' => 0,
            'shipping_total' => 0,
            'total' => 45,
            'currency' => 'BOB',
        ]);
        $item = StoreOrderItem::create([
            'store_order_id' => $order->id,
            'store_product_id' => $product->id,
            'store_product_variant_id' => $variant->id,
            'sku_snapshot' => 'CAF-001-MOL',
            'name_snapshot' => 'Cafe',
            'variant_snapshot' => 'Molido',
            'unit_price' => 45,
            'quantity' => 1,
            'discount_total' => 0,
            'line_total' => 45,
        ]);

        $variant->forceDelete();
        $product->forceDelete();

        $item->refresh();
        $this->assertNull($item->store_product_id);
        $this->assertNull($item->store_product_variant_id);
        $this->assertSame('Cafe', $item->name_snapshot);
        $this->assertSame('Molido', $item->variant_snapshot);
        $this->assertFalse(Schema::hasColumn('store_products', 'stock_quantity'));
    }

    public function test_scopes_return_active_visible_and_available_records(): void
    {
        $category = StoreCategory::create(['slug' => 'joyeria', 'name' => 'Joyeria']);
        StoreProduct::create([
            'store_category_id' => $category->id,
            'slug' => 'visible',
            'sku' => 'VIS',
            'name' => 'Visible',
            'regular_price' => 10,
            'affiliate_price' => 9,
            'availability_status' => StoreAvailabilityStatus::AVAILABLE,
            'delivery_modes' => [StoreDeliveryMethod::PICKUP],
            'active' => true,
        ]);
        StoreProduct::create([
            'store_category_id' => $category->id,
            'slug' => 'oculto',
            'sku' => 'HID',
            'name' => 'Oculto',
            'regular_price' => 10,
            'affiliate_price' => 9,
            'availability_status' => StoreAvailabilityStatus::HIDDEN,
            'delivery_modes' => [StoreDeliveryMethod::PICKUP],
            'active' => true,
        ]);

        $this->assertSame(2, StoreProduct::active()->count());
        $this->assertSame(1, StoreProduct::visible()->count());
        $this->assertSame(1, StoreProduct::available()->count());
    }

    private function shippingRate(
        string $scope,
        ?string $department,
        ?string $city,
        ?string $zone,
        int $amount,
        int $priority
    ): StoreShippingRate {
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

    private function affiliate(): Affiliate
    {
        $sector = Sector::create([
            'name' => 'Magisterio',
            'code' => 'MAG-'.fake()->unique()->numerify('###'),
            'is_active' => true,
        ]);
        $plan = AffiliationPlan::create([
            'name' => 'Plan base '.fake()->unique()->numerify('###'),
            'affiliation_fee' => 100,
            'credential_fee' => 20,
            'is_active' => true,
        ]);
        $person = Person::create([
            'full_name' => 'Afiliado Tienda',
            'ci' => fake()->unique()->numerify('#######'),
            'email' => fake()->unique()->safeEmail(),
        ]);
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
            'registration_number' => 'MAG-'.fake()->unique()->numerify('######'),
            'verification_token' => fake()->uuid(),
            'status' => 'activo',
        ]);
    }
}
