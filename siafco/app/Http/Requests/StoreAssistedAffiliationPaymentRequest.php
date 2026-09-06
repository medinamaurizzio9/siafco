<?php

namespace App\Http\Requests;

use App\Models\PublicAffiliationRequest;
use App\Support\PaymentStatus;
use Illuminate\Validation\Rule;

class StoreAssistedAffiliationPaymentRequest extends StoreManualPaymentRequest
{
    public function authorize(): bool
    {
        return (bool) ($this->user()?->isInternal() && $this->user()->hasPermission('payments.create'));
    }

    protected function prepareForValidation(): void
    {
        $application = $this->route('application');

        $this->merge([
            'affiliate_id' => $application instanceof PublicAffiliationRequest ? $application->affiliate_id : null,
            'currency' => 'BOB',
            'paid_at' => $this->input('paid_at', now()->format('Y-m-d\TH:i')),
            'status' => PaymentStatus::UNDER_REVIEW,
        ]);
    }

    public function rules(): array
    {
        $rules = parent::rules();
        $rules['payment_method'] = ['required', Rule::in(['efectivo', 'qr', 'transferencia'])];

        return $rules;
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if (in_array($this->input('payment_method'), ['qr', 'transferencia'], true)
                && ! $this->filled('reference_number')
                && ! $this->filled('transaction_number')) {
                $validator->errors()->add('reference_number', 'Debe registrar una referencia o numero de transaccion.');
            }

            $application = $this->route('application');
            if (! $application instanceof PublicAffiliationRequest || ! $application->affiliate_id) {
                $validator->errors()->add('payment', 'La solicitud no tiene una preafiliación válida asociada.');

                return;
            }

            if (round((float) $this->input('amount'), 2) !== round((float) $application->amount_due, 2)) {
                $validator->errors()->add('amount', 'El monto debe coincidir con el total esperado de la solicitud.');
            }
        });
    }
}
