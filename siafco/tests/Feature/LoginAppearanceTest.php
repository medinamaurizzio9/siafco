<?php

namespace Tests\Feature;

use App\Models\InstitutionalSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LoginAppearanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        InstitutionalSetting::clearCurrentCache();
    }

    protected function tearDown(): void
    {
        InstitutionalSetting::clearCurrentCache();
        parent::tearDown();
    }

    public function test_login_uses_accessible_defaults_without_authentication(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('SISTEMA DE AFILIACIÓN')
            ->assertSee('COOPERATIVA TIERRA BENDITA')
            ->assertSee('Bienvenido a nuestra plataforma institucional')
            ->assertSee('default-login-background.webp')
            ->assertSee('autocomplete="email"', false)
            ->assertSee('autocomplete="current-password"', false)
            ->assertSee('data-password-toggle', false);
    }

    public function test_administrator_can_update_login_appearance_with_safe_images(): void
    {
        Storage::fake('public');
        $setting = InstitutionalSetting::current();
        $admin = User::create([
            'name' => 'Administrador',
            'email' => 'admin-login@test.local',
            'role' => 'administrador',
            'password' => Hash::make('secret'),
        ]);

        $response = $this->actingAs($admin)->put(route('institutional-settings.update'), $this->validPayload([
            'login_background' => UploadedFile::fake()->image('background.jpg', 1600, 900),
            'login_logo' => UploadedFile::fake()->image('logo.png', 400, 400),
            'login_title' => 'PORTAL DE AFILIACIÓN',
            'login_institution_name' => 'COOPERATIVA CONFIGURADA',
            'login_affiliate_message' => 'Mensaje configurado para nuestros afiliados.',
            'login_overlay_opacity' => 74,
        ]));

        $response->assertSessionHasNoErrors();
        $setting->refresh();
        $this->assertSame('PORTAL DE AFILIACIÓN', $setting->login_title);
        $this->assertSame(74, $setting->login_overlay_opacity);
        $this->assertStringStartsWith('institutional/login/', $setting->login_background_path);
        $this->assertStringStartsWith('institutional/login/', $setting->login_logo_path);
        Storage::disk('public')->assertExists($setting->login_background_path);
        Storage::disk('public')->assertExists($setting->login_logo_path);

        auth()->logout();
        InstitutionalSetting::clearCurrentCache();
        $this->get(route('login'))
            ->assertSee('PORTAL DE AFILIACIÓN')
            ->assertSee('COOPERATIVA CONFIGURADA')
            ->assertSee('Mensaje configurado para nuestros afiliados.')
            ->assertSee(Storage::url($setting->login_background_path));
    }

    public function test_login_appearance_rejects_unsafe_oversized_and_out_of_range_values(): void
    {
        $admin = User::create([
            'name' => 'Administrador',
            'email' => 'admin-validation@test.local',
            'role' => 'administrador',
            'password' => Hash::make('secret'),
        ]);

        $this->actingAs($admin)->put(route('institutional-settings.update'), $this->validPayload([
            'login_background' => UploadedFile::fake()->create('background.svg', 20, 'image/svg+xml'),
            'login_logo' => UploadedFile::fake()->create('large.jpg', 5121, 'image/jpeg'),
            'login_overlay_opacity' => 95,
        ]))->assertSessionHasErrors(['login_background', 'login_logo', 'login_overlay_opacity']);
    }

    public function test_login_appearance_accepts_webp_and_rejects_unauthorized_roles(): void
    {
        Storage::fake('public');
        $admin = User::create([
            'name' => 'Administrador',
            'email' => 'admin-webp@test.local',
            'role' => 'administrador',
            'password' => Hash::make('secret'),
        ]);
        $consultant = User::create([
            'name' => 'Consulta',
            'email' => 'consulta-login@test.local',
            'role' => 'consulta',
            'password' => Hash::make('secret'),
        ]);

        $this->actingAs($consultant)
            ->put(route('institutional-settings.update'), $this->validPayload())
            ->assertForbidden();

        $this->actingAs($admin)
            ->put(route('institutional-settings.update'), $this->validPayload([
                'login_logo' => $this->fakeWebp(),
            ]))
            ->assertSessionHasNoErrors();

        $path = InstitutionalSetting::query()->firstOrFail()->login_logo_path;
        $this->assertStringEndsWith('.webp', $path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_existing_authentication_flow_still_succeeds_and_rejects_invalid_passwords(): void
    {
        $user = User::create([
            'name' => 'Secretaría',
            'email' => 'login-flow@test.local',
            'role' => 'secretaria',
            'password' => Hash::make('correct-password'),
        ]);

        $this->post(route('login.post'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $this->post(route('login.post'), [
            'email' => $user->email,
            'password' => 'correct-password',
        ])->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'institution_name' => 'Cooperativa Tierra Bendita',
            'primary_color' => '#0b1f3a',
            'secondary_color' => '#d4af37',
            'login_title' => 'SISTEMA DE AFILIACIÓN',
            'login_institution_name' => 'COOPERATIVA TIERRA BENDITA',
            'login_affiliate_message' => 'Mensaje institucional.',
            'login_overlay_opacity' => 65,
        ], $overrides);
    }

    private function fakeWebp(): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'siafco-webp-');
        $image = imagecreatetruecolor(40, 40);
        imagefilledrectangle($image, 0, 0, 40, 40, imagecolorallocate($image, 11, 31, 58));
        imagewebp($image, $path);
        imagedestroy($image);

        return new UploadedFile($path, 'logo.webp', 'image/webp', null, true);
    }
}
