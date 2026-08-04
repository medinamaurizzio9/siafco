<?php

namespace Tests\Feature;

use App\Models\Affiliate;
use App\Models\AffiliationPlan;
use App\Models\Person;
use App\Models\Sector;
use App\Models\StoreCategory;
use App\Models\StoreProduct;
use App\Models\StoreProductVariant;
use App\Models\User;
use App\Support\StoreAvailabilityStatus;
use App\Support\StoreDeliveryMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MiniStoreWebCatalogCartTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_active_affiliates_can_access_store(): void
    {
        $active = $this->affiliate('activo')->user;
        $pending = $this->affiliate('pendiente_pago')->user;
        $internal = User::factory()->create(['role' => 'administrador', 'user_type' => 'internal']);

        $this->actingAs($active)->get(route('store.catalog.index'))->assertOk();
        $this->actingAs($pending)->get(route('store.catalog.index'))->assertRedirect(route('affiliate.panel'));
        $this->actingAs($internal)->get(route('store.catalog.index'))->assertForbidden();
    }

    public function test_catalog_shows_only_visible_products_and_hidden_detail_is_404(): void
    {
        $user = $this->affiliate()->user;
        $visible = $this->product(['name' => 'Producto visible', 'slug' => 'visible']);
        $this->product(['name' => 'Producto oculto', 'slug' => 'oculto', 'availability_status' => StoreAvailabilityStatus::HIDDEN]);
        $inactiveCategory = StoreCategory::create(['name' => 'Inactiva', 'slug' => 'inactiva', 'active' => false]);
        $this->product(['name' => 'Producto bloqueado', 'slug' => 'bloqueado', 'store_category_id' => $inactiveCategory->id]);

        $this->actingAs($user)->get(route('store.catalog.index'))
            ->assertOk()
            ->assertSee('Producto visible')
            ->assertDontSee('Producto oculto')
            ->assertDontSee('Producto bloqueado');

        $this->actingAs($user)->get(route('store.catalog.show', 'oculto'))->assertNotFound();
        $this->actingAs($user)->get(route('store.catalog.show', $visible->slug))->assertOk()->assertSee($visible->name);
    }

    public function test_cart_adds_consolidates_updates_removes_and_clears_without_storing_prices(): void
    {
        $user = $this->affiliate()->user;
        $product = $this->product(['max_quantity_per_order' => 5]);
        $variant = StoreProductVariant::create([
            'store_product_id' => $product->id,
            'type' => 'COLOR',
            'name' => 'AZUL',
            'price_delta' => 3,
            'active' => true,
        ]);

        $payload = [
            'product_public_code' => $product->public_code,
            'variant_public_code' => $variant->public_code,
            'quantity' => 2,
            'unit_price' => '0.01',
            'total' => '0.02',
        ];

        $this->actingAs($user)->post(route('store.cart.store'), $payload)->assertRedirect(route('store.cart.show'));
        $this->actingAs($user)->post(route('store.cart.store'), $payload)->assertRedirect(route('store.cart.show'));

        $lines = session('store_cart.lines');
        $this->assertCount(1, $lines);
        $this->assertSame(4, $lines[0]['quantity']);
        $this->assertArrayNotHasKey('unit_price', $lines[0]);
        $this->assertArrayNotHasKey('total', $lines[0]);

        $this->actingAs($user)->get(route('store.cart.show'))->assertOk()->assertSee('Bs 93.00');

        $lineKey = $lines[0]['line_key'];
        $this->actingAs($user)->patch(route('store.cart.update', $lineKey), ['quantity' => 1])->assertRedirect();
        $this->assertSame(1, session('store_cart.lines')[0]['quantity']);

        $this->actingAs($user)->delete(route('store.cart.destroy', $lineKey))->assertRedirect();
        $this->assertSame([], session('store_cart.lines'));

        $this->actingAs($user)->post(route('store.cart.store'), ['product_public_code' => $product->public_code, 'quantity' => 1])->assertRedirect();
        $this->actingAs($user)->delete(route('store.cart.clear'))->assertRedirect();
        $this->assertNull(session('store_cart.lines'));
    }

    public function test_sold_out_coming_soon_inactive_category_and_foreign_variant_cannot_be_added(): void
    {
        $user = $this->affiliate()->user;
        $soldOut = $this->product(['availability_status' => StoreAvailabilityStatus::SOLD_OUT]);
        $coming = $this->product(['availability_status' => StoreAvailabilityStatus::COMING_SOON]);
        $inactiveCategory = StoreCategory::create(['name' => 'Cerrada', 'slug' => 'cerrada', 'active' => false]);
        $inactiveProduct = $this->product(['store_category_id' => $inactiveCategory->id]);
        $first = $this->product();
        $second = $this->product();
        $foreignVariant = StoreProductVariant::create(['store_product_id' => $second->id, 'type' => 'T', 'name' => 'X', 'active' => true]);

        foreach ([$soldOut, $coming, $inactiveProduct] as $product) {
            $this->actingAs($user)->from(route('store.catalog.show', $product->slug))->post(route('store.cart.store'), [
                'product_public_code' => $product->public_code,
                'quantity' => 1,
            ])->assertSessionHasErrors();
        }

        $this->actingAs($user)->post(route('store.cart.store'), [
            'product_public_code' => $first->public_code,
            'variant_public_code' => $foreignVariant->public_code,
            'quantity' => 1,
        ])->assertSessionHasErrors();
    }

    private function affiliate(string $status = 'activo'): Affiliate
    {
        $sector = Sector::create(['name' => fake()->unique()->word(), 'code' => fake()->unique()->bothify('SEC-###'), 'is_active' => true]);
        $plan = AffiliationPlan::create(['name' => fake()->unique()->word(), 'affiliation_fee' => 100, 'credential_fee' => 20, 'is_active' => true]);
        $person = Person::create(['full_name' => 'Afiliado Store', 'ci' => fake()->unique()->numerify('#######'), 'email' => fake()->unique()->safeEmail()]);
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
}
