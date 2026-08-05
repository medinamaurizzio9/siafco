<?php

namespace App\Http\Controllers\Admin\Store;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Store\StoreProductImageRequest;
use App\Models\StoreProduct;
use App\Models\StoreProductImage;
use App\Services\AuditService;
use App\Services\StoreProductImageProcessor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProductImageController extends Controller
{
    public function store(StoreProductImageRequest $request, StoreProduct $product, StoreProductImageProcessor $processor)
    {
        $path = $processor->process($product, $request->file('image'));

        try {
            $image = DB::transaction(function () use ($request, $product, $path) {
                if ($request->boolean('is_primary') || ! $product->images()->exists()) {
                    $product->images()->update(['is_primary' => false]);
                }

                return $product->images()->create([
                    'path' => $path,
                    'alt' => $request->validated('alt'),
                    'is_primary' => $request->boolean('is_primary') || ! $product->images()->exists(),
                    'order' => $request->validated('order'),
                ]);
            });
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($path);
            throw $exception;
        }

        AuditService::record('mini_tienda.imagen_agregada', $image, [
            'product_sku' => $product->sku,
            'is_primary' => $image->is_primary,
        ]);

        return back()->with('status', 'Imagen agregada.');
    }

    public function makePrimary(StoreProduct $product, StoreProductImage $image)
    {
        Gate::authorize('store.manage-products');
        abort_unless((int) $image->store_product_id === (int) $product->id, 404);

        DB::transaction(function () use ($product, $image): void {
            $product->images()->update(['is_primary' => false]);
            $image->forceFill(['is_primary' => true])->save();
        });

        AuditService::record('mini_tienda.imagen_principal_actualizada', $image, ['product_sku' => $product->sku]);

        return back()->with('status', 'Imagen principal actualizada.');
    }

    public function update(Request $request, StoreProduct $product, StoreProductImage $image)
    {
        Gate::authorize('store.manage-products');
        abort_unless((int) $image->store_product_id === (int) $product->id, 404);
        $data = $request->validate([
            'alt' => ['nullable', 'string', 'max:180'],
            'order' => ['required', 'integer', 'min:0', 'max:9999'],
        ]);
        $image->update($data);
        AuditService::record('mini_tienda.imagen_actualizada', $image, ['product_sku' => $product->sku]);

        return back()->with('status', 'Imagen actualizada.');
    }

    public function destroy(StoreProduct $product, StoreProductImage $image)
    {
        Gate::authorize('store.manage-products');
        abort_unless((int) $image->store_product_id === (int) $product->id, 404);
        $path = $image->path;
        $image->delete();
        Storage::disk('public')->delete($path);
        AuditService::record('mini_tienda.imagen_eliminada', $image, [
            'product_sku' => $product->sku,
            'was_primary' => $image->is_primary,
        ]);

        if ($image->is_primary && $next = $product->images()->orderBy('order')->first()) {
            $next->forceFill(['is_primary' => true])->save();
        }

        return back()->with('status', 'Imagen eliminada.');
    }
}
