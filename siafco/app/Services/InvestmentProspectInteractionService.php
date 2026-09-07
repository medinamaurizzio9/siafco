<?php

namespace App\Services;

use App\Models\InvestmentProspect;
use App\Models\InvestmentProspectInteraction;
use App\Models\User;
use App\Models\InvestmentAdvisor;
use App\Support\InvestmentCrmDate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class InvestmentProspectInteractionService
{
    public function createInteraction(InvestmentProspect $prospect, User $user, array $data): InvestmentProspectInteraction
    {
        Gate::forUser($user)->authorize('createInteraction', $prospect);
        $occurredAt = InvestmentCrmDate::fromInput($data['occurred_at'] ?? null) ?? now();
        $followUp = InvestmentCrmDate::fromInput($data['next_follow_up_at'] ?? null);
        if ($occurredAt->isFuture()) throw ValidationException::withMessages(['occurred_at' => 'La interacción no puede registrarse en el futuro.']);
        if ($followUp && ! $followUp->isFuture()) throw ValidationException::withMessages(['next_follow_up_at' => 'El próximo seguimiento debe ser futuro.']);

        return DB::transaction(function () use ($prospect, $user, $data, $occurredAt, $followUp) {
            $locked = InvestmentProspect::query()->lockForUpdate()->findOrFail($prospect->id);
            $advisor = app(InvestmentCrmAccessService::class)->advisorFor($user) ?? $locked->currentAdvisor;
            $interaction = InvestmentProspectInteraction::create(['investment_prospect_id' => $locked->id, 'advisor_id' => $advisor?->id, 'user_id' => $user->id, 'type' => $data['type'], 'channel' => $data['channel'] ?? null, 'notes' => filled($data['notes'] ?? null) ? trim($data['notes']) : null, 'occurred_at' => $occurredAt, 'next_follow_up_at' => $followUp]);
            $locked->update(['last_interaction_at' => $occurredAt, 'next_follow_up_at' => $followUp]);
            AuditService::record('investment_prospect_interaction_created', $locked, ['prospect_id' => $locked->id, 'prospect_number' => $locked->prospect_number, 'interaction_id' => $interaction->id, 'type' => $interaction->type, 'channel' => $interaction->channel]);
            return $interaction;
        });
    }

    public function createInteractionForAdvisor(InvestmentProspect $prospect, InvestmentAdvisor $advisor, array $data): InvestmentProspectInteraction
    {
        abort_unless($prospect->current_advisor_id === $advisor->id, 404);
        $occurredAt=InvestmentCrmDate::fromInput($data['occurred_at']??null)??now(); $followUp=InvestmentCrmDate::fromInput($data['next_follow_up_at']??null);
        if($occurredAt->isFuture()) throw ValidationException::withMessages(['occurred_at'=>'La interacción no puede registrarse en el futuro.']);
        if($followUp&&!$followUp->isFuture()) throw ValidationException::withMessages(['next_follow_up_at'=>'El próximo seguimiento debe ser futuro.']);
        return DB::transaction(function() use($prospect,$advisor,$data,$occurredAt,$followUp){ $locked=InvestmentProspect::lockForUpdate()->findOrFail($prospect->id); abort_unless($locked->current_advisor_id===$advisor->id,404); $interaction=InvestmentProspectInteraction::create(['investment_prospect_id'=>$locked->id,'advisor_id'=>$advisor->id,'user_id'=>null,'type'=>$data['type'],'channel'=>$data['channel']??null,'notes'=>filled($data['notes']??null)?trim($data['notes']):null,'occurred_at'=>$occurredAt,'next_follow_up_at'=>$followUp]); $locked->update(['last_interaction_at'=>$occurredAt,'next_follow_up_at'=>$followUp]); AuditService::record('investment_prospect_interaction_created',$locked,['prospect_id'=>$locked->id,'prospect_number'=>$locked->prospect_number,'interaction_id'=>$interaction->id,'type'=>$interaction->type,'channel'=>$interaction->channel]); return $interaction; });
    }
}
