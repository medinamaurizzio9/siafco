<?php

namespace App\Http\Controllers;

use App\Models\AffiliationPlan;
use App\Services\AuditService;
use App\Models\Sector;
use Illuminate\Http\Request;

class AffiliationPlanController extends Controller
{
    public function index()
    {
        return view('plans.index', ['plans' => AffiliationPlan::latest()->paginate(10)]);
    }

    public function create()
    {
        return view('plans.form', ['plan' => new AffiliationPlan(), 'sectors' => Sector::where('is_active', true)->orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $plan = AffiliationPlan::create($this->validated($request));
        AuditService::record('plan.creado', $plan);

        return redirect()->route('plans.index')->with('status', 'Plan creado.');
    }

    public function edit(AffiliationPlan $plan)
    {
        return view('plans.form', ['plan' => $plan, 'sectors' => Sector::where('is_active', true)->orderBy('name')->get()]);
    }

    public function update(Request $request, AffiliationPlan $plan)
    {
        $plan->update($this->validated($request));
        AuditService::record('plan.actualizado', $plan);

        return redirect()->route('plans.index')->with('status', 'Plan actualizado.');
    }

    public function destroy(AffiliationPlan $plan)
    {
        $plan->delete();
        AuditService::record('plan.eliminado', $plan);

        return back()->with('status', 'Plan eliminado.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sector_id' => ['nullable', 'exists:sectors,id'],
            'type' => ['required', 'in:convenio,alianza,independiente'],
            'affiliation_fee' => ['required', 'numeric', 'min:0'],
            'credential_fee' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'description' => ['nullable', 'string'],
            'payment_instructions' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
        ]) + ['is_active' => $request->boolean('is_active')];
    }
}
