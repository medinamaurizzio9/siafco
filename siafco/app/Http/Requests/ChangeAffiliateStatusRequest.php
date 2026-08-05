<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ChangeAffiliateStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('affiliates.change_status') ?? false;
    }

    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['activate', 'suspend', 'deactivate', 'reactivate'])],
            'reason' => ['nullable', 'string', 'min:5', 'max:500'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (in_array($this->input('action'), ['suspend', 'deactivate'], true) && ! $this->filled('reason')) {
                    $validator->errors()->add('reason', 'Debe indicar el motivo.');
                }
            },
        ];
    }
}
