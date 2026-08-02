<?php

namespace App\Services\Store;

use App\Models\StoreOrder;
use App\Models\User;
use App\Services\AuditService;
use App\Support\StoreOrderStatus;
use Illuminate\Support\Facades\DB;

class StoreOrderStatusService
{
    public function transition(StoreOrder $order, string $toStatus, ?User $actor = null, ?string $note = null): StoreOrder
    {
        return DB::transaction(function () use ($order, $toStatus, $actor, $note): StoreOrder {
            $order = StoreOrder::query()->with('couponUsage')->lockForUpdate()->findOrFail($order->id);
            $from = $order->status;
            StoreOrderStatus::assertTransition($from, $toStatus);

            $order->forceFill($this->timestampsFor($toStatus) + ['status' => $toStatus])->save();
            $order->statusHistories()->create([
                'actor_user_id' => $actor?->id,
                'from_status' => $from,
                'to_status' => $toStatus,
                'admin_note' => $note,
                'changed_at' => now(),
            ]);

            if ($toStatus === StoreOrderStatus::CANCELLED) {
                $this->releaseCoupon($order, $actor);
            }

            AuditService::record(
                $toStatus === StoreOrderStatus::CANCELLED ? 'mini_tienda.pedido_cancelado' : 'mini_tienda.estado_pedido_actualizado',
                $order,
                [
                    'code' => $order->code,
                    'from_status' => $from,
                    'to_status' => $toStatus,
                    'has_note' => filled($note),
                ]
            );

            return $order->fresh(['items', 'couponUsage', 'statusHistories']);
        });
    }

    private function releaseCoupon(StoreOrder $order, ?User $actor): void
    {
        $usage = $order->couponUsage;
        if (! $usage || $usage->released_at) {
            return;
        }

        $usage->update([
            'released_at' => now(),
            'release_reason' => 'order_cancelled',
            'released_by_user_id' => $actor?->id,
        ]);

        AuditService::record('mini_tienda.cupon_liberado', $usage, [
            'order_code' => $order->code,
            'reason' => 'order_cancelled',
        ]);
    }

    private function timestampsFor(string $status): array
    {
        return match ($status) {
            StoreOrderStatus::CONFIRMED => ['confirmed_at' => now()],
            StoreOrderStatus::DELIVERED => ['delivered_at' => now()],
            StoreOrderStatus::CANCELLED => ['cancelled_at' => now()],
            default => [],
        };
    }
}
