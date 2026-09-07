<?php

namespace App\Services;

use App\Models\InvestmentAdvisor;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class InvestmentCrmAccessService
{
    public function scopeProspects(Builder $query, User $user): Builder
    {
        if ($user->hasRole(['superadministrador', 'administrador', 'gerente'])) {
            return $query;
        }

        return $query->where('current_advisor_id', $this->advisorFor($user)?->id ?? 0);
    }

    public function advisorFor(User $user): ?InvestmentAdvisor
    {
        return InvestmentAdvisor::query()->where('user_id', $user->id)->where('is_active', true)->first();
    }
}
