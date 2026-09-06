<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RejectCashDepositRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) ($this->user()?->isInternal()
            && $this->user()->hasRole(['superadministrador', 'administrador', 'gerente'])
            && $this->user()->hasPermission('cash_deposits.reject'));
    }

    public function rules(): array
    {
        return ['rejection_reason' => ['required', 'string', 'min:5', 'max:500']];
    }
}
