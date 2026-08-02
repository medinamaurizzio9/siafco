<?php

namespace App\Services\Store;

use App\Models\Affiliate;
use App\Models\StoreProduct;
use App\Models\StoreProductVariant;
use App\Models\StoreSetting;
use App\Services\StoreShippingRateResolver;
use App\Support\StoreAvailabilityStatus;
use App\Support\StoreDeliveryMethod;
use Illuminate\Validation\ValidationException;

class StorePricingService
{
    public function __construct(
        private readonly StoreShippingRateResolver $shippingRates,
        private readonly StoreCouponService $coupons,
    ) {}

    public function quote(Affiliate $affiliate, array $items, array $delivery, ?string $couponCode = null): array
    {
        $this->assertAffiliateCanBuy($affiliate);
        if ($items === []) {
            throw ValidationException::withMessages(['items' => 'El pedido debe contener al menos un producto.']);
        }

        $lines = [];
        foreach ($items as $index => $item) {
            $lines[] = $this->line($item, $index);
        }

        $subtotalCents = array_sum(array_column($lines, 'line_total_cents'));
        $coupon = $this->coupons->findValidCoupon($couponCode, $affiliate, $subtotalCents, $lines);
        $discountCents = $coupon['discount_cents'] ?? 0;
        $shipping = $this->shipping($delivery);
        $totalCents = max(0, $subtotalCents - $discountCents + $shipping['amount_cents']);

        return [
            'currency' => 'BOB',
            'lines' => $lines,
            'subtotal_cents' => $subtotalCents,
            'discount_cents' => $discountCents,
            'shipping_cents' => $shipping['amount_cents'],
            'total_cents' => $totalCents,
            'subtotal' => StoreMoney::decimal($subtotalCents),
            'discount_total' => StoreMoney::decimal($discountCents),
            'shipping_total' => StoreMoney::decimal($shipping['amount_cents']),
            'total' => StoreMoney::decimal($totalCents),
            'coupon' => $coupon,
            'shipping' => $shipping,
        ];
    }

    private function assertAffiliateCanBuy(Affiliate $affiliate): void
    {
        $affiliate->loadMissing('user');
        if (! $affiliate->user || $affiliate->user->user_type !== 'affiliate' || ! $affiliate->user->is_active || $affiliate->status !== 'activo') {
            throw ValidationException::withMessages(['affiliate' => 'El afiliado no puede crear pedidos.']);
        }
    }

    private function line(array $item, int $index): array
    {
        $product = StoreProduct::query()
            ->with('category')
            ->where('public_code', $item['product_public_code'] ?? null)
            ->lockForUpdate()
            ->first();

        if (! $product || ! $product->active || $product->availability_status !== StoreAvailabilityStatus::AVAILABLE || ! $product->category?->active) {
            throw ValidationException::withMessages(["items.{$index}.product_public_code" => 'El producto no está disponible.']);
        }

        $quantity = (int) ($item['quantity'] ?? 0);
        if ($quantity < 1 || $quantity > $product->max_quantity_per_order) {
            throw ValidationException::withMessages(["items.{$index}.quantity" => 'La cantidad no es válida para este producto.']);
        }

        $variant = null;
        if (! empty($item['variant_public_code'])) {
            $variant = StoreProductVariant::query()
                ->where('public_code', $item['variant_public_code'])
                ->lockForUpdate()
                ->first();

            if (! $variant || (int) $variant->store_product_id !== (int) $product->id || ! $variant->active) {
                throw ValidationException::withMessages(["items.{$index}.variant_public_code" => 'La variante no está disponible.']);
            }
        }

        $base = $this->unitPriceCents($product);
        $delta = $variant ? StoreMoney::cents($variant->price_delta) : 0;
        $unit = max(0, $base['cents'] + $delta);
        $lineTotal = $unit * $quantity;

        return [
            'product' => $product,
            'variant' => $variant,
            'quantity' => $quantity,
            'unit_price_cents' => $unit,
            'line_total_cents' => $lineTotal,
            'unit_price' => StoreMoney::decimal($unit),
            'line_total' => StoreMoney::decimal($lineTotal),
            'price_reason' => $base['reason'],
        ];
    }

    private function unitPriceCents(StoreProduct $product): array
    {
        $candidates = [
            'regular' => StoreMoney::cents($product->regular_price),
            'affiliate' => StoreMoney::cents($product->affiliate_price),
        ];

        if ($product->promo_price !== null
            && (! $product->promo_starts_at || $product->promo_starts_at->lte(now()))
            && (! $product->promo_ends_at || $product->promo_ends_at->gte(now()))) {
            $promo = StoreMoney::cents($product->promo_price);
            if ($promo <= min($candidates)) {
                $candidates['promo'] = $promo;
            }
        }

        $reason = array_search(min($candidates), $candidates, true);

        return ['cents' => min($candidates), 'reason' => $reason ?: 'regular'];
    }

    private function shipping(array $delivery): array
    {
        $method = $delivery['method'] ?? null;
        $settings = StoreSetting::current();

        if ($method === StoreDeliveryMethod::PICKUP) {
            if (! $settings->pickup_enabled) {
                throw ValidationException::withMessages(['delivery_method' => 'El recojo no está habilitado.']);
            }

            return ['method' => $method, 'amount_cents' => 0, 'snapshot' => ['method' => $method, 'amount' => '0.00', 'currency' => 'BOB']];
        }

        if ($method !== StoreDeliveryMethod::SHIPPING) {
            throw ValidationException::withMessages(['delivery_method' => 'La modalidad de entrega no es válida.']);
        }

        if (! $settings->shipping_enabled) {
            throw ValidationException::withMessages(['delivery_method' => 'El envío no está habilitado.']);
        }

        $department = $this->normalize($delivery['department'] ?? null);
        $city = $this->normalize($delivery['city'] ?? null);
        $zone = $this->normalize($delivery['zone'] ?? null);
        $address = trim((string) ($delivery['address'] ?? ''));

        if (! $department || ! $city || $address === '') {
            throw ValidationException::withMessages(['delivery_address' => 'El envío requiere departamento, ciudad y dirección.']);
        }

        $rate = $this->shippingRates->resolve($department, $city, $zone);
        if (! $rate) {
            throw ValidationException::withMessages(['shipping' => 'No existe una tarifa de envío aplicable.']);
        }

        return [
            'method' => $method,
            'department' => $department,
            'city' => $city,
            'zone' => $zone,
            'address' => $address,
            'amount_cents' => StoreMoney::cents($rate->amount),
            'rate' => $rate,
            'snapshot' => [
                'scope' => $rate->scope,
                'department' => $department,
                'city' => $city,
                'zone' => $zone,
                'amount' => $rate->amount,
                'currency' => $rate->currency,
            ],
        ];
    }

    private function normalize(mixed $value): ?string
    {
        $value = str($value ?? '')->squish()->upper()->toString();

        return $value !== '' ? $value : null;
    }
}
