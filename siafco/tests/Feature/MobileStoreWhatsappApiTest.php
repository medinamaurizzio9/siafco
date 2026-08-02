<?php

namespace Tests\Feature;

use App\Models\Affiliate;
use App\Models\AffiliationPlan;
use App\Models\AuditLog;
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
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileStoreWhatsappApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_gets_wa_me_url_without_separate_number_or_sensitive_message(): void
    {
        $affiliate = $this->affiliate();
        $order = $this->order($affiliate);
        StoreSetting::current()->update([
            'whatsapp_enabled' => true,
            'whatsapp_number_encrypted' => '59170000000',
            'whatsapp_number_hint' => '591*****000',
        ]);
        Sanctum::actingAs($affiliate->user);

        $response = $this->postJson("/api/mobile/v1/store/orders/{$order->code}/whatsapp");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['whatsapp' => ['url', 'opened_at', 'message_preview']]])
            ->assertJsonMissingPath('data.whatsapp.number')
            ->assertJsonMissingPath('data.whatsapp.phone');

        $url = $response->json('data.whatsapp.url');
        $message = rawurldecode(parse_url($url, PHP_URL_QUERY) ?: '');
        $this->assertStringStartsWith('https://wa.me/59170000000?text=', $url);
        $this->assertStringNotContainsString($affiliate->ci, $message);
        $this->assertStringNotContainsString($affiliate->email, $message);
        $this->assertNotNull($order->fresh()->whatsapp_opened_at);

        $audit = json_encode(AuditLog::firstWhere('action', 'mini_tienda.whatsapp_pedido_abierto')->metadata);
        $this->assertStringContainsString('591*****000', $audit);
        $this->assertStringNotContainsString('59170000000', $audit);
    }

    public function test_whatsapp_requires_owner_and_enabled_setting(): void
    {
        $owner = $this->affiliate();
        $other = $this->affiliate();
        $order = $this->order($owner);

        Sanctum::actingAs($other->user);
        $this->postJson("/api/mobile/v1/store/orders/{$order->code}/whatsapp")->assertNotFound();

        Sanctum::actingAs($owner->user);
        $this->postJson("/api/mobile/v1/store/orders/{$order->code}/whatsapp")
            ->assertUnprocessable()
            ->assertJsonPath('success', false);
    }

    private function order(Affiliate $affiliate): StoreOrder
    {
        $product = $this->product();

        return app(StoreOrderService::class)->create($affiliate, [
            'items' => [['product_public_code' => $product->public_code, 'quantity' => 1]],
            'delivery' => ['method' => StoreDeliveryMethod::PICKUP],
        ], (string) Str::uuid());
    }

    private function affiliate(): Affiliate
    {
        $sector = Sector::create(['name' => fake()->unique()->word(), 'code' => fake()->unique()->bothify('SEC-###'), 'is_active' => true]);
        $plan = AffiliationPlan::create(['name' => fake()->unique()->word(), 'affiliation_fee' => 100, 'credential_fee' => 20, 'is_active' => true]);
        $person = Person::create(['full_name' => 'Afiliado WhatsApp', 'ci' => fake()->unique()->numerify('#######'), 'email' => fake()->unique()->safeEmail()]);
        $user = User::create(['person_id' => $person->id, 'name' => $person->full_name, 'email' => $person->email, 'role' => 'afiliado', 'user_type' => 'affiliate', 'password' => Hash::make('secret'), 'is_active' => true]);

        return Affiliate::create(['person_id' => $person->id, 'user_id' => $user->id, 'sector_id' => $sector->id, 'affiliation_plan_id' => $plan->id, 'full_name' => $person->full_name, 'ci' => $person->ci, 'email' => $person->email, 'registration_number' => fake()->unique()->bothify('REG-#####'), 'verification_token' => fake()->uuid(), 'status' => 'activo']);
    }

    private function product(): StoreProduct
    {
        $category = StoreCategory::create(['name' => fake()->unique()->word(), 'slug' => fake()->unique()->slug(), 'active' => true]);

        return StoreProduct::create(['store_category_id' => $category->id, 'sku' => fake()->unique()->bothify('SKU-###'), 'slug' => fake()->unique()->slug(), 'name' => 'Producto WhatsApp', 'regular_price' => 100, 'affiliate_price' => 80, 'availability_status' => StoreAvailabilityStatus::AVAILABLE, 'delivery_modes' => [StoreDeliveryMethod::PICKUP], 'max_quantity_per_order' => 10, 'active' => true]);
    }
}
