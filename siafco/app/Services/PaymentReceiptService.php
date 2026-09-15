<?php

namespace App\Services;

use App\Models\AffiliationPayment;
use App\Models\InstitutionalSetting;
use Barryvdh\DomPDF\Facade\Pdf;

class PaymentReceiptService
{
    public function __construct(private AffiliationPaymentReceiptPresenter $presenter) {}

    public function output(AffiliationPayment $payment): string
    {
        $receipt = $this->presenter->present($payment);

        AuditService::record('receipt_printed', $payment, [
            'payment_id' => $payment->id,
            'receipt_number' => $payment->receipt_number,
            'printed_by' => auth()->id(),
            'printed_at' => now()->toDateTimeString(),
            'status' => $payment->status,
        ]);

        $institution = InstitutionalSetting::current();

        return Pdf::loadView('payments.receipt', [
            'payment' => $receipt['payment'],
            'receipt' => $receipt,
            'institution' => $institution,
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
