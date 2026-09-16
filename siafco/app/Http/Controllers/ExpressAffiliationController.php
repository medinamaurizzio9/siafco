<?php

namespace App\Http\Controllers;

use App\Models\AffiliationPlan;
use App\Models\InstitutionalSetting;
use App\Models\PublicAffiliationRequest;
use App\Models\Sector;
use App\Services\AffiliateAccountService;
use App\Services\PublicAffiliationService;
use App\Support\PublicAffiliationCatalogs;
use App\Support\PublicAffiliationValidation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ExpressAffiliationController extends Controller
{
    public function create()
    {
        return view('public-affiliation.express', [
            'sectors' => Sector::query()->where('is_active', true)->orderBy('name')->get(),
            'plans' => AffiliationPlan::query()->available()->orderBy('name')->get(),
            'expeditionPlaces' => PublicAffiliationCatalogs::issuedInSelectOptions(),
            'institution' => InstitutionalSetting::current(),
            'today' => today()->format('Y-m-d'),
        ]);
    }

    public function store(Request $request, PublicAffiliationService $service)
    {
        $data = $request->validate($this->rules(), $this->messages());
        $receiptPath = $request->hasFile('receipt')
            ? $request->file('receipt')->store('payments/vouchers', 'local')
            : null;

        try {
            $application = $service->registerExpress(
                $data,
                $receiptPath,
                $request->ip(),
                $request->userAgent()
            );
        } catch (\Throwable $exception) {
            if ($receiptPath) {
                Storage::disk('local')->delete($receiptPath);
            }

            throw $exception;
        }

        $request->session()->flash('express_access_user.'.$application->public_token, $application->user?->email);

        return redirect()->route('public-affiliation.express.completed', $application);
    }

    public function completed(PublicAffiliationRequest $application)
    {
        $application->load('person', 'sector', 'plan', 'payment', 'user');
        $temporaryPassword = app(AffiliateAccountService::class)->normalizedCi($application->person?->ci);

        return view('public-affiliation.express-completed', [
            'application' => $application,
            'accessUser' => session()->pull('express_access_user.'.$application->public_token, $application->user?->email),
            'temporaryPassword' => $temporaryPassword,
        ]);
    }

    private function rules(): array
    {
        return [
            'full_name' => ['bail', 'required', 'string', 'max:255'],
            'ci' => ['bail', 'required', 'string', 'max:30'],
            'issued_in' => ['bail', 'required', 'string', Rule::in(PublicAffiliationCatalogs::ISSUED_IN)],
            'phone' => ['bail', 'required', 'string', 'regex:/^\d{8}$/'],
            'sector_id' => ['required', Rule::exists('sectors', 'id')->where('is_active', true)],
            'affiliation_plan_id' => ['required', Rule::exists('affiliation_plans', 'id')->where('is_active', true)],
            'transaction_number' => ['required', 'string', 'max:120'],
            'bank_name' => ['required', 'string', 'max:120'],
            'payer_name' => ['required', 'string', 'max:255'],
            'payment_date' => ['required', 'date', 'before_or_equal:today'],
            'receipt' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'observations' => ['nullable', 'string', 'max:1000'],
        ];
    }

    private function messages(): array
    {
        return array_merge(PublicAffiliationValidation::registrationMessages(), [
            'phone.regex' => 'Ingresa un número de celular válido de 8 dígitos.',
            'bank_name.required' => 'El banco es obligatorio.',
            'transaction_number.required' => 'El número de transacción es obligatorio.',
            'payment_date.before_or_equal' => 'La fecha de pago no puede ser futura.',
        ]);
    }
}
