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
}
