<?php

namespace App\Policies;

use App\Models\InvestmentProspect;
use App\Models\User;
use App\Services\InvestmentCrmAccessService;

class InvestmentProspectPolicy
{
    public function viewAny(User $user): bool { return $user->hasPermission('investment_prospects.view'); }
    public function create(User $user): bool { return $user->hasPermission('investment_prospects.create'); }
    public function view(User $user, InvestmentProspect $prospect): bool { return $this->ownsOrManages($user, $prospect) && $user->hasPermission('investment_prospects.view'); }
    public function update(User $user, InvestmentProspect $prospect): bool { return $this->ownsOrManages($user, $prospect) && $user->hasPermission('investment_prospects.update'); }
    public function changeStatus(User $user, InvestmentProspect $prospect): bool { return $this->update($user, $prospect); }
    public function viewInteractions(User $user, InvestmentProspect $prospect): bool { return $this->ownsOrManages($user, $prospect) && $user->hasPermission('investment_prospect_interactions.view'); }
    public function createInteraction(User $user, InvestmentProspect $prospect): bool { return $this->ownsOrManages($user, $prospect) && $user->hasPermission('investment_prospect_interactions.create'); }
    public function reassign(User $user, InvestmentProspect $prospect): bool { return $this->ownsOrManages($user, $prospect) && $user->hasPermission('investment_prospects.reassign'); }

    private function ownsOrManages(User $user, InvestmentProspect $prospect): bool
    {
        if ($user->hasRole(['superadministrador', 'administrador', 'gerente'])) return true;
        return app(InvestmentCrmAccessService::class)->advisorFor($user)?->id === $prospect->current_advisor_id;
    }
}
