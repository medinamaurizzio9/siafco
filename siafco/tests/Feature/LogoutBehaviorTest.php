<?php

namespace Tests\Feature;

use App\Models\Affiliate;
use App\Models\AffiliationPlan;
use App\Models\Person;
use App\Models\Sector;
use App\Models\StoreCategory;
use App\Models\StoreProduct;
use App\Models\User;
use App\Support\StoreAvailabilityStatus;
use App\Support\StoreDeliveryMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LogoutBehaviorTest extends TestCase
{
    use RefreshDatabase;

    public function test_affiliate_logout_uses_post_clears_session_and_store_cart(): void
    {
        $affiliate = $this->affiliate();
        $product = $this->product();

        $this->actingAs($affiliate->user)
            ->post(route('store.cart.store'), ['product_public_code' => $product->public_code, 'quantity' => 1])
            ->assertRedirect(route('store.cart.show'));
        $this->assertNotNull(session('store_cart.lines'));

        $this->actingAs($affiliate->user)
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertNull(session('store_cart.lines'));
        $this->assertNull(session('store_checkout.idempotency_key'));
    }

    public function test_logout_with_valid_csrf_closes_session_and_clears_cart(): void
    {
        $affiliate = $this->affiliate();

        $this->app->instance('env', 'local');

        $this->withMiddleware(ValidateCsrfToken::class)
            ->actingAs($affiliate->user)
            ->withSession([
                '_token' => 'valid-token',
                'store_cart.lines' => [['line_key' => 'x', 'quantity' => 1]],
                'store_checkout.idempotency_key' => fake()->uuid(),
            ])
            ->post(route('logout'), ['_token' => 'valid-token'])
            ->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertNull(session('store_cart.lines'));
        $this->assertNull(session('store_checkout.idempotency_key'));
    }

    public function test_admin_logout_uses_post_and_user_cannot_return_to_authenticated_content(): void
    {
        $admin = User::factory()->create(['role' => 'administrador', 'user_type' => 'internal']);

        $this->actingAs($admin)
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
    }

    public function test_get_logout_is_not_destructive(): void
    {
        $admin = User::factory()->create(['role' => 'administrador', 'user_type' => 'internal']);

        $this->actingAs($admin)
            ->get('/logout')
            ->assertStatus(405);

        $this->assertAuthenticatedAs($admin);
    }

    public function test_expired_csrf_during_logout_does_not_close_authenticated_session_or_clear_cart(): void
    {
        $admin = User::factory()->create(['role' => 'administrador', 'user_type' => 'internal']);

        $this->app->instance('env', 'local');

        $this->withMiddleware(ValidateCsrfToken::class)
            ->actingAs($admin)
            ->withSession([
                '_token' => 'valid-token',
                'store_cart.lines' => [['line_key' => 'x', 'quantity' => 1]],
                'store_checkout.idempotency_key' => fake()->uuid(),
            ])
            ->post(route('logout'), ['_token' => 'expired-token'])
            ->assertRedirect(route('logout.confirm'))
            ->assertSessionHas('warning', 'No se pudo cerrar sesión porque el formulario expiró. Confirma nuevamente para salir.');

        $this->assertAuthenticatedAs($admin);
        $this->assertNotNull(session('store_cart.lines'));
        $this->assertNotNull(session('store_checkout.idempotency_key'));
    }

    public function test_missing_csrf_during_logout_uses_controlled_retry_without_destructive_action(): void
    {
        $admin = User::factory()->create(['role' => 'administrador', 'user_type' => 'internal']);

        $this->app->instance('env', 'local');

        $this->withMiddleware(ValidateCsrfToken::class)
            ->actingAs($admin)
            ->withSession([
                '_token' => 'valid-token',
                'store_cart.lines' => [['line_key' => 'x', 'quantity' => 1]],
            ])
            ->post(route('logout'))
            ->assertRedirect(route('logout.confirm'));

        $this->assertAuthenticatedAs($admin);
        $this->assertNotNull(session('store_cart.lines'));
    }

    public function test_logout_confirmation_contains_fresh_post_form_and_then_logs_out(): void
    {
        $admin = User::factory()->create(['role' => 'administrador', 'user_type' => 'internal']);

        $this->actingAs($admin)
            ->get(route('logout.confirm'))
            ->assertOk()
            ->assertSee('method="post"', false)
            ->assertSee(route('logout'), false)
            ->assertSee('name="_token"', false)
            ->assertSee('Cerrar sesión');

        $this->app->instance('env', 'local');

        $this->withMiddleware(ValidateCsrfToken::class)
            ->actingAs($admin)
            ->withSession(['_token' => 'fresh-token'])
            ->post(route('logout'), ['_token' => 'fresh-token'])
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_guest_with_expired_logout_session_goes_to_login(): void
    {
        $this->app->instance('env', 'local');

        $this->withMiddleware(ValidateCsrfToken::class)
            ->withSession(['_token' => 'valid-token'])
            ->post(route('logout'), ['_token' => 'expired-token'])
            ->assertRedirect(route('login'))
            ->assertSessionHas('warning', 'La sesión expiró.');
    }

    public function test_external_post_without_csrf_cannot_close_session(): void
    {
        $admin = User::factory()->create(['role' => 'administrador', 'user_type' => 'internal']);

        $this->app->instance('env', 'local');

        $this->withMiddleware(ValidateCsrfToken::class)
            ->actingAs($admin)
            ->withSession(['_token' => 'valid-token'])
            ->withHeader('Origin', 'https://externo.example')
            ->post(route('logout'))
            ->assertRedirect(route('logout.confirm'));

        $this->assertAuthenticatedAs($admin);
    }

    public function test_csrf_remains_active_for_sensitive_posts(): void
    {
        $this->app->instance('env', 'local');

        $this->withMiddleware(ValidateCsrfToken::class)
            ->withSession(['_token' => 'valid-token'])
            ->post(route('login'), [
                '_token' => 'bad-token',
                'email' => 'admin@siafco.test',
                'password' => 'secret',
            ])
            ->assertStatus(419);
    }

    private function affiliate(): Affiliate
    {
        $sector = Sector::create(['name' => 'MAGISTERIO', 'code' => 'MAG', 'is_active' => true]);
        $plan = AffiliationPlan::create(['name' => 'PLAN', 'affiliation_fee' => 100, 'credential_fee' => 20, 'is_active' => true]);
        $person = Person::create(['full_name' => 'AFILIADO LOGOUT', 'ci' => fake()->unique()->numerify('#######'), 'email' => fake()->unique()->safeEmail()]);
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
            'status' => 'activo',
        ]);
    }

    private function product(): StoreProduct
    {
        $category = StoreCategory::create(['name' => 'CATALOGO', 'slug' => 'catalogo', 'active' => true]);

        return StoreProduct::create([
            'store_category_id' => $category->id,
            'sku' => fake()->unique()->bothify('SKU-###'),
            'slug' => fake()->unique()->slug(),
            'name' => 'PRODUCTO',
            'regular_price' => 100,
            'affiliate_price' => 90,
            'availability_status' => StoreAvailabilityStatus::AVAILABLE,
            'delivery_modes' => [StoreDeliveryMethod::PICKUP],
            'max_quantity_per_order' => 10,
            'active' => true,
        ]);
    }
}
