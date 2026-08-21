<?php

namespace App\Services;

use App\Models\AffiliationPayment;
use App\Models\User;
use App\Support\PaymentStatus;

class PaymentActionAuthorization
{
    private const OFFICE_QR_REVIEW_ROLES = ['superadministrador', 'administrador', 'gerente'];

    public function canConfirm(?User $user, AffiliationPayment $payment): bool
    {
        return $this->canAuthorizeConfirmation($user, $payment)
            && PaymentStatus::isEditable($payment->status);
    }

    public function canAuthorizeConfirmation(?User $user, AffiliationPayment $payment): bool
    {
        return (bool) ($user?->hasPermission('payments.confirm'))
            && $this->canReviewOfficeQr($user, $payment);
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
            && $this->canReviewOfficeQr($user, $payment);
    }

    private function canReviewOfficeQr(?User $user, AffiliationPayment $payment): bool
    {
        if ($payment->source !== 'office_qr') {
            return true;
        }

        return (bool) ($user?->isInternal() && $user->hasRole(self::OFFICE_QR_REVIEW_ROLES));
    }
}
