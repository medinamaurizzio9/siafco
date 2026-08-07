<?php

namespace App\Http\Controllers\Api\Mobile\V1\Store;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Mobile\V1\StoreReceiptRequest;
use App\Http\Resources\Api\Mobile\V1\StoreOrderResource;
use App\Http\Responses\MobileApiResponse;
use App\Models\MobileApiIdempotencyKey;
use App\Models\StoreOrder;
use App\Services\Store\StoreReceiptService;
use Illuminate\Support\Facades\DB;

class ReceiptController extends Controller
{
    private const IDEMPOTENCY_SCOPE = 'store.order.receipt';

    public function store(StoreReceiptRequest $request, string $orderCode, StoreReceiptService $receipts)
    {
        $order = StoreOrder::query()
            ->with(['items.product.images', 'receipts', 'statusHistories'])
            ->where('code', $orderCode)
            ->where('affiliate_id', $request->user()->affiliate->id)
            ->first();

        if (! $order) {
            return MobileApiResponse::error('Pedido no encontrado.', 404);
        }

        $file = $request->file('receipt');
        $hash = hash('sha256', $order->code.'|'.hash_file('sha256', $file->getRealPath()));
        $existing = $this->existingEntry($request, $hash);
        if ($existing === false) {
            return MobileApiResponse::error('La clave de idempotencia ya fue utilizada con otro contenido.', 409);
        }
        if ($existing) {
            $order->refresh()->load(['items.product.images', 'receipts', 'statusHistories']);

            return MobileApiResponse::success([
                'order' => StoreOrderResource::make($order)->resolve(),
            ], 'Comprobante ya registrado.');
        }

        $receipt = DB::transaction(function () use ($request, $receipts, $order, $file, $hash) {
            $entry = MobileApiIdempotencyKey::create([
                'user_id' => $request->user()->id,
                'scope' => self::IDEMPOTENCY_SCOPE,
                'idempotency_key' => $request->idempotencyKey(),
                'request_hash' => $hash,
                'status' => 'processing',
            ]);

            $receipt = $receipts->submit($order, $request->user(), $file);
            $entry->update([
                'status' => 'completed',
                'response_status' => 201,
                'response_body' => ['order_code' => $order->code, 'receipt_public_id' => $receipt->public_id],
            ]);

            return $receipt;
        });

        $order->refresh()->load(['items.product.images', 'receipts', 'statusHistories']);

        return MobileApiResponse::success([
            'order' => StoreOrderResource::make($order)->resolve(),
            'receipt' => [
                'public_code' => $receipt->public_id,
                'status' => $receipt->status,
                'submitted_at' => $receipt->submitted_at?->toIso8601String(),
                'mime_type' => $receipt->mime,
                'size_bytes' => $receipt->size_bytes,
            ],
        ], 'Comprobante registrado.', 201);
    }

    private function existingEntry(StoreReceiptRequest $request, string $hash): MobileApiIdempotencyKey|false|null
    {
        $entry = MobileApiIdempotencyKey::query()
            ->where('user_id', $request->user()->id)
            ->where('scope', self::IDEMPOTENCY_SCOPE)
            ->where('idempotency_key', $request->idempotencyKey())
            ->first();

        if (! $entry) {
            return null;
        }

        if (! hash_equals($entry->request_hash, $hash)) {
            return false;
        }

        return $entry;
    }
}
