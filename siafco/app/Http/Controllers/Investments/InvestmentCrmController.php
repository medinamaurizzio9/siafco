<?php
namespace App\Http\Controllers\Investments;
use App\Http\Controllers\Controller;
use App\Models\InvestmentProspect;
use App\Services\InvestmentCrmAccessService;
use App\Support\InvestmentProspectStatus;
use App\Support\InvestmentCrmDate;
use App\Support\InvestmentProspectServiceRating;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
class InvestmentCrmController extends Controller
{
    private function query(Request $request) { Gate::authorize('viewAny', InvestmentProspect::class); return app(InvestmentCrmAccessService::class)->scopeProspects(InvestmentProspect::query(), $request->user()); }
    public function dashboard(Request $request) { $q=$this->query($request); [$start,$end]=InvestmentCrmDate::dayBounds(); $quality=collect(InvestmentProspectServiceRating::labels())->mapWithKeys(fn($label,$rating)=>[$rating=>['label'=>$label,'count'=>(clone $q)->where('service_rating',$rating)->count()]]); $rated=$quality->sum('count'); return view('investments.crm.dashboard',['counts'=>['total'=>(clone $q)->whereNotIn('status',['closed','lost'])->count(),'captured'=>(clone $q)->where('status','captured')->count(),'in_transition'=>(clone $q)->where('status','in_transition')->count(),'closed'=>(clone $q)->where('status','closed')->count(),'lost'=>(clone $q)->where('status','lost')->count(),'today'=>(clone $q)->whereBetween('captured_at',[$start,$end])->count(),'follow_today'=>(clone $q)->whereBetween('next_follow_up_at',[$start,$end])->count(),'overdue'=>(clone $q)->where('next_follow_up_at','<',now())->count()],'quality'=>$quality->map(fn($item)=>$item+['percentage'=>$rated?round($item['count']*100/$rated):0])]); }
    public function kanban(Request $request) { $prospects=$this->query($request)->with('currentAdvisor')->latest('captured_at')->get()->groupBy('status'); return view('investments.crm.kanban',['prospects'=>$prospects,'statuses'=>InvestmentProspectStatus::labels()]); }
}
