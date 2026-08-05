<?php

namespace App\Http\Controllers;

use App\Http\Requests\AffiliateCredentialActionRequest;
use App\Http\Requests\ChangeAffiliatePlanRequest;
use App\Http\Requests\ChangeAffiliateSectorRequest;
use App\Http\Requests\ChangeAffiliateStatusRequest;
use App\Http\Requests\UpdateAffiliateInstitutionalRequest;
use App\Http\Requests\UpdateAffiliatePersonalRequest;
use App\Http\Requests\UpdateAffiliatePhotoRequest;
use App\Models\Affiliate;
use App\Models\AffiliationPlan;
use App\Models\Sector;
use App\Services\AffiliateAdministrationService;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class AffiliateAdministrationController extends Controller
{
    public function __construct(private readonly AffiliateAdministrationService $affiliates)
    {
    }

    public function updatePersonal(UpdateAffiliatePersonalRequest $request, Affiliate $affiliate): RedirectResponse
    {
        $this->affiliates->updatePersonal($affiliate, $request->validated(), $request->user());

        return back()->with('status', 'Datos personales actualizados.');
    }

    public function updateInstitutional(UpdateAffiliateInstitutionalRequest $request, Affiliate $affiliate): RedirectResponse
    {
        $this->affiliates->updateInstitutional($affiliate, $request->validated(), $request->user());

        return back()->with('status', 'Datos institucionales actualizados.');
    }

    public function changeSector(ChangeAffiliateSectorRequest $request, Affiliate $affiliate): RedirectResponse
    {
        $sector = Sector::findOrFail($request->validated('sector_id'));
        $this->affiliates->changeSector($affiliate, $sector, $request->user());

        return back()->with('status', 'Sector actualizado sin regenerar el numero de afiliado.');
    }

    public function changePlan(ChangeAffiliatePlanRequest $request, Affiliate $affiliate): RedirectResponse
    {
        $plan = AffiliationPlan::findOrFail($request->validated('affiliation_plan_id'));
        $this->affiliates->changePlan($affiliate, $plan, $request->user());

        return back()->with('status', 'Plan actualizado y saldo recalculado.');
    }

    public function changeStatus(ChangeAffiliateStatusRequest $request, Affiliate $affiliate): RedirectResponse
    {
        $data = $request->validated();
        $this->affiliates->changeStatus($affiliate, $data['action'], $data['reason'] ?? null, $request->user());

        return back()->with('status', 'Estado de afiliacion actualizado.');
    }

    public function updatePhoto(UpdateAffiliatePhotoRequest $request, Affiliate $affiliate): RedirectResponse
    {
        $this->affiliates->updatePhoto($affiliate, $request->file('photo'), $request->user());

        return back()->with('status', 'Fotografia actualizada.');
    }

    public function regenerateCredential(AffiliateCredentialActionRequest $request, Affiliate $affiliate): RedirectResponse
    {
        $this->affiliates->regenerateCredentialFiles($affiliate, $request->user());

        return back()->with('status', 'Archivos derivados de la credencial regenerados.');
    }

    public function suspendCredential(AffiliateCredentialActionRequest $request, Affiliate $affiliate): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);
        $this->affiliates->suspendCredential($affiliate->loadMissing('credential'), $request->user(), $data['reason']);

        return back()->with('status', 'Credencial suspendida sin cambiar el QR ni el numero.');
    }

    public function reactivateCredential(AffiliateCredentialActionRequest $request, Affiliate $affiliate): RedirectResponse
    {
        $this->affiliates->reactivateCredential($affiliate->loadMissing('credential'), $request->user());

        return back()->with('status', 'Credencial reactivada.');
    }

    public function restore(Request $request, int $affiliate): RedirectResponse
    {
        $target = Affiliate::withTrashed()->findOrFail($affiliate);
        Gate::authorize('restore', $target);

        DB::transaction(function () use ($target, $request): void {
            $target->restore();
            AuditService::record('affiliate_restored', $target, [
                'actor_id' => $request->user()->id,
                'user_reactivated' => false,
                'credential_reactivated' => false,
            ]);
        });

        return redirect()->route('affiliates.show', $target)->with('status', 'Afiliado restaurado. Revise por separado cuenta y credencial.');
    }
}
