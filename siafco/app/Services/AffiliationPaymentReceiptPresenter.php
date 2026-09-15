<?php

namespace App\Services;

use App\Models\AffiliationPayment;
use App\Support\AffiliationStatusPresenter;
use App\Support\PaymentMethodPresenter;
use App\Support\PaymentStatus;

class AffiliationPaymentReceiptPresenter
{
    public function present(AffiliationPayment $payment): array
    {
        $payment->loadMissing(
            'affiliate.person',
            'affiliate.sector',
            'affiliate.plan',
            'publicRequest.person',
            'publicRequest.sector',
            'publicRequest.plan',
            'registrar',
            'cashier',
            'plan'
        );

        $affiliate = $payment->affiliate;
        $request = $payment->publicRequest;
        $person = $affiliate?->person ?? $request?->person;
        $date = $payment->confirmed_at ?? $payment->paid_at ?? $payment->payment_date ?? $payment->created_at;
        $registrationNumber = $affiliate?->registration_number;

        return [
            'payment' => $payment,
            'affiliate' => $affiliate,
            'request' => $request,
            'person_name' => $affiliate?->full_name ?? $person?->full_name ?? $payment->payer_name ?? 'Persona no disponible',
            'person_ci' => $affiliate?->ci ?? $person?->ci ?? 'No disponible',
            'request_code' => $request?->request_code,
            'registration_number' => $registrationNumber ?: 'PENDIENTE DE APROBACION',
            'has_registration_number' => filled($registrationNumber),
            'affiliation_status_label' => $registrationNumber && $affiliate?->status === 'activo'
                ? 'AFILIADO ACTIVO'
                : 'AFILIACION PENDIENTE',
            'payment_status_label' => PaymentStatus::label($payment->status),
            'payment_status_classes' => PaymentStatus::badgeClasses($payment->status),
            'payment_status_color' => $this->statusColor($payment->status),
            'affiliation_status_source' => $affiliate ? AffiliationStatusPresenter::forAffiliate($affiliate) : null,
            'sector_name' => $affiliate?->sector?->name ?? $request?->sector?->name ?? 'Sin sector',
            'plan_name' => $affiliate?->plan?->name ?? $request?->plan?->name ?? $payment->plan?->name ?? 'Sin plan',
            'method_label' => $this->methodLabel($payment),
            'amount' => (float) ($payment->paid_amount ?? $payment->amount),
            'date' => $date,
            'registered_by' => $payment->registrar?->name ?? 'No registrado',
            'confirmed_by' => $payment->cashier?->name ?? 'No confirmado',
        ];
    }

    private function methodLabel(AffiliationPayment $payment): string
    {
        if ($payment->source === 'office_qr') {
            return 'QR / Transferencia en oficina';
        }

        if ($payment->source === 'office_cash') {
            return 'Efectivo / Pago en oficina';
        }

        if ($payment->source === 'manual_admin') {
            return PaymentMethodPresenter::label($payment->payment_method).' / Registro administrativo';
        }

        return PaymentMethodPresenter::label($payment->payment_method);
    }

    private function statusColor(?string $status): string
    {
        if (PaymentStatus::isConfirmed($status)) {
            return '#166534';
        }

        if (PaymentStatus::isRejected($status) || PaymentStatus::isVoided($status)) {
            return '#991b1b';
        }

        return '#92400e';
    }
}
