<?php

namespace App\Services;

use App\Models\AffiliationPayment;

class PaymentReceiptNumberService
{
    public function assignIfMissing(AffiliationPayment $payment): string
    {
        if (filled($payment->receipt_number)) {
            return $payment->receipt_number;
        }

        $payment->forceFill([
            'receipt_number' => $this->nextReceiptNumber(),
        ])->save();

        return $payment->receipt_number;
    }

    public function nextReceiptNumber(): string
    {
        $year = now()->format('Y');
        $prefix = "REC-{$year}-";
        $latest = AffiliationPayment::query()
            ->where('receipt_number', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('receipt_number')
            ->value('receipt_number');

        $next = $latest && preg_match('/^REC-\d{4}-(\d{6})$/', $latest, $matches)
            ? ((int) $matches[1]) + 1
            : 1;

        do {
            $number = $prefix.str_pad((string) $next++, 6, '0', STR_PAD_LEFT);
        } while (AffiliationPayment::where('receipt_number', $number)->exists());

        return $number;
    }
}
