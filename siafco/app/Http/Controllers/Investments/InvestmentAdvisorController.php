<?php

namespace App\Http\Controllers\Investments;

use App\Http\Controllers\Controller;
use App\Http\Requests\Investments\StoreInvestmentAdvisorRequest;
use App\Http\Requests\Investments\UpdateInvestmentAdvisorRequest;
use App\Models\InvestmentAdvisor;
use App\Models\User;
use App\Services\InvestmentAdvisorService;
use App\Services\InvestmentAdvisorAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Support\InvestmentProspectServiceRating;

class InvestmentAdvisorController extends Controller
{
    public function index(Request $request)
    {
        $advisors = InvestmentAdvisor::query()
            ->with('user')
            ->when($request->search, fn ($query, $search) => $query
                ->where(fn ($filtered) => $filtered
                    ->where('advisor_number', 'like', "%{$search}%")
                    ->orWhere('full_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")))
            ->when($request->status !== null && $request->status !== '', fn ($query) => $query->where('is_active', $request->boolean('status')))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('investments.advisors.index', compact('advisors'));
    }

    public function create()
    {
        return view('investments.advisors.form', [
            'advisor' => new InvestmentAdvisor(),
            'users' => $this->availableUsers(),
        ]);
    }

    public function store(StoreInvestmentAdvisorRequest $request, InvestmentAdvisorService $service)
    {
        $advisor = $service->create($request->validated());
        $credentials = $service->pullAccessCredentials();

        return redirect()->route('investments.advisors.show', $advisor)->with('status', 'Asesor de inversión registrado.')->with('crm_credentials', ['login_code'=>$credentials['access']->login_code,'pin'=>$credentials['pin']]);
    }

    public function show(InvestmentAdvisor $advisor)
    {
        $quality = collect(InvestmentProspectServiceRating::labels())
            ->mapWithKeys(fn ($label, $rating) => [$rating => [
                'label' => $label,
                'count' => $advisor->originalProspects()->where('service_rating', $rating)->count(),
            ]]);

        return view('investments.advisors.show', [
            'advisor' => $advisor->load('user', 'createdBy', 'access'),
            'publicUrl' => route('investments.advisors.public', ['token' => $advisor->public_token]),
            'qrUrl' => Storage::disk('public')->url($advisor->qrPath()),
            'quality' => $quality,
            'unratedCount' => $advisor->originalProspects()->whereNull('service_rating')->count(),
        ]);
    }

    public function generateAccess(InvestmentAdvisor $advisor, InvestmentAdvisorAccessService $service) { $result=$service->createAccess($advisor); return back()->with('status','Acceso CRM generado.')->with('crm_credentials',['login_code'=>$result['access']->login_code,'pin'=>$result['pin']]); }
    public function resetPin(InvestmentAdvisor $advisor, InvestmentAdvisorAccessService $service) { abort_unless($advisor->access,404); $result=$service->resetPin($advisor->access); return back()->with('status','PIN restablecido.')->with('crm_credentials',['login_code'=>$result['access']->login_code,'pin'=>$result['pin']]); }
    public function enableAccess(InvestmentAdvisor $advisor, InvestmentAdvisorAccessService $service) { abort_unless($advisor->access,404); $service->enable($advisor->access); return back()->with('status','Acceso CRM activado.'); }
    public function disableAccess(InvestmentAdvisor $advisor, InvestmentAdvisorAccessService $service) { abort_unless($advisor->access,404); $service->disable($advisor->access); return back()->with('status','Acceso CRM desactivado.'); }
    public function unlockAccess(InvestmentAdvisor $advisor, InvestmentAdvisorAccessService $service) { abort_unless($advisor->access,404); $service->unlock($advisor->access); return back()->with('status','Acceso CRM desbloqueado.'); }

    public function edit(InvestmentAdvisor $advisor)
    {
        return view('investments.advisors.form', [
            'advisor' => $advisor,
            'users' => $this->availableUsers($advisor),
        ]);
    }

    public function update(UpdateInvestmentAdvisorRequest $request, InvestmentAdvisor $advisor, InvestmentAdvisorService $service)
    {
        $service->update($advisor, $request->validated());

        return redirect()->route('investments.advisors.show', $advisor)->with('status', 'Asesor de inversión actualizado.');
    }

    public function downloadQr(InvestmentAdvisor $advisor)
    {
        abort_unless(Storage::disk('public')->exists($advisor->qrPath()), 404);

        return Storage::disk('public')->download($advisor->qrPath(), "QR-{$advisor->advisor_number}.png");
    }

    private function availableUsers(?InvestmentAdvisor $advisor = null)
    {
        return User::query()
            ->where(fn ($query) => $query->where('user_type', 'internal')->orWhereNull('user_type'))
            ->where('is_active', true)
            ->where(fn ($query) => $query
                ->whereDoesntHave('investmentAdvisor')
                ->when($advisor?->user_id, fn ($users, $userId) => $users->orWhereKey($userId)))
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
    }
}
