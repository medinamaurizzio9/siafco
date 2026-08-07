<?php

namespace App\Http\Resources\Api\Mobile\V1;

use App\Models\StoreProduct;
use App\Services\Store\StoreMoney;
use App\Support\StoreAvailabilityStatus;
use App\Support\StoreProductImages;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var StoreProduct $product */
        $product = $this->resource;
        return [
            'public_code' => $product->public_code,
            'slug' => $product->slug,
            'sku' => $product->sku,
            'name' => $product->name,
            'short_description' => $product->short_description,
            'description' => $this->when($request->routeIs('api.mobile.v1.store.products.show'), $product->description),
            'regular_price' => $product->regular_price,
            'affiliate_price' => $product->affiliate_price,
            'effective_price' => StoreMoney::decimal($this->effectivePriceCents($product)),
            'promo_price' => $this->activePromo($product) ? $product->promo_price : null,
            'currency' => 'BOB',
            'availability_status' => $product->availability_status,
            'delivery_modes' => $product->delivery_modes ?? [],
            'featured' => (bool) $product->featured,
            'max_quantity_per_order' => $product->max_quantity_per_order,
            'primary_image_url' => StoreProductImages::primaryImageUrl($product),
            'category' => $product->category ? [
                'slug' => $product->category->slug,
                'name' => $product->category->name,
            ] : null,
            'capabilities' => [
                'can_order' => $product->availability_status === StoreAvailabilityStatus::AVAILABLE,
            ],
            'images' => $this->whenLoaded('images', fn () => $product->images->sortBy('order')->values()->map(fn ($image) => [
                'url' => StoreProductImages::publicUrl($image->path),
                'alt' => $image->alt,
                'is_primary' => (bool) $image->is_primary,
            ])->all()),
            'variants' => $this->whenLoaded('variants', fn () => $product->variants->where('active', true)->sortBy('order')->values()->map(fn ($variant) => [
                'public_code' => $variant->public_code,
                'name' => $variant->name,
                'type' => $variant->type,
                'price_delta' => $variant->price_delta,
                'effective_price' => StoreMoney::decimal(max(0, $this->effectivePriceCents($product) + StoreMoney::cents($variant->price_delta))),
            ])->all()),
        ];
    }

    private function effectivePriceCents(StoreProduct $product): int
    {
        $prices = [
            StoreMoney::cents($product->regular_price),
            StoreMoney::cents($product->affiliate_price),
        ];

        if ($this->activePromo($product)) {
            $prices[] = StoreMoney::cents($product->promo_price);
        }

        return min($prices);
    }

    private function activePromo(StoreProduct $product): bool
    {
        return $product->promo_price !== null
            && (! $product->promo_starts_at || $product->promo_starts_at->lte(now()))
            && (! $product->promo_ends_at || $product->promo_ends_at->gte(now()));
    }

}
