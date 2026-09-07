<?php

namespace App\Http\Requests\Investments;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Support\InvestmentProspectServiceRating;

class StoreInvestmentProspectPublicRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'full_name' => trim((string) $this->input('full_name')),
            'phone' => trim((string) $this->input('phone')),
        ]);
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:40', 'regex:/^[+0-9()\s-]+$/'],
            'requested_shares' => ['required', 'integer', 'min:1'],
            'preferred_contact_method' => ['required', Rule::in(['whatsapp', 'call'])],
            'service_rating' => ['nullable', Rule::in(InvestmentProspectServiceRating::values())],
        ];
    }
}
