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

    public function test_root_and_dashboard_redirect_by_authenticated_user_type(): void
    {
        $affiliate = $this->affiliate();
        $admin = User::create([
            'name' => 'Administrador',
            'email' => 'admin-root@test.local',
            'role' => 'administrador',
            'user_type' => 'internal',
            'password' => Hash::make('secret-password'),
            'is_active' => true,
        ]);

        $this->get('/')->assertRedirect(route('login'));

        $this->actingAs($affiliate->user)->get('/')->assertRedirect(route('affiliate.panel'));
        $this->actingAs($affiliate->user)->get(route('admin.dashboard'))->assertRedirect(route('affiliate.panel'));
        $this->followingRedirects()->actingAs($affiliate->user)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee('Dashboard general');

        auth()->logout();

        $this->actingAs($admin)->get('/')->assertRedirect(route('admin.dashboard'));
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()
            ->assertSee('Dashboard general');
    }

    public function test_affiliate_store_access_remains_available_and_admin_paths_remain_blocked(): void
    {
        $affiliate = $this->affiliate();

        $this->actingAs($affiliate->user)->get(route('store.catalog.index'))->assertOk();
        $this->actingAs($affiliate->user)->get('/admin/mini-tienda')->assertForbidden();
    }

    public function test_responsive_layout_exposes_drawer_and_affiliate_bottom_navigation_only_for_affiliates(): void
    {
        $affiliate = $this->affiliate();
        $admin = User::create([
            'name' => 'Super Admin',
            'email' => 'super-responsive@test.local',
            'role' => 'superadministrador',
            'user_type' => 'internal',
            'password' => Hash::make('secret-password'),
            'is_active' => true,
        ]);

        $this->actingAs($affiliate->user)->get(route('affiliate.panel'))
            ->assertOk()
            ->assertSee('id="mobile-sidebar"', false)
            ->assertSee('aria-controls="mobile-sidebar"', false)
            ->assertSee('mobile-bottom-nav', false)
            ->assertSee('Navegacion rapida del afiliado', false);

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('id="mobile-sidebar"', false)
            ->assertSee('Mini tienda')
            ->assertDontSee('mobile-bottom-nav', false);
    }

    public function test_affiliate_show_prioritizes_mobile_summary_and_collapses_secondary_information(): void
    {
        $affiliate = $this->affiliate();
        $admin = User::create([
            'name' => 'Super Admin Perfil',
            'email' => 'super-profile-responsive@test.local',
            'role' => 'superadministrador',
            'user_type' => 'internal',
            'password' => Hash::make('secret-password'),
            'is_active' => true,
        ]);

        $this->actingAs($admin)->get(route('affiliates.show', $affiliate))
            ->assertOk()
            ->assertSee('Informacion completa')
            ->assertSee('Ver pagos')
            ->assertSee('desktop-table', false)
            ->assertSee('mobile-card-list', false);
    }

    public function test_inactive_affiliate_cannot_login_into_affiliate_panel(): void
    {
        $affiliate = $this->affiliate();
        $affiliate->user->forceFill(['is_active' => false])->save();

        $this->post(route('login.post'), [
            'email' => $affiliate->user->email,
            'password' => 'secret-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_affiliate_login_rejects_admin_and_external_intended_urls(): void
    {
        $affiliate = $this->affiliate();

        $this->withSession(['url.intended' => route('admin.dashboard')])
            ->post(route('login.post'), [
                'email' => $affiliate->user->email,
                'password' => 'secret-password',
            ])
            ->assertRedirect(route('affiliate.panel'));

        auth()->logout();
        session()->invalidate();
        session()->regenerateToken();

        $this->withSession(['url.intended' => 'https://example.test/dashboard'])
            ->post(route('login.post'), [
                'email' => $affiliate->user->email,
                'password' => 'secret-password',
            ])
            ->assertRedirect(route('affiliate.panel'));
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
