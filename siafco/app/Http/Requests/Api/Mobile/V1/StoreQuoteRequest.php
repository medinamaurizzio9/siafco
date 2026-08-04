<?php

namespace App\Http\Requests\Api\Mobile\V1;

use App\Support\StoreDeliveryMethod;
use App\Support\TextNormalizer;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreQuoteRequest extends MobileFormRequest
{
    private const PROTECTED_FIELDS = [
        'price', 'unit_price', 'subtotal', 'total', 'discount', 'discount_total',
        'shipping_total', 'affiliate_id', 'user_id', 'status', 'receipt_path',
        'coupon_discount',
    ];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $items = collect($this->input('items', []))->map(fn ($item) => [
            'product_public_code' => isset($item['product_public_code']) ? trim((string) $item['product_public_code']) : null,
            'variant_public_code' => filled($item['variant_public_code'] ?? null) ? trim((string) $item['variant_public_code']) : null,
            'quantity' => (int) ($item['quantity'] ?? 0),
        ])->all();

        $this->merge([
            'items' => $items,
            'delivery_method' => $this->filled('delivery_method') ? trim((string) $this->input('delivery_method')) : null,
            'department' => $this->filled('department') ? TextNormalizer::uppercase((string) $this->input('department')) : null,
            'city' => $this->filled('city') ? TextNormalizer::uppercase((string) $this->input('city')) : null,
            'zone' => $this->filled('zone') ? TextNormalizer::uppercase((string) $this->input('zone')) : null,
            'delivery_address' => $this->filled('delivery_address') ? TextNormalizer::uppercase((string) $this->input('delivery_address')) : null,
            'coupon_code' => $this->filled('coupon_code') ? str($this->input('coupon_code'))->squish()->upper()->toString() : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:30'],
            'items.*.product_public_code' => ['required', 'uuid'],
            'items.*.variant_public_code' => ['nullable', 'uuid'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:999'],
            'delivery_method' => ['required', Rule::in([StoreDeliveryMethod::PICKUP, StoreDeliveryMethod::SHIPPING])],
            'department' => ['nullable', 'string', 'max:80'],
            'city' => ['nullable', 'string', 'max:80'],
            'zone' => ['nullable', 'string', 'max:80'],
            'delivery_address' => ['nullable', 'string', 'max:255'],
            'coupon_code' => ['nullable', 'string', 'max:80'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                foreach (self::PROTECTED_FIELDS as $field) {
                    if ($this->exists($field)) {
                        $validator->errors()->add($field, 'No se permite enviar valores calculados o administrativos.');
                    }
                }
            },
        ];
    }

    public function quotePayload(): array
    {
        return [
            'items' => $this->validated('items'),
            'delivery' => [
                'method' => $this->validated('delivery_method'),
                'department' => $this->validated('department'),
                'city' => $this->validated('city'),
                'zone' => $this->validated('zone'),
                'address' => $this->validated('delivery_address'),
            ],
            'coupon_code' => $this->validated('coupon_code'),
        ];
    }
}
