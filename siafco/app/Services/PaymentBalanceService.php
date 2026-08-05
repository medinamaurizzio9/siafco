<?php

namespace App\Services;

use App\Models\Affiliate;
use App\Models\AffiliationPayment;
use App\Support\PaymentStatus;

class PaymentBalanceService
{
    public function summary(Affiliate $affiliate): array
    {
        $affiliate->loadMissing('plan', 'credential');
        $payments = $affiliate->payments()->latest()->get();
        $required = (float) ($affiliate->plan?->total_amount ?? $payments->max('expected_amount') ?? 0);
        $confirmed = (float) $payments
            ->whereIn('status', PaymentStatus::confirmedValues())
            ->sum(fn (AffiliationPayment $payment) => (float) ($payment->paid_amount ?? $payment->amount));
        $balance = max(round($required - $confirmed, 2), 0);

        return [
            'required_amount' => $required,
            'confirmed_amount' => $confirmed,
            'pending_balance' => $balance,
            'payment_count' => $payments->count(),
            'latest_payment' => $payments->first(),
            'payment_status' => $this->paymentStatus($payments),
            'credential_status' => $affiliate->credential ? 'generada' : 'no_generada',
        ];
    }

    public function confirmedAmount(Affiliate $affiliate): float
    {
        return (float) $affiliate->payments()
            ->whereIn('status', PaymentStatus::confirmedValues())
            ->get()
            ->sum(fn (AffiliationPayment $payment) => (float) ($payment->paid_amount ?? $payment->amount));
    }

    public function balance(Affiliate $affiliate): float
    {
        $required = (float) ($affiliate->plan?->total_amount ?? $affiliate->payments()->max('expected_amount') ?? 0);

        return max(round($required - $this->confirmedAmount($affiliate), 2), 0);
    }

    private function paymentStatus($payments): string
    {
        if ($payments->whereIn('status', PaymentStatus::voidedValues())->isNotEmpty()) {
            return 'con_anulaciones';
        }

        if ($payments->whereIn('status', PaymentStatus::confirmedValues())->isNotEmpty()) {
            return 'confirmed';
        }

        if ($payments->whereIn('status', PaymentStatus::rejectedValues())->isNotEmpty()) {
            return 'rejected';
        }

        if ($payments->whereIn('status', [PaymentStatus::UNDER_REVIEW])->isNotEmpty()) {
            return 'under_review';
        }

        return $payments->isEmpty() ? 'sin_pagos' : 'pending';
    }
}
