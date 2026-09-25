<?php

namespace App\Http\Controllers;

use App\Models\Affiliate;
use App\Models\AffiliateJewelDeliveryHistory;
use App\Services\AuditService;
use App\Support\SiafcoDate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AffiliateJewelDeliveryController extends Controller
{
    public function deliver(Request $request, Affiliate $affiliate)
    {
        $data = $request->validate([
            'delivered_at' => ['nullable', 'date', 'before_or_equal:now'],
        ]);

        $deliveredAt = SiafcoDate::fromLocalInput($data['delivered_at'] ?? null) ?? now();
        $actor = $request->user();

        DB::transaction(function () use ($affiliate, $deliveredAt, $actor) {
            $locked = Affiliate::query()->lockForUpdate()->findOrFail($affiliate->id);
            $before = $locked->jewel_delivered_at;
            $action = $before ? AffiliateJewelDeliveryHistory::ACTION_DATE_CHANGED : AffiliateJewelDeliveryHistory::ACTION_DELIVERED;

            $locked->forceFill([
                'jewel_delivered_at' => $deliveredAt,
                'jewel_delivered_by' => $actor->id,
            ])->save();

            AffiliateJewelDeliveryHistory::create([
                'affiliate_id' => $locked->id,
                'action' => $action,
                'delivery_date_before' => $before,
                'delivery_date_after' => $deliveredAt,
                'performed_by' => $actor->id,
            ]);

            AuditService::record($action === AffiliateJewelDeliveryHistory::ACTION_DELIVERED
                ? 'affiliate_jewel_delivered'
                : 'affiliate_jewel_delivery_date_changed', $locked, [
                    'affiliate_id' => $locked->id,
                    'delivery_date_before' => $before?->toDateTimeString(),
                    'delivery_date_after' => $deliveredAt->toDateTimeString(),
                    'performed_by' => $actor->id,
                ]);
        });

        return back()->with('status', 'Entrega de joya registrada correctamente.');
    }

    public function revert(Request $request, Affiliate $affiliate)
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);
        $actor = $request->user();

        DB::transaction(function () use ($affiliate, $data, $actor) {
            $locked = Affiliate::query()->lockForUpdate()->findOrFail($affiliate->id);

            if (! $locked->jewel_delivered_at) {
                throw ValidationException::withMessages([
                    'reason' => 'La joya ya se encuentra pendiente de entrega.',
                ]);
            }

            $before = $locked->jewel_delivered_at;
            $locked->forceFill([
                'jewel_delivered_at' => null,
                'jewel_delivered_by' => null,
            ])->save();

            AffiliateJewelDeliveryHistory::create([
                'affiliate_id' => $locked->id,
                'action' => AffiliateJewelDeliveryHistory::ACTION_REVERTED,
                'delivery_date_before' => $before,
                'delivery_date_after' => null,
                'performed_by' => $actor->id,
                'reason' => $data['reason'],
            ]);

            AuditService::record('affiliate_jewel_delivery_reverted', $locked, [
                'affiliate_id' => $locked->id,
                'delivery_date_before' => $before?->toDateTimeString(),
                'performed_by' => $actor->id,
                'reason' => $data['reason'],
            ]);
        });

        return back()->with('status', 'Entrega de joya revertida correctamente.');
    }
}
