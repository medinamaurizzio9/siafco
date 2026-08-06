<?php

namespace App\Http\Resources\Api\Mobile\V1;

use App\Models\StoreOrder;
use App\Support\StoreOrderStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreOrderSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var StoreOrder $order */
        $order = $this->resource;

        return [
            'code' => $order->code,
            'date' => $order->created_at?->toIso8601String(),
            'status' => $order->status,
            'status_label' => $this->statusLabel($order->status),
            'total' => $order->total,
            'currency' => $order->currency,
            'delivery_method' => $order->delivery_method,
            'item_summary' => $order->relationLoaded('items')
                ? $order->items->map(fn ($item) => $item->quantity.' x '.$item->name_snapshot)->implode(', ')
                : null,
            'capabilities' => $this->capabilities($order),
        ];
    }

    protected function capabilities(StoreOrder $order): array
    {
        return [
            'can_upload_receipt' => in_array($order->status, [StoreOrderStatus::PENDING, StoreOrderStatus::WAITING_PAYMENT], true)
                && ! $order->receipts()->where('status', 'pending')->exists(),
            'can_open_whatsapp' => ! in_array($order->status, [StoreOrderStatus::CANCELLED, StoreOrderStatus::DELIVERED], true),
            'can_cancel' => in_array($order->status, [StoreOrderStatus::PENDING, StoreOrderStatus::WAITING_PAYMENT], true),
            'can_view_receipt' => $order->receipts()->exists(),
        ];
    }

    protected function statusLabel(string $status): string
    {
        return str($status)->replace('_', ' ')->headline()->toString();
    }
}
