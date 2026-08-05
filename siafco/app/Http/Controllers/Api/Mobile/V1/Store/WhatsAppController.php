<?php

namespace App\Http\Controllers\Api\Mobile\V1\Store;

use App\Http\Controllers\Controller;
use App\Http\Responses\MobileApiResponse;
use App\Models\StoreOrder;
use App\Services\Store\StoreWhatsAppOrderService;
use Illuminate\Http\Request;

class WhatsAppController extends Controller
{
    public function store(Request $request, string $orderCode, StoreWhatsAppOrderService $whatsApp)
    {
        $order = StoreOrder::query()
            ->with('items')
            ->where('code', $orderCode)
            ->where('affiliate_id', $request->user()->affiliate->id)
            ->first();

        if (! $order) {
            return MobileApiResponse::error('Pedido no encontrado.', 404);
        }

        $url = $whatsApp->open($order, $request->user());

        return MobileApiResponse::success([
            'whatsapp' => [
                'url' => $url,
                'opened_at' => $order->fresh()->whatsapp_opened_at?->toIso8601String(),
                'message_preview' => $whatsApp->messagePreview($order),
            ],
        ], 'WhatsApp preparado.');
    }
}
