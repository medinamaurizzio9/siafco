<?php

namespace App\Http\Requests;

class UpdateManualPaymentRequest extends StoreManualPaymentRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('payments.update_pending') ?? false;
    }

    public function rules(): array
    {
        return $this->baseRules();
    }
}
