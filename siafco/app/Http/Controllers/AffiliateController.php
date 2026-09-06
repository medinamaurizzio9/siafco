<?php

namespace App\Http\Controllers;

use App\Models\Affiliate;
use App\Models\AffiliationPayment;
use App\Models\AffiliationPlan;
use App\Models\InstitutionalSetting;
use App\Models\Person;
use App\Models\Sector;
use App\Models\User;
use App\Services\AuditService;
use App\Services\AffiliateDuplicateDetector;
use App\Services\AffiliateDeletionService;
use App\Services\AffiliateTimelineService;
use App\Services\AffiliatePasswordService;
use App\Services\AffiliatePhotoProcessor;
use App\Services\PaymentBalanceService;
use App\Support\PublicAffiliationCatalogs;
use App\Support\TextNormalizer;
use App\Rules\ActivePlanForSector;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AffiliateController extends Controller
{
    public function index(Request $request)
    {
        $affiliates = Affiliate::official()->with('sector', 'plan', 'latestPayment')
            ->when($request->search, fn ($query, $search) => $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('ci', 'like', "%{$search}%")
                    ->orWhere('registration_number', 'like', "%{$search}%");
            }))
            ->when($request->status, fn ($query, $status) => $query->where('status', $status))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('affiliates.index', compact('affiliates'));
    }

    public function create()
    {
        return view('affiliates.form', [
            'affiliate' => new Affiliate(),
            'sectors' => Sector::where('is_active', true)->orderBy('name')->get(),
            'plans' => AffiliationPlan::available()->orderBy('name')->get(),
            'regionals' => PublicAffiliationCatalogs::regionalOptions(),
            'maritalStatuses' => PublicAffiliationCatalogs::maritalStatusOptions(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $photoPath = $request->hasFile('photo')
            ? app(AffiliatePhotoProcessor::class)->process(
                $request->file('photo'),
                AffiliatePhotoProcessor::CREDENTIAL_WIDTH,
                AffiliatePhotoProcessor::CREDENTIAL_HEIGHT
            )
            : null;
        $institutionalQrPath = InstitutionalSetting::current()->payment_qr_path;

        try {
            $affiliate = DB::transaction(function () use ($data, $photoPath, $institutionalQrPath) {
                $sector = Sector::findOrFail($data['sector_id']);
                $plan = AffiliationPlan::findOrFail($data['affiliation_plan_id']);
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
                    'username' => $this->uniqueAffiliateUsername($data['ci']),
                    'role' => 'afiliado',
                    'user_type' => 'affiliate',
                    'is_active' => true,
                    'must_change_password' => true,
                    'password' => Hash::make(app(AffiliatePasswordService::class)->temporaryPasswordFromCi($data['ci'])),
                ]);

                $affiliate = Affiliate::create($data + [
                    'user_id' => $user->id,
                    'person_id' => $person->id,
                    'regional' => ($data['regional'] ?? null) ?: $sector->regional,
                    'institution' => ($data['institution'] ?? null) ?: $sector->institution,
                    'photo_path' => $photoPath,
                    'registration_number' => null,
                    'status' => 'pendiente_pago',
                    'verification_token' => Str::uuid()->toString(),
                ]);

                AffiliationPayment::create([
                    'affiliate_id' => $affiliate->id,
                    'amount' => $plan->total_amount,
                    'institutional_qr_path' => $institutionalQrPath,
                    'status' => 'pendiente',
                ]);

                AuditService::record('affiliate_application_registered', $affiliate, [
                    'affiliate_id' => $affiliate->id,
                    'status' => 'pending_payment',
                ]);

                return $affiliate;
            });
        } catch (\Throwable $exception) {
            if ($photoPath) {
                Storage::disk('public')->delete($photoPath);
            }
            throw $exception;
        }

        return redirect()->route('affiliates.show', $affiliate)
            ->with('status', 'Solicitud registrada con pago pendiente. El código oficial se asignará después de confirmar el pago.');
    }

    public function show(
        Affiliate $affiliate,
        PaymentBalanceService $balances,
        AffiliateTimelineService $timeline,
        AffiliateDuplicateDetector $duplicates
    )
    {
        $affiliate->load('sector', 'plan', 'payments.cashier', 'credential', 'user', 'person');

        return view('affiliates.show', [
            'affiliate' => $affiliate,
            'treasury' => $balances->summary($affiliate),
            'timeline' => auth()->user()->hasPermission('affiliates.view_timeline') ? $timeline->forAffiliate($affiliate, 20) : collect(),
            'duplicates' => $duplicates->forAffiliate($affiliate),
            'auditLogs' => auth()->user()->hasPermission('affiliates.view_audit')
                ? \App\Models\AuditLog::where('auditable_type', Affiliate::class)->where('auditable_id', $affiliate->id)->latest()->limit(20)->get()
                : collect(),
            'sectors' => Sector::where('is_active', true)->orderBy('name')->get(),
            'plans' => AffiliationPlan::available()->orderBy('name')->get(),
        ]);
    }

    public function edit(Affiliate $affiliate)
    {
        return view('affiliates.form', [
            'affiliate' => $affiliate,
            'sectors' => Sector::where('is_active', true)->orderBy('name')->get(),
            'plans' => AffiliationPlan::available()->orderBy('name')->get(),
            'regionals' => PublicAffiliationCatalogs::regionalOptions(),
            'maritalStatuses' => PublicAffiliationCatalogs::maritalStatusOptions(),
        ]);
    }

    public function update(Request $request, Affiliate $affiliate)
    {
        $data = $this->validated($request, $affiliate);
        if ($request->hasFile('photo')) {
            $data['photo_path'] = app(AffiliatePhotoProcessor::class)->process(
                $request->file('photo'),
                AffiliatePhotoProcessor::CREDENTIAL_WIDTH,
                AffiliatePhotoProcessor::CREDENTIAL_HEIGHT
            );
        }

        $affiliate->update($data);
        $affiliate->person?->update([
            'full_name' => $data['full_name'],
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'],
            'address' => $data['address'] ?? null,
            'birth_date' => $data['birth_date'] ?? null,
            'marital_status' => $data['marital_status'] ?? null,
            'photo' => $data['photo_path'] ?? $affiliate->person?->photo,
        ]);
        $affiliate->user?->update(['name' => $data['full_name'], 'email' => $data['email']]);
        AuditService::record('afiliado.actualizado', $affiliate);

        return redirect()->route('affiliates.show', $affiliate)->with('status', 'Afiliado actualizado.');
    }

    public function destroy(Request $request, Affiliate $affiliate, AffiliateDeletionService $deletion): RedirectResponse
    {
        Gate::authorize('delete', $affiliate);

        try {
            $deletion->delete($affiliate, $request->user(), 'Eliminación administrativa confirmada por el usuario.');
        } catch (ValidationException $exception) {
            return back()->with('error', collect($exception->errors())->flatten()->first() ?: 'Este registro no puede eliminarse.');
        }

        return redirect()
            ->route('affiliates.index')
            ->with('status', 'Registro eliminado correctamente.');
    }

    private function validated(Request $request, ?Affiliate $affiliate = null): array
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'ci' => ['required', 'string', 'max:30', Rule::unique('affiliates')->ignore($affiliate)],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['required', 'email', 'max:255', Rule::unique('affiliates')->ignore($affiliate), Rule::unique('users', 'email')->ignore($affiliate?->user_id)],
            'address' => ['nullable', 'string', 'max:255'],
            'sector_id' => ['required', 'exists:sectors,id'],
            'affiliation_plan_id' => ['required', new ActivePlanForSector($request->input('sector_id'))],
            'regional' => ['nullable', 'string', Rule::in(PublicAffiliationCatalogs::REGIONALS)],
            'institution' => ['nullable', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'birth_date' => ['nullable', 'date'],
            'marital_status' => ['nullable', 'string', Rule::in(PublicAffiliationCatalogs::MARITAL_STATUSES)],
            'status' => ['nullable', 'in:pendiente_pago,activo,inactivo,observado'],
        ]);

        $data = TextNormalizer::fields($data, [
            'full_name', 'address', 'regional', 'institution', 'position', 'marital_status',
        ]);
        $data['email'] = TextNormalizer::lowercaseEmail($data['email'] ?? null);
        $data['phone'] = TextNormalizer::squish($data['phone'] ?? null);

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
