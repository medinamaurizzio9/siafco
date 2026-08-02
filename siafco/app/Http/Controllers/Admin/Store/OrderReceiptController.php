<?php

namespace App\Http\Controllers\Admin\Store;

use App\Http\Controllers\Controller;
use App\Models\StoreOrder;
use App\Models\StoreOrderReceipt;
use App\Services\Store\StoreReceiptService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class OrderReceiptController extends Controller
{
    public function show(StoreOrder $order, StoreOrderReceipt $receipt)
    {
        Gate::authorize('store.view');
        abort_unless((int) $receipt->store_order_id === (int) $order->id, 404);
        abort_unless(Storage::disk('local')->exists($receipt->path), 404);

        return Storage::disk('local')->download($receipt->path, 'comprobante-'.$order->code.'.'.pathinfo($receipt->path, PATHINFO_EXTENSION));
    }

    public function confirm(Request $request, StoreOrder $order, StoreOrderReceipt $receipt, StoreReceiptService $receipts)
    {
        Gate::authorize('store.verify-receipts');
        $receipts->confirm($order, $receipt, $request->user());

        return back()->with('status', 'Comprobante confirmado.');
    }

    public function reject(Request $request, StoreOrder $order, StoreOrderReceipt $receipt, StoreReceiptService $receipts)
    {
        Gate::authorize('store.verify-receipts');
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $receipts->reject($order, $receipt, $request->user(), $data['reason']);

        return back()->with('status', 'Comprobante rechazado.');
    }
}
