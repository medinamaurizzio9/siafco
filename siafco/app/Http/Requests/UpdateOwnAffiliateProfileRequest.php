<?php

namespace App\Http\Requests;

use App\Services\AuditService;
use App\Support\PublicAffiliationCatalogs;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateOwnAffiliateProfileRequest extends FormRequest
{
    private const PROTECTED_FIELDS = [
        'id', 'affiliate_id', 'user_id', 'person_id', 'name', 'full_name', 'ci',
        'ci_complement', 'affiliate_number', 'registration_number', 'status',
        'sector_id', 'institution_id', 'institution', 'regional_id', 'regional',
        'affiliation_plan_id', 'verification_token', 'approved_at', 'created_at',
        'updated_at', 'deleted_at',
    ];

    public function authorize(): bool
    {
        return $this->user()?->hasRole('afiliado') && $this->user()->affiliate !== null;
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
            'marital_status' => ['nullable', 'string', 'max:50', Rule::in(PublicAffiliationCatalogs::MARITAL_STATUSES)],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->containsProtectedFields()) {
                    $validator->errors()->add(
                        'profile',
                        'No tienes autorización para modificar datos institucionales.'
                    );
                }
            },
        ];
    }

    protected function failedValidation(ValidatorContract $validator): void
    {
        if ($this->containsProtectedFields()) {
            $fields = collect(self::PROTECTED_FIELDS)->filter(fn ($field) => $this->exists($field))->values()->all();
            AuditService::record('affiliate_profile_institutional_change_rejected', $this->user()?->affiliate, [
                'attempted_fields' => $fields,
                'user_agent' => mb_substr((string) $this->userAgent(), 0, 500),
            ]);
        }

        parent::failedValidation($validator);
    }

    public function messages(): array
    {
        return [
            'photo.image' => 'El archivo seleccionado no es una imagen válida.',
            'photo.mimes' => 'La fotografía debe ser JPG, JPEG, PNG o WEBP.',
            'photo.max' => 'La fotografía no debe superar los 5 MB.',
            'photo.dimensions' => 'La fotografía debe medir al menos 300 × 300 píxeles.',
            'phone.regex' => 'El celular contiene caracteres no permitidos.',
            'email.unique' => 'El correo electrónico ya está registrado.',
            'birth_date.before' => 'La fecha de nacimiento debe ser anterior a hoy.',
        ];
    }

    private function containsProtectedFields(): bool
    {
        return collect(self::PROTECTED_FIELDS)->contains(fn ($field) => $this->exists($field));
    }
}
