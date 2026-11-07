<?php

namespace App\Policies;

use App\Models\User;

class CommunicationPolicy
{
    public function manage(User $user): bool
    {
        return $user->is_active && $user->hasPermission('communications.manage');
    }

    public function publish(User $user): bool
    {
        return $this->manage($user) && $user->hasPermission('communications.publish');
    }

    public function report(User $user): bool
    {
        return $this->manage($user) && $user->hasPermission('communications.report');
    }
}
