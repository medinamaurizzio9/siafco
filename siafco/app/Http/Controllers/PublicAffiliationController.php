<?php

namespace App\Http\Controllers;

use App\Models\AffiliationPayment;
use App\Models\AffiliationPlan;
use App\Models\InstitutionalSetting;
use App\Models\PublicAffiliationRequest;
use App\Models\Sector;
use App\Services\AffiliatePhotoProcessor;
use App\Services\PublicAffiliationService;
use App\Support\PublicAffiliationValidation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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

    public function store(Request $request, PublicAffiliationService $service, AffiliatePhotoProcessor $photoProcessor)
    {
        $data = $request->validate(
            PublicAffiliationValidation::registrationRules(),
            PublicAffiliationValidation::registrationMessages()
        );

        $photo = $photoProcessor->process($request->file('photo'));
        try {
            $application = $service->register($data, $photo, $request->ip(), $request->userAgent());
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete($photo);
            throw $exception;
        }
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
                ? AffiliationPayment::where('transaction_number', $application->payment->transaction_number)->count()
                : 0,
        ]);
    }

    public function storePayment(Request $request, PublicAffiliationRequest $application, PublicAffiliationService $service)
    {
        $passwordKey = 'public_affiliation_password.'.$application->public_token;
        $request->session()->keep($passwordKey);
        $data = $request->validate(PublicAffiliationValidation::paymentRules(
            config('siafco.public_affiliation_receipt_max_kb', 6144)
        ));
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
