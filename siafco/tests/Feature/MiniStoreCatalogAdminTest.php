<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\StoreCategory;
use App\Models\StoreProduct;
use App\Models\User;
use App\Support\StoreAvailabilityStatus;
use App\Support\StoreDeliveryMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class MiniStoreCatalogAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_crud_uses_slug_soft_delete_and_blocks_delete_when_it_has_products(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.store.categories.store'), [
            'name' => 'Joyas SIAFCO',
            'description' => 'Accesorios',
            'active' => '1',
            'order' => 2,
        ])->assertRedirect(route('admin.store.categories.index'));

        $category = StoreCategory::firstOrFail();
        $this->assertSame('joyas-siafco', $category->slug);
        $this->assertTrue($category->active);
        $this->assertDatabaseHas('audit_logs', ['action' => 'mini_tienda.categoria_creada']);

        $this->actingAs($admin)->put(route('admin.store.categories.update', $category), [
            'name' => 'Joyas',
            'slug' => 'joyas',
            'active' => '0',
            'order' => 1,
        ])->assertRedirect(route('admin.store.categories.index'));

        $this->assertFalse($category->fresh()->active);
        $product = $this->product($category->fresh());

        $this->actingAs($admin)->delete(route('admin.store.categories.destroy', $category))
            ->assertSessionHasErrors('category');
        $this->assertNull($category->fresh()->deleted_at);

        $product->forceDelete();
        $this->actingAs($admin)->delete(route('admin.store.categories.destroy', $category))
            ->assertRedirect();
        $this->assertSoftDeleted($category);
    }

    public function test_product_crud_validates_prices_promotions_delivery_modes_and_availability(): void
    {
        $admin = $this->admin();
        $category = StoreCategory::create(['name' => 'Cooperativa', 'slug' => 'cooperativa', 'active' => true]);

        $payload = [
            'store_category_id' => $category->id,
            'sku' => ' prod-001 ',
            'name' => 'Café cooperativo',
            'regular_price' => '50.00',
            'affiliate_price' => '45.00',
            'availability_status' => StoreAvailabilityStatus::AVAILABLE,
            'delivery_modes' => [StoreDeliveryMethod::PICKUP, StoreDeliveryMethod::SHIPPING],
            'max_quantity_per_order' => 5,
            'featured' => '1',
            'active' => '1',
            'order' => 3,
        ];

        $this->actingAs($admin)->post(route('admin.store.products.store'), $payload)
            ->assertRedirect(route('admin.store.products.index'));

        $product = StoreProduct::firstOrFail();
        $this->assertSame('PROD-001', $product->sku);
        $this->assertSame('cafe-cooperativo', $product->slug);
        $this->assertSame('45.00', $product->affiliate_price);
        $this->assertSame([StoreDeliveryMethod::PICKUP, StoreDeliveryMethod::SHIPPING], $product->delivery_modes);
        $this->assertTrue($product->featured);
        $this->assertDatabaseHas('audit_logs', ['action' => 'mini_tienda.producto_creado']);
        $this->assertFalse(Schema::hasColumn('store_products', 'stock_quantity'));

        $this->actingAs($admin)->post(route('admin.store.products.store'), array_merge($payload, [
            'sku' => 'prod-002',
            'slug' => 'promo-invalida',
            'name' => 'Promo inválida',
            'promo_price' => 40,
            'delivery_modes' => [],
        ]))->assertSessionHasErrors(['promo_price', 'delivery_modes']);

        $this->actingAs($admin)->put(route('admin.store.products.update', $product), array_merge($payload, [
            'sku' => 'PROD-001-A',
            'slug' => 'cafe-editado',
            'name' => 'Café editado',
            'availability_status' => StoreAvailabilityStatus::SOLD_OUT,
            'featured' => '0',
            'active' => '0',
        ]))->assertRedirect(route('admin.store.products.index'));

        $product->refresh();
        $this->assertSame(StoreAvailabilityStatus::SOLD_OUT, $product->availability_status);
        $this->assertFalse($product->featured);
        $this->assertFalse($product->active);

        $this->actingAs($admin)->delete(route('admin.store.products.destroy', $product))
            ->assertRedirect();
        $this->assertSoftDeleted($product);
    }

    public function test_store_products_receive_non_editable_unique_public_codes_and_keep_safe_image_paths(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $category = StoreCategory::create(['name' => 'Productos', 'slug' => 'productos', 'active' => true]);

        $payload = [
            'store_category_id' => $category->id,
            'sku' => 'PUB-001',
            'slug' => 'producto-publico',
            'name' => 'Producto publico',
            'regular_price' => '100.00',
            'affiliate_price' => '90.00',
            'availability_status' => StoreAvailabilityStatus::AVAILABLE,
            'delivery_modes' => [StoreDeliveryMethod::PICKUP],
            'max_quantity_per_order' => 3,
            'active' => '1',
            'order' => 1,
            'public_code' => '11111111-1111-1111-1111-111111111111',
        ];

        $this->actingAs($admin)->post(route('admin.store.products.store'), $payload)
            ->assertRedirect(route('admin.store.products.index'));

        $first = StoreProduct::firstOrFail();
        $this->assertTrue(Str::isUuid($first->public_code));
        $this->assertNotSame('11111111-1111-1111-1111-111111111111', $first->public_code);

        $this->actingAs($admin)->post(route('admin.store.products.store'), array_merge($payload, [
            'sku' => 'PUB-002',
            'slug' => 'producto-publico-dos',
            'name' => 'Producto publico dos',
            'public_code' => $first->public_code,
        ]))->assertRedirect(route('admin.store.products.index'));

        $second = StoreProduct::query()->where('sku', 'PUB-002')->firstOrFail();
        $this->assertTrue(Str::isUuid($second->public_code));
        $this->assertNotSame($first->public_code, $second->public_code);

        $this->actingAs($admin)->put(route('admin.store.products.update', $first), array_merge($payload, [
            'sku' => 'PUB-001-A',
            'public_code' => '22222222-2222-2222-2222-222222222222',
        ]))->assertRedirect(route('admin.store.products.index'));

        $this->assertSame($first->public_code, $first->fresh()->public_code);
        $this->assertFalse(Schema::hasColumn('store_products', 'stock_quantity'));

        $this->actingAs($admin)->post(route('admin.store.products.images.store', $first), [
            'image' => \Illuminate\Http\UploadedFile::fake()->image('producto.jpg', 800, 800),
            'order' => 1,
        ])->assertRedirect();

        $this->assertStringStartsWith('store/products/'.$first->public_code.'/', $first->images()->firstOrFail()->path);
    }

    public function test_public_code_migration_backfills_existing_products_without_code(): void
    {
        $product = $this->product(StoreCategory::create([
            'name' => 'Backfill',
            'slug' => 'backfill',
            'active' => true,
        ]));

        DB::table('store_products')->where('id', $product->id)->update(['public_code' => null]);
        $this->assertNull(StoreProduct::find($product->id)->public_code);

        (require database_path('migrations/2026_08_02_000005_ensure_public_code_on_store_products_table.php'))->up();

        $product->refresh();
        $this->assertTrue(Str::isUuid($product->public_code));
        $this->assertSame(0, DB::table('store_products')->whereNull('public_code')->count());
        $this->assertSame(
            DB::table('store_products')->count(),
            DB::table('store_products')->distinct()->count('public_code')
        );
    }

    public function test_catalog_lists_are_paginated_filterable_and_readable_by_consulta(): void
    {
        $consulta = User::factory()->create(['role' => 'consulta', 'user_type' => 'internal']);
        $category = StoreCategory::create(['name' => 'Joyas', 'slug' => 'joyas', 'active' => true]);
        $this->product($category, ['name' => 'Anillo institucional', 'sku' => 'JOY-001']);

        $this->actingAs($consulta)->get(route('admin.store.categories.index', ['search' => 'joyas']))
            ->assertOk()
            ->assertSee('Joyas');

        $this->actingAs($consulta)->get(route('admin.store.products.index', ['search' => 'JOY-001']))
            ->assertOk()
            ->assertSee('Anillo institucional');

        $this->actingAs($consulta)->get(route('admin.store.products.create'))->assertForbidden();
    }

    public function test_audit_does_not_store_client_calculated_price_or_sensitive_payload(): void
    {
        $admin = $this->admin();
        $category = StoreCategory::create(['name' => 'Productos', 'slug' => 'productos', 'active' => true]);

        $this->actingAs($admin)->post(route('admin.store.products.store'), [
            'store_category_id' => $category->id,
            'sku' => 'SAFE-001',
            'slug' => 'safe-001',
            'name' => 'Producto seguro',
            'regular_price' => '100.00',
            'affiliate_price' => '90.00',
            'availability_status' => StoreAvailabilityStatus::AVAILABLE,
            'delivery_modes' => [StoreDeliveryMethod::PICKUP],
            'max_quantity_per_order' => 2,
            'active' => '1',
            'order' => 1,
            'calculated_price' => '0.01',
        ])->assertRedirect();

        $metadata = json_encode(AuditLog::firstWhere('action', 'mini_tienda.producto_creado')->metadata);
        $this->assertStringNotContainsString('calculated_price', $metadata);
        $this->assertStringNotContainsString('0.01', $metadata);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'administrador', 'user_type' => 'internal']);
    }

    private function product(StoreCategory $category, array $overrides = []): StoreProduct
    {
        return StoreProduct::create(array_merge([
            'store_category_id' => $category->id,
            'sku' => fake()->unique()->bothify('SKU-###'),
            'slug' => fake()->unique()->slug(),
            'name' => 'Producto',
            'regular_price' => 100,
            'affiliate_price' => 90,
            'availability_status' => StoreAvailabilityStatus::AVAILABLE,
            'delivery_modes' => [StoreDeliveryMethod::PICKUP],
            'active' => true,
        ], $overrides));
    }
}
