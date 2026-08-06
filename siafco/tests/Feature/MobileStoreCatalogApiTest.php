<?php

namespace Tests\Feature;

use App\Models\Affiliate;
use App\Models\AffiliationPlan;
use App\Models\Person;
use App\Models\Sector;
use App\Models\StoreCategory;
use App\Models\StoreProduct;
use App\Models\StoreProductImage;
use App\Models\StoreProductVariant;
use App\Models\User;
use App\Support\StoreAvailabilityStatus;
use App\Support\StoreDeliveryMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileStoreCatalogApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_affiliate_sees_paginated_catalog_without_internal_fields(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('store/products/demo.jpg', 'fake-image');
        $affiliate = $this->affiliate();
        $category = $this->category('uniformes');
        $product = $this->product($category, ['name' => 'Polera SIAFCO', 'featured' => true]);
        StoreProductImage::create(['store_product_id' => $product->id, 'path' => 'store/products/demo.jpg', 'is_primary' => true, 'order' => 1]);

        $hidden = $this->product($category, ['name' => 'Oculto', 'availability_status' => StoreAvailabilityStatus::HIDDEN]);
        $inactiveCategory = $this->category('inactiva', false);
        $this->product($inactiveCategory, ['name' => 'Categoria oculta']);

        Sanctum::actingAs($affiliate->user);

        $response = $this->getJson('/api/mobile/v1/store?search=polera&featured=1&per_page=5');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.settings.currency', 'BOB')
            ->assertJsonPath('data.products.0.public_code', $product->public_code)
            ->assertJsonPath('data.products.0.effective_price', '80.00')
            ->assertJsonPath('data.products.0.category.slug', 'uniformes')
            ->assertJsonPath('data.pagination.per_page', 5)
            ->assertJsonMissingPath('data.products.0.id')
            ->assertJsonMissingPath('data.products.0.path')
            ->assertJsonMissingPath('data.settings.whatsapp_number_encrypted');

        $this->assertStringContainsString('/storage/store/products/demo.jpg?v=', $response->json('data.products.0.primary_image_url'));
        $this->assertStringNotContainsString($hidden->public_code, $response->getContent());
        $this->assertStringNotContainsString('Categoria oculta', $response->getContent());
    }

    public function test_product_detail_returns_active_variants_and_blocks_hidden_products(): void
    {
        $affiliate = $this->affiliate();
        $category = $this->category();
        $product = $this->product($category);
        $variant = StoreProductVariant::create(['store_product_id' => $product->id, 'type' => 'Talla', 'name' => 'L', 'price_delta' => 5, 'active' => true]);
        StoreProductVariant::create(['store_product_id' => $product->id, 'type' => 'Talla', 'name' => 'XL', 'price_delta' => 8, 'active' => false]);
        $hidden = $this->product($category, ['availability_status' => StoreAvailabilityStatus::HIDDEN]);

        Sanctum::actingAs($affiliate->user);

        $this->getJson("/api/mobile/v1/store/products/{$product->public_code}")
            ->assertOk()
            ->assertJsonPath('data.product.variants.0.public_code', $variant->public_code)
            ->assertJsonPath('data.product.variants.0.effective_price', '85.00')
            ->assertJsonMissingPath('data.product.variants.1');

        $this->getJson("/api/mobile/v1/store/products/{$hidden->public_code}")
            ->assertNotFound()
            ->assertJsonPath('success', false);
    }

    public function test_store_requires_active_affiliate_token(): void
    {
        $pending = $this->affiliate(status: 'pendiente_pago');
        Sanctum::actingAs($pending->user);
        $this->getJson('/api/mobile/v1/store')->assertForbidden()->assertJsonPath('success', false);

        $internal = User::factory()->create(['role' => 'administrador', 'user_type' => 'internal']);
        Sanctum::actingAs($internal);
        $this->getJson('/api/mobile/v1/store')->assertForbidden()->assertJsonPath('success', false);
    }

    private function affiliate(string $status = 'activo'): Affiliate
    {
        $sector = Sector::create(['name' => fake()->unique()->word(), 'code' => fake()->unique()->bothify('SEC-###'), 'is_active' => true]);
        $plan = AffiliationPlan::create(['name' => fake()->unique()->word(), 'affiliation_fee' => 100, 'credential_fee' => 20, 'is_active' => true]);
        $person = Person::create(['full_name' => 'Afiliado Store', 'ci' => fake()->unique()->numerify('#######'), 'email' => fake()->unique()->safeEmail()]);
        $user = User::create(['person_id' => $person->id, 'name' => $person->full_name, 'email' => $person->email, 'role' => 'afiliado', 'user_type' => 'affiliate', 'password' => Hash::make('secret'), 'is_active' => true]);

        return Affiliate::create(['person_id' => $person->id, 'user_id' => $user->id, 'sector_id' => $sector->id, 'affiliation_plan_id' => $plan->id, 'full_name' => $person->full_name, 'ci' => $person->ci, 'email' => $person->email, 'registration_number' => fake()->unique()->bothify('REG-#####'), 'verification_token' => fake()->uuid(), 'status' => $status]);
    }

    private function category(string $slug = 'catalogo', bool $active = true): StoreCategory
    {
        return StoreCategory::create(['name' => str($slug)->headline()->toString(), 'slug' => $slug, 'active' => $active]);
    }

    private function product(StoreCategory $category, array $overrides = []): StoreProduct
    {
        return StoreProduct::create(array_merge([
            'store_category_id' => $category->id,
            'sku' => fake()->unique()->bothify('SKU-###'),
            'slug' => fake()->unique()->slug(),
            'name' => 'Producto',
            'short_description' => 'Producto institucional',
            'description' => 'Producto institucional completo',
            'regular_price' => 100,
            'affiliate_price' => 80,
            'availability_status' => StoreAvailabilityStatus::AVAILABLE,
            'delivery_modes' => [StoreDeliveryMethod::PICKUP],
            'max_quantity_per_order' => 5,
            'active' => true,
        ], $overrides));
    }
}
