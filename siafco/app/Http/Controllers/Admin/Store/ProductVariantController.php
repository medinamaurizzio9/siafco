<?php

namespace App\Http\Controllers\Admin\Store;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Store\StoreProductVariantRequest;
use App\Models\StoreProduct;
use App\Models\StoreProductVariant;
use App\Services\AuditService;
use Illuminate\Support\Facades\Gate;

class ProductVariantController extends Controller
{
    public function create(StoreProduct $product)
    {
        Gate::authorize('store.manage-products');

        return view('admin.store.products.variant-form', [
            'product' => $product,
            'variant' => new StoreProductVariant(['active' => true, 'order' => 0, 'price_delta' => 0]),
        ]);
    }

    public function store(StoreProductVariantRequest $request, StoreProduct $product)
    {
        $variant = $product->variants()->create($request->validated());
        AuditService::record('mini_tienda.variante_creada', $variant, [
            'product_sku' => $product->sku,
            'public_code' => $variant->public_code,
            'active' => $variant->active,
        ]);

        return redirect()->route('admin.store.products.edit', $product)->with('status', 'Variante creada.');
    }

    public function edit(StoreProduct $product, StoreProductVariant $variant)
    {
        Gate::authorize('store.manage-products');
        abort_unless((int) $variant->store_product_id === (int) $product->id, 404);

        return view('admin.store.products.variant-form', compact('product', 'variant'));
    }

    public function update(StoreProductVariantRequest $request, StoreProduct $product, StoreProductVariant $variant)
    {
        abort_unless((int) $variant->store_product_id === (int) $product->id, 404);
        $variant->update($request->validated());
        AuditService::record('mini_tienda.variante_actualizada', $variant, [
            'product_sku' => $product->sku,
            'public_code' => $variant->public_code,
            'active' => $variant->active,
        ]);

        return redirect()->route('admin.store.products.edit', $product)->with('status', 'Variante actualizada.');
    }

    public function destroy(StoreProduct $product, StoreProductVariant $variant)
    {
        Gate::authorize('store.manage-products');
        abort_unless((int) $variant->store_product_id === (int) $product->id, 404);
        $variant->delete();
        AuditService::record('mini_tienda.variante_desactivada', $variant, [
            'product_sku' => $product->sku,
            'public_code' => $variant->public_code,
        ]);

        return back()->with('status', 'Variante eliminada de forma lógica.');
    }
}
