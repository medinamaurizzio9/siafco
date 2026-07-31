<?php

namespace App\Http\Requests\Api\Mobile\V1;

use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Validator;

class UpdatePasswordRequest extends MobileFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->user_type === 'affiliate'
            && $this->user()?->role === 'afiliado'
            && $this->user()?->affiliate !== null;
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'regex:/[A-Za-z]/', 'regex:/[0-9]/', 'confirmed'],
            'password_confirmation' => ['required', 'string'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->filled('current_password') && ! Hash::check((string) $this->input('current_password'), $this->user()->password)) {
                $validator->errors()->add('current_password', 'La contraseña actual es incorrecta.');
            }

            if ($this->filled('password') && Hash::check((string) $this->input('password'), $this->user()->password)) {
                $validator->errors()->add('password', 'La nueva contraseña debe ser diferente a la contraseña actual.');
            }
        }];
    }
}
