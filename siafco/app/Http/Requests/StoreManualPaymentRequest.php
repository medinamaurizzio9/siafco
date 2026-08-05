<?php

namespace App\Http\Requests;

use App\Models\AffiliationPayment;
use App\Support\PaymentStatus;
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
        $data['paid_at'] = Carbon::parse($data['paid_at']);

        return $data;
    }

    protected function baseRules(): array
    {
        return [
            'amount' => ['required', 'regex:/^\d{1,8}(\.\d{1,2})?$/', 'numeric', 'min:0.01'],
            'currency' => ['required', Rule::in(['BOB'])],
            'paid_at' => ['required', 'date'],
            'payment_method' => ['required', Rule::in(['efectivo', 'qr', 'transferencia', 'deposito', 'pos', 'otro'])],
            'bank_name' => ['nullable', 'string', 'max:120'],
            'reference_number' => ['nullable', 'string', 'max:120'],
            'transaction_number' => ['nullable', 'string', 'max:120'],
            'observations' => ['nullable', 'string', 'max:500'],
            'status' => ['required', Rule::in([PaymentStatus::PENDING, PaymentStatus::UNDER_REVIEW])],
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

            if ($validator->errors()->isEmpty() && ! $this->boolean('duplicate_confirmed') && $this->filled(['affiliate_id', 'amount', 'paid_at'])) {
                $possibleDuplicate = AffiliationPayment::query()
                    ->when($this->route('payment'), fn ($query, $payment) => $query->whereKeyNot($payment->id))
                    ->where('affiliate_id', $this->integer('affiliate_id'))
                    ->where('amount', $this->input('amount'))
                    ->whereDate('paid_at', Carbon::parse($this->input('paid_at'))->toDateString())
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
