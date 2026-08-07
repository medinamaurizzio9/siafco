<?php

namespace App\Http\Resources\Api\Mobile\V1;

use App\Support\StoreProductImages;
use Illuminate\Http\Request;

class StoreOrderResource extends StoreOrderSummaryResource
{
    public function toArray(Request $request): array
    {
        $order = $this->resource;

        return parent::toArray($request) + [
            'created_at' => $order->created_at?->toIso8601String(),
            'updated_at' => $order->updated_at?->toIso8601String(),
            'delivery' => [
                'method' => $order->delivery_method,
                'department' => $order->department,
                'city' => $order->city,
                'zone' => $order->zone,
                'address' => $order->delivery_address,
            ],
            'items' => $order->items->map(fn ($item) => [
                'sku' => $item->sku_snapshot,
                'name' => $item->name_snapshot,
                'variant' => $item->variant_snapshot,
                'unit_price' => $item->unit_price,
                'quantity' => $item->quantity,
                'discount_total' => $item->discount_total,
                'line_total' => $item->line_total,
                'primary_image_url' => StoreProductImages::primaryImageUrl(
                    $item->relationLoaded('product') ? $item->product : null
                ),
            ])->all(),
            'subtotal' => $order->subtotal,
            'discount_total' => $order->discount_total,
            'shipping_total' => $order->shipping_total,
            'payment' => $order->payment_snapshot,
            'receipts' => $this->whenLoaded('receipts', fn () => $order->receipts->map(fn ($receipt) => [
                'public_code' => $receipt->public_id,
                'status' => $receipt->status,
                'submitted_at' => $receipt->submitted_at?->toIso8601String(),
                'reviewed_at' => $receipt->reviewed_at?->toIso8601String(),
                'rejection_reason' => $receipt->rejection_reason,
                'mime_type' => $receipt->mime,
                'size_bytes' => $receipt->size_bytes,
            ])->all()),
            'status_history' => $this->whenLoaded('statusHistories', fn () => $order->statusHistories->map(fn ($history) => [
                'from_status' => $history->from_status,
                'to_status' => $history->to_status,
                'changed_at' => $history->changed_at?->toIso8601String(),
            ])->all()),
        ];
    }
}
