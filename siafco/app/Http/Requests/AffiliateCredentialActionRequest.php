<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AffiliateCredentialActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('affiliates.manage_credential') ?? false;
    }

    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'min:5', 'max:500'],
        ];
    }
}
