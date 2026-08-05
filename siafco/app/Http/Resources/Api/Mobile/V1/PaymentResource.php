<?php

namespace App\Http\Resources\Api\Mobile\V1;

use App\Support\PaymentStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'public_id' => 'PAY-'.$this->id,
            'amount' => (float) ($this->paid_amount ?? $this->amount),
            'currency' => $this->currency ?? 'BOB',
            'method' => $this->payment_method,
            'reference' => $this->reference_number ?: $this->transaction_number,
            'status' => $this->status,
            'status_label' => PaymentStatus::label($this->status),
            'paid_at' => $this->paid_at?->toISOString() ?? $this->payment_date?->toDateString(),
            'confirmed_at' => $this->confirmed_at?->toISOString(),
            'receipt_available' => (bool) $this->voucher_path,
            'source' => $this->source ?: 'web',
            'rejection_reason' => PaymentStatus::isRejected($this->status) ? $this->rejection_reason : null,
        ];
    }
}
