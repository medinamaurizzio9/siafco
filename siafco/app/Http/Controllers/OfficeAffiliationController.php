<?php

namespace App\Http\Controllers;

use App\Models\Affiliate;
use App\Models\AffiliationPayment;
use App\Models\AffiliationPlan;
use App\Models\InstitutionalSetting;
use App\Models\Person;
use App\Models\Sector;
use App\Models\User;
use App\Services\AffiliatePasswordService;
use App\Services\AuditService;
use App\Services\PaymentLifecycleService;
use App\Support\PaymentStatus;
use App\Support\TextNormalizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OfficeAffiliationController extends Controller
{
    private const AUTHORIZED_ROLES = ['superadministrador', 'administrador', 'gerente', 'caja', 'cajero'];

    public function create()
    {
        return view('affiliates.office-form', [
            'affiliate' => new Affiliate(),
            'sectors' => Sector::where('is_active', true)->orderBy('name')->get(),
            'plans' => AffiliationPlan::where('is_active', true)->orderBy('name')->get(),
            'paidAt' => now(),
        ]);
    }

    public function store(Request $request, PaymentLifecycleService $payments)
    {
        $actor = $request->user();
        abort_unless($actor?->isInternal() && $actor->hasRole(self::AUTHORIZED_ROLES), 403);

        $data = $this->validated($request);
        $plan = AffiliationPlan::where('is_active', true)->findOrFail($data['affiliation_plan_id']);
        $received = round((float) $data['received_amount'], 2);
        $required = round((float) $plan->total_amount, 2);

        if ($received < $required) {
            throw ValidationException::withMessages([
                'received_amount' => 'El monto recibido debe cubrir el total del plan para confirmar el pago en oficina.',
            ]);
        }

        $payment = DB::transaction(function () use ($request, $data, $plan, $received, $required, $actor, $payments) {
            $sector = Sector::whereKey($data['sector_id'])->lockForUpdate()->firstOrFail();
            $sector->increment('current_sequence');
            $sector->refresh();

            $photoPath = $request->file('photo')?->store('affiliates/photos', 'public');
            $registration = sprintf('%s-%06d', mb_strtoupper($sector->code), $sector->current_sequence);

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

            $user = User::create([
                'person_id' => $person->id,
                'name' => $data['full_name'],
                'email' => $data['email'],
                'username' => $this->uniqueAffiliateUsername($registration),
                'role' => 'afiliado',
                'user_type' => 'affiliate',
                'is_active' => true,
                'must_change_password' => true,
                'password' => Hash::make(app(AffiliatePasswordService::class)->temporaryPasswordFromCi($data['ci'])),
            ]);

            $affiliate = Affiliate::create([
                ...$data,
                'user_id' => $user->id,
                'person_id' => $person->id,
                'regional' => ($data['regional'] ?? null) ?: $sector->regional,
                'institution' => ($data['institution'] ?? null) ?: $sector->institution,
                'photo_path' => $photoPath,
                'registration_number' => $registration,
                'status' => 'pendiente_pago',
                'verification_token' => Str::uuid()->toString(),
            ]);

            $payment = AffiliationPayment::create([
                'affiliate_id' => $affiliate->id,
                'affiliation_plan_id' => $plan->id,
                'amount' => $received,
                'expected_amount' => $required,
                'paid_amount' => $received,
                'currency' => $plan->currency ?? 'BOB',
                'institutional_qr_path' => InstitutionalSetting::current()->payment_qr_path,
                'payment_method' => 'efectivo',
                'reference_number' => $data['reference_number'] ?? null,
                'observations' => $data['observations'] ?? null,
                'payment_date' => $data['paid_at']->toDateString(),
                'paid_at' => $data['paid_at'],
                'submitted_at' => now(),
                'status' => PaymentStatus::PENDING,
                'source' => 'office_cash',
                'registered_by' => $actor->id,
            ]);

            AuditService::record('office_affiliation_registered', $affiliate, [
                'affiliate_id' => $affiliate->id,
                'registration_number' => $affiliate->registration_number,
                'actor_id' => $actor->id,
                'payment_id' => $payment->id,
                'amount' => number_format($received, 2, '.', ''),
                'payment_method' => 'efectivo',
            ]);

            AuditService::record('office_cash_payment_received', $payment, [
                'affiliate_id' => $affiliate->id,
                'actor_id' => $actor->id,
                'amount' => number_format($received, 2, '.', ''),
                'payment_method' => 'efectivo',
            ]);

            return $payments->confirm($payment, $actor);
        });

        return redirect()
            ->route('affiliates.office.show', $payment)
            ->with('status', 'Afiliacion registrada en oficina con pago confirmado.');
    }

    public function show(Request $request, AffiliationPayment $payment)
    {
        $actor = $request->user();
        abort_unless($actor?->isInternal() && $actor->hasRole(self::AUTHORIZED_ROLES), 403);

        $payment->load('affiliate.sector', 'affiliate.plan', 'affiliate.credential', 'cashier', 'registrar', 'plan');

        return view('affiliates.office-summary', [
            'payment' => $payment,
            'affiliate' => $payment->affiliate,
        ]);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'ci' => ['required', 'string', 'max:30', Rule::unique('affiliates', 'ci')],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['required', 'email', 'max:255', Rule::unique('affiliates', 'email'), Rule::unique('users', 'email')],
            'address' => ['nullable', 'string', 'max:255'],
            'sector_id' => ['required', 'exists:sectors,id'],
            'affiliation_plan_id' => ['required', 'exists:affiliation_plans,id'],
            'regional' => ['nullable', 'string', 'max:255'],
            'institution' => ['nullable', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
            'photo' => ['nullable', 'image', 'max:2048'],
            'birth_date' => ['nullable', 'date'],
            'marital_status' => ['nullable', 'string', 'max:80'],
            'received_amount' => ['required', 'numeric', 'min:0.01'],
            'paid_at' => ['required', 'date'],
            'reference_number' => ['nullable', 'string', 'max:120', Rule::unique('affiliation_payments', 'reference_number')],
            'observations' => ['nullable', 'string', 'max:1000'],
        ]);

        $data = TextNormalizer::fields($data, [
            'full_name', 'address', 'regional', 'institution', 'position', 'marital_status',
        ]);
        $data['email'] = TextNormalizer::lowercaseEmail($data['email'] ?? null);
        $data['phone'] = TextNormalizer::squish($data['phone'] ?? null);
        $data['paid_at'] = \Carbon\Carbon::parse($data['paid_at']);

        return $data;
    }

    private function uniqueAffiliateUsername(string $registration): string
    {
        $base = str('afiliado_'.$registration)->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->toString();
        $username = $base;
        $suffix = 1;

        while (User::where('username', $username)->exists()) {
            $username = $base.'_'.$suffix++;
        }

        return $username;
    }
}
