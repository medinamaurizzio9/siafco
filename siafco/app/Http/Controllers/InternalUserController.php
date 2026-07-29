<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInternalUserRequest;
use App\Http\Requests\UpdateInternalUserRequest;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditService;
use App\Services\InternalUserService;
use App\Services\UserAccessService;
use App\Services\UserPasswordService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class InternalUserController extends Controller
{
    public function __construct(
        private readonly InternalUserService $users,
        private readonly UserAccessService $access,
        private readonly UserPasswordService $passwords,
    ) {}

    public function index(Request $request)
    {
        Gate::authorize('viewAny', User::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'role' => ['nullable', 'string', Rule::in(array_keys(config('internal_roles.labels')))],
            'status' => ['nullable', Rule::in(['active', 'blocked', 'deleted'])],
        ]);

        $base = User::query()->where('user_type', 'internal');
        $query = (clone $base)->when(($filters['status'] ?? null) === 'deleted', fn ($q) => $q->onlyTrashed());

        if (($filters['status'] ?? null) !== 'deleted') {
            $query->when(($filters['status'] ?? null) === 'active', fn ($q) => $q->where('is_active', true))
                ->when(($filters['status'] ?? null) === 'blocked', fn ($q) => $q->where('is_active', false));
        }

        $query->when($filters['search'] ?? null, function ($q, $search) {
            $q->where(function ($nested) use ($search) {
                $nested->where('name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('ci', 'like', "%{$search}%");
            });
        })->when($filters['role'] ?? null, fn ($q, $role) => $q->where('role', $role));

        return view('internal-users.index', [
            'users' => $query->latest()->paginate(15)->withQueryString(),
            'filters' => $filters,
            'roles' => config('internal_roles.labels'),
            'metrics' => [
                'active' => (clone $base)->where('is_active', true)->count(),
                'blocked' => (clone $base)->where('is_active', false)->count(),
                'deleted' => User::onlyTrashed()->where('user_type', 'internal')->count(),
                'recent' => (clone $base)->where('last_login_at', '>=', now()->subDays(30))->count(),
            ],
            'roleCounts' => (clone $base)->selectRaw('role, count(*) as total')->groupBy('role')->pluck('total', 'role'),
        ]);
    }

    public function create()
    {
        Gate::authorize('create', User::class);

        return view('internal-users.form', [
            'internalUser' => new User(['is_active' => true]),
            'roles' => $this->assignableRoles(),
            'areas' => $this->areas(),
        ]);
    }

    public function store(StoreInternalUserRequest $request)
    {
        $user = $this->users->create($request->validated(), $request->file('photo'));
        AuditService::record('internal_user_created', $user, ['role_new' => $user->role, 'result' => 'success']);

        return redirect()->route('admin.users.show', $user)->with('status', 'Usuario interno creado correctamente.');
    }

    public function show(User $user)
    {
        Gate::authorize('view', $user);

        return view('internal-users.show', [
            'internalUser' => $user,
            'history' => AuditLog::query()->where('auditable_type', User::class)
                ->where('auditable_id', $user->id)->latest()->limit(25)->get(),
        ]);
    }

    public function edit(User $user)
    {
        Gate::authorize('update', $user);

        return view('internal-users.form', [
            'internalUser' => $user,
            'roles' => $this->assignableRoles($user),
            'areas' => $this->areas(),
        ]);
    }

    public function update(UpdateInternalUserRequest $request, User $user)
    {
        $oldRole = $user->role;
        $changes = $this->users->update($user, $request->validated(), $request->file('photo'));

        AuditService::record('internal_user_updated', $user, ['fields' => $changes, 'result' => 'success']);
        if ($oldRole !== $user->fresh()->role) {
            AuditService::record('internal_user_role_changed', $user, [
                'role_old' => $oldRole, 'role_new' => $user->role, 'result' => 'success',
            ]);
        }

        return redirect()->route('admin.users.show', $user)->with('status', 'Usuario actualizado correctamente.');
    }

    public function block(Request $request, User $user)
    {
        Gate::authorize('block', $user);
        DB::transaction(function () use ($user) {
            $this->access->block($user);
            AuditService::record('internal_user_blocked', $user, ['result' => 'success']);
        });

        return back()->with('status', 'El usuario fue bloqueado y sus sesiones se cerraron.');
    }

    public function activate(User $user)
    {
        Gate::authorize('activate', $user);
        DB::transaction(function () use ($user) {
            $this->access->activate($user);
            AuditService::record('internal_user_activated', $user, ['result' => 'success']);
        });

        return back()->with('status', 'El usuario fue activado.');
    }

    public function resetPassword(Request $request, User $user)
    {
        Gate::authorize('resetPassword', $user);
        $request->validate(['confirmation' => ['required', 'in:RESTABLECER']]);

        DB::transaction(function () use ($user) {
            $this->passwords->resetToCi($user);
            $this->access->invalidateSessions($user);
            AuditService::record('internal_user_password_reset', $user, ['result' => 'success']);
        });

        return back()->with('status', 'Contraseña restablecida al CI. El usuario deberá cambiarla al ingresar.');
    }

    public function destroy(Request $request, User $user)
    {
        Gate::authorize('delete', $user);
        $request->validate(['confirmation' => ['required', 'in:ELIMINAR']]);
        $this->users->delete($user);
        AuditService::record('internal_user_deleted', $user, ['result' => 'success']);

        return redirect()->route('admin.users.index')->with('status', 'Usuario eliminado de forma lógica.');
    }

    public function restore(Request $request, int $user)
    {
        $target = User::onlyTrashed()->where('user_type', 'internal')->findOrFail($user);
        Gate::authorize('restore', $target);
        $this->users->restore($target);
        AuditService::record('internal_user_restored', $target, ['result' => 'success']);

        return redirect()->route('admin.users.show', $target)->with('status', 'Usuario restaurado correctamente.');
    }

    private function assignableRoles(?User $target = null): array
    {
        $roles = config('internal_roles.assignable');
        if (! request()->user()->hasRole(['superadministrador', 'administrador'])) {
            $roles = array_diff($roles, ['superadministrador']);
        }
        if ($target && ! in_array($target->role, $roles, true)) {
            $roles[] = $target->role;
        }

        return collect($roles)->mapWithKeys(fn ($role) => [$role => config("internal_roles.labels.{$role}", $role)])->all();
    }

    private function areas(): array
    {
        return ['Gerencia', 'Secretaría', 'Caja', 'Administración', 'Créditos', 'Sistemas'];
    }
}
