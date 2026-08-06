<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\StoreOrder;
use App\Models\StoreOrderReceipt;
use App\Models\StoreSetting;
use App\Services\Store\StoreReceiptService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ReceiptController extends Controller
{
    public function store(Request $request, StoreOrder $order, StoreReceiptService $receipts)
    {
        abort_unless((int) $order->affiliate_id === (int) $request->user()->affiliate->id, 404);
        $data = $request->validate([
            'receipt' => ['required', 'file', 'max:'.StoreSetting::current()->max_receipt_size_kb],
        ]);
        $receipts->submit($order, $request->user(), $data['receipt']);

        return back()->with('status', 'Comprobante enviado para revisión.');
    }

    public function show(Request $request, StoreOrder $order, StoreOrderReceipt $receipt)
    {
        abort_unless((int) $order->affiliate_id === (int) $request->user()->affiliate->id, 404);
        abort_unless((int) $receipt->store_order_id === (int) $order->id, 404);
        abort_unless(Storage::disk('local')->exists($receipt->path), 404);

        return Storage::disk('local')->download($receipt->path, 'comprobante-'.$order->code.'.'.pathinfo($receipt->path, PATHINFO_EXTENSION));
    }
}
