<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSimpleInvestmentContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('simple_investments.contracts.create');
    }

    public function rules(): array
    {
        return ['investor_id' => ['required', 'exists:simple_investment_investors,id'], 'quantity' => ['required', 'integer', 'min:1', 'max:20'], 'registered_on' => ['required', 'date', 'before_or_equal:today'], 'notes' => ['nullable', 'string', 'max:2000']];
    }
}
