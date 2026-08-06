<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditLogSanitizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdministrationModulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_administrator_sees_roles_permissions_and_real_internal_roles_only(): void
    {
        $super = $this->internalUser('superadministrador');
        User::factory()->create(['name' => 'Afiliado Visible No', 'role' => 'afiliado', 'user_type' => 'affiliate']);

        $this->actingAs($super)->get(route('administration.roles.index'))
            ->assertOk()
            ->assertSee('Roles y permisos')
            ->assertSee('Super Administrador')
            ->assertSee('Administrador (legado)')
            ->assertSee('Gerente')
            ->assertSee('Mini tienda')
            ->assertDontSee('Afiliado Visible No')
            ->assertDontSee('/administracion/roles-permisos/afiliado', false);

        $this->assertDatabaseHas('audit_logs', ['action' => 'role_user_count_viewed']);
    }

    public function test_user_without_roles_permission_receives_forbidden(): void
    {
        $cashier = $this->internalUser('cajero');

        $this->actingAs($cashier)->get(route('administration.roles.index'))->assertForbidden();
        $this->actingAs($cashier)->patch(route('administration.roles.update', 'gerente'), [
            'permissions' => ['dashboard.view'],
        ])->assertForbidden();
    }

    public function test_matrix_shows_real_permissions_and_mobile_cards(): void
    {
        $super = $this->internalUser('superadministrador');

        $this->actingAs($super)->get(route('administration.roles.edit', 'gerente'))
            ->assertOk()
            ->assertSee('affiliates.view')
            ->assertSee('store.manage-products')
            ->assertSee('roles.view')
            ->assertSee('sm:grid-cols-2', false);
    }

    public function test_update_adds_and_removes_permissions_and_is_audited(): void
    {
        $super = $this->internalUser('superadministrador');
        $permissions = collect(config('internal_roles.roles.gerente'))
            ->reject(fn (string $permission) => $permission === 'reports.export')
            ->push('store.manage-products')
            ->values()
            ->all();

        $this->actingAs($super)->patch(route('administration.roles.update', 'gerente'), [
            'permissions' => $permissions,
        ])->assertSessionHasNoErrors();

        $manager = $this->internalUser('gerente');
        $this->assertTrue($manager->hasPermission('store.manage-products'));
        $this->assertFalse($manager->hasPermission('reports.export'));

        $audit = AuditLog::where('action', 'role_permissions_updated')->firstOrFail();
        $this->assertContains('store.manage-products', $audit->metadata['added_permissions']);
        $this->assertContains('reports.export', $audit->metadata['removed_permissions']);
    }

    public function test_rejects_arbitrary_permissions_and_protects_super_admin_critical_access(): void
    {
        $super = $this->internalUser('superadministrador');

        $this->actingAs($super)->patch(route('administration.roles.update', 'gerente'), [
            'permissions' => ['dashboard.view', 'inventado.destroy'],
        ])->assertSessionHasErrors('permissions.1');

        $this->actingAs($super)->patch(route('administration.roles.update', 'superadministrador'), [
            'permissions' => ['dashboard.view', 'audit.view'],
        ])->assertSessionHasErrors('permissions');
    }

    public function test_historical_administrator_keeps_total_access_and_mini_store_permissions(): void
    {
        $legacy = $this->internalUser('administrador');

        $this->assertTrue($legacy->hasPermission('roles.view'));
        $this->assertTrue($legacy->hasPermission('roles.update'));
        $this->assertTrue($legacy->hasPermission('store.view'));
        $this->assertTrue($legacy->hasPermission('store.manage-products'));

        $this->actingAs($legacy)->get(route('administration.roles.index'))->assertOk();
    }

    public function test_secretary_and_cashier_keep_expected_safe_matrices(): void
    {
        $secretary = $this->internalUser('secretaria');
        $cashier = $this->internalUser('cajero');

        $this->assertTrue($secretary->hasPermission('payments.confirm'));
        $this->assertTrue($secretary->hasPermission('store.manage-coupons'));
        $this->assertFalse($secretary->hasPermission('roles.update'));

        $this->assertTrue($cashier->hasPermission('payments.confirm'));
        $this->assertFalse($cashier->hasPermission('payments.void'));
        $this->assertFalse($cashier->hasPermission('users.delete'));
    }

    public function test_audit_index_lists_filters_and_redacts_sensitive_summary(): void
    {
        $super = $this->internalUser('superadministrador');
        $actor = $this->internalUser('secretaria', ['name' => 'Secretaria Auditada']);
        AuditLog::create([
            'user_id' => $actor->id,
            'action' => 'affiliate_personal_data_updated',
            'auditable_type' => User::class,
            'auditable_id' => $actor->id,
            'metadata' => ['fields' => ['phone'], 'password' => 'Secreto123', 'request_id' => 'REQ-1'],
            'ip_address' => '127.0.0.55',
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);
        AuditLog::create([
            'user_id' => $super->id,
            'action' => 'mini_tienda.pedido_creado',
            'metadata' => ['order_code' => 'ORD-1'],
            'ip_address' => '10.0.0.1',
        ]);

        $this->actingAs($super)->get(route('administration.audit.index', [
            'action' => 'affiliate',
            'role' => 'secretaria',
            'ip' => '127.0.0',
            'date_from' => now()->subDays(2)->toDateString(),
        ]))
            ->assertOk()
            ->assertSee('affiliate_personal_data_updated')
            ->assertSee('Secretaria Auditada')
            ->assertSee('Afiliacion')
            ->assertDontSee('Secreto123');
    }

    public function test_audit_detail_shows_changes_readably_and_redacts_sensitive_keys(): void
    {
        $super = $this->internalUser('superadministrador');
        $audit = AuditLog::create([
            'user_id' => $super->id,
            'action' => 'payment_updated',
            'metadata' => [
                'old_values' => ['status' => 'pendiente', 'remember_token' => 'secret-token'],
                'new_values' => ['status' => 'confirmado', 'voucher_path' => 'private/file.jpg'],
                'user_agent' => 'PHPUnit',
            ],
            'ip_address' => '127.0.0.1',
        ]);

        $this->actingAs($super)->get(route('administration.audit.show', $audit))
            ->assertOk()
            ->assertSee('pendiente')
            ->assertSee('confirmado')
            ->assertSee('[REDACTADO]')
            ->assertDontSee('secret-token')
            ->assertDontSee('private/file.jpg');
    }

    public function test_audit_export_requires_permission_and_excludes_sensitive_values(): void
    {
        $super = $this->internalUser('superadministrador');
        $manager = $this->internalUser('gerente');
        AuditLog::create([
            'user_id' => $super->id,
            'action' => 'mobile_login',
            'metadata' => ['token' => 'Bearer secret', 'result' => 'success'],
            'ip_address' => '127.0.0.1',
        ]);

        $this->actingAs($manager)->get(route('administration.audit.export'))->assertForbidden();

        $response = $this->actingAs($super)->get(route('administration.audit.export'))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $csv = $response->streamedContent();
        $this->assertStringContainsString('mobile_login', $csv);
        $this->assertStringNotContainsString('Bearer secret', $csv);
    }

    public function test_audit_pagination_is_real(): void
    {
        $super = $this->internalUser('superadministrador');
        foreach (range(1, 25) as $index) {
            AuditLog::create([
                'user_id' => $super->id,
                'action' => 'audit.pagination_'.$index,
                'metadata' => ['result' => 'ok'],
                'ip_address' => '127.0.0.1',
            ]);
        }

        $this->actingAs($super)->get(route('administration.audit.index'))
            ->assertOk()
            ->assertSee('page=2', false);
    }

    public function test_audit_summary_normalizes_simple_field_arrays(): void
    {
        $summary = app(AuditLogSanitizer::class)->summary(['fields' => ['nombre', 'correo', 'telefono']]);

        $this->assertSame('Campos: nombre, correo, telefono', $summary);
    }

    public function test_audit_summary_normalizes_nested_field_arrays(): void
    {
        $summary = app(AuditLogSanitizer::class)->summary([
            'fields' => ['nombre', ['correo', ['telefono', 'direccion']]],
        ]);

        $this->assertSame('Campos: nombre, correo, telefono, direccion', $summary);
    }

    public function test_audit_summary_normalizes_associative_field_arrays(): void
    {
        $summary = app(AuditLogSanitizer::class)->summary([
            'fields' => [
                ['field' => 'nombre'],
                'contacto' => ['old' => 'A', 'new' => 'B'],
                ['field' => 2026],
            ],
        ]);

        $this->assertSame('Campos: nombre, contacto, 2026', $summary);
    }

    public function test_audit_summary_handles_empty_metadata(): void
    {
        $summary = app(AuditLogSanitizer::class)->summary([]);

        $this->assertSame('Sin resumen', $summary);
    }

    public function test_audit_summary_handles_corrupt_metadata_without_exceptions(): void
    {
        $summary = app(AuditLogSanitizer::class)->summary(['fields' => [null, false, [], ['field' => []]]]);

        $this->assertSame('Sin detalles', $summary);
    }

    public function test_audit_summary_handles_mixed_strings_numbers_and_arrays(): void
    {
        $summary = app(AuditLogSanitizer::class)->summary([
            'fields' => ['nombre', ['field' => 'correo'], ['telefono', 123], 'nombre', ''],
        ]);

        $this->assertSame('Campos: nombre, correo, telefono, 123', $summary);
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
}
