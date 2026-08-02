<?php

namespace App\Http\Resources\Api\Mobile\V1;

use App\Models\InstitutionalSetting;
use App\Support\AffiliationStatusPresenter;
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
                'status_label' => AffiliationStatusPresenter::label($payment->status),
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
            ],
            'capabilities' => [
                'can_submit_payment' => in_array($this->status, ['pending_payment', 'payment_submitted', 'rejected'], true),
                'can_login' => true,
                'can_view_credential' => $this->affiliate?->status === 'activo',
            ],
        ];
    }
}
