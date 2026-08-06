<?php

namespace Tests\Feature;

use App\Models\Affiliate;
use App\Models\AffiliationPlan;
use App\Models\MobileApiIdempotencyKey;
use App\Models\Person;
use App\Models\Sector;
use App\Models\StoreCategory;
use App\Models\StoreOrder;
use App\Models\StoreOrderReceipt;
use App\Models\StoreProduct;
use App\Models\User;
use App\Services\Store\StoreOrderService;
use App\Support\StoreAvailabilityStatus;
use App\Support\StoreDeliveryMethod;
use App\Support\StoreOrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileStoreReceiptApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_affiliate_uploads_private_receipt_with_idempotency(): void
    {
        Storage::fake('local');
        $affiliate = $this->affiliate();
        $order = $this->order($affiliate);
        Sanctum::actingAs($affiliate->user);
        $key = (string) Str::uuid();

        $response = $this->withHeader('Idempotency-Key', $key)->postJson("/api/mobile/v1/store/orders/{$order->code}/receipt", [
            'receipt' => UploadedFile::fake()->image('receipt.jpg', 800, 800),
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.order.status', StoreOrderStatus::PAYMENT_REVIEW)
            ->assertJsonPath('data.receipt.status', 'pending')
            ->assertJsonMissingPath('data.receipt.path')
            ->assertJsonMissingPath('data.receipt.sha256');

        $receipt = StoreOrderReceipt::firstOrFail();
        Storage::disk('local')->assertExists($receipt->path);
        Storage::disk('public')->assertMissing($receipt->path);

        $second = $this->withHeader('Idempotency-Key', $key)->postJson("/api/mobile/v1/store/orders/{$order->code}/receipt", [
            'receipt' => UploadedFile::fake()->image('receipt.jpg', 800, 800),
        ]);
        $second->assertOk()->assertJsonPath('data.order.status', StoreOrderStatus::PAYMENT_REVIEW);

        $this->assertSame(1, StoreOrderReceipt::count());
        $this->assertSame(1, MobileApiIdempotencyKey::where('scope', 'store.order.receipt')->count());
    }

    public function test_receipt_rejects_invalid_owner_missing_key_and_fake_mime(): void
    {
        Storage::fake('local');
        $owner = $this->affiliate();
        $other = $this->affiliate();
        $order = $this->order($owner);

        Sanctum::actingAs($other->user);
        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson("/api/mobile/v1/store/orders/{$order->code}/receipt", [
                'receipt' => UploadedFile::fake()->image('receipt.jpg'),
            ])->assertNotFound();

        Sanctum::actingAs($owner->user);
        $this->withHeaders(['Idempotency-Key' => ''])->postJson("/api/mobile/v1/store/orders/{$order->code}/receipt", [
            'receipt' => UploadedFile::fake()->image('receipt.jpg'),
        ])->assertUnprocessable()->assertJsonStructure(['errors' => ['Idempotency-Key']]);

        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson("/api/mobile/v1/store/orders/{$order->code}/receipt", [
                'receipt' => UploadedFile::fake()->createWithContent('bad.svg', '<svg></svg>'),
            ])->assertUnprocessable()->assertJsonPath('success', false);
    }

    public function test_rejected_receipt_allows_new_submission(): void
    {
        Storage::fake('local');
        $affiliate = $this->affiliate();
        $order = $this->order($affiliate);
        Sanctum::actingAs($affiliate->user);

        $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson("/api/mobile/v1/store/orders/{$order->code}/receipt", [
            'receipt' => UploadedFile::fake()->image('receipt.jpg'),
        ])->assertCreated();

        $receipt = StoreOrderReceipt::firstOrFail();
        $receipt->update(['status' => 'rejected', 'rejection_reason' => 'No legible', 'reviewed_at' => now()]);
        $order->forceFill(['status' => StoreOrderStatus::WAITING_PAYMENT])->save();

        $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson("/api/mobile/v1/store/orders/{$order->code}/receipt", [
            'receipt' => UploadedFile::fake()->image('receipt-new.png'),
        ])->assertCreated();

        $this->assertSame(2, StoreOrderReceipt::count());
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
        $person = Person::create(['full_name' => 'Afiliado Comprobante', 'ci' => fake()->unique()->numerify('#######'), 'email' => fake()->unique()->safeEmail()]);
        $user = User::create(['person_id' => $person->id, 'name' => $person->full_name, 'email' => $person->email, 'role' => 'afiliado', 'user_type' => 'affiliate', 'password' => Hash::make('secret'), 'is_active' => true]);

        return Affiliate::create(['person_id' => $person->id, 'user_id' => $user->id, 'sector_id' => $sector->id, 'affiliation_plan_id' => $plan->id, 'full_name' => $person->full_name, 'ci' => $person->ci, 'email' => $person->email, 'registration_number' => fake()->unique()->bothify('REG-#####'), 'verification_token' => fake()->uuid(), 'status' => 'activo']);
    }

    private function product(): StoreProduct
    {
        $category = StoreCategory::create(['name' => fake()->unique()->word(), 'slug' => fake()->unique()->slug(), 'active' => true]);

        return StoreProduct::create(['store_category_id' => $category->id, 'sku' => fake()->unique()->bothify('SKU-###'), 'slug' => fake()->unique()->slug(), 'name' => 'Producto Recibo', 'regular_price' => 100, 'affiliate_price' => 80, 'availability_status' => StoreAvailabilityStatus::AVAILABLE, 'delivery_modes' => [StoreDeliveryMethod::PICKUP], 'max_quantity_per_order' => 10, 'active' => true]);
    }
}
