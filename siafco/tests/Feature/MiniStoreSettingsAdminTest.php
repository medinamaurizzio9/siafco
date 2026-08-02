<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\StoreSetting;
use App\Models\User;
use App\Services\StoreWhatsAppNumberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MiniStoreSettingsAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_settings_are_permission_protected_and_affiliates_are_blocked(): void
    {
        $affiliate = User::factory()->create(['role' => 'afiliado', 'user_type' => 'affiliate']);
        $consulta = User::factory()->create(['role' => 'consulta', 'user_type' => 'internal']);

        $this->actingAs($affiliate)->get(route('admin.store.dashboard'))->assertForbidden();
        $this->actingAs($consulta)->get(route('admin.store.dashboard'))->assertOk();
        $this->actingAs($consulta)->get(route('admin.store.settings.edit'))->assertForbidden();
    }

    public function test_admin_updates_singleton_settings_with_encrypted_whatsapp_hint_and_safe_audit(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->put(route('admin.store.settings.update'), [
            'whatsapp_enabled' => '1',
            'whatsapp_number' => '7000-0000',
            'pickup_enabled' => '1',
            'shipping_enabled' => '1',
            'pickup_instructions' => 'Recojo en oficina central',
            'shipping_instructions' => 'Envio nacional',
            'default_currency' => 'BOB',
            'max_receipt_size_kb' => 4096,
        ]);

        $response->assertRedirect(route('admin.store.settings.edit'));

        $setting = StoreSetting::firstOrFail();
        $this->assertSame('59170000000', $setting->whatsapp_number_encrypted);
        $this->assertSame(hash('sha256', '59170000000'), $setting->whatsapp_number_hash);
        $this->assertSame('591*****000', $setting->whatsapp_number_hint);
        $this->assertTrue($setting->whatsapp_enabled);
        $this->assertTrue($setting->shipping_enabled);
        $this->assertSame(1, StoreSetting::count());

        $audit = AuditLog::firstWhere('action', 'mini_tienda.configuracion_actualizada');
        $this->assertNotNull($audit);
        $metadata = json_encode($audit->metadata, JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString('59170000000', $metadata);
        $this->assertStringNotContainsString($setting->whatsapp_number_hash, $metadata);
        $this->assertStringContainsString('591*****000', $metadata);
    }

    public function test_empty_whatsapp_field_keeps_existing_number_and_explicit_remove_clears_it(): void
    {
        $admin = $this->admin();
        $service = app(StoreWhatsAppNumberService::class);
        StoreSetting::create([
            'whatsapp_number_encrypted' => '59171112222',
            'whatsapp_number_hash' => $service->hash('59171112222'),
            'whatsapp_number_hint' => $service->hint('59171112222'),
            'whatsapp_enabled' => true,
            'pickup_enabled' => true,
            'shipping_enabled' => true,
            'default_currency' => 'BOB',
            'max_receipt_size_kb' => 6144,
        ]);

        $this->actingAs($admin)->put(route('admin.store.settings.update'), [
            'whatsapp_enabled' => '1',
            'whatsapp_number' => '',
            'pickup_enabled' => '1',
            'default_currency' => 'BOB',
            'max_receipt_size_kb' => 2048,
        ])->assertRedirect();

        $this->assertSame('59171112222', StoreSetting::first()->whatsapp_number_encrypted);
        $this->assertFalse(StoreSetting::first()->shipping_enabled);

        $this->actingAs($admin)->put(route('admin.store.settings.update'), [
            'remove_whatsapp_number' => '1',
            'pickup_enabled' => '1',
            'default_currency' => 'BOB',
            'max_receipt_size_kb' => 2048,
        ])->assertRedirect();

        $setting = StoreSetting::first();
        $this->assertNull($setting->whatsapp_number_encrypted);
        $this->assertNull($setting->whatsapp_number_hash);
        $this->assertNull($setting->whatsapp_number_hint);
    }

    public function test_whatsapp_normalization_accepts_bolivian_and_international_numbers(): void
    {
        $service = app(StoreWhatsAppNumberService::class);

        $this->assertSame('59170000000', $service->normalize('7000 0000'));
        $this->assertSame('59170000000', $service->normalize('+591 7000-0000'));
        $this->assertSame('5491123456789', $service->normalize('+54 9 11 2345-6789'));
        $this->assertSame('https://wa.me/59170000000?text=Hola', $service->waMeUrl('59170000000', 'Hola'));
    }

    private function admin(): User
    {
        return User::factory()->create([
            'role' => 'administrador',
            'user_type' => 'internal',
            'password' => Hash::make('secret'),
        ]);
    }
}
