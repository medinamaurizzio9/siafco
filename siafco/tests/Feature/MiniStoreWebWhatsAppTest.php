<?php

namespace Tests\Feature;

use App\Models\Affiliate;
use App\Models\AffiliationPlan;
use App\Models\AuditLog;
use App\Models\Person;
use App\Models\Sector;
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

class MiniStoreWebWhatsAppTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_opens_whatsapp_url_from_settings_without_exposing_full_number(): void
    {
        $affiliate = $this->affiliate();
        $order = $this->order($affiliate);
        StoreSetting::current()->update([
            'whatsapp_enabled' => true,
            'whatsapp_number_encrypted' => '59170000000',
            'whatsapp_number_hint' => '591*****000',
        ]);

        $response = $this->actingAs($affiliate->user)->post(route('store.orders.whatsapp', $order));
        $response->assertRedirect();
        $location = $response->headers->get('Location');

        $this->assertStringStartsWith('https://wa.me/59170000000?text=', $location);
        $this->assertSame('https', parse_url($location, PHP_URL_SCHEME));
        $this->assertSame('wa.me', parse_url($location, PHP_URL_HOST));
        $message = rawurldecode(parse_url($location, PHP_URL_QUERY) ?: '');
        $this->assertStringContainsString($affiliate->full_name, $message);
        $this->assertStringContainsString($affiliate->registration_number, $message);
        $this->assertStringContainsString(rawurlencode($order->code), $location);
        $this->assertStringContainsString('Producto', $message);
        $this->assertStringContainsString('Total: Bs '.$order->total, $message);
        $this->assertStringContainsString('Entrega: RECOJO EN OFICINA', $message);
        $this->assertStringNotContainsString($affiliate->ci, $message);
        $this->assertStringNotContainsString($affiliate->email, $message);
        $this->assertStringNotContainsString((string) $affiliate->phone, $message);
        $this->assertStringNotContainsString((string) $affiliate->address, $message);
        $this->assertStringNotContainsString('affiliate_id', $message);
        $this->assertStringNotContainsString('user_id', $message);
        $this->assertNotNull($order->fresh()->whatsapp_opened_at);

        $metadata = json_encode(AuditLog::firstWhere('action', 'mini_tienda.whatsapp_pedido_abierto')->metadata);
        $this->assertStringContainsString('591*****000', $metadata);
        $this->assertStringNotContainsString('59170000000', $metadata);
    }

    public function test_whatsapp_requires_owner_and_enabled_configuration(): void
    {
        $owner = $this->affiliate();
        $other = $this->affiliate();
        $order = $this->order($owner);

        $this->actingAs($other->user)->post(route('store.orders.whatsapp', $order))->assertNotFound();
        $this->actingAs($owner->user)->post(route('store.orders.whatsapp', $order))->assertSessionHasErrors('whatsapp');
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
        $person = Person::create(['full_name' => 'Afiliado WhatsApp', 'ci' => fake()->unique()->numerify('#######'), 'phone' => '70000001', 'email' => fake()->unique()->safeEmail(), 'address' => 'Direccion Secreta']);
        $user = User::create(['person_id' => $person->id, 'name' => $person->full_name, 'email' => $person->email, 'role' => 'afiliado', 'user_type' => 'affiliate', 'password' => Hash::make('secret'), 'is_active' => true]);

        return Affiliate::create(['person_id' => $person->id, 'user_id' => $user->id, 'sector_id' => $sector->id, 'affiliation_plan_id' => $plan->id, 'full_name' => $person->full_name, 'ci' => $person->ci, 'phone' => $person->phone, 'email' => $person->email, 'address' => $person->address, 'registration_number' => fake()->unique()->bothify('REG-#####'), 'verification_token' => fake()->uuid(), 'status' => 'activo']);
    }

    private function product(): StoreProduct
    {
        $category = \App\Models\StoreCategory::create(['name' => fake()->unique()->word(), 'slug' => fake()->unique()->slug(), 'active' => true]);

        return StoreProduct::create(['store_category_id' => $category->id, 'sku' => fake()->unique()->bothify('SKU-###'), 'slug' => fake()->unique()->slug(), 'name' => 'Producto', 'regular_price' => 100, 'affiliate_price' => 90, 'availability_status' => StoreAvailabilityStatus::AVAILABLE, 'delivery_modes' => [StoreDeliveryMethod::PICKUP], 'max_quantity_per_order' => 10, 'active' => true]);
    }
}
