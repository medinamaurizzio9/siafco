<?php

namespace App\Support;

use Illuminate\Validation\Rule;

final class PublicAffiliationValidation
{
    public static function registrationRules(string $termsField = 'terms', string $privacyField = 'data_processing'): array
    {
        return [
            'full_name' => ['bail', 'required', 'string', 'max:255'],
            'ci' => ['bail', 'required', 'string', 'max:30'],
            'ci_complement' => ['nullable', 'string', 'max:10'],
            'issued_in' => ['bail', 'required', 'string', Rule::in(PublicAffiliationCatalogs::ISSUED_IN)],
            'phone' => ['bail', 'required', 'string', 'regex:/^\d{8}$/'],
            'email' => ['bail', 'required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'address' => ['bail', 'required', 'string', 'max:255'],
            'sector_id' => ['required', Rule::exists('sectors', 'id')->where('is_active', true)],
            'affiliation_plan_id' => ['required', Rule::exists('affiliation_plans', 'id')->where('is_active', true)],
            'regional' => ['bail', 'required', 'string', Rule::in(PublicAffiliationCatalogs::REGIONALS)],
            'institution' => ['required', 'string', 'max:160'],
            'position' => ['required', 'string', 'max:120'],
            'photo' => ['bail', 'required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'birth_date' => ['required', 'date', 'before:today'],
            'marital_status' => ['bail', 'required', 'string', Rule::in(PublicAffiliationCatalogs::MARITAL_STATUSES)],
            $termsField => ['accepted'],
            $privacyField => ['accepted'],
        ];
    }

    public static function registrationMessages(string $termsField = 'terms', string $privacyField = 'data_processing'): array
    {
        return [
            'full_name.required' => 'El nombre completo es obligatorio.',
            'ci.required' => 'La cédula de identidad es obligatoria.',
            'issued_in.required' => 'Selecciona el lugar de expedición.',
            'issued_in.in' => 'Selecciona un lugar de expedición válido.',
            'phone.required' => 'El número de celular es obligatorio.',
            'phone.regex' => 'Ingresa un número de celular válido de 8 dígitos.',
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'Ingresa un correo electrónico válido.',
            'password.required' => 'La contraseña es obligatoria.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
            'address.required' => 'La dirección es obligatoria.',
            'regional.required' => 'Selecciona la regional.',
            'regional.in' => 'Selecciona una regional válida.',
            'marital_status.required' => 'Selecciona tu estado civil.',
            'marital_status.in' => 'Selecciona un estado civil válido.',
            'photo.required' => 'Selecciona y recorta una fotografía.',
            'photo.image' => 'El archivo seleccionado no es una imagen válida.',
            'photo.mimes' => 'Selecciona una imagen en formato JPG, PNG o WEBP.',
            'photo.max' => 'La fotografía supera el tamaño permitido de 5 MB.',
            'birth_date.required' => 'La fecha de nacimiento es obligatoria.',
            'birth_date.before' => 'La fecha de nacimiento debe ser anterior a hoy.',
            'position.required' => 'El cargo o profesión es obligatorio.',
            'institution.required' => 'La institución es obligatoria.',
            'sector_id.required' => 'Selecciona un sector.',
            'affiliation_plan_id.required' => 'Selecciona un plan.',
            "{$termsField}.accepted" => 'Debes aceptar los términos de afiliación.',
            "{$privacyField}.accepted" => 'Debes aceptar el tratamiento de datos.',
        ];
    }

    public static function paymentRules(int $receiptMaxKb): array
    {
        return [
            'transaction_number' => ['required', 'string', 'max:120'],
            'payment_date' => ['required', 'date', 'before_or_equal:today'],
            'bank_name' => ['nullable', 'string', 'max:120'],
            'payer_name' => ['required', 'string', 'max:255'],
            'paid_amount' => ['bail', 'required', 'regex:/^\d{1,8}(\.\d{1,2})?$/', 'numeric', 'min:0.01'],
            'receipt' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:'.$receiptMaxKb],
            'observations' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
