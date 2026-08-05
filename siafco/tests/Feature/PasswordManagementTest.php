<?php

namespace Tests\Feature;

use App\Models\Affiliate;
use App\Models\AffiliationPlan;
use App\Models\AuditLog;
use App\Models\Sector;
use App\Models\User;
use App\Services\AffiliatePasswordService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class PasswordManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_affiliate_changes_own_password_with_secure_rules(): void
    {
        [$user, $affiliate] = $this->affiliate();

        $this->actingAs($user)->get(route('affiliate.profile.show'))
            ->assertOk()->assertSee('SEGURIDAD DE LA CUENTA')->assertDontSee($user->password);

        $this->actingAs($user)->patch(route('affiliate.profile.password.update'), [
            'current_password' => 'OldPassword1',
            'password' => 'NuevaClave2026',
            'password_confirmation' => 'NuevaClave2026',
        ])->assertRedirect();

        $user->refresh();
        $this->assertTrue(Hash::check('NuevaClave2026', $user->password));
        $this->assertFalse($user->must_change_password);
        $audit = AuditLog::where('action', 'affiliate_password_changed')->firstOrFail();
        $this->assertStringNotContainsString('NuevaClave2026', json_encode($audit->metadata));
    }

    public function test_own_password_rejects_wrong_current_confirmation_and_identity_values(): void
    {
        [$user, $affiliate] = $this->affiliate();

        $this->actingAs($user)->patch(route('affiliate.profile.password.update'), [
            'current_password' => 'WrongPassword1', 'password' => 'NuevaClave2026',
            'password_confirmation' => 'NuevaClave2026',
        ])->assertSessionHasErrors('current_password');

        $this->actingAs($user)->patch(route('affiliate.profile.password.update'), [
            'current_password' => 'OldPassword1', 'password' => 'NuevaClave2026',
            'password_confirmation' => 'OtraClave2026',
        ])->assertSessionHasErrors('password');

        foreach ([$affiliate->ci, $affiliate->registration_number, $user->email] as $forbidden) {
            $this->actingAs($user)->patch(route('affiliate.profile.password.update'), [
                'current_password' => 'OldPassword1', 'password' => $forbidden,
                'password_confirmation' => $forbidden,
            ])->assertSessionHasErrors('password');
        }
    }

    public function test_authorized_roles_reset_to_normalized_ci_and_force_password_change(): void
    {
        foreach (['administrador', 'superadministrador', 'secretaria'] as $index => $role) {
            $temporary = '159635'.($index + 7);
            [$affiliateUser, $affiliate] = $this->affiliate("affiliate-{$role}@example.com", $temporary.'-1A');
            $actor = User::create([
                'name' => $role, 'email' => "{$role}@example.com", 'role' => $role,
                'password' => Hash::make('AdminPassword1'),
            ]);
            $oldToken = $affiliateUser->remember_token;

            $this->actingAs($actor)->post(route('admin.affiliates.password.reset', $affiliate), [
                'confirmation' => 'RESTABLECER',
            ])->assertRedirect();

            $affiliateUser->refresh();
            $this->assertTrue(Hash::check($temporary, $affiliateUser->password));
            $this->assertTrue($affiliateUser->must_change_password);
            $this->assertNotSame($oldToken, $affiliateUser->remember_token);
            $this->assertSame(0, $affiliateUser->tokens()->count());
        }

        $audit = AuditLog::where('action', 'affiliate_password_reset')->firstOrFail();
        $this->assertArrayNotHasKey('password', $audit->metadata);
    }

    public function test_consultation_and_affiliate_cannot_reset_password(): void
    {
        [$affiliateUser, $affiliate] = $this->affiliate();
        $consultation = User::create([
            'name' => 'Consulta', 'email' => 'consulta@example.com', 'role' => 'consulta',
            'password' => Hash::make('Password1'),
        ]);

        $this->actingAs($consultation)->post(route('admin.affiliates.password.reset', $affiliate), [
            'confirmation' => 'RESTABLECER',
        ])->assertForbidden();
        $this->actingAs($affiliateUser)->post(route('admin.affiliates.password.reset', $affiliate), [
            'confirmation' => 'RESTABLECER',
        ])->assertForbidden();
    }

    public function test_forced_user_can_only_change_password_or_logout_then_reaches_panel(): void
    {
        [$user] = $this->affiliate();
        $user->update(['must_change_password' => true]);

        $this->actingAs($user)->get(route('affiliate.panel'))
            ->assertRedirect(route('password.force.edit'));
        $this->get(route('password.force.edit'))->assertOk()->assertSee('ACTUALIZA TU CONTRASEÑA');
        $this->patch(route('password.force.update'), [
            'password' => 'ClaveForzada2026',
            'password_confirmation' => 'ClaveForzada2026',
        ])->assertRedirect(route('affiliate.panel'));
        $this->assertFalse($user->fresh()->must_change_password);
        $this->get(route('affiliate.panel'))->assertOk();
    }

    public function test_affiliate_login_ignores_unauthorized_admin_intended_url(): void
    {
        [$user] = $this->affiliate();

        $this->withSession(['url.intended' => route('admin.dashboard')])
            ->post(route('login.post'), [
                'email' => $user->email,
                'password' => 'OldPassword1',
            ])->assertRedirect(route('affiliate.panel'));
    }

    public function test_internal_forced_password_change_goes_to_admin_dashboard(): void
    {
        $user = User::create([
            'name' => 'Secretaria',
            'email' => 'secretaria@example.com',
            'role' => 'secretaria',
            'user_type' => 'internal',
            'password' => Hash::make('OldPassword1'),
            'must_change_password' => true,
            'is_active' => true,
        ]);

        $this->actingAs($user)->patch(route('password.force.update'), [
            'password' => 'ClaveInterna2026',
            'password_confirmation' => 'ClaveInterna2026',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertFalse($user->fresh()->must_change_password);
    }

    public function test_affiliate_access_section_handles_linked_and_missing_user(): void
    {
        [$affiliateUser, $affiliate] = $this->affiliate();
        $actor = $this->internalActor();

        $this->actingAs($actor)->get(route('affiliates.show', $affiliate))
            ->assertOk()
            ->assertSee('Cuenta de acceso')
            ->assertSee($affiliateUser->email)
            ->assertSee('Estado de afiliacion')
            ->assertSee('Estado de la cuenta');

        $affiliate->update(['user_id' => null]);
        $this->actingAs($actor)->get(route('affiliates.show', $affiliate->fresh()))
            ->assertOk()
            ->assertSee('Sin cuenta vinculada');
    }

    public function test_block_activate_and_revoke_affiliate_access_are_account_only_actions(): void
    {
        [$affiliateUser, $affiliate] = $this->affiliate();
        $actor = $this->internalActor();
        $token = $affiliateUser->createToken('Pixel 8', ['mobile'])->plainTextToken;
        DB::table('sessions')->insert([
            'id' => 'affiliate-session',
            'user_id' => $affiliateUser->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'payload' => '',
            'last_activity' => time(),
        ]);

        $this->actingAs($actor)->post(route('admin.affiliates.access.block', $affiliate))
            ->assertSessionHas('status');

        $affiliateUser->refresh();
        $this->assertFalse($affiliateUser->is_active);
        $this->assertSame('activo', $affiliate->fresh()->status);
        $this->assertSame(0, $affiliateUser->tokens()->count());
        $this->assertDatabaseMissing('sessions', ['user_id' => $affiliateUser->id]);
        auth()->logout();
        $this->withToken($token)->getJson('/api/mobile/v1/me')->assertUnauthorized();
        $this->assertDatabaseHas('audit_logs', ['action' => 'affiliate_access_blocked', 'auditable_id' => $affiliate->id]);

        $this->actingAs($actor)->post(route('admin.affiliates.access.activate', $affiliate))
            ->assertSessionHas('status');
        $this->assertTrue($affiliateUser->fresh()->is_active);
        $this->assertDatabaseHas('audit_logs', ['action' => 'affiliate_access_activated', 'auditable_id' => $affiliate->id]);

        $affiliateUser->createToken('Tablet', ['mobile']);
        $this->actingAs($actor)->post(route('admin.affiliates.access.revoke-sessions', $affiliate))
            ->assertSessionHas('status');
        $this->assertSame(0, $affiliateUser->fresh()->tokens()->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'affiliate_sessions_revoked', 'auditable_id' => $affiliate->id]);
    }

    public function test_reset_without_linked_user_is_controlled(): void
    {
        [, $affiliate] = $this->affiliate();
        $affiliate->update(['user_id' => null]);
        $actor = User::create([
            'name' => 'Admin', 'email' => 'admin@example.com', 'role' => 'administrador',
            'password' => Hash::make('Password1'),
        ]);

        $this->actingAs($actor)->post(route('admin.affiliates.password.reset', $affiliate), [
            'confirmation' => 'RESTABLECER',
        ])->assertSessionHasErrors('affiliate');
    }

    public function test_temporary_password_normalization_is_centralized(): void
    {
        $this->assertSame('1596357', app(AffiliatePasswordService::class)->temporaryPasswordFromCi(' 1596357-1A '));
    }

    private function affiliate(string $email = 'affiliate@example.com', string $ci = '1596357'): array
    {
        $sector = Sector::create([
            'name' => 'Magisterio', 'code' => 'SEC-'.Str::upper(Str::random(6)), 'is_active' => true,
        ]);
        $plan = AffiliationPlan::create([
            'name' => 'Plan '.Str::random(4), 'affiliation_fee' => 100, 'credential_fee' => 20, 'is_active' => true,
        ]);
        $user = User::create([
            'name' => 'Afiliado Prueba', 'email' => $email, 'role' => 'afiliado',
            'password' => Hash::make('OldPassword1'), 'remember_token' => 'old-token',
        ]);
        $affiliate = Affiliate::create([
            'user_id' => $user->id, 'sector_id' => $sector->id, 'affiliation_plan_id' => $plan->id,
            'full_name' => 'Afiliado Prueba', 'ci' => $ci, 'email' => $email,
            'registration_number' => 'MAG-RUR-'.Str::upper(Str::random(6)),
            'status' => 'activo', 'verification_token' => (string) Str::uuid(),
        ]);

        return [$user, $affiliate];
    }

    private function internalActor(): User
    {
        return User::create([
            'name' => 'Super Admin',
            'email' => 'super@example.com',
            'role' => 'superadministrador',
            'user_type' => 'internal',
            'password' => Hash::make('Password1'),
            'is_active' => true,
        ]);
    }
}
