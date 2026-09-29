<?php

namespace App\Http\Controllers;

use App\Models\AffiliationPlan;
use App\Models\Affiliate;
use App\Models\InstitutionalSetting;
use App\Models\Person;
use App\Models\PublicAffiliationRequest;
use App\Models\Sector;
use App\Models\User;
use App\Services\AffiliateAccountService;
use App\Services\PaymentVoucherService;
use App\Services\PublicAffiliationService;
use App\Support\PaymentTransactionNumber;
use App\Support\PublicAffiliationCatalogs;
use App\Support\PublicAffiliationValidation;
use App\Support\SiafcoDate;
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
            'nowInput' => now(SiafcoDate::timezone())->format('Y-m-d\TH:i'),
        ]);
    }

    public function store(Request $request, PublicAffiliationService $service, PaymentVoucherService $vouchers)
    {
        $data = $request->validate($this->rules(), $this->messages());
        $timezone = SiafcoDate::validTimezoneOrDefault($data['browser_timezone'] ?? null);
        if (SiafcoDate::fromLocalInput($data['payment_date'], $timezone)->timezone($timezone)->toDateString() > now()->toDateString()) {
            return back()->withErrors(['payment_date' => 'La fecha y hora de pago no puede ser futura.'])->withInput();
        }
        $data['transaction_number'] = PaymentTransactionNumber::normalize($data['transaction_number'] ?? null);
        $ciAlreadyExists = Affiliate::where('ci', $data['ci'] ?? null)->exists()
            || Person::where('ci', $data['ci'] ?? null)->exists()
            || User::where('ci', $data['ci'] ?? null)->exists();
        if (! $ciAlreadyExists && PaymentTransactionNumber::hasDuplicate($data['transaction_number'])) {
            return back()->withErrors(['transaction_number' => PaymentTransactionNumber::duplicateMessage()])->withInput();
        }
        $data['payment_timezone'] = $timezone;
        $data['paid_at'] = SiafcoDate::fromLocalInput($data['payment_date'], $data['payment_timezone']);
        unset($data['browser_timezone']);
        $receiptPath = $vouchers->store($request->file('receipt'), 'receipt');

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
            'payment_date' => ['required', 'date'],
            'browser_timezone' => ['nullable', 'string', 'timezone'],
            'receipt' => ['required', 'file', 'mimetypes:image/jpeg,image/png,image/webp,application/pdf', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'observations' => ['nullable', 'string', 'max:1000'],
        ];
    }

    private function messages(): array
    {
        return array_merge(PublicAffiliationValidation::registrationMessages(), [
            'phone.regex' => 'Ingresa un número de celular válido de 8 dígitos.',
            'bank_name.required' => 'El banco es obligatorio.',
            'transaction_number.required' => 'El número de transacción es obligatorio.',
            'payment_date.before_or_equal' => 'La fecha y hora de pago no puede ser futura.',
            'receipt.required' => 'Adjunta una fotografía o PDF del comprobante de pago.',
        ]);
    }
}
