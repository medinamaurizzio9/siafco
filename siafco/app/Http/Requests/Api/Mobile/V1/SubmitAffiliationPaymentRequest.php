<?php

namespace App\Http\Requests\Api\Mobile\V1;

use App\Support\PublicAffiliationValidation;
use Illuminate\Validation\Validator;

class SubmitAffiliationPaymentRequest extends MobileFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->user_type === 'affiliate'
            && $this->user()?->role === 'afiliado'
            && $this->user()?->affiliate !== null;
    }

    public function rules(): array
    {
        return PublicAffiliationValidation::paymentRules(
            config('siafco.public_affiliation_receipt_max_kb', 6144)
        ) + [
            'status' => ['prohibited'],
            'affiliate_id' => ['prohibited'],
            'public_affiliation_request_id' => ['prohibited'],
            'voucher_path' => ['prohibited'],
            'confirmed_by' => ['prohibited'],
            'confirmed_at' => ['prohibited'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $idempotencyKey = trim((string) $this->header('Idempotency-Key'));
                if ($idempotencyKey !== '' && mb_strlen($idempotencyKey) > 200) {
                    $validator->errors()->add('Idempotency-Key', 'La clave de idempotencia no debe superar 200 caracteres.');
                }

                $file = $this->file('receipt');
                if (! $file || $validator->errors()->has('receipt')) {
                    return;
                }

                $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath());
                if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'], true)) {
                    $validator->errors()->add('receipt', 'El comprobante no tiene un formato válido.');
                }
            },
        ];
    }
}
