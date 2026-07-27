<?php

namespace App\Services;

use App\Models\AffiliationPayment;
use App\Models\PublicAffiliationRequest;
use App\Models\Sector;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PublicAffiliationApprovalService
{
    public function __construct(private CredentialService $credentials) {}

    public function take(PublicAffiliationRequest $request, int $reviewerId): void
    {
        $request->update(['status' => 'under_review', 'reviewed_by' => $reviewerId, 'reviewed_at' => now()]);
        $request->payment?->update(['status' => 'under_review']);
    }

    public function approve(AffiliationPayment $payment, int $reviewerId): void
    {
        $affiliate = DB::transaction(function () use ($payment, $reviewerId) {
            $payment = AffiliationPayment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if (! in_array($payment->status, ['pending', 'under_review'], true)) {
                throw ValidationException::withMessages(['payment' => 'El pago ya fue procesado.']);
            }

            $request = PublicAffiliationRequest::whereKey($payment->public_affiliation_request_id)->lockForUpdate()->firstOrFail();
            $affiliate = $request->affiliate()->lockForUpdate()->firstOrFail();
            $sector = Sector::whereKey($request->sector_id)->lockForUpdate()->firstOrFail();

            if (! $affiliate->registration_number) {
                $sector->increment('current_sequence');
                $affiliate->registration_number = sprintf('%s-%06d', Str::upper($sector->code), $sector->fresh()->current_sequence);
            }
            $affiliate->verification_token ??= (string) Str::uuid();
            $affiliate->status = 'activo';
            $affiliate->save();

            $payment->update([
                'status' => 'confirmed', 'confirmed_by' => $reviewerId,
                'confirmed_at' => now(), 'rejection_reason' => null,
            ]);
            $request->update([
                'status' => 'approved', 'reviewed_by' => $reviewerId,
                'reviewed_at' => now(), 'rejection_reason' => null,
            ]);
            AuditService::record('autoafiliacion.pago_confirmado', $payment, ['request_code' => $request->request_code]);

            return $affiliate;
        });

        $this->credentials->generate($affiliate);
        Cache::forget('dashboard.metrics');
        Cache::forget('reports.affiliation.summary');
    }

    public function reject(AffiliationPayment $payment, int $reviewerId, string $reason): void
    {
        DB::transaction(function () use ($payment, $reviewerId, $reason) {
            $payment = AffiliationPayment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            abort_if($payment->status === 'confirmed', 409, 'Un pago confirmado no puede rechazarse.');
            $payment->update(['status' => 'rejected', 'confirmed_by' => $reviewerId, 'confirmed_at' => now(), 'rejection_reason' => $reason]);
            $payment->publicRequest->update(['status' => 'rejected', 'reviewed_by' => $reviewerId, 'reviewed_at' => now(), 'rejection_reason' => $reason]);
            $payment->affiliate->update(['status' => 'rechazado']);
            AuditService::record('autoafiliacion.pago_rechazado', $payment);
        });
    }
}
