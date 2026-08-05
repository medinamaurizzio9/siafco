<?php

namespace App\Http\Requests;

use App\Support\TextNormalizer;
use Illuminate\Foundation\Http\FormRequest;

class RejectPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('payments.reject') ?? false;
    }

    public function rules(): array
    {
        return ['rejection_reason' => ['required', 'string', 'min:5', 'max:500']];
    }

    public function validated($key = null, $default = null): mixed
    {
        $data = parent::validated($key, $default);

        return is_array($data) ? TextNormalizer::fields($data, ['rejection_reason']) : $data;
    }
}
