<?php

namespace App\Http\Controllers\Admin\Store;

use App\Http\Controllers\Controller;
use App\Models\StoreOrder;
use App\Services\Store\StoreOrderStatusService;
use App\Support\StoreDeliveryMethod;
use App\Support\StoreOrderStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('store.view');
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(StoreOrderStatus::ALL)],
            'delivery_method' => ['nullable', Rule::in(StoreDeliveryMethod::ALL)],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        return view('admin.store.orders.index', [
            'orders' => StoreOrder::query()
                ->with('affiliate')
                ->when($filters['search'] ?? null, function ($query, $search): void {
                    $query->where(function ($nested) use ($search): void {
                        $nested->where('code', 'like', "%{$search}%")
                            ->orWhereHas('affiliate', fn ($affiliate) => $affiliate->where('full_name', 'like', "%{$search}%"));
                    });
                })
                ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
                ->when($filters['delivery_method'] ?? null, fn ($query, $method) => $query->where('delivery_method', $method))
                ->when($filters['from'] ?? null, fn ($query, $from) => $query->whereDate('created_at', '>=', $from))
                ->when($filters['to'] ?? null, fn ($query, $to) => $query->whereDate('created_at', '<=', $to))
                ->latest()
                ->paginate(15)->withQueryString(),
            'filters' => $filters,
            'statuses' => StoreOrderStatus::ALL,
            'deliveryMethods' => StoreDeliveryMethod::ALL,
        ]);
    }

    public function show(StoreOrder $order)
    {
        Gate::authorize('store.view');

        return view('admin.store.orders.show', [
            'order' => $order->load('affiliate', 'items', 'couponUsage', 'receipts', 'statusHistories.actor'),
            'statuses' => StoreOrderStatus::ALL,
        ]);
    }

    public function updateStatus(Request $request, StoreOrder $order, StoreOrderStatusService $statuses)
    {
        Gate::authorize('store.manage-orders');
        $data = $request->validate([
            'status' => ['required', Rule::in(StoreOrderStatus::ALL)],
            'admin_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $statuses->transition($order, $data['status'], $request->user(), $data['admin_note'] ?? null);

        return back()->with('status', 'Estado del pedido actualizado.');
    }
}
