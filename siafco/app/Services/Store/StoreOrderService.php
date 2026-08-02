<?php

namespace App\Services\Store;

use App\Exceptions\StoreIdempotencyConflictException;
use App\Models\Affiliate;
use App\Models\MobileApiIdempotencyKey;
use App\Models\StoreCouponUsage;
use App\Models\StoreOrder;
use App\Models\StoreSetting;
use App\Services\AuditService;
use App\Services\StoreCouponCodeService;
use App\Support\StoreOrderStatus;
use Illuminate\Support\Facades\DB;

class StoreOrderService
{
    public const IDEMPOTENCY_SCOPE = 'store.order.create';

    public function __construct(private readonly StorePricingService $pricing) {}

    public function create(Affiliate $affiliate, array $payload, ?string $idempotencyKey = null): StoreOrder
    {
        return DB::transaction(function () use ($affiliate, $payload, $idempotencyKey): StoreOrder {
            $hash = $this->requestHash($payload);
            $idempotency = null;

            if ($idempotencyKey) {
                $idempotency = MobileApiIdempotencyKey::query()
                    ->where('user_id', $affiliate->user_id)
                    ->where('scope', self::IDEMPOTENCY_SCOPE)
                    ->where('idempotency_key', $idempotencyKey)
                    ->lockForUpdate()
                    ->first();

                if ($idempotency) {
                    if (! hash_equals($idempotency->request_hash, $hash)) {
                        throw new StoreIdempotencyConflictException();
                    }

                    $code = $idempotency->response_body['order_code'] ?? null;
                    if ($code && $order = StoreOrder::query()->where('code', $code)->first()) {
                        return $order->load('items', 'couponUsage');
                    }
                } else {
                    $idempotency = MobileApiIdempotencyKey::create([
                        'user_id' => $affiliate->user_id,
                        'scope' => self::IDEMPOTENCY_SCOPE,
                        'idempotency_key' => $idempotencyKey,
                        'request_hash' => $hash,
                        'status' => 'processing',
                    ]);
                }
            }

            $quote = $this->pricing->quote(
                $affiliate,
                $payload['items'] ?? [],
                $payload['delivery'] ?? [],
                $payload['coupon_code'] ?? null,
            );

            $order = StoreOrder::create([
                'affiliate_id' => $affiliate->id,
                'status' => StoreOrderStatus::PENDING,
                'delivery_method' => $quote['shipping']['method'],
                'department' => $quote['shipping']['department'] ?? null,
                'city' => $quote['shipping']['city'] ?? null,
                'zone' => $quote['shipping']['zone'] ?? null,
                'delivery_address' => $quote['shipping']['address'] ?? null,
                'subtotal' => $quote['subtotal'],
                'discount_total' => $quote['discount_total'],
                'shipping_total' => $quote['shipping_total'],
                'total' => $quote['total'],
                'currency' => $quote['currency'],
                'coupon_snapshot' => $quote['coupon']['snapshot'] ?? null,
                'shipping_snapshot' => $quote['shipping']['snapshot'],
                'payment_snapshot' => $this->paymentSnapshot(),
                'whatsapp_number_snapshot' => $this->whatsappSnapshot(),
            ]);

            $lineDiscounts = $this->lineDiscounts($quote);
            foreach ($quote['lines'] as $index => $line) {
                $discount = $lineDiscounts[$index] ?? 0;
                $variant = $line['variant'];
                $order->items()->create([
                    'store_product_id' => $line['product']->id,
                    'store_product_variant_id' => $variant?->id,
                    'sku_snapshot' => $line['product']->sku.($variant?->sku_suffix ? '-'.$variant->sku_suffix : ''),
                    'name_snapshot' => $line['product']->name,
                    'variant_snapshot' => $variant ? $variant->type.' '.$variant->name : null,
                    'unit_price' => $line['unit_price'],
                    'quantity' => $line['quantity'],
                    'discount_total' => StoreMoney::decimal($discount),
                    'line_total' => StoreMoney::decimal($line['line_total_cents'] - $discount),
                ]);
            }

            if (($quote['coupon']['coupon'] ?? null) && $quote['discount_cents'] > 0) {
                StoreCouponUsage::create([
                    'store_coupon_id' => $quote['coupon']['coupon']->id,
                    'store_order_id' => $order->id,
                    'affiliate_id' => $affiliate->id,
                    'amount' => $quote['discount_total'],
                    'used_at' => now(),
                ]);
            }

            AuditService::record('mini_tienda.pedido_creado', $order, [
                'code' => $order->code,
                'status' => $order->status,
                'delivery_method' => $order->delivery_method,
                'total' => $order->total,
                'currency' => $order->currency,
                'coupon_hint' => $order->coupon_snapshot['code_hint'] ?? null,
            ]);

            if ($idempotency) {
                $idempotency->update([
                    'status' => 'completed',
                    'response_status' => 201,
                    'response_body' => ['order_code' => $order->code],
                ]);
            }

            return $order->load('items', 'couponUsage');
        });
    }

