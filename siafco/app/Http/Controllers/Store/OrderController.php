<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\StoreOrder;
use App\Support\StoreOrderStatus;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'string', 'max:30'],
            'from' => ['nullable', 'date'],
        ]);

        return view('store.orders.index', [
            'orders' => StoreOrder::query()
                ->where('affiliate_id', $request->user()->affiliate->id)
                ->when($filters['search'] ?? null, fn ($query, $search) => $query->where('code', 'like', "%{$search}%"))
                ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
                ->when($filters['from'] ?? null, fn ($query, $from) => $query->whereDate('created_at', '>=', $from))
                ->latest()
                ->paginate(10)->withQueryString(),
            'filters' => $filters,
            'statuses' => StoreOrderStatus::ALL,
        ]);
    }

    public function show(Request $request, StoreOrder $order)
    {
        abort_unless((int) $order->affiliate_id === (int) $request->user()->affiliate->id, 404);

        return view('store.orders.show', [
            'order' => $order->load('items', 'receipts', 'statusHistories'),
        ]);
    }
}
