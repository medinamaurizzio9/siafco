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
        $payload = [
            'code' => $this->filled('code') ? str($this->input('code'))->squish()->upper()->toString() : null,
            'per_page' => min(30, max(1, (int) $this->input('per_page', 15))),
        ];

        if ($this->has('attention_only')) {
            $attentionOnly = $this->input('attention_only');
            if (is_string($attentionOnly)) {
                $normalized = strtolower($attentionOnly);
                if (in_array($normalized, ['true', 'false', '1', '0'], true)) {
                    $payload['attention_only'] = filter_var($normalized, FILTER_VALIDATE_BOOLEAN);
                }
            } elseif (is_bool($attentionOnly) || $attentionOnly === 1 || $attentionOnly === 0) {
                $payload['attention_only'] = (bool) $attentionOnly;
            }
        }

        $this->merge($payload);
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string', Rule::in(StoreOrderStatus::ALL)],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'code' => ['nullable', 'string', 'max:40'],
            'attention_only' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:30'],
        ];
    }
}
