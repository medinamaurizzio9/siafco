<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->isInternal() && $actor->hasPermission('users.view');
    }

    public function view(User $actor, User $user): bool
    {
        return $this->viewAny($actor) && $user->isInternal();
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission('users.create');
    }

    public function update(User $actor, User $user): bool
    {
        return $user->isInternal() && $actor->hasPermission('users.update')
            && $this->canManageProtectedUser($actor, $user);
    }

    public function block(User $actor, User $user): bool
    {
        return $actor->id !== $user->id && $user->isInternal() && $user->is_active
            && $actor->hasPermission('users.block')
            && $this->canManageProtectedUser($actor, $user)
            && ! $this->isLastActiveSuperAdministrator($user);
    }

    public function activate(User $actor, User $user): bool
    {
        return $user->isInternal() && ! $user->is_active
            && $actor->hasPermission('users.activate')
            && $this->canManageProtectedUser($actor, $user);
    }

    public function resetPassword(User $actor, User $user): bool
    {
        return $user->isInternal() && $actor->hasPermission('users.reset-password')
            && $this->canManageProtectedUser($actor, $user);
    }

    public function delete(User $actor, User $user): bool
    {
        return $actor->id !== $user->id && $user->isInternal()
            && $actor->hasPermission('users.delete')
            && $this->canManageProtectedUser($actor, $user)
            && ! $this->isLastActiveSuperAdministrator($user);
    }

    public function restore(User $actor, User $user): bool
    {
        return $user->isInternal() && $actor->hasPermission('users.restore')
            && $this->canManageProtectedUser($actor, $user);
    }

    public function assignRole(User $actor, User $user, string $role): bool
    {
        if (! $actor->hasPermission('users.assign-role')) {
            return false;
        }

        if ($role === 'superadministrador' && ! $this->isSuperAdministrator($actor)) {
            return false;
        }

        if ($user->exists && ! $this->canManageProtectedUser($actor, $user)) {
            return false;
        }

        return ! ($user->exists && $user->role === 'superadministrador'
            && $role !== 'superadministrador' && $this->isLastActiveSuperAdministrator($user));
    }

    private function canManageProtectedUser(User $actor, User $user): bool
    {
        return $user->role !== 'superadministrador' || $this->isSuperAdministrator($actor);
    }

    private function isSuperAdministrator(User $user): bool
    {
        return $user->hasRole(['superadministrador', 'administrador']);
    }

    private function isLastActiveSuperAdministrator(User $user): bool
    {
        return $user->role === 'superadministrador'
            && User::query()->where('user_type', 'internal')
                ->where('role', 'superadministrador')->where('is_active', true)->count() <= 1;
    }
}