    public function requestHash(array $payload): string
    {
        $coupon = $payload['coupon_code'] ?? null;
        $normalized = [
            'items' => collect($payload['items'] ?? [])
                ->map(fn ($item) => [
                    'product_public_code' => $item['product_public_code'] ?? null,
                    'variant_public_code' => $item['variant_public_code'] ?? null,
                    'quantity' => (int) ($item['quantity'] ?? 0),
                ])
                ->sortBy([['product_public_code', 'asc'], ['variant_public_code', 'asc']])
                ->values()
                ->all(),
            'delivery' => [
                'method' => $payload['delivery']['method'] ?? null,
                'department' => $this->normalize($payload['delivery']['department'] ?? null),
                'city' => $this->normalize($payload['delivery']['city'] ?? null),
                'zone' => $this->normalize($payload['delivery']['zone'] ?? null),
                'address' => $this->normalizeAddress($payload['delivery']['address'] ?? null),
            ],
            'coupon_hash' => $coupon ? app(StoreCouponCodeService::class)->hash((string) $coupon) : null,
        ];

        return hash('sha256', json_encode($normalized, JSON_THROW_ON_ERROR));
    }

    private function lineDiscounts(array $quote): array
    {
        $discount = $quote['discount_cents'];
        if ($discount <= 0 || ! ($quote['coupon']['coupon'] ?? null)) {
            return [];
        }

        $eligible = $quote['coupon']['eligible_cents'];
        $remaining = $discount;
        $discounts = [];
        foreach ($quote['lines'] as $index => $line) {
            $isEligible = $this->lineEligible($quote['coupon']['coupon'], $line);
            $lineDiscount = $isEligible ? intdiv($discount * $line['line_total_cents'], $eligible) : 0;
            $discounts[$index] = $lineDiscount;
            $remaining -= $lineDiscount;
        }

        foreach ($quote['lines'] as $index => $line) {
            if ($remaining <= 0) {
                break;
            }
            if ($this->lineEligible($quote['coupon']['coupon'], $line)) {
                $discounts[$index]++;
                $remaining--;
            }
        }

        return $discounts;
    }

    private function lineEligible($coupon, array $line): bool
    {
        if ($coupon->targets->isEmpty()) {
            return true;
        }

        return $coupon->targets->contains('store_product_id', $line['product']->id)
            || $coupon->targets->contains('store_category_id', $line['product']->store_category_id);
    }

    private function paymentSnapshot(): array
    {
        return ['status' => 'pending_receipt', 'message' => 'Carga de comprobantes pendiente para una fase posterior.'];
    }

    private function whatsappSnapshot(): ?string
    {
        $settings = StoreSetting::current();

        return $settings->whatsapp_enabled ? $settings->whatsapp_number_encrypted : null;
    }

    private function normalize(mixed $value): ?string
    {
        $value = str($value ?? '')->squish()->upper()->toString();

        return $value !== '' ? $value : null;
    }

    private function normalizeAddress(mixed $value): ?string
    {
        $value = str($value ?? '')->squish()->toString();

        return $value !== '' ? $value : null;
    }
}
