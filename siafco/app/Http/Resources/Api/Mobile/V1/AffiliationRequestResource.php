<?php

namespace App\Http\Resources\Api\Mobile\V1;

use App\Models\InstitutionalSetting;
use App\Support\AffiliationStatusPresenter;
use App\Support\PaymentStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AffiliationRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing('plan', 'payment');
        $institution = InstitutionalSetting::current();
        $payment = $this->payment;

        return [
            'request_code' => $this->request_code,
            'status' => $this->status,
            'status_label' => AffiliationStatusPresenter::label($this->status),
            'status_description' => AffiliationStatusPresenter::description($this->status),
            'observations' => $this->rejection_reason,
            'amount_due' => (float) $this->amount_due,
            'currency' => $this->plan?->currency ?? 'BOB',
            'payment_status' => $payment?->status,
            'plan' => $this->plan ? [
                'name' => $this->plan->name,
                'type' => $this->plan->type,
                'affiliation_fee' => (float) $this->plan->affiliation_fee,
                'credential_fee' => (float) $this->plan->credential_fee,
                'total_amount' => $this->plan->total_amount,
                'payment_instructions' => $this->plan->payment_instructions,
            ] : null,
            'payment' => $payment ? [
                'status' => $payment->status,
                'status_label' => PaymentStatus::label($payment->status),
                'transaction_number' => $payment->transaction_number,
                'payment_date' => $payment->payment_date?->toDateString(),
                'paid_amount' => $payment->paid_amount !== null ? (float) $payment->paid_amount : null,
                'submitted_at' => $payment->submitted_at?->toISOString(),
                'rejection_reason' => $payment->rejection_reason,
                'has_receipt' => (bool) $payment->voucher_path,
            ] : null,
            'payment_instructions' => [
                'bank' => $institution->payment_bank,
                'holder' => $institution->payment_holder,
                'account' => $institution->payment_account,
                'instructions' => $this->plan?->payment_instructions ?: $institution->payment_instructions,
                'qr_url' => $institution->paymentQrUrl(),
                'support_phone' => $institution->phone,
            ],
            'capabilities' => [
                'can_submit_payment' => $this->canSubmitPayment($payment?->status),
                'can_login' => true,
                'can_view_credential' => $this->affiliate?->status === 'activo',
            ],
        ];
    }

    private function canSubmitPayment(?string $paymentStatus): bool
    {
        if ($this->status === 'payment_submitted') {
            return false;
        }

        if (in_array($paymentStatus, [...PaymentStatus::pendingValues(), PaymentStatus::UNDER_REVIEW], true)) {
            return false;
        }

        return $paymentStatus === null || PaymentStatus::isRejected($paymentStatus) || in_array($this->status, ['pending_payment', 'rejected'], true);
    }
}
