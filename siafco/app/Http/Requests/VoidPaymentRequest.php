<?php

namespace App\Http\Requests;

use App\Support\TextNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VoidPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('payments.void') ?? false;
    }

    public function rules(): array
    {
        return [
            'confirmation' => ['required', Rule::in(['ANULAR'])],
            'void_reason' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return ['confirmation.in' => 'Escriba ANULAR exactamente para confirmar.'];
    }

    public function validated($key = null, $default = null): mixed
    {
        $data = parent::validated($key, $default);

        return is_array($data) ? TextNormalizer::fields($data, ['void_reason']) : $data;
    }
}
