<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateInternalUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $target = $this->route('user');

        return Gate::allows('update', $target)
            && Gate::allows('assignRole', [$target, (string) $this->input('role')]);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => mb_strtolower(trim((string) $this->input('email'))),
            'username' => mb_strtolower(trim((string) $this->input('username'))),
        ]);
    }

    public function rules(): array
    {
        $user = $this->route('user');
        $roles = array_unique([...config('internal_roles.assignable'), $user->role]);

        return [
            'name' => ['required', 'string', 'max:150'],
            'ci' => ['required', 'string', 'max:30', Rule::unique('users', 'ci')->ignore($user)],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user)],
            'username' => ['required', 'string', 'min:4', 'max:60', 'alpha_dash', Rule::unique('users', 'username')->ignore($user)],
            'position' => ['nullable', 'string', 'max:100'],
            'area' => ['nullable', 'string', 'max:100'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'role' => ['required', Rule::in($roles)],
        ];
    }
}
