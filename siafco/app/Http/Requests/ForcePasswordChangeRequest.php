<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ForcePasswordChangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->must_change_password;
    }

    public function rules(): array
    {
        return [
            'password' => ['required', 'string', 'min:8', 'regex:/[A-Za-z]/', 'regex:/[0-9]/', 'confirmed'],
        ];
    }

    public function messages(): array
    {
        return [
            'password.min' => 'La nueva contraseña debe tener al menos 8 caracteres e incluir letras y números.',
            'password.regex' => 'La nueva contraseña debe tener al menos 8 caracteres e incluir letras y números.',
            'password.confirmed' => 'La confirmación de la nueva contraseña no coincide.',
        ];
    }
}
