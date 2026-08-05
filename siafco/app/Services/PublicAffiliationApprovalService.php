<?php

namespace App\Services;

use App\Models\AffiliationPayment;
use App\Models\PublicAffiliationRequest;
use App\Models\User;

class PublicAffiliationApprovalService
{
    public function __construct(private ?CredentialService $credentials = null) {}

    public function take(PublicAffiliationRequest $request, int $reviewerId): void
    {
        $request->update(['status' => 'under_review', 'reviewed_by' => $reviewerId, 'reviewed_at' => now()]);
        $request->payment?->update(['status' => 'under_review']);
    }

    public function approve(AffiliationPayment $payment, int $reviewerId): void
    {
        app(PaymentLifecycleService::class)->confirm($payment, User::findOrFail($reviewerId));
    }

    public function reject(AffiliationPayment $payment, int $reviewerId, string $reason): void
    {
        app(PaymentLifecycleService::class)->reject($payment, User::findOrFail($reviewerId), $reason);
    }
}
