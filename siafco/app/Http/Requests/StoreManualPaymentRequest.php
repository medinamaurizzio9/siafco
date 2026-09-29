<?php

namespace App\Http\Requests;

use App\Models\AffiliationPayment;
use App\Support\PaymentStatus;
use App\Support\PaymentTransactionNumber;
use App\Support\SiafcoDate;
use App\Support\TextNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class StoreManualPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('payments.create') ?? false;
    }

    public function rules(): array
    {
        return $this->baseRules() + [
            'affiliate_id' => ['required', 'integer', 'exists:affiliates,id'],
        ];
    }

    public function validated($key = null, $default = null): mixed
    {
        $data = parent::validated($key, $default);
        if (! is_array($data)) {
            return $data;
        }

        $data = TextNormalizer::fields($data, ['bank_name', 'observations']);
        $data['reference_number'] = PaymentTransactionNumber::normalize($data['reference_number'] ?? null);
        $data['transaction_number'] = PaymentTransactionNumber::normalize($data['transaction_number'] ?? null);
        $data['payment_timezone'] = SiafcoDate::validTimezoneOrDefault($data['browser_timezone'] ?? null);
        $data['paid_at'] = SiafcoDate::fromLocalInput($data['paid_at'], $data['payment_timezone']);
        unset($data['browser_timezone']);

        return $data;
    }

    protected function baseRules(): array
    {
        return [
            'amount' => ['required', 'regex:/^\d{1,8}(\.\d{1,2})?$/', 'numeric', 'min:0.01'],
            'currency' => ['required', Rule::in(['BOB'])],
            'paid_at' => ['required', 'date', 'before_or_equal:now'],
            'browser_timezone' => ['nullable', 'string', 'timezone'],
            'payment_method' => ['required', Rule::in(['efectivo', 'qr', 'transferencia', 'deposito', 'pos', 'otro'])],
            'bank_name' => ['nullable', 'string', 'max:120'],
            'reference_number' => ['nullable', 'string', 'max:120'],
            'transaction_number' => ['nullable', 'string', 'max:120'],
            'observations' => ['nullable', 'string', 'max:500'],
            'status' => ['sometimes', Rule::in([PaymentStatus::PENDING, PaymentStatus::UNDER_REVIEW])],
            'voucher' => ['nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp,application/pdf', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'duplicate_confirmed' => ['sometimes', 'accepted'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if (in_array($this->input('payment_method'), ['qr', 'transferencia', 'deposito'], true)
                && ! $this->filled('reference_number')
                && ! $this->filled('transaction_number')) {
                $validator->errors()->add('reference_number', 'Debe registrar una referencia o numero de transaccion.');
            }

            $method = (string) $this->input('payment_method');
            $transaction = PaymentTransactionNumber::normalize($this->input('transaction_number'))
                ?? PaymentTransactionNumber::normalize($this->input('reference_number'));
            if (in_array($method, ['qr', 'transferencia'], true)
                && PaymentTransactionNumber::hasDuplicate($transaction, $this->route('payment')?->id)) {
                $validator->errors()->add('transaction_number', PaymentTransactionNumber::duplicateMessage());
            }

            $existingPayment = $this->route('payment');
            if (in_array($this->input('payment_method'), ['qr', 'transferencia', 'deposito'], true)
                && ! $this->hasFile('voucher')
                && ! $existingPayment?->voucher_path) {
                $validator->errors()->add('voucher', 'Adjunta una fotografía o PDF del comprobante de pago.');
            }

            if ($validator->errors()->isEmpty() && ! $this->boolean('duplicate_confirmed') && $this->filled(['affiliate_id', 'amount', 'paid_at'])) {
                [$paidAtDayStart, $paidAtDayEnd] = SiafcoDate::utcDayBounds(
                    SiafcoDate::fromLocalInput($this->input('paid_at'), $this->input('browser_timezone'))->timezone(SiafcoDate::timezone())->toDateString()
                );
                $possibleDuplicate = AffiliationPayment::query()
                    ->when($this->route('payment'), fn ($query, $payment) => $query->whereKeyNot($payment->id))
                    ->where('affiliate_id', $this->integer('affiliate_id'))
                    ->where('amount', $this->input('amount'))
                    ->whereBetween('paid_at', [$paidAtDayStart, $paidAtDayEnd])
                    ->where(function ($query) {
                        $query->when($this->filled('reference_number'), fn ($q) => $q->orWhere('reference_number', $this->input('reference_number')))
                            ->when($this->filled('transaction_number'), fn ($q) => $q->orWhere('transaction_number', $this->input('transaction_number')));
                    })
                    ->exists();

                if ($possibleDuplicate) {
                    $validator->errors()->add('duplicate_confirmed', 'Existe un pago similar. Confirme que desea registrar otro pago legitimo.');
                }
            }
        });
    }
}
