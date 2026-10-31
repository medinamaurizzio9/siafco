<?php

namespace App\Policies;

use App\Models\SimpleInvestmentInvestor;
use App\Models\User;

class SimpleInvestmentInvestorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('simple_investments.investors.view');
    }

    public function view(User $user, SimpleInvestmentInvestor $investor): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('simple_investments.investors.manage');
    }

    public function update(User $user, SimpleInvestmentInvestor $investor): bool
    {
        return $user->hasPermission('simple_investments.investors.manage');
    }
}
