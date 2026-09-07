<?php

namespace App\Services;

use App\Models\InvestmentAdvisor;
use App\Models\InvestmentProspect;
use App\Models\InvestmentProspectAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class InvestmentProspectAssignmentService
{
    public function reassign(InvestmentProspect $prospect, InvestmentAdvisor $advisor, User $user, ?string $reason): InvestmentProspect
    {
        Gate::forUser($user)->authorize('reassign', $prospect);
        if (! $advisor->isActive()) throw ValidationException::withMessages(['advisor_id' => 'El asesor seleccionado no está activo.']);
        return DB::transaction(function () use ($prospect, $advisor, $user, $reason) {
            $locked = InvestmentProspect::query()->lockForUpdate()->findOrFail($prospect->id);
            if ($locked->current_advisor_id === $advisor->id) throw ValidationException::withMessages(['advisor_id' => 'El prospecto ya está asignado a ese asesor.']);
            $from = $locked->current_advisor_id;
            $locked->update(['current_advisor_id' => $advisor->id]);
            InvestmentProspectAssignment::create(['investment_prospect_id' => $locked->id, 'from_advisor_id' => $from, 'to_advisor_id' => $advisor->id, 'assigned_by_user_id' => $user->id, 'assigned_at' => now(), 'reason' => filled($reason) ? trim($reason) : null]);
            AuditService::record('investment_prospect_reassigned', $locked, ['prospect_id' => $locked->id, 'prospect_number' => $locked->prospect_number, 'from_advisor_id' => $from, 'to_advisor_id' => $advisor->id]);
            return $locked;
        });
    }
}
