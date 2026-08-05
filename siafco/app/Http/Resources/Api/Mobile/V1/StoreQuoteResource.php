<?php

namespace App\Http\Resources\Api\Mobile\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreQuoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $quote = $this->resource;

        return [
            'items' => collect($quote['lines'])->map(fn ($line) => [
                'product' => [
                    'public_code' => $line['product']->public_code,
                    'name' => $line['product']->name,
                ],
                'variant' => $line['variant'] ? [
                    'public_code' => $line['variant']->public_code,
                    'name' => $line['variant']->name,
                    'type' => $line['variant']->type,
                ] : null,
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'],
                'line_total' => $line['line_total'],
                'price_reason' => $line['price_reason'],
            ])->all(),
            'subtotal' => $quote['subtotal'],
            'discount_total' => $quote['discount_total'],
            'shipping_total' => $quote['shipping_total'],
            'total' => $quote['total'],
            'currency' => $quote['currency'],
            'coupon' => [
                'applied' => (bool) ($quote['coupon']['coupon'] ?? null),
                'hint' => $quote['coupon']['snapshot']['code_hint'] ?? null,
            ],
            'shipping' => $quote['shipping']['snapshot'],
            'expires_at' => null,
        ];
    }
}
