<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Validator;

class UpdateOwnPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('afiliado') && $this->user()->affiliate !== null;
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'current_password:web'],
            'password' => ['required', 'string', 'min:8', 'regex:/[A-Za-z]/', 'regex:/[0-9]/', 'confirmed'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->filled('password') && Hash::check((string) $this->input('password'), $this->user()->password)) {
                $validator->errors()->add('password', 'La nueva contraseña debe ser diferente a la contraseña actual.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'current_password.current_password' => 'La contraseña actual es incorrecta.',
            'password.min' => 'La nueva contraseña debe tener al menos 8 caracteres e incluir letras y números.',
            'password.regex' => 'La nueva contraseña debe tener al menos 8 caracteres e incluir letras y números.',
            'password.confirmed' => 'La confirmación de la nueva contraseña no coincide.',
        ];
    }
}
