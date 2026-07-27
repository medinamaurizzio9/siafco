<?php

namespace App\Http\Controllers;

use App\Models\AffiliationPlan;
use App\Models\InstitutionalSetting;
use App\Models\PublicAffiliationRequest;
use App\Models\Sector;
use App\Services\PublicAffiliationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PublicAffiliationController extends Controller
{
    public function index()
    {
        return view('public-affiliation.index');
    }

    public function create()
    {
        return view('public-affiliation.create', [
            'sectors' => Sector::where('is_active', true)->orderBy('name')->get(),
            'plans' => AffiliationPlan::where('is_active', true)
                ->where(fn ($q) => $q->whereNull('valid_from')->orWhereDate('valid_from', '<=', today()))
                ->where(fn ($q) => $q->whereNull('valid_until')->orWhereDate('valid_until', '>=', today()))
                ->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, PublicAffiliationService $service)
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'ci' => ['required', 'string', 'max:30'],
            'ci_complement' => ['nullable', 'string', 'max:10'],
            'issued_in' => ['required', 'string', 'max:80'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'address' => ['required', 'string', 'max:255'],
            'sector_id' => ['required', Rule::exists('sectors', 'id')->where('is_active', true)],
            'affiliation_plan_id' => ['required', Rule::exists('affiliation_plans', 'id')->where('is_active', true)],
            'regional' => ['required', 'string', 'max:120'],
            'institution' => ['required', 'string', 'max:160'],
            'position' => ['required', 'string', 'max:120'],
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.config('siafco.public_affiliation_photo_max_kb', 4096)],
            'birth_date' => ['required', 'date', 'before:today'],
            'marital_status' => ['required', 'string', 'max:40'],
            'terms' => ['accepted'],
            'data_processing' => ['accepted'],
        ]);

        $photo = $request->file('photo')->store('affiliates/photos', 'public');
        $application = $service->register($data, $photo, $request->ip(), $request->userAgent());
        $request->session()->flash('public_affiliation_password.'.$application->public_token, $data['password']);

        return redirect()->route('public-affiliation.payment', $application)
            ->with('status', 'Tu solicitud fue registrada. Realiza el pago y registra tu número de transacción.');
    }

    public function payment(PublicAffiliationRequest $application)
    {
        session()->keep('public_affiliation_password.'.$application->public_token);
        $application->load('person', 'sector', 'plan', 'payment');
        return view('public-affiliation.payment', [
            'application' => $application,
            'institution' => InstitutionalSetting::current(),
            'duplicateCount' => $application->payment?->transaction_number
                ? \App\Models\AffiliationPayment::where('transaction_number', $application->payment->transaction_number)->count()
                : 0,
        ]);
    }

    public function storePayment(Request $request, PublicAffiliationRequest $application, PublicAffiliationService $service)
    {
        $passwordKey = 'public_affiliation_password.'.$application->public_token;
        $request->session()->keep($passwordKey);
        $data = $request->validate([
            'transaction_number' => ['required', 'string', 'max:120'],
            'payment_date' => ['required', 'date', 'before_or_equal:today'],
            'bank_name' => ['nullable', 'string', 'max:120'],
            'payer_name' => ['required', 'string', 'max:255'],
            'paid_amount' => ['required', 'numeric', 'min:0.01'],
            'receipt' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:'.config('siafco.public_affiliation_receipt_max_kb', 6144)],
            'observations' => ['nullable', 'string', 'max:1000'],
        ]);
        $receipt = $request->file('receipt')?->store('affiliation-receipts', 'local');
        $service->submitPayment($application, $data, $receipt);
        if ($temporaryPassword = $request->session()->get($passwordKey)) {
            $request->session()->flash('completed_password.'.$application->public_token, $temporaryPassword);
        }

        return redirect()->route('public-affiliation.completed', $application)
            ->with('status', 'Tu pago está en revisión. La activación no es automática.');
    }

    public function status(PublicAffiliationRequest $application)
    {
        return view('public-affiliation.status', ['application' => $application->load('person', 'sector', 'plan')]);
    }

    public function completed(PublicAffiliationRequest $application)
    {
        return view('public-affiliation.completed', [
            'application' => $application->load('person', 'sector', 'plan'),
            'temporaryPassword' => session()->pull('completed_password.'.$application->public_token),
        ]);
    }
}
