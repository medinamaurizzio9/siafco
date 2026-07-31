<?php

namespace App\Http\Requests\Api\Mobile\V1;

use App\Services\AuditService;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateProfileRequest extends MobileFormRequest
{
    private const PROTECTED_FIELDS = [
        'id', 'affiliate_id', 'user_id', 'person_id', 'name', 'full_name', 'ci',
        'ci_complement', 'issued_in', 'affiliate_number', 'registration_number',
        'status', 'sector_id', 'institution_id', 'institution', 'regional_id',
        'regional', 'position', 'affiliation_plan_id', 'verification_token',
        'approved_at', 'created_at', 'updated_at', 'deleted_at', 'role',
        'user_type', 'is_active', 'must_change_password',
    ];

    public function authorize(): bool
    {
        return $this->user()?->user_type === 'affiliate'
            && $this->user()?->role === 'afiliado'
            && $this->user()?->affiliate !== null;
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];
        foreach (['phone', 'address', 'marital_status'] as $field) {
            if ($this->exists($field)) {
                $normalized[$field] = $this->filled($field)
                    ? preg_replace('/\s+/u', ' ', trim((string) $this->input($field)))
                    : null;
            }
        }

        if ($this->exists('email')) {
            $normalized['email'] = $this->filled('email')
                ? mb_strtolower(trim((string) $this->input('email')))
                : null;
        }

        $this->merge($normalized);
    }

    public function rules(): array
    {
        $affiliate = $this->user()?->affiliate;

        return [
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:min_width=300,min_height=300'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+().\s-]*$/'],
            'email' => [
                'required', 'email:rfc', 'max:150',
                Rule::unique('affiliates', 'email')->ignore($affiliate?->id),
                Rule::unique('users', 'email')->ignore($this->user()?->id),
            ],
            'address' => ['nullable', 'string', 'max:255'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'marital_status' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->containsProtectedFields()) {
                    $validator->errors()->add('profile', 'No tienes autorización para modificar datos administrativos.');
                }
            },
        ];
    }

    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator): void
    {
        if ($this->containsProtectedFields()) {
            AuditService::record('mobile_affiliate_profile_protected_fields_rejected', $this->user()?->affiliate, [
                'attempted_fields' => collect(self::PROTECTED_FIELDS)
                    ->filter(fn ($field) => $this->exists($field))
                    ->values()
                    ->all(),
            ]);
        }

        parent::failedValidation($validator);
    }

    private function containsProtectedFields(): bool
    {
        return collect(self::PROTECTED_FIELDS)->contains(fn ($field) => $this->exists($field));
    }
}
