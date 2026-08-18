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

        $institution = InstitutionalSetting::current();

        return Pdf::loadView('payments.receipt', [
            'payment' => $payment,
            'institution' => $institution,
            'statusLabel' => PaymentStatus::label($payment->status),
            'logoSrc' => $this->dataUri($institution->logoAbsolutePath()),
        ])->setPaper('a4')->output();
    }

    private function dataUri(?string $path): ?string
    {
        if (! $path || ! is_file($path)) {
            return null;
        }

        $mime = mime_content_type($path) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($path));
    }
}
