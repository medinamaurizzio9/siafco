<?php

namespace App\Services;

use App\Models\RolePermissionOverride;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class RolePermissionService
{
    private const CRITICAL_PERMISSIONS = ['dashboard.view', 'roles.view', 'roles.update', 'audit.view'];

    private const SENSITIVE_BY_ROLE = [
        'cajero' => [
            'roles.view', 'roles.update', 'audit.export', 'settings.update', 'users.delete',
            'users.assign-role', 'payments.void', 'affiliates.delete', 'affiliates.soft_delete',
        ],
        'secretaria' => ['roles.update', 'users.delete', 'payments.void', 'affiliates.delete'],
        'consulta' => ['roles.view', 'roles.update', 'audit.export', 'settings.update', 'users.delete', 'payments.void'],
    ];

    public function catalog(): array
    {
        return array_values(array_unique(config('internal_roles.permissions', [])));
    }

    public function groupedCatalog(): array
    {
        $groups = [
            'Dashboard' => ['dashboard.'],
            'Afiliacion' => ['affiliates.', 'affiliate_access.', 'investors.'],
            'Tesoreria' => ['payments.', 'credits.'],
            'Credenciales' => ['credentials.'],
            'Mini Tienda' => ['store.'],
            'Usuarios Internos' => ['users.'],
            'Configuracion' => ['settings.'],
            'Auditoria' => ['audit.'],
            'Reportes' => ['reports.'],
            'Otros' => [],
        ];

        $grouped = array_fill_keys(array_keys($groups), []);

        foreach ($this->catalog() as $permission) {
            $group = 'Otros';
            foreach ($groups as $name => $prefixes) {
                if (collect($prefixes)->contains(fn (string $prefix) => str_starts_with($permission, $prefix))) {
                    $group = $name;
                    break;
                }
            }

            $grouped[$group][] = [
                'key' => $permission,
                'label' => $this->permissionLabel($permission),
                'description' => $this->permissionDescription($permission),
            ];
        }

        return array_filter($grouped);
    }

    public function roles(): Collection
    {
        return collect(config('internal_roles.labels', []))
            ->reject(fn (string $label, string $role) => in_array($role, ['afiliado', 'accionista'], true))
            ->map(fn (string $label, string $role) => [
                'key' => $role,
                'label' => $label,
                'description' => config("internal_roles.descriptions.{$role}", $this->defaultDescription($role)),
                'status' => in_array($role, config('internal_roles.assignable', []), true) ? 'Asignable' : 'Historico',
                'permission_count' => count($this->permissionsForRole($role)),
                'user_count' => User::query()
                    ->where('role', $role)
                    ->where(fn ($query) => $query->where('user_type', 'internal')->orWhereNull('user_type'))
                    ->count(),
                'has_override' => $this->overrideFor($role) !== null,
            ]);
    }

    public function normalizeRole(?string $role): ?string
    {
        return match ($role) {
            'super_admin', 'super-admin' => 'superadministrador',
            'admin' => 'administrador',
            default => $role,
        };
    }

    public function isKnownInternalRole(string $role): bool
    {
        return array_key_exists($this->normalizeRole($role), config('internal_roles.labels', []))
            && ! in_array($this->normalizeRole($role), ['afiliado', 'accionista'], true);
    }

    public function permissionsForRole(?string $role): array
    {
        $role = $this->normalizeRole($role);
        if (! $role) {
            return [];
        }

        $permissions = $this->overrideFor($role)?->permissions
            ?? config("internal_roles.roles.{$role}", []);

        $permissions = array_values(array_intersect($this->catalog(), array_unique($permissions)));

        if (in_array($role, ['superadministrador', 'administrador'], true)) {
            $permissions = array_values(array_unique([...$permissions, ...self::CRITICAL_PERMISSIONS]));
        }

        return $permissions;
    }

    public function update(string $role, array $permissions, User $actor): RolePermissionOverride
    {
        $role = $this->normalizeRole($role);
        $this->ensureEditable($role, $permissions);

        $current = $this->permissionsForRole($role);
        $permissions = array_values(array_intersect($this->catalog(), array_unique($permissions)));

        if (in_array($role, ['superadministrador', 'administrador'], true)) {
            $permissions = array_values(array_unique([...$permissions, ...self::CRITICAL_PERMISSIONS]));
        }

        sort($current);
        sort($permissions);

        $override = RolePermissionOverride::query()->updateOrCreate(
            ['role' => $role],
            ['permissions' => $permissions, 'updated_by' => $actor->id]
        );

        AuditService::record('role_permissions_updated', $override, [
            'role' => $role,
            'added_permissions' => array_values(array_diff($permissions, $current)),
            'removed_permissions' => array_values(array_diff($current, $permissions)),
            'actor_id' => $actor->id,
        ]);

        return $override;
    }

    public function reset(string $role, User $actor): void
    {
        $role = $this->normalizeRole($role);
        if (! $this->isKnownInternalRole($role)) {
            throw ValidationException::withMessages(['role' => 'El rol no existe.']);
        }

        RolePermissionOverride::query()->where('role', $role)->delete();

        AuditService::record('role_permissions_reset', null, [
            'role' => $role,
            'actor_id' => $actor->id,
        ]);
    }

    private function ensureEditable(?string $role, array $permissions): void
    {
        if (! $role || ! $this->isKnownInternalRole($role)) {
            throw ValidationException::withMessages(['role' => 'El rol no existe.']);
        }

        $unknown = array_values(array_diff($permissions, $this->catalog()));
        if ($unknown !== []) {
            throw ValidationException::withMessages(['permissions' => 'La matriz contiene permisos no registrados.']);
        }

        if (in_array($role, ['superadministrador', 'administrador'], true)) {
            $missing = array_values(array_diff(self::CRITICAL_PERMISSIONS, $permissions));
            if ($missing !== []) {
                throw ValidationException::withMessages(['permissions' => 'No se pueden retirar permisos criticos del administrador principal.']);
            }
        }

        $forbidden = array_values(array_intersect($permissions, self::SENSITIVE_BY_ROLE[$role] ?? []));
        if ($forbidden !== []) {
            throw ValidationException::withMessages(['permissions' => 'El rol seleccionado no puede recibir permisos administrativos sensibles.']);
        }
    }

    private function overrideFor(string $role): ?RolePermissionOverride
    {
        if (! $this->hasOverrideTable()) {
            return null;
        }

        return RolePermissionOverride::query()->firstWhere('role', $role);
    }

    private function hasOverrideTable(): bool
    {
        try {
            return Schema::hasTable('role_permission_overrides');
        } catch (QueryException) {
            return false;
        }
    }

    private function permissionLabel(string $permission): string
    {
        return str($permission)->replace(['.', '_', '-'], ' ')->headline()->toString();
    }

    private function permissionDescription(string $permission): string
    {
        return 'Permite '.str($permission)->replace(['.', '_', '-'], ' ')->lower()->toString().'.';
    }

    private function defaultDescription(string $role): string
    {
        return match ($role) {
            'superadministrador' => 'Acceso total al sistema y a la administracion de permisos.',
            'administrador' => 'Rol historico con acceso administrativo completo.',
            'gerente' => 'Supervision operativa y consulta ejecutiva.',
            'secretaria' => 'Operacion de afiliacion, pagos y credenciales.',
            'cajero' => 'Gestion de caja y pagos.',
            'consulta' => 'Acceso de solo lectura a modulos permitidos.',
            default => 'Rol interno configurado para SIAFCO.',
        };
    }
}
