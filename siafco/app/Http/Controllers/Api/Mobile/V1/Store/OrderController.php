<?php

namespace App\Http\Controllers\Api\Mobile\V1\Store;

use App\Exceptions\StoreIdempotencyConflictException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Mobile\V1\StoreOrderListRequest;
use App\Http\Requests\Api\Mobile\V1\StoreOrderRequest;
use App\Http\Resources\Api\Mobile\V1\StoreOrderResource;
use App\Http\Resources\Api\Mobile\V1\StoreOrderSummaryResource;
use App\Http\Responses\MobileApiResponse;
use App\Models\MobileApiIdempotencyKey;
use App\Models\StoreOrder;
use App\Services\Store\StoreOrderService;
use App\Support\StoreOrderStatus;

class OrderController extends Controller
{
    private const ATTENTION_STATUSES = [
        StoreOrderStatus::PENDING,
        StoreOrderStatus::RESERVED,
        StoreOrderStatus::WAITING_PAYMENT,
        StoreOrderStatus::PAYMENT_REVIEW,
    ];

    public function store(StoreOrderRequest $request, StoreOrderService $orders)
    {
        $payload = $request->quotePayload();
        $status = $this->idempotentRepeatStatus($request, $orders, $payload) ?? 201;

        try {
            $order = $orders->create($request->user()->affiliate, $payload, $request->idempotencyKey());
        } catch (StoreIdempotencyConflictException) {
            return MobileApiResponse::error('La clave de idempotencia ya fue utilizada con otro contenido.', 409);
        }

        $order->load('items.product.images', 'receipts', 'statusHistories');

        return MobileApiResponse::success([
            'order' => StoreOrderResource::make($order)->resolve(),
        ], $status === 201 ? 'Pedido creado.' : 'Pedido ya registrado.', $status);
    }

    public function index(StoreOrderListRequest $request)
    {
        $filters = $request->validated();
        $orders = StoreOrder::query()
            ->with(['items'])
            ->where('affiliate_id', $request->user()->affiliate->id)
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '<=', $date))
            ->when($filters['code'] ?? null, fn ($query, $code) => $query->where('code', 'like', "%{$code}%"))
            ->when(($filters['attention_only'] ?? false) === true, fn ($query) => $query->whereIn('status', self::ATTENTION_STATUSES))
            ->latest()
            ->paginate($filters['per_page'] ?? 15);

        return MobileApiResponse::success([
            'orders' => StoreOrderSummaryResource::collection($orders->getCollection())->resolve(),
            'pagination' => [
                'current_page' => $orders->currentPage(),
                'per_page' => $orders->perPage(),
                'last_page' => $orders->lastPage(),
                'total' => $orders->total(),
            ],
        ], 'Pedidos disponibles.');
    }

    public function show($orderCode)
    {
        $order = StoreOrder::query()
            ->with(['items.product.images', 'receipts', 'statusHistories'])
            ->where('code', $orderCode)
            ->where('affiliate_id', request()->user()->affiliate->id)
            ->first();

        if (! $order) {
            return MobileApiResponse::error('Pedido no encontrado.', 404);
        }

        return MobileApiResponse::success([
            'order' => StoreOrderResource::make($order)->resolve(),
        ], 'Pedido disponible.');
    }

    private function idempotentRepeatStatus(StoreOrderRequest $request, StoreOrderService $orders, array $payload): ?int
    {
        $entry = MobileApiIdempotencyKey::query()
            ->where('user_id', $request->user()->id)
            ->where('scope', StoreOrderService::IDEMPOTENCY_SCOPE)
            ->where('idempotency_key', $request->idempotencyKey())
            ->first();

        if (! $entry) {
            return null;
        }

        if (! hash_equals($entry->request_hash, $orders->requestHash($payload))) {
            return null;
        }

        return 200;
    }
}
