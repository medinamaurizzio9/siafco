<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCashDepositRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) ($this->user()?->isInternal()
            && $this->user()->hasRole(['caja', 'cajero'])
            && $this->user()->hasPermission('cash_deposits.create'));
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0'],
            'transaction_number' => ['required', 'string', 'max:120'],
            'deposited_at' => ['required', 'date', 'before_or_equal:now'],
            'voucher' => ['nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp,application/pdf', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'observations' => ['nullable', 'string', 'max:500'],
            'registered_by' => ['prohibited'],
            'status' => ['prohibited'],
        ];
    }
}
