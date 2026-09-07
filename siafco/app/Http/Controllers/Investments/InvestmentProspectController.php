<?php

namespace App\Http\Controllers\Investments;

use App\Http\Controllers\Controller;
use App\Models\InvestmentAdvisor;
use App\Models\InvestmentProspect;
use App\Support\InvestmentProspectStatus;
use App\Support\InvestmentCrmDate;
use App\Support\InvestmentProspectServiceRating;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use App\Services\InvestmentCrmAccessService;
use App\Http\Requests\Investments\StoreInvestmentProspectAdminRequest;
use App\Services\InvestmentProspectService;

class InvestmentProspectController extends Controller
{
    public function create()
    {
        Gate::authorize('create', InvestmentProspect::class);

        return view('investments.prospects.create', [
            'advisors' => InvestmentAdvisor::where('is_active', true)->orderBy('advisor_number')->get(),
        ]);
    }

    public function store(StoreInvestmentProspectAdminRequest $request, InvestmentProspectService $service)
    {
        Gate::authorize('create', InvestmentProspect::class);
        $data = $request->validated();
        $advisor = InvestmentAdvisor::where('is_active', true)->findOrFail($data['advisor_id']);
        unset($data['advisor_id']);
        $result = $service->createManual($advisor, $data);

        return redirect()->route('investments.prospects.show', $result['prospect'])
            ->with('status', $result['status'] === 'existing' ? 'Este número ya se encuentra registrado en el CRM.' : "Prospecto registrado: {$result['prospect']->prospect_number}.");
    }

    public function index(Request $request)
    {
        Gate::authorize('viewAny', InvestmentProspect::class);
        $status = in_array($request->status, InvestmentProspectStatus::values(), true)
            ? $request->status
            : null;
        $followUp = in_array($request->follow_up, ['pending', 'today', 'overdue', 'none'], true)
            ? $request->follow_up
            : null;
        $rating = in_array($request->service_rating, [...InvestmentProspectServiceRating::values(), 'none'], true)
            ? $request->service_rating
            : null;
        [$todayStart, $todayEnd] = InvestmentCrmDate::dayBounds();
        $capturedFrom = $request->filled('from') ? InvestmentCrmDate::dayBounds($request->string('from')->toString())[0] : null;
        $capturedTo = $request->filled('to') ? InvestmentCrmDate::dayBounds($request->string('to')->toString())[1] : null;
        $interactionFrom = $request->filled('interaction_from') ? InvestmentCrmDate::dayBounds($request->string('interaction_from')->toString())[0] : null;
        $interactionTo = $request->filled('interaction_to') ? InvestmentCrmDate::dayBounds($request->string('interaction_to')->toString())[1] : null;

        $prospects = app(InvestmentCrmAccessService::class)->scopeProspects(InvestmentProspect::query(), $request->user())
            ->with('currentAdvisor')
            ->when($request->search, fn ($query, $search) => $query
                ->where(fn ($filtered) => $filtered
                    ->where('prospect_number', 'like', "%{$search}%")
                    ->orWhere('full_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhereHas('currentAdvisor', fn ($advisor) => $advisor
                        ->where('advisor_number', 'like', "%{$search}%")
                        ->orWhere('full_name', 'like', "%{$search}%"))))
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($rating === 'none', fn ($query) => $query->whereNull('service_rating'))
            ->when($rating && $rating !== 'none', fn ($query) => $query->where('service_rating', $rating))
            ->when($request->filled('advisor_id'), fn ($query) => $query->where('current_advisor_id', $request->integer('advisor_id')))
            ->when($capturedFrom, fn ($query) => $query->where('captured_at', '>=', $capturedFrom))
            ->when($capturedTo, fn ($query) => $query->where('captured_at', '<=', $capturedTo))
            ->when($followUp === 'pending', fn ($query) => $query->where('next_follow_up_at', '>', $todayEnd))
            ->when($followUp === 'today', fn ($query) => $query->whereBetween('next_follow_up_at', [$todayStart, $todayEnd]))
            ->when($followUp === 'overdue', fn ($query) => $query->where('next_follow_up_at', '<', now()))
            ->when($followUp === 'none', fn ($query) => $query->whereNull('next_follow_up_at'))
            ->when($interactionFrom, fn ($query) => $query->where('last_interaction_at', '>=', $interactionFrom))
            ->when($interactionTo, fn ($query) => $query->where('last_interaction_at', '<=', $interactionTo))
            ->latest('captured_at')
            ->paginate(15)
            ->withQueryString();

        return view('investments.prospects.index', [
            'prospects' => $prospects,
            'advisors' => InvestmentAdvisor::query()->orderBy('advisor_number')->get(['id', 'advisor_number', 'full_name']),
            'statuses' => InvestmentProspectStatus::labels(),
            'ratings' => InvestmentProspectServiceRating::labels(),
            'isAdvisorView' => $request->user()->hasRole('asesor_inversiones'),
        ]);
    }

    public function show(InvestmentProspect $prospect)
    {
        Gate::authorize('view', $prospect);
        return view('investments.prospects.show', [
            'prospect' => $prospect->load(['originalAdvisor','currentAdvisor','interactions'=>fn($q)=>$q->with('advisor','user')->latest('occurred_at'),'statusHistory'=>fn($q)=>$q->with('user')->latest('changed_at'),'assignments'=>fn($q)=>$q->with('fromAdvisor','toAdvisor','assignedBy')->latest('assigned_at')]),
            'advisors' => InvestmentAdvisor::where('is_active',true)->orderBy('advisor_number')->get(),
            'types' => \App\Support\InvestmentProspectInteractionType::types(),
            'channels' => \App\Support\InvestmentProspectInteractionType::channels(),
        ]);
    }
}
