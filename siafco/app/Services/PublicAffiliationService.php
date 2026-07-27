<?php

namespace App\Services;

use App\Models\Affiliate;
use App\Models\AffiliationPayment;
use App\Models\AffiliationPlan;
use App\Models\Person;
use App\Models\PublicAffiliationRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PublicAffiliationService
{
    public function register(array $data, ?string $photoPath, string $ip, ?string $userAgent): PublicAffiliationRequest
    {
        return DB::transaction(function () use ($data, $photoPath, $ip, $userAgent) {
            $plan = AffiliationPlan::query()
                ->whereKey($data['affiliation_plan_id'])
                ->where('is_active', true)
                ->lockForUpdate()
                ->firstOrFail();

            if ($plan->sector_id && (int) $plan->sector_id !== (int) $data['sector_id']) {
                throw ValidationException::withMessages(['affiliation_plan_id' => 'El plan no corresponde al sector seleccionado.']);
            }

            $person = Person::where('ci', $data['ci'])->lockForUpdate()->first();
            if ($person?->affiliate) {
                $pending = PublicAffiliationRequest::where('person_id', $person->id)
                    ->whereNotIn('status', ['rejected', 'cancelled'])->latest()->first();
                throw ValidationException::withMessages([
                    'ci' => $pending
                        ? 'Ya existe una solicitud para este CI. Código: '.$pending->request_code
                        : 'Este CI ya pertenece a un afiliado.',
                ]);
            }

            $person = Person::updateOrCreate(['ci' => $data['ci']], [
                'full_name' => $data['full_name'],
                'ci_complement' => $data['ci_complement'] ?? null,
                'issued_in' => $data['issued_in'],
                'phone' => $data['phone'],
                'email' => $data['email'],
                'address' => $data['address'],
                'birth_date' => $data['birth_date'],
                'marital_status' => $data['marital_status'],
                'photo' => $photoPath ?: $person?->photo,
            ]);

            $user = User::where('person_id', $person->id)->orWhere('email', $data['email'])->lockForUpdate()->first();
            if ($user && $user->person_id && $user->person_id !== $person->id) {
                throw ValidationException::withMessages(['email' => 'El correo ya está vinculado a otra persona.']);
            }
            $user ??= User::create([
                'person_id' => $person->id,
                'name' => $data['full_name'],
                'email' => $data['email'],
                'role' => 'afiliado',
                'password' => Hash::make($data['password']),
            ]);

            $affiliate = Affiliate::create([
                'person_id' => $person->id,
                'user_id' => $user->id,
                'sector_id' => $data['sector_id'],
                'affiliation_plan_id' => $plan->id,
                'full_name' => $data['full_name'],
                'ci' => $data['ci'],
                'phone' => $data['phone'],
                'email' => $data['email'],
                'address' => $data['address'],
                'regional' => $data['regional'],
                'institution' => $data['institution'],
                'position' => $data['position'],
                'photo_path' => $photoPath,
                'birth_date' => $data['birth_date'],
                'marital_status' => $data['marital_status'],
                'status' => 'pendiente_pago',
            ]);

            return PublicAffiliationRequest::create([
                'person_id' => $person->id,
                'affiliate_id' => $affiliate->id,
                'user_id' => $user->id,
                'sector_id' => $data['sector_id'],
                'affiliation_plan_id' => $plan->id,
                'public_token' => (string) Str::uuid(),
                'request_code' => 'SOL-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
                'amount_due' => $plan->total_amount,
                'status' => 'pending_payment',
                'submitted_at' => now(),
                'ip_address' => $ip,
                'user_agent' => Str::limit($userAgent, 1000, ''),
            ]);
        });
    }

    public function submitPayment(PublicAffiliationRequest $request, array $data, ?string $receiptPath): AffiliationPayment
    {
        return DB::transaction(function () use ($request, $data, $receiptPath) {
            $request = PublicAffiliationRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();
            if (in_array($request->status, ['approved', 'cancelled'], true)) {
                throw ValidationException::withMessages(['transaction_number' => 'Esta solicitud ya no admite pagos.']);
            }

            $payment = AffiliationPayment::updateOrCreate(
                ['public_affiliation_request_id' => $request->id],
                [
                    'affiliate_id' => $request->affiliate_id,
                    'affiliation_plan_id' => $request->affiliation_plan_id,
                    'amount' => $data['paid_amount'],
                    'expected_amount' => $request->amount_due,
                    'paid_amount' => $data['paid_amount'],
                    'transaction_number' => $data['transaction_number'],
                    'payment_date' => $data['payment_date'],
                    'payment_method' => 'transferencia',
                    'bank_name' => $data['bank_name'] ?? null,
                    'payer_name' => $data['payer_name'],
                    'voucher_path' => $receiptPath ?: $request->payment?->voucher_path,
                    'observations' => $data['observations'] ?? null,
                    'status' => 'pending',
                    'submitted_at' => now(),
                ]
            );
            $request->update(['status' => 'payment_submitted', 'payment_submitted_at' => now(), 'rejection_reason' => null]);
            $request->affiliate->update(['status' => 'pago_en_revision']);

            return $payment;
        });
    }
}
