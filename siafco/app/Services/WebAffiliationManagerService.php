<?php

namespace App\Services;

use App\Models\InstitutionalSetting;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class WebAffiliationManagerService
{
    public function current(): ?User
    {
        $id = InstitutionalSetting::current()->web_affiliation_manager_id;

        return $id ? $this->authorizedUsers()->whereKey($id)->first() : null;
    }

    public function authorizedUsers(): Builder
    {
        return User::query()
            ->where('is_active', true)
            ->where(fn (Builder $query) => $query->where('user_type', 'internal')->orWhereNull('user_type'))
            ->whereIn('role', ['superadministrador', 'administrador', 'gerente', 'secretaria', 'administrador_sector'])
            ->orderBy('name');
    }
}
