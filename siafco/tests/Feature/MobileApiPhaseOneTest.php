<?php

namespace Tests\Feature;

use App\Models\Affiliate;
use App\Models\AffiliationPlan;
use App\Models\AuditLog;
use App\Models\Person;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class MobileApiPhaseOneTest extends TestCase
{
    use RefreshDatabase;

    public function test_affiliate_can_login_and_receive_uniform_profile_payload(): void
    {
        $user = $this->affiliateUser(status: 'activo', password: 'Secret1234');

        $response = $this->postJson('/api/mobile/v1/auth/login', [
            'email' => $user->email,
            'password' => 'Secret1234',
            'device_name' => 'Pixel 8',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.profile.user.email', $user->email)
            ->assertJsonPath('data.profile.affiliate.status', 'activo')
            ->assertJsonPath('data.profile.affiliate.access_level', 'full')
            ->assertJsonStructure(['data' => ['access_token']]);

        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertDatabaseHas('audit_logs', ['action' => 'mobile_login', 'user_id' => $user->id]);
        $this->assertStringNotContainsString(
            $response->json('data.access_token'),
            AuditLog::firstWhere('action', 'mobile_login')->metadata ? json_encode(AuditLog::firstWhere('action', 'mobile_login')->metadata) : ''
        );
    }

    public function test_mobile_login_rejects_internal_users_and_blocked_affiliate_statuses(): void
    {
        $internal = User::factory()->create([
            'email' => 'internal@siafco.test',
            'password' => Hash::make('Secret1234'),
            'role' => 'administrador',
            'user_type' => 'internal',
        ]);
        $rejected = $this->affiliateUser(status: 'rechazado', email: 'rejected@siafco.test', password: 'Secret1234');

        $this->postJson('/api/mobile/v1/auth/login', [
            'email' => $internal->email,
            'password' => 'Secret1234',
        ])->assertForbidden()->assertJsonPath('success', false);

        $this->postJson('/api/mobile/v1/auth/login', [
            'email' => $rejected->email,
            'password' => 'Secret1234',
        ])->assertForbidden()->assertJsonPath('errors.status.0', 'rechazado');

        $this->postJson('/api/mobile/v1/auth/login', [
            'email' => $rejected->email,
            'password' => 'wrong-password',
        ])->assertUnauthorized()->assertJsonPath('success', false);
    }

    public function test_login_rate_limit_uses_uniform_json_response(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => '10.10.10.10'])
                ->postJson('/api/mobile/v1/auth/login', [
                    'email' => 'missing'.$attempt.'@siafco.test',
                    'password' => 'bad-password',
                ])->assertUnauthorized();
        }

        $this->withServerVariables(['REMOTE_ADDR' => '10.10.10.10'])
            ->postJson('/api/mobile/v1/auth/login', [
                'email' => 'missing@siafco.test',
                'password' => 'bad-password',
            ])
            ->assertStatus(429)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Demasiados intentos. Intenta nuevamente más tarde.');
    }

    public function test_existing_token_stops_working_when_affiliate_status_becomes_blocked(): void
    {
        $user = $this->affiliateUser(status: 'activo');
        $token = $this->tokenFor($user);

        $user->affiliate()->update(['status' => 'suspendido']);

        $this->withToken($token)->getJson('/api/mobile/v1/me')
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.status.0', 'suspendido');
    }

    public function test_blocked_affiliate_account_gets_uniform_403_and_existing_token_stops_working(): void
    {
        $user = $this->affiliateUser(status: 'activo', password: 'Secret1234');
        $token = $this->tokenFor($user);

        $user->forceFill(['is_active' => false])->save();

        $this->postJson('/api/mobile/v1/auth/login', [
            'email' => $user->email,
            'password' => 'Secret1234',
        ])->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'La cuenta no está activa.');

        $this->withToken($token)->getJson('/api/mobile/v1/me')
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'La cuenta no está activa.');
    }

    public function test_internal_user_token_cannot_access_mobile_routes(): void
    {
        $internal = User::factory()->create([
            'role' => 'administrador',
            'user_type' => 'internal',
        ]);

        $this->withToken($this->tokenFor($internal))->getJson('/api/mobile/v1/me')
            ->assertForbidden()
            ->assertJsonPath('success', false);
    }

    public function test_pending_affiliate_can_access_limited_profile_password_and_logout(): void
    {
        $user = $this->affiliateUser(status: 'pendiente_pago', password: 'Secret1234');
        $token = $this->tokenFor($user);

        $this->withToken($token)->getJson('/api/mobile/v1/me')
            ->assertOk()
            ->assertJsonPath('data.profile.affiliate.status', 'pendiente_pago')
            ->assertJsonPath('data.profile.affiliate.access_level', 'limited');

        $this->withToken($token)->patchJson('/api/mobile/v1/me/password', [
            'current_password' => 'Secret1234',
            'password' => 'Better1234',
            'password_confirmation' => 'Better1234',
        ])->assertOk();

        $this->withToken($token)->postJson('/api/mobile/v1/auth/logout')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_logout_all_revokes_every_mobile_token_for_current_affiliate(): void
    {
        $user = $this->affiliateUser(status: 'activo');
        $other = $this->affiliateUser(status: 'activo', email: 'other@siafco.test', ci: '90020002');
        $token = $this->tokenFor($user, 'phone');
        $this->tokenFor($user, 'tablet');
        $this->tokenFor($other, 'other-phone');

        $this->withToken($token)->postJson('/api/mobile/v1/auth/logout-all')
            ->assertOk();

        $this->assertSame(0, $user->tokens()->count());
        $this->assertSame(1, $other->tokens()->count());
    }

    public function test_affiliate_updates_only_allowed_profile_fields_with_json_payload(): void
    {
        $user = $this->affiliateUser(status: 'activo', password: 'Secret1234');
        $token = $this->tokenFor($user);

        $response = $this->withToken($token)->patchJson('/api/mobile/v1/me/profile', [
            'phone' => '76543210',
            'email' => 'new-affiliate@siafco.test',
            'address' => 'Nueva direccion',
            'birth_date' => '1990-05-15',
            'marital_status' => 'CASADO',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.profile.user.email', 'new-affiliate@siafco.test')
            ->assertJsonPath('data.profile.affiliate.phone', '76543210');

        $user->refresh();
        $affiliate = $user->affiliate()->first();
        $this->assertSame('new-affiliate@siafco.test', $user->email);
        $this->assertSame('76543210', $affiliate->phone);
        $this->assertSame('Nueva direccion', $affiliate->address);
        $this->assertSame('1990-05-15', $affiliate->birth_date->toDateString());
        $this->assertSame('CASADO', $affiliate->marital_status);
        $this->assertSame('76543210', $affiliate->person->phone);
        $this->assertSame('new-affiliate@siafco.test', $affiliate->person->email);
        $this->assertSame('Nueva direccion', $affiliate->person->address);
        $this->assertSame('1990-05-15', $affiliate->person->birth_date->toDateString());
        $this->assertSame('CASADO', $affiliate->person->marital_status);
        $this->withToken($token)->getJson('/api/mobile/v1/me')
            ->assertOk()
            ->assertJsonPath('data.profile.affiliate.phone', '76543210')
            ->assertJsonPath('data.profile.affiliate.marital_status', 'CASADO');
        $this->actingAs($user)->get(route('affiliate.profile.show'))
            ->assertOk()
            ->assertSee('76543210')
            ->assertSee('CASADO');
        $this->assertDatabaseHas('audit_logs', ['action' => 'mobile_affiliate_profile_updated']);
    }

    public function test_affiliate_updates_photo_through_dedicated_multipart_endpoint(): void
    {
        Storage::fake('public');
        $user = $this->affiliateUser(status: 'activo', password: 'Secret1234');
        $token = $this->tokenFor($user);

        $response = $this->withToken($token)->post('/api/mobile/v1/me/profile/photo', [
            'photo' => UploadedFile::fake()->image('profile.jpg', 700, 700),
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $affiliate = $user->affiliate()->first();
        $this->assertNotNull($affiliate->photo_path);
        $this->assertSame($affiliate->photo_path, $affiliate->person->photo);
        Storage::disk('public')->assertExists($affiliate->photo_path);
        $response->assertJsonPath('data.profile.affiliate.photo_url', fn ($url) => is_string($url)
            && str_contains($url, '/storage/affiliates/photos/')
            && str_contains($url, '?v='));
        $this->assertDatabaseHas('audit_logs', ['action' => 'mobile_affiliate_photo_updated']);
    }

    public function test_profile_rejects_duplicate_email_requires_editable_field_and_invalid_photo_mime(): void
    {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        Storage::fake('public');
        $user = $this->affiliateUser(status: 'activo', email: 'one@siafco.test', ci: '90021001');
        $other = $this->affiliateUser(status: 'activo', email: 'two@siafco.test', ci: '90021002');
        $token = $this->tokenFor($user);

        $this->withToken($token)->patchJson('/api/mobile/v1/me/profile', [
            'email' => $other->email,
        ])->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['email']]);

        $this->withToken($token)->patchJson('/api/mobile/v1/me/profile', [])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['profile']]);

        $beforeMaritalStatus = $user->affiliate->marital_status;
        $beforePersonMaritalStatus = $user->affiliate->person->marital_status;
        $this->withToken($token)->patchJson('/api/mobile/v1/me/profile', [
            'email' => $user->email,
            'marital_status' => 'casado',
        ])->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['marital_status']]);
        $this->assertSame($beforeMaritalStatus, $user->affiliate->fresh()->marital_status);
        $this->assertSame($beforePersonMaritalStatus, $user->affiliate->person->fresh()->marital_status);

        $photoUser = $this->affiliateUser(status: 'activo', email: 'photo-invalid@siafco.test', ci: '90021003');
        $this->withToken($this->tokenFor($photoUser))->post('/api/mobile/v1/me/profile/photo', [
            'photo' => UploadedFile::fake()->create('avatar.svg', 10, 'image/svg+xml'),
        ])->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['photo']]);
    }

    public function test_protected_profile_fields_are_rejected_and_do_not_change_another_affiliate(): void
    {
        $user = $this->affiliateUser(status: 'activo');
        $other = $this->affiliateUser(status: 'activo', email: 'other@siafco.test', ci: '90020003');
        $token = $this->tokenFor($user);

        $this->withToken($token)->patchJson('/api/mobile/v1/me/profile', [
            'email' => $user->email,
            'phone' => '76543210',
            'photo' => 'data:image/png;base64,AAAA',
            'registration_number' => 'HACK-000001',
            'status' => 'activo',
            'user_id' => $other->id,
            'affiliate_id' => $other->affiliate->id,
        ])->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['profile']]);

        $this->assertNotSame('HACK-000001', $user->affiliate()->first()->registration_number);
        $this->assertSame($other->id, $other->fresh()->id);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'mobile_affiliate_profile_protected_fields_rejected',
            'auditable_id' => $user->affiliate->id,
        ]);
    }

    public function test_password_change_validates_current_password_policy_and_forbidden_identity_values(): void
    {
        $user = $this->affiliateUser(status: 'activo', password: 'Secret1234', ci: '77889900');
        $token = $this->tokenFor($user);
        $otherToken = $this->tokenFor($user, 'other-device');

        $this->withToken($token)->patchJson('/api/mobile/v1/me/password', [
            'current_password' => 'bad-password',
            'password' => 'Better1234',
            'password_confirmation' => 'Better1234',
        ])->assertUnprocessable()->assertJsonPath('success', false);

        $this->withToken($token)->patchJson('/api/mobile/v1/me/password', [
            'current_password' => 'Secret1234',
            'password' => 'MAG-RUR-77889900',
            'password_confirmation' => 'MAG-RUR-77889900',
        ])->assertUnprocessable()->assertJsonPath('success', false);

        $this->withToken($token)->patchJson('/api/mobile/v1/me/password', [
            'current_password' => 'Secret1234',
            'password' => 'Better1234',
            'password_confirmation' => 'Better1234',
        ])->assertOk()
            ->assertJsonPath('message', 'Contraseña actualizada.');

        $this->assertTrue(Hash::check('Better1234', $user->fresh()->password));
        $this->assertSame(1, $user->tokens()->count());
        $this->assertNotNull(PersonalAccessToken::findToken($token));
        $this->assertNull(PersonalAccessToken::findToken($otherToken));
        $this->withToken($token)->getJson('/api/mobile/v1/me')->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'mobile_affiliate_password_changed']);
    }

    public function test_token_authentication_is_required_and_cannot_cross_modify_affiliates(): void
    {
        $user = $this->affiliateUser(status: 'activo', email: 'one@siafco.test', ci: '90030001');
        $other = $this->affiliateUser(status: 'activo', email: 'two@siafco.test', ci: '90030002');

        $this->getJson('/api/mobile/v1/me')
            ->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'No autenticado.');

        $this->withToken('invalid-token')->getJson('/api/mobile/v1/me')
            ->assertUnauthorized()
            ->assertJsonPath('success', false);

        $this->withToken($this->tokenFor($user))->patchJson('/api/mobile/v1/me/profile', [
            'email' => 'one-updated@siafco.test',
            'phone' => '70000001',
            'address' => 'Direccion uno',
            'birth_date' => '1991-01-01',
            'marital_status' => 'SOLTERO',
        ])->assertOk();

        $this->assertSame('two@siafco.test', $other->fresh()->email);
        $this->assertSame('two@siafco.test', $other->affiliate()->first()->email);
    }

    private function tokenFor(User $user, string $name = 'test-device'): string
    {
        return $user->createToken($name, ['mobile'])->plainTextToken;
    }

    private function affiliateUser(
        string $status = 'activo',
        string $email = 'affiliate@siafco.test',
        string $ci = '90010001',
        string $password = 'password'
    ): User {
        $sector = Sector::create([
            'name' => 'Magisterio Rural',
            'code' => 'MAG-RUR-'.substr($ci, -3),
            'is_active' => true,
        ]);
        $plan = AffiliationPlan::create([
            'name' => 'Plan '.$ci,
            'affiliation_fee' => 100,
            'credential_fee' => 30,
            'is_active' => true,
        ]);
        $person = Person::create([
            'full_name' => 'Afiliado '.$ci,
            'ci' => $ci,
            'phone' => '70000000',
            'email' => $email,
        ]);
        $user = User::factory()->create([
            'person_id' => $person->id,
            'name' => $person->full_name,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => 'afiliado',
            'user_type' => 'affiliate',
            'is_active' => true,
        ]);
        Affiliate::create([
            'person_id' => $person->id,
            'user_id' => $user->id,
            'sector_id' => $sector->id,
            'affiliation_plan_id' => $plan->id,
            'full_name' => $person->full_name,
            'ci' => $ci,
            'phone' => '70000000',
            'email' => $email,
            'address' => 'Direccion inicial',
            'regional' => 'LA PAZ',
            'institution' => 'Institucion',
            'position' => 'Docente',
            'birth_date' => '1990-01-01',
            'marital_status' => 'SOLTERO',
            'registration_number' => $status === 'activo' ? 'MAG-RUR-'.$ci : null,
            'verification_token' => $status === 'activo' ? 'token-'.$ci : null,
            'status' => $status,
        ]);

        return $user->fresh('affiliate');
    }
}
