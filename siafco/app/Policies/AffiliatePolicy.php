<?php

namespace App\Policies;

use App\Models\Affiliate;
use App\Models\User;

class AffiliatePolicy
{
    public function viewCredential(User $user, Affiliate $affiliate): bool
    {
        if ($affiliate->status !== 'activo' || ($affiliate->credential && ! $affiliate->credential->isActive())) {
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
        return $user->hasPermission('affiliates.soft_delete')
            || $user->hasPermission('affiliates.delete');
    }

    public function restore(User $user, Affiliate $affiliate): bool
    {
        return $affiliate->trashed() && $user->hasPermission('affiliates.restore');
    }

    public function updatePersonal(User $user, Affiliate $affiliate): bool
    {
        return ! $affiliate->trashed() && $user->hasPermission('affiliates.update_personal');
    }

    public function updateInstitutional(User $user, Affiliate $affiliate): bool
    {
        return ! $affiliate->trashed() && $user->hasPermission('affiliates.update_institutional');
    }

    public function changeSector(User $user, Affiliate $affiliate): bool
    {
        return ! $affiliate->trashed() && $user->hasPermission('affiliates.change_sector');
    }

    public function changePlan(User $user, Affiliate $affiliate): bool
    {
        return ! $affiliate->trashed() && $user->hasPermission('affiliates.change_plan');
    }

    public function changeStatus(User $user, Affiliate $affiliate): bool
    {
        return ! $affiliate->trashed() && $user->hasPermission('affiliates.change_status');
    }

    public function managePhoto(User $user, Affiliate $affiliate): bool
    {
        return ! $affiliate->trashed() && $user->hasPermission('affiliates.manage_photo');
    }

    public function manageCredential(User $user, Affiliate $affiliate): bool
    {
        return ! $affiliate->trashed() && $user->hasPermission('affiliates.manage_credential');
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
