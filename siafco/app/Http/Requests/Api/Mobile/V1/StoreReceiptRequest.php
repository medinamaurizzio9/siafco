<?php

namespace App\Http\Requests\Api\Mobile\V1;

use App\Models\StoreSetting;
use Illuminate\Validation\Validator;

class StoreReceiptRequest extends MobileFormRequest
{
    private const PROTECTED_FIELDS = ['receipt_path', 'path', 'status', 'store_order_id', 'order_id', 'affiliate_id', 'user_id'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'receipt' => ['required', 'file', 'max:'.StoreSetting::current()->max_receipt_size_kb],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $key = trim((string) $this->header('Idempotency-Key'));
                if ($key === '') {
                    $validator->errors()->add('Idempotency-Key', 'La clave de idempotencia es obligatoria.');
                } elseif (! preg_match('/^[0-9a-fA-F-]{36}$/', $key)) {
                    $validator->errors()->add('Idempotency-Key', 'La clave de idempotencia debe ser un UUID.');
                }

                foreach (self::PROTECTED_FIELDS as $field) {
                    if ($this->exists($field)) {
                        $validator->errors()->add($field, 'No se permite enviar datos administrativos del comprobante.');
                    }
                }
            },
        ];
    }

    public function idempotencyKey(): string
    {
        return trim((string) $this->header('Idempotency-Key'));
    }
}
