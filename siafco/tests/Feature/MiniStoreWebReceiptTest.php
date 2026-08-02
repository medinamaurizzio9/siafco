<?php

namespace Tests\Feature;

use App\Models\Affiliate;
use App\Models\AffiliationPlan;
use App\Models\Person;
use App\Models\Sector;
use App\Models\StoreOrder;
use App\Models\StoreProduct;
use App\Models\StoreSetting;
use App\Models\User;
use App\Services\Store\StoreOrderService;
use App\Support\StoreAvailabilityStatus;
use App\Support\StoreDeliveryMethod;
use App\Support\StoreOrderStatus;
use App\Support\StoreReceiptStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MiniStoreWebReceiptTest extends TestCase
{
    use RefreshDatabase;

    public function test_affiliate_uploads_private_image_receipt_and_downloads_own_file(): void
    {
        Storage::fake('local');
        $affiliate = $this->affiliate();
        $order = $this->order($affiliate);

        $this->actingAs($affiliate->user)->post(route('store.orders.receipts.store', $order), [
            'receipt' => UploadedFile::fake()->image('pago.png', 800, 600),
        ])->assertRedirect();

        $receipt = $order->receipts()->firstOrFail();
        $this->assertSame(StoreReceiptStatus::PENDING, $receipt->status);
        $this->assertSame(StoreOrderStatus::PAYMENT_REVIEW, $order->fresh()->status);
        $this->assertStringStartsWith('store-receipts/'.$order->code.'/', $receipt->path);
        Storage::disk('local')->assertExists($receipt->path);
        Storage::disk('public')->assertMissing($receipt->path);

        $this->actingAs($affiliate->user)->get(route('store.orders.receipts.show', [$order, $receipt]))->assertOk();
    }

    public function test_receipt_rejects_svg_fake_and_oversized_files_and_isolated_between_affiliates(): void
    {
        Storage::fake('local');
        StoreSetting::current()->update(['max_receipt_size_kb' => 1]);
        $owner = $this->affiliate();
        $other = $this->affiliate();
        $order = $this->order($owner);

        $this->actingAs($owner->user)->post(route('store.orders.receipts.store', $order), [
            'receipt' => UploadedFile::fake()->createWithContent('mal.svg', '<svg></svg>'),
        ])->assertSessionHasErrors('receipt');

        $this->actingAs($owner->user)->post(route('store.orders.receipts.store', $order), [
            'receipt' => UploadedFile::fake()->createWithContent('falso.jpg', 'no-imagen'),
        ])->assertSessionHasErrors('receipt');

        $this->actingAs($owner->user)->post(route('store.orders.receipts.store', $order), [
            'receipt' => UploadedFile::fake()->create('grande.pdf', 2, 'application/pdf'),
        ])->assertSessionHasErrors('receipt');

        StoreSetting::current()->update(['max_receipt_size_kb' => 100]);
        $this->actingAs($owner->user)->post(route('store.orders.receipts.store', $order), [
            'receipt' => UploadedFile::fake()->createWithContent('ok.pdf', "%PDF-1.4\n%test"),
        ])->assertRedirect();
        $receipt = $order->receipts()->firstOrFail();
        $this->actingAs($other->user)->get(route('store.orders.receipts.show', [$order, $receipt]))->assertNotFound();
    }

    public function test_admin_confirms_and_rejects_receipts_with_permissions(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'administrador', 'user_type' => 'internal']);
        $consulta = User::factory()->create(['role' => 'consulta', 'user_type' => 'internal']);
        $affiliate = $this->affiliate();
        $order = $this->order($affiliate);
        $this->actingAs($affiliate->user)->post(route('store.orders.receipts.store', $order), [
            'receipt' => UploadedFile::fake()->image('pago.jpg'),
        ]);
        $receipt = $order->receipts()->firstOrFail();

        $this->actingAs($consulta)->post(route('admin.store.orders.receipts.confirm', [$order, $receipt]))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.store.orders.receipts.show', [$order, $receipt]))->assertOk();
        $this->actingAs($admin)->post(route('admin.store.orders.receipts.confirm', [$order, $receipt]))->assertRedirect();
        $this->assertSame(StoreReceiptStatus::CONFIRMED, $receipt->fresh()->status);
        $this->assertSame(StoreOrderStatus::CONFIRMED, $order->fresh()->status);

        $second = $this->order($affiliate);
        $this->actingAs($affiliate->user)->post(route('store.orders.receipts.store', $second), [
            'receipt' => UploadedFile::fake()->image('pago2.jpg'),
        ]);
        $rejected = $second->receipts()->firstOrFail();
        $this->actingAs($admin)->post(route('admin.store.orders.receipts.reject', [$second, $rejected]), ['reason' => 'No legible'])->assertRedirect();
        $this->assertSame(StoreReceiptStatus::REJECTED, $rejected->fresh()->status);
        $this->assertSame(StoreOrderStatus::WAITING_PAYMENT, $second->fresh()->status);
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
        $person = Person::create(['full_name' => 'Afiliado Recibo', 'ci' => fake()->unique()->numerify('#######'), 'email' => fake()->unique()->safeEmail()]);
        $user = User::create(['person_id' => $person->id, 'name' => $person->full_name, 'email' => $person->email, 'role' => 'afiliado', 'user_type' => 'affiliate', 'password' => Hash::make('secret'), 'is_active' => true]);

        return Affiliate::create(['person_id' => $person->id, 'user_id' => $user->id, 'sector_id' => $sector->id, 'affiliation_plan_id' => $plan->id, 'full_name' => $person->full_name, 'ci' => $person->ci, 'email' => $person->email, 'registration_number' => fake()->unique()->bothify('REG-#####'), 'verification_token' => fake()->uuid(), 'status' => 'activo']);
    }

    private function product(): StoreProduct
    {
        $category = \App\Models\StoreCategory::create(['name' => fake()->unique()->word(), 'slug' => fake()->unique()->slug(), 'active' => true]);

        return StoreProduct::create(['store_category_id' => $category->id, 'sku' => fake()->unique()->bothify('SKU-###'), 'slug' => fake()->unique()->slug(), 'name' => 'Producto', 'regular_price' => 100, 'affiliate_price' => 90, 'availability_status' => StoreAvailabilityStatus::AVAILABLE, 'delivery_modes' => [StoreDeliveryMethod::PICKUP], 'max_quantity_per_order' => 10, 'active' => true]);
    }
}
