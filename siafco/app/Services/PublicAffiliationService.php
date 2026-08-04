<?php

namespace App\Services;

use App\Models\Affiliate;
use App\Models\AffiliationPayment;
use App\Models\AffiliationPlan;
use App\Models\Person;
use App\Models\PublicAffiliationRequest;
use App\Models\User;
use App\Support\TextNormalizer;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PublicAffiliationService
{
    public function register(array $data, ?string $photoPath, string $ip, ?string $userAgent): PublicAffiliationRequest
    {
        $data = TextNormalizer::fields($data, [
            'full_name', 'ci_complement', 'issued_in', 'address', 'regional',
            'institution', 'position', 'marital_status',
        ]);
        $data['email'] = TextNormalizer::lowercaseEmail($data['email'] ?? null);
        $data['phone'] = TextNormalizer::squish($data['phone'] ?? null);

        try {
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
                        ->whereNotIn('status', ['rejected', 'cancelled'])
                        ->latest()
                        ->first();

                    throw ValidationException::withMessages([
                        'ci' => $pending
                            ? 'Ya existe una solicitud para este CI. Código: '.$pending->request_code
                            : 'Este CI ya pertenece a un afiliado.',
                    ]);
                }

                if ($person && ! $this->personMatchesRegistration($person, $data)) {
                    throw ValidationException::withMessages([
                        'ci' => 'El CI ya existe con datos distintos. La solicitud requiere revisión administrativa.',
                    ]);
                }

                $existingUser = User::query()
                    ->where('email', $data['email'])
                    ->when($person, fn ($query) => $query->orWhere('person_id', $person->id))
                    ->lockForUpdate()
                    ->first();

                if ($existingUser) {
                    throw ValidationException::withMessages([
                        'email' => 'Ya existe una cuenta asociada a estos datos.',
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

                $user = User::create([
                    'person_id' => $person->id,
                    'name' => $data['full_name'],
                    'email' => $data['email'],
                    'role' => 'afiliado',
                    'user_type' => 'affiliate',
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

                $application = PublicAffiliationRequest::create([
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
                    'terms_accepted_at' => now(),
                    'privacy_accepted_at' => now(),
                    'terms_version' => config('siafco.terms_version', '2026.1'),
                    'privacy_version' => config('siafco.privacy_version', '2026.1'),
                    'acceptance_ip' => $ip,
                    'acceptance_user_agent' => Str::limit($userAgent, 1000, ''),
                ]);
                $application->setAttribute('mobile_user_created', true);

                return $application;
            });
        } catch (QueryException $exception) {
            if ($this->isDuplicateConstraint($exception)) {
                throw ValidationException::withMessages([
                    'ci' => 'Ya existe una cuenta o solicitud asociada a estos datos.',
                    'email' => 'Ya existe una cuenta o solicitud asociada a estos datos.',
                ]);
            }

            throw $exception;
        }
    }

    public function submitPayment(PublicAffiliationRequest $request, array $data, ?string $receiptPath): AffiliationPayment
    {
        $data = TextNormalizer::fields($data, ['bank_name', 'payer_name', 'observations']);

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

    private function personMatchesRegistration(Person $person, array $data): bool
    {
        $sameName = $this->normalized($person->full_name) === $this->normalized($data['full_name']);
        $sameEmail = $person->email === null || strcasecmp((string) $person->email, (string) $data['email']) === 0;
        $sameBirthDate = $person->birth_date === null || $person->birth_date->toDateString() === $data['birth_date'];

        return $sameName && $sameEmail && $sameBirthDate;
    }

    private function normalized(?string $value): string
    {
        return TextNormalizer::uppercase($value) ?? '';
    }

    private function isDuplicateConstraint(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? '');
        $driverCode = (string) ($exception->errorInfo[1] ?? '');

        return in_array($sqlState, ['23000', '23505'], true)
            || in_array($driverCode, ['1062', '1555', '19'], true);
    }
}
