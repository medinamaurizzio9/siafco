<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateRolePermissionsRequest;
use App\Services\AuditService;
use App\Services\RolePermissionService;
use Illuminate\Http\Request;

class RolePermissionController extends Controller
{
    public function __construct(private readonly RolePermissionService $roles) {}

    public function index(Request $request)
    {
        abort_unless($request->user()?->hasPermission('roles.view'), 403);

        AuditService::record('role_user_count_viewed', null, [
            'roles' => $this->roles->roles()->pluck('key')->all(),
        ]);

        return view('administration.roles.index', [
            'title' => 'Roles y permisos',
            'roles' => $this->roles->roles(),
        ]);
    }

    public function edit(Request $request, string $role)
    {
        abort_unless($request->user()?->hasPermission('roles.view'), 403);

        $role = $this->roles->normalizeRole($role);
        abort_unless($this->roles->isKnownInternalRole($role), 404);

        return view('administration.roles.edit', [
            'title' => 'Editar permisos',
            'role' => $role,
            'roleLabel' => config("internal_roles.labels.{$role}", $role),
            'groups' => $this->roles->groupedCatalog(),
            'assigned' => $this->roles->permissionsForRole($role),
            'canUpdate' => $request->user()->hasPermission('roles.update'),
            'protected' => in_array($role, ['superadministrador', 'administrador'], true)
                ? ['dashboard.view', 'roles.view', 'roles.update', 'audit.view']
                : [],
        ]);
    }

    public function update(UpdateRolePermissionsRequest $request, string $role)
    {
        $role = $this->roles->normalizeRole($role);
        $this->roles->update($role, $request->permissions(), $request->user());

        return redirect()->route('administration.roles.edit', $role)
            ->with('status', 'Permisos actualizados correctamente.');
    }

    public function reset(Request $request, string $role)
    {
        abort_unless($request->user()?->hasPermission('roles.update'), 403);

        $this->roles->reset($role, $request->user());

        return redirect()->route('administration.roles.edit', $this->roles->normalizeRole($role))
            ->with('status', 'Permisos restaurados a la configuracion base.');
    }
}
