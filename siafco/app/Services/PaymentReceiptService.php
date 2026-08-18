<?php

namespace App\Services;

use App\Models\AffiliationPayment;
use App\Models\InstitutionalSetting;
use App\Support\PaymentStatus;
use Barryvdh\DomPDF\Facade\Pdf;

class PaymentReceiptService
{
    public function output(AffiliationPayment $payment): string
    {
        $payment->loadMissing('affiliate.sector', 'affiliate.plan', 'registrar', 'cashier');

        AuditService::record('receipt_printed', $payment, [
            'payment_id' => $payment->id,
            'receipt_number' => $payment->receipt_number,
            'printed_by' => auth()->id(),
            'printed_at' => now()->toDateTimeString(),
            'status' => $payment->status,
        ]);

        return Pdf::loadView('payments.receipt', [
            'payment' => $payment,
            'institution' => InstitutionalSetting::current(),
            'statusLabel' => PaymentStatus::label($payment->status),
        ])->setPaper('letter')->output();
    }
}
