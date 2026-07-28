<?php

namespace App\Http\Controllers;

use App\Models\AffiliationPlan;
use App\Models\InstitutionalSetting;
use App\Models\PublicAffiliationRequest;
use App\Models\Sector;
use App\Services\PublicAffiliationService;
use App\Services\AffiliatePhotoProcessor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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

    public function store(Request $request, PublicAffiliationService $service, AffiliatePhotoProcessor $photoProcessor)
    {
        $data = $request->validate([
            'full_name' => ['bail', 'required', 'string', 'max:255'],
            'ci' => ['bail', 'required', 'string', 'max:30'],
            'ci_complement' => ['nullable', 'string', 'max:10'],
            'issued_in' => ['bail', 'required', 'string', Rule::in(['LP', 'CB', 'SC', 'BN', 'PA', 'TR', 'CH', 'OR', 'PT'])],
            'phone' => ['bail', 'required', 'string', 'regex:/^\d{8}$/'],
            'email' => ['bail', 'required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'address' => ['bail', 'required', 'string', 'max:255'],
            'sector_id' => ['required', Rule::exists('sectors', 'id')->where('is_active', true)],
            'affiliation_plan_id' => ['required', Rule::exists('affiliation_plans', 'id')->where('is_active', true)],
            'regional' => ['bail', 'required', 'string', Rule::in(['LA PAZ', 'COCHABAMBA', 'SANTA CRUZ', 'ORURO', 'POTOSÍ', 'SUCRE', 'TARIJA', 'BENI', 'PANDO'])],
            'institution' => ['required', 'string', 'max:160'],
            'position' => ['required', 'string', 'max:120'],
            'photo' => ['bail', 'required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'birth_date' => ['required', 'date', 'before:today'],
            'marital_status' => ['bail', 'required', 'string', Rule::in(['SOLTERO', 'CASADO', 'DIVORCIADO', 'VIUDO'])],
            'terms' => ['accepted'],
            'data_processing' => ['accepted'],
        ], [
            'full_name.required' => 'El nombre completo es obligatorio.',
            'ci.required' => 'La cédula de identidad es obligatoria.',
            'issued_in.required' => 'Selecciona el lugar de expedición.',
            'issued_in.in' => 'Selecciona un lugar de expedición válido.',
            'phone.required' => 'El número de celular es obligatorio.',
            'phone.regex' => 'Ingresa un número de celular válido de 8 dígitos.',
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'Ingresa un correo electrónico válido.',
            'password.required' => 'La contraseña es obligatoria.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
            'address.required' => 'La dirección es obligatoria.',
            'regional.required' => 'Selecciona la regional.',
            'regional.in' => 'Selecciona una regional válida.',
            'marital_status.required' => 'Selecciona tu estado civil.',
            'marital_status.in' => 'Selecciona un estado civil válido.',
            'photo.required' => 'Selecciona y recorta una fotografía.',
            'photo.image' => 'El archivo seleccionado no es una imagen válida.',
            'photo.mimes' => 'Selecciona una imagen en formato JPG, PNG o WEBP.',
            'photo.max' => 'La fotografía supera el tamaño permitido de 5 MB.',
            'birth_date.required' => 'La fecha de nacimiento es obligatoria.',
            'birth_date.before' => 'La fecha de nacimiento debe ser anterior a hoy.',
            'position.required' => 'El cargo o profesión es obligatorio.',
            'institution.required' => 'La institución es obligatoria.',
            'sector_id.required' => 'Selecciona un sector.',
            'affiliation_plan_id.required' => 'Selecciona un plan.',
            'terms.accepted' => 'Debes aceptar los términos de afiliación.',
            'data_processing.accepted' => 'Debes aceptar el tratamiento de datos.',
        ]);

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
