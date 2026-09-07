<?php

namespace App\Services;

use App\Models\InvestmentProspect;
use App\Models\InvestmentProspectStatusHistory;
use App\Models\User;
use App\Models\InvestmentAdvisor;
use App\Support\InvestmentProspectStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class InvestmentProspectWorkflowService
{
    private const TRANSITIONS = ['captured' => ['in_transition', 'lost'], 'in_transition' => ['closed', 'lost'], 'lost' => ['in_transition'], 'closed' => ['in_transition']];
    public function allowedTargets(InvestmentProspect $prospect, User $user): array
    {
        $targets = self::TRANSITIONS[$prospect->status] ?? [];
        if ($prospect->status === 'closed' && ! $user->hasPermission('investment_prospects.reassign')) return [];
        return $targets;
    }

    public function allowedTargetsForAdvisor(InvestmentProspect $prospect): array
    {
        return $prospect->status === 'closed' ? [] : (self::TRANSITIONS[$prospect->status] ?? []);
    }

    public function changeStatus(InvestmentProspect $prospect, User $user, string $status, ?string $reason): InvestmentProspect
    {
        Gate::forUser($user)->authorize('changeStatus', $prospect);
        if (! in_array($status, $this->allowedTargets($prospect, $user), true)) throw ValidationException::withMessages(['status' => 'La transición de estado no está permitida.']);
        if ($status === InvestmentProspectStatus::LOST && blank($reason)) throw ValidationException::withMessages(['reason' => 'Debe indicar el motivo de pérdida.']);

        return DB::transaction(function () use ($prospect, $user, $status, $reason) {
            $locked = InvestmentProspect::query()->lockForUpdate()->findOrFail($prospect->id);
            if (! in_array($status, $this->allowedTargets($locked, $user), true)) throw ValidationException::withMessages(['status' => 'El estado cambió; actualice la pantalla e intente nuevamente.']);
            $from = $locked->status;
            $locked->update(['status' => $status]);
            InvestmentProspectStatusHistory::create(['investment_prospect_id' => $locked->id, 'from_status' => $from, 'to_status' => $status, 'changed_by_user_id' => $user->id, 'changed_at' => now(), 'reason' => filled($reason) ? trim($reason) : null]);
            AuditService::record('investment_prospect_status_changed', $locked, ['prospect_id' => $locked->id, 'prospect_number' => $locked->prospect_number, 'from_status' => $from, 'to_status' => $status, 'user_id' => $user->id]);
            return $locked;
        });
    }

    public function changeStatusForAdvisor(InvestmentProspect $prospect, InvestmentAdvisor $advisor, string $status, ?string $reason): InvestmentProspect
    {
        abort_unless($prospect->current_advisor_id === $advisor->id, 404);
        return $this->persistStatus($prospect, null, $status, $reason, false);
    }

    private function persistStatus(InvestmentProspect $prospect, ?User $user, string $status, ?string $reason, bool $mayReopen): InvestmentProspect
    {
        $targets = self::TRANSITIONS[$prospect->status] ?? [];
        if ($prospect->status === 'closed' && ! $mayReopen) $targets=[];
        if (!in_array($status,$targets,true)) throw ValidationException::withMessages(['status'=>'La transición de estado no está permitida.']);
        if ($status===InvestmentProspectStatus::LOST && blank($reason)) throw ValidationException::withMessages(['reason'=>'Debe indicar el motivo de pérdida.']);
        return DB::transaction(function() use($prospect,$user,$status,$reason,$mayReopen){ $locked=InvestmentProspect::lockForUpdate()->findOrFail($prospect->id); $targets=self::TRANSITIONS[$locked->status]??[]; if($locked->status==='closed'&&!$mayReopen)$targets=[]; if(!in_array($status,$targets,true)) throw ValidationException::withMessages(['status'=>'El estado cambió; actualice la pantalla e intente nuevamente.']); $from=$locked->status; $locked->update(['status'=>$status]); InvestmentProspectStatusHistory::create(['investment_prospect_id'=>$locked->id,'from_status'=>$from,'to_status'=>$status,'changed_by_user_id'=>$user?->id,'changed_at'=>now(),'reason'=>filled($reason)?trim($reason):null]); AuditService::record('investment_prospect_status_changed',$locked,['prospect_id'=>$locked->id,'prospect_number'=>$locked->prospect_number,'from_status'=>$from,'to_status'=>$status,'user_id'=>$user?->id]); return $locked; });
    }
}
