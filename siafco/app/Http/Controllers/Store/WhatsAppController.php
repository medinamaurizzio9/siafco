<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\StoreOrder;
use App\Services\Store\StoreWhatsAppOrderService;
use Illuminate\Http\Request;

class WhatsAppController extends Controller
{
    public function store(Request $request, StoreOrder $order, StoreWhatsAppOrderService $whatsApp)
    {
        abort_unless((int) $order->affiliate_id === (int) $request->user()->affiliate->id, 404);

        return redirect()->away($whatsApp->open($order, $request->user()));
    }
}
