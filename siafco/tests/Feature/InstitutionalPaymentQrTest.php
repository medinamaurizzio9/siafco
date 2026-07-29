<?php

namespace Tests\Feature;

use App\Models\InstitutionalSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InstitutionalPaymentQrTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        InstitutionalSetting::clearCurrentCache();
    }

    public function test_institutional_settings_only_links_to_official_payment_qr_module(): void
    {
        $user = $this->user('administrador');

        $this->actingAs($user)->get(route('institutional-settings.edit'))
            ->assertOk()
            ->assertSee('GESTIONAR QR DE PAGO')
            ->assertSee(route('institutional-qr.show'))
            ->assertDontSee('name="payment_qr"', false)
            ->assertDontSee('name="payment_bank"', false)
            ->assertDontSee('name="payment_holder"', false)
            ->assertDontSee('name="payment_account"', false);

        $this->assertSame(1, collect(Route::getRoutes())->where('action.as', 'institutional-qr.update')->count());
    }

    public function test_official_module_replaces_qr_with_uuid_versioned_url_and_clears_cache(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('institutional/payment/old.png', 'old');
        Storage::disk('public')->put('institutional/logo/logo.png', 'logo');
        $setting = InstitutionalSetting::current();
        $setting->update([
            'payment_qr_path' => 'institutional/payment/old.png',
            'logo_path' => 'institutional/logo/logo.png',
        ]);
        InstitutionalSetting::clearCurrentCache();
        $user = $this->user('administrador');

        $this->actingAs($user)->post(route('institutional-qr.update'), [
            'qr' => UploadedFile::fake()->image('new-qr.png', 600, 600),
            'payment_bank' => 'Banco Institucional',
            'payment_holder' => 'Cooperativa Tierra Bendita',
            'payment_account' => '123456',
            'payment_instructions' => 'Pagar el monto exacto',
        ])->assertRedirect()->assertSessionHas('status', 'QR institucional de pago actualizado correctamente.');

        $fresh = InstitutionalSetting::current();
        $this->assertStringStartsWith('institutional/payment/', $fresh->payment_qr_path);
        $this->assertNotSame('institutional/payment/old.png', $fresh->payment_qr_path);
        $this->assertMatchesRegularExpression('/\?v=\d+$/', $fresh->paymentQrUrl());
        $this->assertSame('BANCO INSTITUCIONAL', $fresh->payment_bank);
        Storage::disk('public')->assertExists($fresh->payment_qr_path);
        Storage::disk('public')->assertMissing('institutional/payment/old.png');
        Storage::disk('public')->assertExists('institutional/logo/logo.png');
        $this->assertTrue(Cache::has('institutional_settings.current'));
    }

    public function test_replacing_qr_changes_visible_url_in_all_consumer_views(): void
    {
        Storage::fake('public');
        $setting = InstitutionalSetting::current();
        Storage::disk('public')->put('institutional/payment/first.png', 'first');
        $setting->update(['payment_qr_path' => 'institutional/payment/first.png']);
        InstitutionalSetting::clearCurrentCache();
        $firstUrl = InstitutionalSetting::current()->paymentQrUrl();
        $user = $this->user('secretaria');

        $this->actingAs($user)->post(route('institutional-qr.update'), [
            'qr' => UploadedFile::fake()->image('second.webp', 500, 500),
        ])->assertRedirect();

        $secondUrl = InstitutionalSetting::current()->paymentQrUrl();
        $this->assertNotSame($firstUrl, $secondUrl);
        $this->get(route('institutional-qr.show'))->assertOk()->assertSee($secondUrl, false);
    }

    public function test_authorization_and_invalid_files_are_enforced(): void
    {
        foreach (['consulta', 'afiliado', 'administrador_sector'] as $role) {
            $this->actingAs($this->user($role, "{$role}@example.com"))
                ->post(route('institutional-qr.update'), [
                    'qr' => UploadedFile::fake()->image('qr.png'),
                ])->assertForbidden();
        }

        $this->actingAs($this->user('superadministrador', 'super@example.com'))
            ->get(route('institutional-qr.show'))->assertOk();

        $this->actingAs($this->user('secretaria', 'secretaria@example.com'))
            ->post(route('institutional-qr.update'), [
                'qr' => UploadedFile::fake()->create('qr.svg', 20, 'image/svg+xml'),
            ])->assertSessionHasErrors('qr');
    }

    private function user(string $role, string $email = 'admin@example.com'): User
    {
        return User::create([
            'name' => ucfirst($role),
            'email' => $email,
            'role' => $role,
            'password' => Hash::make('Password1'),
        ]);
    }
}
