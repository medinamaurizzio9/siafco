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

    public function test_expired_csrf_during_logout_redirects_to_login_with_controlled_message(): void
    {
        $admin = User::factory()->create(['role' => 'administrador', 'user_type' => 'internal']);

        $this->app->instance('env', 'local');

        $this->withMiddleware(ValidateCsrfToken::class)
            ->actingAs($admin)
            ->withSession(['_token' => 'valid-token'])
            ->post(route('logout'), ['_token' => 'expired-token'])
            ->assertRedirect(route('login'))
            ->assertSessionHas('warning', 'Tu sesión expiró. Vuelve a iniciar sesión.');

        $this->assertGuest();
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
