<?php

namespace App\Services;

use App\Models\AffiliationPayment;
use App\Models\User;
use App\Support\PaymentStatus;

class PaymentActionAuthorization
{
    private const REVIEW_ROLES = ['superadministrador', 'administrador', 'gerente'];

    public function canConfirm(?User $user, AffiliationPayment $payment): bool
    {
        return $this->canAuthorizeConfirmation($user, $payment)
            && PaymentStatus::isEditable($payment->status);
    }

    public function canAuthorizeConfirmation(?User $user, AffiliationPayment $payment): bool
    {
        return (bool) ($user?->hasPermission('payments.confirm'))
            && $this->canReviewPayments($user);
    }

    public function canReject(?User $user, AffiliationPayment $payment): bool
    {
        return $this->canAuthorizeRejection($user, $payment)
            && ! PaymentStatus::isConfirmed($payment->status)
            && ! PaymentStatus::isVoided($payment->status);
    }

    public function canAuthorizeRejection(?User $user, AffiliationPayment $payment): bool
    {
        return (bool) ($user?->hasPermission('payments.reject'))
            && $this->canReviewPayments($user);
    }

    private function canReviewPayments(?User $user): bool
    {
        return (bool) ($user?->isInternal() && $user->hasRole(self::REVIEW_ROLES));
    }
}
