<?php

namespace App\Http\Requests\Investments;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvestmentAdvisorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'full_name' => trim((string) $this->input('full_name')),
            'phone' => $this->normalizePhone($this->input('phone')),
            'email' => filled($this->input('email')) ? trim((string) $this->input('email')) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id'), Rule::unique('investment_advisors', 'user_id')],
            'is_active' => ['required', 'boolean'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
            'remove_photo' => ['nullable', 'boolean'],
        ];
    }

    private function normalizePhone(mixed $phone): string
    {
        $phone = trim((string) $phone);
        $hasPlus = str_starts_with($phone, '+');
        $digits = preg_replace('/[^0-9]/', '', $phone) ?? '';

        return ($hasPlus && $digits !== '' ? '+' : '').$digits;
    }
}
