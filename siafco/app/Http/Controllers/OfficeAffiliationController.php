<?php

namespace App\Http\Controllers;

use App\Models\Affiliate;
use App\Models\AffiliationPayment;
use App\Models\AffiliationPlan;
use App\Models\InstitutionalSetting;
use App\Models\Person;
use App\Models\Sector;
use App\Rules\ActivePlanForSector;
use App\Services\AffiliateAccountService;
use App\Services\AffiliatePhotoProcessor;
use App\Services\AuditService;
use App\Services\PaymentReceiptNumberService;
use App\Services\PaymentVoucherService;
use App\Support\PaymentStatus;
use App\Support\PaymentTransactionNumber;
use App\Support\PublicAffiliationCatalogs;
use App\Support\SiafcoDate;
use App\Support\TextNormalizer;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OfficeAffiliationController extends Controller
{
    private const AUTHORIZED_ROLES = ['superadministrador', 'administrador', 'gerente', 'caja', 'cajero'];

    public function create()
    {
        return view('affiliates.office-form', [
            'affiliate' => new Affiliate,
            'sectors' => Sector::where('is_active', true)->orderBy('name')->get(),
            'plans' => AffiliationPlan::available()->orderBy('name')->get(),
            'regionals' => PublicAffiliationCatalogs::regionalOptions(),
            'maritalStatuses' => PublicAffiliationCatalogs::maritalStatusOptions(),
            'paidAt' => now(),
        ]);
    }

    public function store(Request $request, PaymentVoucherService $vouchers)
    {
        $actor = $request->user();
        abort_unless($actor?->isInternal() && $actor->hasRole(self::AUTHORIZED_ROLES), 403);

        $data = $this->validated($request);
        $paymentMethod = $data['payment_method'];
        $isElectronic = in_array($paymentMethod, ['qr', 'transferencia'], true);
        $data['reference_number'] = PaymentTransactionNumber::normalize($data['reference_number'] ?? null);
        if ($isElectronic && PaymentTransactionNumber::hasDuplicate($data['reference_number'])) {
            throw ValidationException::withMessages([
                'reference_number' => PaymentTransactionNumber::duplicateMessage(),
            ]);
        }
        $plan = AffiliationPlan::where('is_active', true)->findOrFail($data['affiliation_plan_id']);
        $received = round((float) $data['received_amount'], 2);
        $required = round((float) $plan->total_amount, 2);

        if ($received < $required) {
            throw ValidationException::withMessages([
                'received_amount' => 'El monto recibido debe cubrir el total del plan para confirmar el pago en oficina.',
            ]);
        }

        $photoPath = $request->hasFile('photo')
            ? app(AffiliatePhotoProcessor::class)->process(
                $request->file('photo'),
                AffiliatePhotoProcessor::CREDENTIAL_WIDTH,
                AffiliatePhotoProcessor::CREDENTIAL_HEIGHT
            )
            : null;
        $voucherPath = $vouchers->store($request->file('voucher'));
        $institutionalQrPath = InstitutionalSetting::current()->payment_qr_path;

        try {
            $payment = DB::transaction(function () use ($data, $plan, $received, $required, $actor, $paymentMethod, $isElectronic, $photoPath, $voucherPath, $institutionalQrPath) {
                $sector = Sector::whereKey($data['sector_id'])->firstOrFail();

                $person = Person::updateOrCreate(
                    ['ci' => $data['ci']],
                    [
                        'full_name' => $data['full_name'],
                        'phone' => $data['phone'] ?? null,
                        'email' => $data['email'],
                        'address' => $data['address'] ?? null,
                        'birth_date' => $data['birth_date'] ?? null,
                        'marital_status' => $data['marital_status'] ?? null,
                        'photo' => $photoPath,
                    ]
                );

                $affiliate = Affiliate::create([
                    ...$data,
                    'person_id' => $person->id,
                    'regional' => ($data['regional'] ?? null) ?: $sector->regional,
                    'institution' => ($data['institution'] ?? null) ?: $sector->institution,
                    'photo_path' => $photoPath,
                    'registration_number' => null,
                    'status' => 'pendiente_pago',
                    'verification_token' => Str::uuid()->toString(),
                ]);

                app(AffiliateAccountService::class)->ensureForAffiliate($affiliate, $person);

                $payment = AffiliationPayment::create([
                    'affiliate_id' => $affiliate->id,
                    'affiliation_plan_id' => $plan->id,
                    'amount' => $received,
                    'expected_amount' => $required,
                    'paid_amount' => $received,
                    'currency' => $plan->currency ?? 'BOB',
                    'institutional_qr_path' => $institutionalQrPath,
                    'payment_method' => $paymentMethod,
                    'reference_number' => $data['reference_number'] ?? null,
                    'voucher_path' => $voucherPath,
                    'observations' => $data['observations'] ?? null,
                    'payment_date' => $data['paid_at']->toDateString(),
                    'paid_at' => $data['paid_at'],
                    'payment_timezone' => $data['payment_timezone'] ?? null,
                    'submitted_at' => now(),
                    'status' => PaymentStatus::UNDER_REVIEW,
                    'source' => $isElectronic ? 'office_qr' : 'office_cash',
                    'registered_by' => $actor->id,
                ]);
                app(PaymentReceiptNumberService::class)->assignIfMissing($payment);

                AuditService::record('office_affiliation_application_registered', $affiliate, [
                    'affiliate_id' => $affiliate->id,
                    'actor_id' => $actor->id,
                    'payment_id' => $payment->id,
                    'amount' => number_format($received, 2, '.', ''),
                    'payment_method' => $paymentMethod,
                ]);

                AuditService::record($isElectronic ? 'office_qr_registered' : 'office_cash_payment_registered', $payment, [
                    'affiliate_id' => $affiliate->id,
                    'actor_id' => $actor->id,
                    'amount' => number_format($received, 2, '.', ''),
                    'payment_method' => $paymentMethod,
                    'reference_number' => $isElectronic ? $payment->reference_number : null,
                ]);

                return $payment;
            });
        } catch (\Throwable $exception) {
            if ($photoPath) {
                Storage::disk('public')->delete($photoPath);
            }
            if ($voucherPath) {
                Storage::disk('local')->delete($voucherPath);
            }
            if ($exception instanceof UniqueConstraintViolationException) {
                $this->throwDuplicateValidationException($exception);
            }
            throw $exception;
        }

        return redirect()
            ->route('affiliates.office.show', $payment)
            ->with('status', 'Pago registrado correctamente. Queda pendiente de verificación por Gerencia o Administración.');
    }

    public function show(Request $request, AffiliationPayment $payment)
    {
        $actor = $request->user();
        abort_unless($actor?->isInternal() && $actor->hasRole(self::AUTHORIZED_ROLES), 403);

        $payment->load('affiliate.sector', 'affiliate.plan', 'affiliate.credential', 'affiliate.user', 'cashier', 'registrar', 'plan');

        return view('affiliates.office-summary', [
            'payment' => $payment,
            'affiliate' => $payment->affiliate,
            'temporaryPassword' => app(AffiliateAccountService::class)->normalizedCi($payment->affiliate?->ci),
        ]);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'ci' => [
                'required',
                'string',
                'max:30',
                Rule::unique('people', 'ci'),
                Rule::unique('affiliates', 'ci'),
                Rule::unique('users', 'ci'),
            ],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['required', 'email', 'max:255', Rule::unique('affiliates', 'email'), Rule::unique('users', 'email')],
            'address' => ['nullable', 'string', 'max:255'],
            'sector_id' => ['required', 'exists:sectors,id'],
            'affiliation_plan_id' => ['required', new ActivePlanForSector($request->input('sector_id'))],
            'regional' => ['nullable', 'string', Rule::in(PublicAffiliationCatalogs::REGIONALS)],
            'institution' => ['nullable', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'birth_date' => ['nullable', 'date'],
            'marital_status' => ['nullable', 'string', Rule::in(PublicAffiliationCatalogs::MARITAL_STATUSES)],
            'received_amount' => ['required', 'numeric', 'min:0.01'],
            'paid_at' => ['required', 'date', 'before_or_equal:now'],
            'browser_timezone' => ['nullable', 'string', 'timezone'],
            'payment_method' => ['required', Rule::in(['efectivo', 'qr', 'transferencia'])],
            'reference_number' => [Rule::requiredIf(in_array($request->input('payment_method'), ['qr', 'transferencia'], true)), 'nullable', 'string', 'max:120'],
            'voucher' => [Rule::requiredIf(in_array($request->input('payment_method'), ['qr', 'transferencia'], true)), 'nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp,application/pdf', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'observations' => ['nullable', 'string', 'max:1000'],
        ], [
            'email.unique' => 'El correo electrónico ya está registrado en el sistema.',
            'ci.unique' => 'El número de CI ya se encuentra registrado.',
            'voucher.required' => 'Adjunta una fotografía o PDF del comprobante de pago.',
        ]);

        $data = TextNormalizer::fields($data, [
            'full_name', 'address', 'regional', 'institution', 'position', 'marital_status',
        ]);
        $data['email'] = TextNormalizer::lowercaseEmail($data['email'] ?? null);
        $data['phone'] = TextNormalizer::squish($data['phone'] ?? null);
        $data['payment_timezone'] = SiafcoDate::validTimezoneOrDefault($data['browser_timezone'] ?? null);
        $data['paid_at'] = SiafcoDate::fromLocalInput($data['paid_at'], $data['payment_timezone']);
        unset($data['browser_timezone']);

        return $data;
    }

    private function throwDuplicateValidationException(UniqueConstraintViolationException $exception): never
    {
        $message = mb_strtolower($exception->getMessage());

        if (str_contains($message, 'email')) {
            throw ValidationException::withMessages([
                'email' => 'El correo electrónico ya está registrado en el sistema.',
            ]);
        }

        if (str_contains($message, 'ci')) {
            throw ValidationException::withMessages([
                'ci' => 'El número de CI ya se encuentra registrado.',
            ]);
        }

        if (str_contains($message, 'username')) {
            throw ValidationException::withMessages([
                'full_name' => 'No se pudo generar un usuario único. Revise los datos e intente nuevamente.',
            ]);
        }

        throw $exception;
    }
}
