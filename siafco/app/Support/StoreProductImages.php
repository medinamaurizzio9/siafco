<?php

namespace App\Support;

use App\Models\StoreProduct;
use Illuminate\Support\Facades\Storage;

class StoreProductImages
{
    public static function primaryImageUrl(?StoreProduct $product): ?string
    {
        if (! $product || ! $product->relationLoaded('images')) {
            return null;
        }

        $primaryImage = $product->images
            ->sortBy([['is_primary', 'desc'], ['order', 'asc']])
            ->first();

        return self::publicUrl($primaryImage?->path);
    }

    public static function publicUrl(?string $path): ?string
    {
        if (! $path || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        return Storage::disk('public')->url($path).'?v='.Storage::disk('public')->lastModified($path);
    }
}
