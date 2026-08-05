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
        $payment->loadMissing('affiliate', 'registrar', 'cashier');

        AuditService::record('payment_receipt_generated', $payment, [
            'receipt_number' => $payment->receipt_number,
            'status' => $payment->status,
        ]);

        return Pdf::loadView('payments.receipt', [
            'payment' => $payment,
            'institution' => InstitutionalSetting::current(),
            'statusLabel' => PaymentStatus::label($payment->status),
        ])->setPaper('letter')->output();
    }
}
