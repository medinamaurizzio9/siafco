<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\StoreCategory;
use App\Models\StoreProduct;
use App\Models\StoreProductImage;
use App\Models\StoreProductVariant;
use App\Models\User;
use App\Support\StoreAvailabilityStatus;
use App\Support\StoreDeliveryMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MiniStoreMediaAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_variants_are_created_validated_and_soft_deleted(): void
    {
        $admin = $this->admin();
        $product = $this->product(['affiliate_price' => 50]);

        $this->actingAs($admin)->post(route('admin.store.products.variants.store', $product), [
            'type' => ' talla ',
            'name' => ' mediana ',
            'sku_suffix' => ' m ',
            'price_delta' => '5.50',
            'active' => '1',
            'order' => 2,
            'public_code' => 'no-debe-entrar',
        ])->assertRedirect(route('admin.store.products.edit', $product));

        $variant = StoreProductVariant::firstOrFail();
        $this->assertSame('TALLA', $variant->type);
        $this->assertSame('MEDIANA', $variant->name);
        $this->assertSame('M', $variant->sku_suffix);
        $this->assertNotSame('no-debe-entrar', $variant->public_code);
        $this->assertDatabaseHas('audit_logs', ['action' => 'mini_tienda.variante_creada']);

        $this->actingAs($admin)->post(route('admin.store.products.variants.store', $product), [
            'type' => 'TALLA',
            'name' => 'MEDIANA',
            'price_delta' => '0',
            'active' => '1',
            'order' => 3,
        ])->assertSessionHasErrors('name');

        $this->actingAs($admin)->post(route('admin.store.products.variants.store', $product), [
            'type' => 'COLOR',
            'name' => 'AZUL',
            'price_delta' => '-51',
            'active' => '1',
            'order' => 3,
        ])->assertSessionHasErrors('price_delta');

        $this->actingAs($admin)->delete(route('admin.store.products.variants.destroy', [$product, $variant]))
            ->assertRedirect();
        $this->assertSoftDeleted($variant);
    }

    public function test_product_images_are_processed_primary_and_deleted_without_auditing_paths(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $product = $this->product();

        $this->actingAs($admin)->post(route('admin.store.products.images.store', $product), [
            'image' => UploadedFile::fake()->image('producto.png', 1600, 900),
            'alt' => 'Imagen principal',
            'is_primary' => '1',
            'order' => 5,
        ])->assertRedirect();

        $first = StoreProductImage::firstOrFail();
        $this->assertTrue($first->is_primary);
        $this->assertSame('Imagen principal', $first->alt);
        $this->assertStringStartsWith('store/products/'.$product->public_code.'/', $first->path);
        $this->assertStringEndsWith('.jpg', $first->path);
        Storage::disk('public')->assertExists($first->path);

        $this->actingAs($admin)->post(route('admin.store.products.images.store', $product), [
            'image' => UploadedFile::fake()->image('producto-2.jpg', 900, 900),
            'alt' => 'Secundaria',
            'order' => 6,
        ])->assertRedirect();

        $second = StoreProductImage::query()->where('alt', 'Secundaria')->firstOrFail();
        $this->assertFalse($second->is_primary);

        $this->actingAs($admin)->post(route('admin.store.products.images.primary', [$product, $second]))
            ->assertRedirect();
        $this->assertFalse($first->fresh()->is_primary);
        $this->assertTrue($second->fresh()->is_primary);

        $this->actingAs($admin)->delete(route('admin.store.products.images.destroy', [$product, $second]))
            ->assertRedirect();
        $this->assertDatabaseMissing('store_product_images', ['id' => $second->id]);
        Storage::disk('public')->assertMissing($second->path);
        $this->assertTrue($first->fresh()->is_primary);

        $metadata = AuditLog::query()
            ->whereIn('action', ['mini_tienda.imagen_agregada', 'mini_tienda.imagen_eliminada'])
            ->pluck('metadata')
            ->map(fn ($metadata) => json_encode($metadata))
            ->implode(' ');

        $this->assertStringNotContainsString('store/products', $metadata);
        $this->assertStringNotContainsString($first->path, $metadata);
    }

    public function test_product_image_validation_rejects_svg_corrupt_files_and_limits_images(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $product = $this->product();

        $svg = UploadedFile::fake()->createWithContent('icon.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>');
        $this->actingAs($admin)->post(route('admin.store.products.images.store', $product), [
            'image' => $svg,
            'order' => 1,
        ])->assertSessionHasErrors('image');

        $corrupt = UploadedFile::fake()->createWithContent('foto.jpg', 'no es una imagen real');
        $this->actingAs($admin)->post(route('admin.store.products.images.store', $product), [
            'image' => $corrupt,
            'order' => 1,
        ])->assertSessionHasErrors('image');

        StoreProductImage::query()->insert(collect(range(1, 8))->map(fn ($index) => [
            'store_product_id' => $product->id,
            'path' => 'store/products/'.$product->public_code.'/'.$index.'.jpg',
            'alt' => null,
            'is_primary' => $index === 1,
            'order' => $index,
            'created_at' => now(),
            'updated_at' => now(),
        ])->all());

        $this->actingAs($admin)->post(route('admin.store.products.images.store', $product), [
            'image' => UploadedFile::fake()->image('novena.jpg', 800, 800),
            'order' => 9,
        ])->assertSessionHasErrors('image');
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'administrador', 'user_type' => 'internal']);
    }

    private function product(array $overrides = []): StoreProduct
    {
        $category = StoreCategory::create([
            'name' => fake()->unique()->words(2, true),
            'slug' => fake()->unique()->slug(),
            'active' => true,
        ]);

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
