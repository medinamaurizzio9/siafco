<?php

namespace Tests\Feature;

use App\Models\Affiliate;
use App\Models\AffiliationPlan;
use App\Models\Person;
use App\Models\Sector;
use App\Models\StoreCategory;
use App\Models\StoreOrder;
use App\Models\StoreProduct;
use App\Models\StoreSetting;
use App\Models\User;
use App\Services\Store\StoreOrderService;
use App\Support\StoreAvailabilityStatus;
use App\Support\StoreDeliveryMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MiniStoreWebNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_affiliate_login_ignores_admin_intended_url_and_admin_keeps_dashboard(): void
    {
        $affiliate = $this->affiliate();
        $admin = User::create([
            'name' => 'Secretaria',
            'email' => 'secretaria-navigation@test.local',
            'role' => 'secretaria',
            'password' => Hash::make('secret-password'),
            'is_active' => true,
        ]);

        $this->withSession(['url.intended' => route('admin.dashboard')])
            ->post(route('login.post'), [
                'email' => $affiliate->user->email,
                'password' => 'secret-password',
            ])
            ->assertRedirect(route('affiliate.panel'));

        auth()->logout();
        session()->invalidate();
        session()->regenerateToken();

        $this->withSession(['url.intended' => route('admin.dashboard')])
            ->post(route('login.post'), [
                'email' => $admin->email,
                'password' => 'secret-password',
            ])
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_store_affiliate_pages_use_explicit_non_admin_navigation_targets(): void
    {
        $affiliate = $this->affiliate();
        $product = $this->product();

        $catalog = $this->actingAs($affiliate->user)->get(route('store.catalog.index'));
        $catalog->assertOk()
            ->assertSee(route('affiliate.panel'), false)
            ->assertDontSee('/dashboard', false);

        $productPage = $this->actingAs($affiliate->user)->get(route('store.catalog.show', $product->slug));
        $productPage->assertOk()
            ->assertSee(route('store.catalog.index'), false)
            ->assertDontSee('/dashboard', false);

        $this->actingAs($affiliate->user)->post(route('store.cart.store'), [
            'product_public_code' => $product->public_code,
            'quantity' => 1,
        ])->assertRedirect(route('store.cart.show'));

        $cart = $this->actingAs($affiliate->user)->get(route('store.cart.show'));
        $cart->assertOk()
            ->assertSee(route('store.catalog.index'), false)
            ->assertDontSee('/dashboard', false);

        $checkout = $this->actingAs($affiliate->user)->get(route('store.checkout.show'));
        $checkout->assertOk()
            ->assertSee(route('store.cart.show'), false)
            ->assertDontSee('/dashboard', false);
    }

    public function test_order_detail_back_link_points_to_orders_and_whatsapp_opens_in_new_tab(): void
    {
        $affiliate = $this->affiliate();
        $order = $this->order($affiliate);
        StoreSetting::current()->update([
            'whatsapp_enabled' => true,
            'whatsapp_number_encrypted' => '59170000000',
            'whatsapp_number_hint' => '591*****000',
        ]);

        $response = $this->actingAs($affiliate->user)->get(route('store.orders.show', $order));

        $response->assertOk()
            ->assertSee(route('store.orders.index'), false)
            ->assertSee('target="_blank"', false)
            ->assertSee('rel="noopener"', false)
            ->assertSee('name="_token"', false)
            ->assertDontSee('/dashboard', false)
            ->assertDontSee('59170000000', false);
    }

    public function test_whatsapp_endpoint_keeps_authorization_and_generates_only_wa_me_redirect(): void
    {
        $owner = $this->affiliate();
        $other = $this->affiliate();
        $order = $this->order($owner);
        StoreSetting::current()->update([
            'whatsapp_enabled' => true,
            'whatsapp_number_encrypted' => '59170000000',
            'whatsapp_number_hint' => '591*****000',
        ]);

        $this->actingAs($other->user)->post(route('store.orders.whatsapp', $order))->assertNotFound();

        $response = $this->actingAs($owner->user)->post(route('store.orders.whatsapp', $order));
        $response->assertRedirect();
        $location = $response->headers->get('Location');
        $message = rawurldecode(parse_url($location, PHP_URL_QUERY) ?? '');

        $this->assertStringStartsWith('https://wa.me/', $location);
        $this->assertStringNotContainsString($owner->ci, $message);
        $this->assertStringNotContainsString($owner->email, $message);
        $this->assertStringNotContainsString('affiliate_id', strtolower($message));
        $this->assertStringNotContainsString('order_id', strtolower($message));
        $this->assertStringNotContainsString('user_id', strtolower($message));
        $this->assertNotNull($order->fresh()->whatsapp_opened_at);
    }

    private function order(Affiliate $affiliate): StoreOrder
    {
        $product = $this->product();

        return app(StoreOrderService::class)->create($affiliate, [
            'items' => [['product_public_code' => $product->public_code, 'quantity' => 1]],
            'delivery' => ['method' => StoreDeliveryMethod::PICKUP],
        ]);
    }

    private function affiliate(): Affiliate
    {
        $sector = Sector::create(['name' => fake()->unique()->word(), 'code' => fake()->unique()->bothify('SEC-###'), 'is_active' => true]);
        $plan = AffiliationPlan::create(['name' => fake()->unique()->word(), 'affiliation_fee' => 100, 'credential_fee' => 20, 'is_active' => true]);
        $person = Person::create(['full_name' => 'Afiliado Tienda', 'ci' => fake()->unique()->numerify('#######'), 'email' => fake()->unique()->safeEmail()]);
        $user = User::create(['person_id' => $person->id, 'name' => $person->full_name, 'email' => $person->email, 'role' => 'afiliado', 'user_type' => 'affiliate', 'password' => Hash::make('secret-password'), 'is_active' => true]);

        return Affiliate::create(['person_id' => $person->id, 'user_id' => $user->id, 'sector_id' => $sector->id, 'affiliation_plan_id' => $plan->id, 'full_name' => $person->full_name, 'ci' => $person->ci, 'email' => $person->email, 'registration_number' => fake()->unique()->bothify('REG-#####'), 'verification_token' => fake()->uuid(), 'status' => 'activo']);
    }

    private function product(): StoreProduct
    {
        $category = StoreCategory::create(['name' => fake()->unique()->word(), 'slug' => fake()->unique()->slug(), 'active' => true]);

        return StoreProduct::create(['store_category_id' => $category->id, 'sku' => fake()->unique()->bothify('SKU-###'), 'slug' => fake()->unique()->slug(), 'name' => 'Producto', 'regular_price' => 100, 'affiliate_price' => 90, 'availability_status' => StoreAvailabilityStatus::AVAILABLE, 'delivery_modes' => [StoreDeliveryMethod::PICKUP], 'max_quantity_per_order' => 10, 'active' => true]);
    }
}
