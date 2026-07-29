<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreInternalUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', User::class)
            && Gate::allows('assignRole', [new User, (string) $this->input('role')]);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => mb_strtolower(trim((string) $this->input('email'))),
            'username' => mb_strtolower(trim((string) $this->input('username'))),
            'use_ci_password' => $this->boolean('use_ci_password'),
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'ci' => ['required', 'string', 'max:30', 'unique:users,ci'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'username' => ['required', 'string', 'min:4', 'max:60', 'alpha_dash', 'unique:users,username'],
            'position' => ['nullable', 'string', 'max:100'],
            'area' => ['nullable', 'string', 'max:100'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'password' => ['exclude_if:use_ci_password,true', 'required', 'string', 'min:8', 'confirmed'],
            'use_ci_password' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'role' => ['required', Rule::in(config('internal_roles.assignable'))],
        ];
    }
}
