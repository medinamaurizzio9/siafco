<?php

namespace App\Policies;

use App\Models\Affiliate;
use App\Models\User;

class AffiliatePolicy
{
    public function viewCredential(User $user, Affiliate $affiliate): bool
    {
        if ($affiliate->status !== 'activo') {
            return false;
        }

        return $user->hasRole(['administrador', 'superadministrador', 'secretaria'])
            || ($user->role === 'afiliado' && $user->id === $affiliate->user_id);
    }

    public function downloadCredential(User $user, Affiliate $affiliate): bool
    {
        return $affiliate->status === 'activo'
            && $user->hasRole(['administrador', 'superadministrador', 'secretaria']);
    }

    public function printCredential(User $user, Affiliate $affiliate): bool
    {
        return $this->downloadCredential($user, $affiliate);
    }

    public function delete(User $user, Affiliate $affiliate): bool
    {
        return $user->hasRole(['administrador', 'superadministrador']);
    }

    public function resetPassword(User $user, Affiliate $affiliate): bool
    {
        return ! $affiliate->trashed()
            && $this->canManageAffiliateAccess($user, $affiliate, 'affiliate_access.reset_password');
    }

    public function viewAccess(User $user, Affiliate $affiliate): bool
    {
        return ! $affiliate->trashed()
            && $this->canManageAffiliateAccess($user, $affiliate, 'affiliate_access.view');
    }

    public function blockAccess(User $user, Affiliate $affiliate): bool
    {
        return ! $affiliate->trashed()
            && $affiliate->user?->is_active
            && $this->canManageAffiliateAccess($user, $affiliate, 'affiliate_access.block');
    }

    public function activateAccess(User $user, Affiliate $affiliate): bool
    {
        return ! $affiliate->trashed()
            && $affiliate->user
            && ! $affiliate->user->is_active
            && $this->canManageAffiliateAccess($user, $affiliate, 'affiliate_access.activate');
    }

    public function revokeSessions(User $user, Affiliate $affiliate): bool
    {
        return ! $affiliate->trashed()
            && $affiliate->user
            && $this->canManageAffiliateAccess($user, $affiliate, 'affiliate_access.revoke_sessions');
    }

    private function canManageAffiliateAccess(User $user, Affiliate $affiliate, string $permission): bool
    {
        return ($user->isInternal() || ($user->user_type === null && $user->role !== 'afiliado'))
            && $user->hasPermission($permission)
            && ! ($affiliate->user && $affiliate->user->role === 'superadministrador');
    }
}
