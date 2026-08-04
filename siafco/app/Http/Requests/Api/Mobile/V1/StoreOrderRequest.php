<?php

namespace App\Http\Requests\Api\Mobile\V1;

use Illuminate\Validation\Validator;

class StoreOrderRequest extends StoreQuoteRequest
{
    public function after(): array
    {
        return array_merge(parent::after(), [
            function (Validator $validator): void {
                $key = trim((string) $this->header('Idempotency-Key'));
                if ($key === '') {
                    $validator->errors()->add('Idempotency-Key', 'La clave de idempotencia es obligatoria.');
                } elseif (! preg_match('/^[0-9a-fA-F-]{36}$/', $key)) {
                    $validator->errors()->add('Idempotency-Key', 'La clave de idempotencia debe ser un UUID.');
                }
            },
        ]);
    }

    public function idempotencyKey(): string
    {
        return trim((string) $this->header('Idempotency-Key'));
    }
}
