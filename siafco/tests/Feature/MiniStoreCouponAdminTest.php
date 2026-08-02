<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\StoreCategory;
use App\Models\StoreCoupon;
use App\Models\StoreProduct;
use App\Models\User;
use App\Services\StoreCouponCodeService;
use App\Support\StoreAvailabilityStatus;
use App\Support\StoreDeliveryMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MiniStoreCouponAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_coupon_crud_normalizes_hashes_hints_targets_and_safe_audit(): void
    {
        $admin = $this->admin();
        $category = StoreCategory::create(['name' => 'Textiles', 'slug' => 'textiles', 'active' => true]);
        $product = $this->product($category);

        $this->actingAs($admin)->post(route('admin.store.coupons.store'), [
            'code' => ' promo - 10 ',
            'type' => StoreCoupon::TYPE_PERCENTAGE,
            'value' => '10',
            'starts_at' => now()->subDay()->format('Y-m-d H:i:s'),
            'ends_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'minimum_amount' => '50.00',
            'global_limit' => 100,
            'per_affiliate_limit' => 2,
            'active' => '1',
            'target_products' => [$product->id, $product->id],
            'target_categories' => [$category->id],
        ])->assertRedirect(route('admin.store.coupons.index'));

        $coupon = StoreCoupon::firstOrFail();
        $service = app(StoreCouponCodeService::class);
        $this->assertSame($service->hash('PROMO-10'), $coupon->code_hash);
        $this->assertSame('PR****10', $coupon->code_hint);
        $this->assertNotSame('PROMO-10', $coupon->getRawOriginal('code_encrypted'));
        $this->assertSame(2, $coupon->targets()->count());

        $metadata = json_encode(AuditLog::firstWhere('action', 'mini_tienda.cupon_creado')->metadata);
        $this->assertStringContainsString('PR****10', $metadata);
        $this->assertStringNotContainsString('PROMO-10', $metadata);
        $this->assertStringNotContainsString($coupon->code_hash, $metadata);

        $this->actingAs($admin)->put(route('admin.store.coupons.update', $coupon), [
            'code' => '',
            'type' => StoreCoupon::TYPE_FIXED,
            'value' => '15',
            'minimum_amount' => '0',
            'active' => '0',
        ])->assertRedirect(route('admin.store.coupons.index'));

        $coupon->refresh();
        $this->assertSame($service->hash('PROMO-10'), $coupon->code_hash);
        $this->assertSame(StoreCoupon::TYPE_FIXED, $coupon->type);
        $this->assertFalse($coupon->active);

        $this->actingAs($admin)->delete(route('admin.store.coupons.destroy', $coupon))->assertRedirect();
        $this->assertSoftDeleted($coupon);
    }

    public function test_coupon_validation_rejects_duplicate_active_code_invalid_percent_dates_and_inactive_targets(): void
    {
        $admin = $this->admin();
        StoreCoupon::create([
            'code_encrypted' => 'PROMO10',
            'type' => StoreCoupon::TYPE_PERCENTAGE,
            'value' => 10,
            'minimum_amount' => 0,
            'active' => true,
        ]);
        $inactive = StoreCategory::create(['name' => 'Oculta', 'slug' => 'oculta', 'active' => false]);

        $this->actingAs($admin)->post(route('admin.store.coupons.store'), [
            'code' => 'promo 10',
            'type' => StoreCoupon::TYPE_PERCENTAGE,
            'value' => '101',
            'starts_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'ends_at' => now()->subDay()->format('Y-m-d H:i:s'),
            'minimum_amount' => '-1',
            'active' => '1',
            'target_categories' => [$inactive->id],
        ])->assertSessionHasErrors(['code', 'value', 'ends_at', 'minimum_amount', 'target_categories.0']);
    }

    public function test_coupon_admin_access_uses_store_permissions(): void
    {
        $consulta = User::factory()->create(['role' => 'consulta', 'user_type' => 'internal']);
        $affiliate = User::factory()->create(['role' => 'afiliado', 'user_type' => 'affiliate']);

        $this->actingAs($consulta)->get(route('admin.store.coupons.index'))->assertOk();
        $this->actingAs($consulta)->get(route('admin.store.coupons.create'))->assertForbidden();
        $this->actingAs($affiliate)->get(route('admin.store.coupons.index'))->assertForbidden();
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'administrador', 'user_type' => 'internal']);
    }

    private function product(StoreCategory $category): StoreProduct
    {
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
