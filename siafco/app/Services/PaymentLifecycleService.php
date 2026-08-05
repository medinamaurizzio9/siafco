<?php

namespace App\Services;

use App\Events\PaymentConfirmed;
use App\Events\PaymentRejected;
use App\Events\PaymentVoided;
use App\Events\AffiliateActivated;
use App\Events\CredentialActivated;
use App\Events\CredentialCreated;
use App\Models\Affiliate;
use App\Models\AffiliationPayment;
use App\Models\PublicAffiliationRequest;
use App\Models\Sector;
use App\Models\User;
use App\Support\PaymentStatus;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PaymentLifecycleService
{
    public function __construct(
        private CredentialService $credentials,
        private PaymentBalanceService $balances
    ) {
    }

    public function createManual(Affiliate $affiliate, array $data, ?UploadedFile $voucher, User $actor): AffiliationPayment
    {
        $voucherPath = $this->storeVoucher($voucher);

        try {
            return DB::transaction(function () use ($affiliate, $data, $voucherPath, $actor) {
                $affiliate = Affiliate::with('plan', 'publicRequest')->whereKey($affiliate->id)->lockForUpdate()->firstOrFail();
                $plan = $affiliate->plan;
                $request = $affiliate->publicRequest;

                $payment = AffiliationPayment::create([
                    'affiliate_id' => $affiliate->id,
                    'public_affiliation_request_id' => $request?->id,
                    'affiliation_plan_id' => $affiliate->affiliation_plan_id,
                    'amount' => $data['amount'],
                    'expected_amount' => $plan?->total_amount ?? $data['amount'],
                    'paid_amount' => $data['amount'],
                    'currency' => $data['currency'] ?? $plan?->currency ?? 'BOB',
                    'payment_method' => $data['payment_method'],
                    'bank_name' => $data['bank_name'] ?? null,
                    'reference_number' => $data['reference_number'] ?? null,
                    'transaction_number' => $data['transaction_number'] ?? null,
                    'observations' => $data['observations'] ?? null,
                    'voucher_path' => $voucherPath,
                    'payment_date' => $data['paid_at']->toDateString(),
                    'paid_at' => $data['paid_at'],
                    'submitted_at' => now(),
                    'status' => $data['status'] ?? PaymentStatus::PENDING,
                    'source' => 'manual_admin',
                    'registered_by' => $actor->id,
                ]);

                if ($request && in_array($payment->status, [PaymentStatus::PENDING, PaymentStatus::UNDER_REVIEW], true)) {
                    $request->update([
                        'status' => $payment->status === PaymentStatus::UNDER_REVIEW ? 'payment_submitted' : 'pending_payment',
                        'payment_submitted_at' => $payment->status === PaymentStatus::UNDER_REVIEW ? now() : $request->payment_submitted_at,
                    ]);
                }

                AuditService::record('payment_manual_created', $payment, [
                    'affiliate_id' => $affiliate->id,
                    'amount' => (string) $payment->amount,
                    'status' => $payment->status,
                ]);

                return $payment;
            });
        } catch (\Throwable $exception) {
            if ($voucherPath) {
                Storage::disk('local')->delete($voucherPath);
            }

            throw $exception;
        }
    }

    public function updatePending(AffiliationPayment $payment, array $data, ?UploadedFile $voucher, User $actor): AffiliationPayment
    {
        $payment = AffiliationPayment::whereKey($payment->id)->firstOrFail();
        if (! PaymentStatus::isEditable($payment->status)) {
            throw ValidationException::withMessages(['payment' => 'Solo se pueden editar pagos pendientes o en revision.']);
        }

        $voucherPath = $this->storeVoucher($voucher);
        $oldVoucherPath = $payment->voucher_path;
        $oldValues = $payment->only(['amount', 'currency', 'payment_method', 'bank_name', 'reference_number', 'transaction_number', 'payment_date', 'paid_at', 'observations', 'status']);

        try {
            DB::transaction(function () use ($payment, $data, $voucherPath): void {
                $updates = [
                    'amount' => $data['amount'],
                    'paid_amount' => $data['amount'],
                    'currency' => $data['currency'] ?? $payment->currency ?? 'BOB',
                    'payment_method' => $data['payment_method'],
                    'bank_name' => $data['bank_name'] ?? null,
                    'reference_number' => $data['reference_number'] ?? null,
                    'transaction_number' => $data['transaction_number'] ?? null,
                    'observations' => $data['observations'] ?? null,
                    'payment_date' => $data['paid_at']->toDateString(),
                    'paid_at' => $data['paid_at'],
                    'status' => $data['status'] ?? $payment->status,
                ];

                if ($voucherPath) {
                    $updates['voucher_path'] = $voucherPath;
                }

                $payment->update($updates);
            });
        } catch (\Throwable $exception) {
            if ($voucherPath) {
                Storage::disk('local')->delete($voucherPath);
            }

            throw $exception;
        }

        if ($voucherPath && $oldVoucherPath) {
            Storage::disk('local')->delete($oldVoucherPath);
        }

        AuditService::record('payment_updated', $payment, [
            'old_values' => $this->safeAuditValues($oldValues),
            'new_values' => $this->safeAuditValues($payment->only(array_keys($oldValues))),
        ]);

        return $payment->refresh();
    }

    public function confirm(AffiliationPayment $payment, User $actor): AffiliationPayment
    {
        $events = [];
        $confirmedPayment = DB::transaction(function () use ($payment, $actor, &$events) {
            $payment = AffiliationPayment::with('affiliate.plan', 'affiliate.sector', 'publicRequest')
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->firstOrFail();
            $previousStatus = $payment->status;

            if (PaymentStatus::isConfirmed($payment->status)) {
                throw ValidationException::withMessages(['payment' => 'El pago ya fue confirmado.']);
            }

            if (! PaymentStatus::isEditable($payment->status)) {
                throw ValidationException::withMessages(['payment' => 'El pago no se puede confirmar en su estado actual.']);
            }

            $affiliate = Affiliate::with('plan', 'sector', 'credential', 'user')
                ->whereKey($payment->affiliate_id)
                ->lockForUpdate()
                ->firstOrFail();
            $request = $payment->public_affiliation_request_id
                ? PublicAffiliationRequest::whereKey($payment->public_affiliation_request_id)->lockForUpdate()->first()
                : null;
            $previousBalance = $this->balances->summary($affiliate);

            $payment->update([
                'status' => PaymentStatus::CONFIRMED,
                'confirmed_by' => $actor->id,
                'confirmed_at' => $payment->confirmed_at ?: now(),
                'paid_at' => $payment->paid_at ?: now(),
                'paid_amount' => $payment->paid_amount ?? $payment->amount,
                'rejected_by' => null,
                'rejected_at' => null,
                'rejection_reason' => null,
                'receipt_number' => $payment->receipt_number ?: $this->nextReceiptNumber(),
            ]);

            if (! $affiliate->registration_number && $affiliate->sector_id) {
                $sector = Sector::whereKey($affiliate->sector_id)->lockForUpdate()->first();
                if ($sector) {
                    $sector->increment('current_sequence');
                    $affiliate->registration_number = sprintf('%s-%06d', mb_strtoupper($sector->code), $sector->fresh()->current_sequence);
                }
            }

            $affiliate->verification_token ??= (string) Str::uuid();
            $newBalance = $this->balances->summary($affiliate->fresh('plan'));
            $covered = $newBalance['pending_balance'] <= 0;

            if ($covered && $affiliate->status !== 'activo') {
                $affiliate->status = 'activo';
                $affiliate->save();
            }

            if ($request) {
                $request->update([
                    'status' => $covered ? 'approved' : 'payment_submitted',
                    'payment_submitted_at' => $request->payment_submitted_at ?: now(),
                    'reviewed_by' => $covered ? $actor->id : $request->reviewed_by,
                    'reviewed_at' => $covered ? now() : $request->reviewed_at,
                    'rejection_reason' => null,
                ]);
            }

            $credential = null;
            $credentialWasExisting = false;
            if ($covered) {
                $credentialWasExisting = $affiliate->credential()->exists();
                $credential = $this->credentials->generate($affiliate->fresh('sector', 'credential'));
            }

            $metadata = [
                'payment_public_id' => 'PAY-'.$payment->id,
                'affiliate_id' => $affiliate->id,
                'actor_id' => $actor->id,
                'amount' => (string) ($payment->paid_amount ?? $payment->amount),
                'currency' => $payment->currency ?? 'BOB',
                'previous_balance' => $previousBalance['pending_balance'],
                'new_balance' => $newBalance['pending_balance'],
                'previous_status' => $previousStatus,
                'new_status' => PaymentStatus::CONFIRMED,
                'credential' => $credential ? ($credentialWasExisting ? 'reused' : 'created') : 'not_created',
                'public_request_updated' => (bool) $request,
            ];

            $events[] = new PaymentConfirmed($payment->id, $affiliate->id, $actor->id, PaymentStatus::CONFIRMED, $metadata);
            if ($covered) {
                $events[] = new AffiliateActivated($affiliate->id, $actor->id, ['payment_id' => $payment->id]);
            }
            if ($credential?->getKey()) {
                $events[] = $credentialWasExisting
                    ? new CredentialActivated($credential->id, $affiliate->id, $actor->id, ['payment_id' => $payment->id])
                    : new CredentialCreated($credential->id, $affiliate->id, $actor->id, ['payment_id' => $payment->id]);
            }

            return $payment->fresh('affiliate', 'publicRequest');
        });

        foreach ($events as $event) {
            $this->dispatchDomainEvent($event);
        }
        $this->clearCaches();

        return $confirmedPayment;
    }

    public function reject(AffiliationPayment $payment, User $actor, string $reason): AffiliationPayment
    {
        $event = null;
        DB::transaction(function () use ($payment, $actor, $reason, &$event): void {
            $payment = AffiliationPayment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if (PaymentStatus::isConfirmed($payment->status) || PaymentStatus::isVoided($payment->status)) {
                throw ValidationException::withMessages(['payment' => 'El pago no se puede rechazar en su estado actual.']);
            }

            $payment->update([
                'status' => PaymentStatus::REJECTED,
                'rejected_by' => $actor->id,
                'rejected_at' => now(),
                'confirmed_by' => null,
                'confirmed_at' => null,
                'rejection_reason' => $reason,
            ]);

            $payment->publicRequest?->update([
                'status' => 'rejected',
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'rejection_reason' => $reason,
            ]);

            $payment->affiliate?->update(['status' => 'observado']);
            $event = new PaymentRejected($payment->id, $payment->affiliate_id, $actor->id, PaymentStatus::REJECTED, [
                'reason' => $reason,
            ]);
        });

        if ($event) {
            $this->dispatchDomainEvent($event);
        }
        $this->clearCaches();

        return $payment->fresh();
    }

    public function void(AffiliationPayment $payment, User $actor, string $reason): AffiliationPayment
    {
        $event = null;
        DB::transaction(function () use ($payment, $actor, $reason, &$event): void {
            $payment = AffiliationPayment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if (! PaymentStatus::isConfirmed($payment->status)) {
                throw ValidationException::withMessages(['payment' => 'Solo se pueden anular pagos confirmados.']);
            }

            $payment->update([
                'status' => PaymentStatus::VOIDED,
                'voided_by' => $actor->id,
                'voided_at' => now(),
                'void_reason' => $reason,
            ]);

            $affiliate = $payment->affiliate()->with('plan')->lockForUpdate()->firstOrFail();
            if ($this->balances->balance($affiliate) > 0) {
                $affiliate->update(['status' => 'pendiente_pago']);
                $payment->publicRequest?->update(['status' => 'pending_payment']);
            }

            $event = new PaymentVoided($payment->id, $affiliate->id, $actor->id, PaymentStatus::VOIDED, [
                'reason' => $reason,
            ]);
        });

        if ($event) {
            $this->dispatchDomainEvent($event);
        }
        $this->clearCaches();

        return $payment->fresh();
    }

    private function storeVoucher(?UploadedFile $voucher): ?string
    {
        if (! $voucher) {
            return null;
        }

        $extension = strtolower($voucher->extension() ?: $voucher->guessExtension() ?: 'bin');
        $name = (string) Str::uuid().'.'.$extension;

        return $voucher->storeAs('payments/vouchers', $name, 'local');
    }

    private function safeAuditValues(array $values): array
    {
        unset($values['voucher_path']);

        return $values;
    }

    private function nextReceiptNumber(): string
    {
        do {
            $number = 'REC-'.now()->format('Ymd').'-'.Str::upper(Str::random(8));
        } while (AffiliationPayment::where('receipt_number', $number)->exists());

        return $number;
    }

    private function clearCaches(): void
    {
        Cache::forget('dashboard.metrics');
        Cache::forget('reports.affiliation.summary');
    }

    private function dispatchDomainEvent(object $event): void
    {
        try {
            event($event);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}
