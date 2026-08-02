<?php

namespace App\Services\Store;

use App\Models\Affiliate;
use App\Models\StoreProduct;
use App\Models\StoreProductVariant;
use App\Support\StoreDeliveryMethod;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StoreCartService
{
    private const SESSION_KEY = 'store_cart.lines';
    private const MAX_LINES = 30;

    public function lines(): array
    {
        return array_values(session(self::SESSION_KEY, []));
    }

    public function count(): int
    {
        return collect($this->lines())->sum('quantity');
    }

    public function add(StoreProduct $product, ?StoreProductVariant $variant, int $quantity): void
    {
        $this->assertPurchasable($product, $variant, $quantity);
        $lines = $this->lines();
        foreach ($lines as &$line) {
            if ($line['product_public_code'] === $product->public_code && ($line['variant_public_code'] ?? null) === $variant?->public_code) {
                $line['quantity'] = min($product->max_quantity_per_order, $line['quantity'] + $quantity);
                $this->put($lines);
                return;
            }
        }

        if (count($lines) >= self::MAX_LINES) {
            throw ValidationException::withMessages(['cart' => 'El carrito alcanzó el máximo de líneas permitido.']);
        }

        $lines[] = [
            'line_key' => (string) Str::uuid(),
            'product_public_code' => $product->public_code,
            'variant_public_code' => $variant?->public_code,
            'quantity' => $quantity,
        ];
        $this->put($lines);
    }

    public function update(string $lineKey, int $quantity): void
    {
        $lines = $this->lines();
        foreach ($lines as &$line) {
            if ($line['line_key'] === $lineKey) {
                if ($quantity < 1) {
                    throw ValidationException::withMessages(['quantity' => 'La cantidad no es válida.']);
                }
                $line['quantity'] = $quantity;
                break;
            }
        }
        $this->put($lines);
    }

    public function remove(string $lineKey): void
    {
        $this->put(array_values(array_filter($this->lines(), fn ($line) => $line['line_key'] !== $lineKey)));
    }

    public function clear(): void
    {
        session()->forget(self::SESSION_KEY);
        session()->forget('store_checkout.idempotency_key');
    }

    public function payloadItems(): array
    {
        return collect($this->lines())->map(fn ($line) => [
            'product_public_code' => $line['product_public_code'],
            'variant_public_code' => $line['variant_public_code'] ?? null,
            'quantity' => (int) $line['quantity'],
        ])->all();
    }

    public function quote(Affiliate $affiliate, array $delivery = ['method' => StoreDeliveryMethod::PICKUP], ?string $couponCode = null): array
    {
        return app(StorePricingService::class)->quote($affiliate, $this->payloadItems(), $delivery, $couponCode);
    }

    public function normalizedLines(): array
    {
        $normalized = [];
        foreach ($this->lines() as $line) {
            $product = StoreProduct::query()->where('public_code', $line['product_public_code'] ?? null)->first();
            $variant = ! empty($line['variant_public_code'])
                ? StoreProductVariant::query()->where('public_code', $line['variant_public_code'])->first()
                : null;

            if (! $product || $product->availability_status === \App\Support\StoreAvailabilityStatus::HIDDEN || ! $product->active || ! $product->category?->active) {
                continue;
            }

            $line['quantity'] = min(max(1, (int) $line['quantity']), $product->max_quantity_per_order);
            $normalized[] = $line;
        }
        $this->put($normalized);

        return $normalized;
    }

    private function assertPurchasable(StoreProduct $product, ?StoreProductVariant $variant, int $quantity): void
    {
        if (! $product->active || ! $product->category?->active || $product->availability_status !== \App\Support\StoreAvailabilityStatus::AVAILABLE) {
            throw ValidationException::withMessages(['product' => 'El producto no está disponible para compra.']);
        }

        if ($variant && ((int) $variant->store_product_id !== (int) $product->id || ! $variant->active)) {
            throw ValidationException::withMessages(['variant' => 'La variante no está disponible.']);
        }

        if ($quantity < 1 || $quantity > $product->max_quantity_per_order) {
            throw ValidationException::withMessages(['quantity' => 'La cantidad no es válida para este producto.']);
        }
    }

    private function put(array $lines): void
    {
        session([self::SESSION_KEY => array_values($lines)]);
    }
}
