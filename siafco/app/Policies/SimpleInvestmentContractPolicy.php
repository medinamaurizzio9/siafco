<?php

namespace App\Policies;

use App\Models\SimpleInvestmentContract;
use App\Models\User;

class SimpleInvestmentContractPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('simple_investments.contracts.view');
    }

    public function view(User $user, SimpleInvestmentContract $contract): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('simple_investments.contracts.create');
    }

    public function transition(User $user, SimpleInvestmentContract $contract): bool
    {
        return $user->hasPermission('simple_investments.contracts.transition');
    }

    public function viewDocument(User $user, SimpleInvestmentContract $contract): bool
    {
        return $user->hasPermission('simple_investments.documents.view');
    }

    public function uploadDocument(User $user, SimpleInvestmentContract $contract): bool
    {
        return $user->hasPermission('simple_investments.documents.upload');
    }

    public function verifyDocument(User $user, SimpleInvestmentContract $contract): bool
    {
        return $user->hasPermission('simple_investments.documents.verify');
    }
}
