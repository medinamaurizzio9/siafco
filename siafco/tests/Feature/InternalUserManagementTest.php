<?php

namespace Tests\Feature;

use App\Models\Affiliate;
use App\Models\AffiliationPlan;
use App\Models\AuditLog;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class InternalUserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_listing_is_permission_protected_and_only_contains_internal_users(): void
    {
        $super = $this->internalUser('superadministrador');
        $manager = $this->internalUser('gerente');
        $secretary = $this->internalUser('secretaria');
        $cashier = $this->internalUser('cajero');
        $affiliate = User::factory()->create(['name' => 'Afiliado oculto', 'role' => 'afiliado', 'user_type' => 'affiliate']);

        foreach ([$super, $manager, $secretary] as $authorized) {
            $this->actingAs($authorized)->get(route('admin.users.index'))
                ->assertOk()
                ->assertSee('USUARIOS INTERNOS')
                ->assertSee('Administra unicamente al personal interno')
                ->assertSee('Ver')
                ->assertSee($super->name)
                ->assertDontSee($affiliate->name);
        }

        $this->actingAs($super)->get(route('admin.users.index'))
            ->assertSee('Editar')
            ->assertSee('Restablecer');

        $this->actingAs($cashier)->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($affiliate)->get(route('admin.users.index'))->assertForbidden();
    }

    public function test_super_administrator_creates_internal_user_with_role_hashed_ci_and_uuid_photo(): void
    {
        Storage::fake('public');
        $super = $this->internalUser('superadministrador');

        $response = $this->actingAs($super)->post(route('admin.users.store'), [
            'name' => 'María Operadora',
            'ci' => ' 123.456-7A ',
            'phone' => '70000000',
            'email' => 'MARIA@EXAMPLE.COM',
            'username' => 'maria_operadora',
            'position' => 'Responsable',
            'area' => 'Caja',
            'role' => 'cajero',
            'photo' => UploadedFile::fake()->image('foto.jpg', 300, 300),
            'use_ci_password' => '1',
            'is_active' => '1',
        ]);

        $response->assertSessionHasNoErrors();
        $user = User::where('username', 'maria_operadora')->firstOrFail();
        $response->assertRedirect(route('admin.users.show', $user));
        $this->assertSame('internal', $user->user_type);
        $this->assertSame('cajero', $user->role);
        $this->assertTrue($user->must_change_password);
        $this->assertTrue(Hash::check('123456', $user->password));
        $this->assertMatchesRegularExpression(
            '#^internal-users/photos/[0-9a-f-]{36}\.jpg$#',
            $user->photo_path
        );
        Storage::disk('public')->assertExists($user->photo_path);
        $this->assertDatabaseHas('audit_logs', ['action' => 'internal_user_created', 'auditable_id' => $user->id]);
    }

    public function test_creation_rejects_duplicates_invalid_roles_and_unauthorized_users(): void
    {
        $super = $this->internalUser('superadministrador', ['ci' => '111', 'username' => 'existing']);
        $payload = $this->validPayload(['ci' => '111', 'username' => 'existing', 'role' => 'inventado']);

        $this->actingAs($super)->post(route('admin.users.store'), $payload)
            ->assertSessionHasErrors(['ci', 'username', 'role']);

        $cashier = $this->internalUser('cajero');
        $this->actingAs($cashier)->post(route('admin.users.store'), $this->validPayload())->assertForbidden();
    }

    public function test_update_changes_allowed_data_and_audits_role_without_touching_password(): void
    {
        $super = $this->internalUser('superadministrador');
        $target = $this->internalUser('secretaria');
        $oldPassword = $target->password;

        $this->actingAs($super)->patch(route('admin.users.update', $target), [
            'name' => 'Secretaria Actualizada',
            'ci' => $target->ci,
            'email' => $target->email,
            'username' => $target->username,
            'phone' => '76543210',
            'position' => 'Jefatura',
            'area' => 'Secretaría',
            'role' => 'gerente',
        ])->assertRedirect(route('admin.users.show', $target));

        $target->refresh();
        $this->assertSame('gerente', $target->role);
        $this->assertSame($oldPassword, $target->password);
        $this->assertDatabaseHas('audit_logs', ['action' => 'internal_user_role_changed', 'auditable_id' => $target->id]);
    }

    public function test_super_administrator_rules_protect_assignment_self_and_last_active_super(): void
    {
        $onlySuper = $this->internalUser('superadministrador');
        $manager = $this->internalUser('gerente');

        $this->actingAs($manager)->post(route('admin.users.store'), $this->validPayload(['role' => 'superadministrador']))
            ->assertForbidden();
        $this->actingAs($onlySuper)->post(route('admin.users.block', $onlySuper))->assertForbidden();
        $this->actingAs($onlySuper)->delete(route('admin.users.destroy', $onlySuper), ['confirmation' => 'ELIMINAR'])->assertForbidden();

        $payload = $this->updatePayload($onlySuper, ['role' => 'gerente']);
        $this->actingAs($onlySuper)->patch(route('admin.users.update', $onlySuper), $payload)->assertForbidden();
        $this->assertSame('superadministrador', $onlySuper->fresh()->role);
    }

    public function test_legacy_administrator_can_bootstrap_the_first_canonical_super_administrator(): void
    {
        $legacyAdministrator = $this->internalUser('administrador');

        $this->actingAs($legacyAdministrator)->post(
            route('admin.users.store'),
            $this->validPayload(['role' => 'superadministrador'])
        )->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', [
            'role' => 'superadministrador',
            'user_type' => 'internal',
        ]);
    }

    public function test_block_closes_sessions_rotates_token_and_prevents_login_then_activate_restores_access(): void
    {
        $super = $this->internalUser('superadministrador');
        $target = $this->internalUser('secretaria', ['password' => Hash::make('secret1234'), 'remember_token' => 'old-token']);
        DB::table('sessions')->insert([
            'id' => 'active-session', 'user_id' => $target->id, 'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit', 'payload' => '', 'last_activity' => time(),
        ]);

        $this->actingAs($super)->post(route('admin.users.block', $target))->assertSessionHas('status');
        $target->refresh();
        $this->assertFalse($target->is_active);
        $this->assertNotSame('old-token', $target->remember_token);
        $this->assertDatabaseMissing('sessions', ['user_id' => $target->id]);
        auth()->logout();
        $this->post(route('login.post'), ['email' => $target->email, 'password' => 'secret1234'])->assertSessionHasErrors('email');

        $this->actingAs($super)->post(route('admin.users.activate', $target))->assertSessionHas('status');
        $this->assertTrue($target->fresh()->is_active);
    }

    public function test_password_reset_uses_ci_forces_change_clears_sessions_and_never_audits_secret(): void
    {
        $super = $this->internalUser('superadministrador');
        $target = $this->internalUser('secretaria', ['ci' => '98.765-2X', 'remember_token' => 'known-token']);
        DB::table('sessions')->insert([
            'id' => 'reset-session', 'user_id' => $target->id, 'ip_address' => null,
            'user_agent' => null, 'payload' => '', 'last_activity' => time(),
        ]);

        $this->actingAs($super)->post(route('admin.users.password.reset', $target), ['confirmation' => 'RESTABLECER'])
            ->assertSessionHas('status');

        $target->refresh();
        $this->assertTrue(Hash::check('98765', $target->password));
        $this->assertTrue($target->must_change_password);
        $this->assertNotSame('known-token', $target->remember_token);
        $this->assertDatabaseMissing('sessions', ['user_id' => $target->id]);
        $metadata = AuditLog::where('action', 'internal_user_password_reset')->firstOrFail()->metadata;
        $this->assertArrayNotHasKey('password', $metadata);
        $this->assertArrayNotHasKey('remember_token', $metadata);
    }

    public function test_password_reset_without_ci_is_controlled(): void
    {
        $super = $this->internalUser('superadministrador');
        $target = $this->internalUser('secretaria', ['ci' => null]);

        $this->actingAs($super)->post(route('admin.users.password.reset', $target), ['confirmation' => 'RESTABLECER'])
            ->assertSessionHasErrors();
    }

    public function test_soft_delete_and_restore_keep_related_affiliate_untouched(): void
    {
        $super = $this->internalUser('superadministrador');
        $target = $this->internalUser('secretaria');
        $affiliateUser = User::factory()->create(['user_type' => 'affiliate', 'role' => 'afiliado']);
        $affiliate = $this->affiliateFor($affiliateUser);

        $this->actingAs($super)->delete(route('admin.users.destroy', $target), ['confirmation' => 'ELIMINAR'])
            ->assertRedirect(route('admin.users.index'));
        $this->assertSoftDeleted($target);
        $this->assertDatabaseHas('affiliates', ['id' => $affiliate->id]);

        $this->actingAs($super)->post(route('admin.users.restore', $target->id))
            ->assertRedirect(route('admin.users.show', $target->id));
        $this->assertNotSoftDeleted($target);
        $this->assertDatabaseHas('audit_logs', ['action' => 'internal_user_restored', 'auditable_id' => $target->id]);
    }

    public function test_successful_login_updates_last_access_and_forced_password_flow_remains_active(): void
    {
        $user = $this->internalUser('secretaria', [
            'password' => Hash::make('secret1234'),
            'must_change_password' => true,
            'last_login_at' => null,
        ]);

        $this->post(route('login.post'), ['email' => $user->email, 'password' => 'secret1234'])
            ->assertRedirect(route('admin.dashboard'));
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->assertSame('127.0.0.1', $user->fresh()->last_login_ip);
        $this->get(route('admin.dashboard'))->assertRedirect(route('password.force.edit'));
    }

    public function test_manager_can_access_dashboard_and_authorized_read_sections_only(): void
    {
        $manager = $this->internalUser('gerente');
        $onlySuper = $this->internalUser('superadministrador');

        $this->actingAs($manager)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Dashboard general')
            ->assertSee('Afiliados')
            ->assertSee('Pagos de afiliacion')
            ->assertSee('Reportes de afiliacion')
            ->assertDontSee('NUEVO USUARIO INTERNO')
            ->assertDontSee('QR y pago institucional');

        $this->actingAs($manager)->get(route('affiliates.index'))->assertOk();
        $this->actingAs($manager)->get(route('reports.index'))->assertOk();
        $this->actingAs($manager)->delete(route('admin.users.destroy', $onlySuper), ['confirmation' => 'ELIMINAR'])
            ->assertForbidden();

        $this->assertFalse($manager->hasPermission('users.delete'));
        $this->assertFalse($manager->hasPermission('users.reset-password'));
        $this->assertFalse($manager->hasPermission('settings.update'));
        $this->assertTrue($manager->hasPermission('dashboard.view'));
    }

    private function internalUser(string $role, array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'role' => $role,
            'user_type' => 'internal',
            'username' => 'user_'.Str::lower(Str::random(10)),
            'ci' => (string) fake()->unique()->numberBetween(100000, 99999999),
            'is_active' => true,
            'must_change_password' => false,
        ], $attributes));
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Usuario Nuevo',
            'ci' => (string) fake()->unique()->numberBetween(100000, 99999999),
            'phone' => '70000000',
            'email' => fake()->unique()->safeEmail(),
            'username' => 'user_'.Str::lower(Str::random(10)),
            'position' => 'Operador',
            'area' => 'Administración',
            'role' => 'cajero',
            'password' => 'temporary123',
            'password_confirmation' => 'temporary123',
            'use_ci_password' => '0',
            'is_active' => '1',
        ], $overrides);
    }

    private function updatePayload(User $user, array $overrides = []): array
    {
        return array_merge([
            'name' => $user->name, 'ci' => $user->ci, 'phone' => $user->phone,
            'email' => $user->email, 'username' => $user->username,
            'position' => $user->position, 'area' => $user->area, 'role' => $user->role,
        ], $overrides);
    }

    private function affiliateFor(User $user): Affiliate
    {
        $sector = Sector::create(['name' => 'Sector', 'code' => Str::upper(Str::random(8)), 'is_active' => true]);
        $plan = AffiliationPlan::create(['name' => 'Plan '.Str::random(5), 'affiliation_fee' => 100, 'credential_fee' => 20, 'is_active' => true]);

        return Affiliate::create([
            'user_id' => $user->id, 'sector_id' => $sector->id, 'affiliation_plan_id' => $plan->id,
            'full_name' => $user->name, 'ci' => 'AFF-'.Str::random(8), 'email' => Str::random(8).'@example.test',
            'registration_number' => 'REG-'.Str::upper(Str::random(8)), 'status' => 'activo',
            'verification_token' => Str::uuid(),
        ]);
    }
}
