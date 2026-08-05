<?php

namespace App\Http\Requests;

use App\Models\Affiliate;
use App\Support\PublicAffiliationCatalogs;
use App\Support\TextNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAffiliatePersonalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('affiliates.update_personal') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $data = $this->only(['phone', 'email', 'address', 'birth_date', 'marital_status']);
        if (array_key_exists('email', $data) && $data['email'] !== null) {
            $data['email'] = mb_strtolower(trim((string) $data['email']));
        }

        $this->merge(TextNormalizer::fields($data, ['address', 'marital_status']));
    }

    public function rules(): array
    {
        /** @var Affiliate|null $affiliate */
        $affiliate = $this->route('affiliate');

        return [
            'phone' => ['nullable', 'string', 'max:40', 'regex:/^[0-9+().\s-]*$/'],
            'email' => [
                'required',
                'email:rfc',
                'max:255',
                Rule::unique('affiliates', 'email')->ignore($affiliate),
                Rule::unique('users', 'email')->ignore($affiliate?->user_id),
            ],
            'address' => ['nullable', 'string', 'max:255'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'marital_status' => ['nullable', 'string', 'max:80', Rule::in(PublicAffiliationCatalogs::MARITAL_STATUSES)],
        ];
    }
}
