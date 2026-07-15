<?php

namespace App\Http\Controllers;

use App\Models\Affiliate;
use App\Models\AffiliationPayment;
use App\Models\AffiliationPlan;
use App\Models\Person;
use App\Models\Sector;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AffiliateController extends Controller
{
    public function index(Request $request)
    {
        $affiliates = Affiliate::with('sector', 'plan')
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
            'plans' => AffiliationPlan::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $affiliate = DB::transaction(function () use ($request, $data) {
            $sector = Sector::lockForUpdate()->findOrFail($data['sector_id']);
            $plan = AffiliationPlan::findOrFail($data['affiliation_plan_id']);
            $sector->increment('current_sequence');

            $photoPath = $request->file('photo')?->store('affiliates/photos', 'public');
            $registration = sprintf('%s-%06d', strtoupper($sector->code), $sector->current_sequence);
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
                'role' => 'afiliado',
                'password' => Hash::make($data['ci']),
            ]);

            $affiliate = Affiliate::create($data + [
                'user_id' => $user->id,
                'person_id' => $person->id,
                'regional' => ($data['regional'] ?? null) ?: $sector->regional,
                'institution' => ($data['institution'] ?? null) ?: $sector->institution,
                'photo_path' => $photoPath,
                'registration_number' => $registration,
                'status' => 'pendiente_pago',
                'verification_token' => Str::uuid()->toString(),
            ]);

            AffiliationPayment::create([
                'affiliate_id' => $affiliate->id,
                'amount' => $plan->total_amount,
                'institutional_qr_path' => 'institutional/payment-qr.png',
                'status' => 'pendiente',
            ]);

            AuditService::record('afiliado.registrado', $affiliate);

            return $affiliate;
        });

        return redirect()->route('affiliates.show', $affiliate)->with('status', 'Afiliado registrado con pago pendiente.');
    }

    public function show(Affiliate $affiliate)
    {
        return view('affiliates.show', [
            'affiliate' => $affiliate->load('sector', 'plan', 'payments.cashier', 'credential'),
        ]);
    }

    public function edit(Affiliate $affiliate)
    {
        return view('affiliates.form', [
            'affiliate' => $affiliate,
            'sectors' => Sector::where('is_active', true)->orderBy('name')->get(),
            'plans' => AffiliationPlan::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Affiliate $affiliate)
    {
        $data = $this->validated($request, $affiliate);
        if ($request->hasFile('photo')) {
            $data['photo_path'] = $request->file('photo')->store('affiliates/photos', 'public');
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

    private function validated(Request $request, ?Affiliate $affiliate = null): array
    {
        return $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'ci' => ['required', 'string', 'max:30', Rule::unique('affiliates')->ignore($affiliate)],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['required', 'email', 'max:255', Rule::unique('affiliates')->ignore($affiliate), Rule::unique('users', 'email')->ignore($affiliate?->user_id)],
            'address' => ['nullable', 'string', 'max:255'],
            'sector_id' => ['required', 'exists:sectors,id'],
            'affiliation_plan_id' => ['required', 'exists:affiliation_plans,id'],
            'regional' => ['nullable', 'string', 'max:255'],
            'institution' => ['nullable', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
            'photo' => ['nullable', 'image', 'max:2048'],
            'birth_date' => ['nullable', 'date'],
            'marital_status' => ['nullable', 'string', 'max:80'],
            'status' => ['nullable', 'in:pendiente_pago,activo,inactivo,observado'],
        ]);
    }
}
