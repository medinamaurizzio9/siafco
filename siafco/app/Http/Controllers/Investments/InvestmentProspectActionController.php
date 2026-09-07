<?php
namespace App\Http\Controllers\Investments;
use App\Http\Controllers\Controller;
use App\Models\InvestmentAdvisor;
use App\Models\InvestmentProspect;
use App\Services\InvestmentProspectAssignmentService;
use App\Services\InvestmentProspectInteractionService;
use App\Services\InvestmentProspectWorkflowService;
use App\Support\InvestmentProspectInteractionType;
use App\Support\InvestmentProspectStatus;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
class InvestmentProspectActionController extends Controller
{
    public function status(Request $r, InvestmentProspect $prospect, InvestmentProspectWorkflowService $s) { $d=$r->validate(['status'=>['required',Rule::in(InvestmentProspectStatus::values())],'reason'=>['nullable','string','max:1000']]); $updated=$s->changeStatus($prospect,$r->user(),$d['status'],$d['reason']??null); if($r->expectsJson()) return response()->json(['ok'=>true,'status'=>$updated->status,'label'=>InvestmentProspectStatus::label($updated->status),'allowed_targets'=>$s->allowedTargets($updated,$r->user())]); return back()->with('status','Estado del prospecto actualizado.'); }
    public function interaction(Request $r, InvestmentProspect $prospect, InvestmentProspectInteractionService $s) { $d=$r->validate(['type'=>['required',Rule::in(array_keys(InvestmentProspectInteractionType::types()))],'channel'=>['nullable',Rule::in(array_keys(InvestmentProspectInteractionType::channels()))],'notes'=>['nullable','string','max:3000'],'occurred_at'=>['nullable','date_format:Y-m-d\TH:i'],'next_follow_up_at'=>['nullable','date_format:Y-m-d\TH:i']]); $s->createInteraction($prospect,$r->user(),$d); return back()->with('status','Interacción registrada.'); }
    public function reassign(Request $r, InvestmentProspect $prospect, InvestmentProspectAssignmentService $s) { $d=$r->validate(['advisor_id'=>['required','exists:investment_advisors,id'],'reason'=>['nullable','string','max:1000']]); $s->reassign($prospect,InvestmentAdvisor::findOrFail($d['advisor_id']),$r->user(),$d['reason']??null); return back()->with('status','Prospecto reasignado.'); }
}
