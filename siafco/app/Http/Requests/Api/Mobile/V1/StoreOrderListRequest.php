<?php

namespace App\Http\Requests\Api\Mobile\V1;

use App\Support\StoreOrderStatus;
use Illuminate\Validation\Rule;

class StoreOrderListRequest extends MobileFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => $this->filled('code') ? str($this->input('code'))->squish()->upper()->toString() : null,
            'per_page' => min(30, max(1, (int) $this->input('per_page', 15))),
        ]);
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string', Rule::in(StoreOrderStatus::ALL)],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'code' => ['nullable', 'string', 'max:40'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:30'],
        ];
    }
}
