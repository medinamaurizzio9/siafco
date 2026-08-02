<?php

namespace App\Http\Controllers\Admin\Store;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Store\StoreProductRequest;
use App\Models\StoreCategory;
use App\Models\StoreProduct;
use App\Services\AuditService;
use App\Support\StoreAvailabilityStatus;
use App\Support\StoreDeliveryMethod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('store.view');
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'integer'],
            'availability_status' => ['nullable', 'string'],
        ]);

        return view('admin.store.products.index', [
            'products' => StoreProduct::query()->with('category')
                ->when($filters['search'] ?? null, fn ($q, $search) => $q->where(function ($nested) use ($search) {
                    $nested->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                }))
                ->when($filters['category'] ?? null, fn ($q, $category) => $q->where('store_category_id', $category))
                ->when($filters['availability_status'] ?? null, fn ($q, $status) => $q->where('availability_status', $status))
                ->orderBy('order')->orderBy('name')
                ->paginate(15)->withQueryString(),
            'categories' => StoreCategory::query()->orderBy('name')->get(),
            'filters' => $filters,
            'availabilityStatuses' => StoreAvailabilityStatus::ALL,
        ]);
    }

    public function create()
    {
        Gate::authorize('store.manage-products');

        return $this->form(new StoreProduct([
            'availability_status' => StoreAvailabilityStatus::AVAILABLE,
            'delivery_modes' => [StoreDeliveryMethod::PICKUP],
            'max_quantity_per_order' => 10,
            'active' => true,
            'order' => 0,
        ]));
    }

    public function store(StoreProductRequest $request)
    {
        $product = StoreProduct::create($request->validated());
        AuditService::record('mini_tienda.producto_creado', $product, [
            'slug' => $product->slug,
            'sku' => $product->sku,
            'availability_status' => $product->availability_status,
            'active' => $product->active,
        ]);

        return redirect()->route('admin.store.products.index')->with('status', 'Producto creado.');
    }

    public function edit(StoreProduct $product)
    {
        Gate::authorize('store.manage-products');

        return $this->form($product->load(['variants' => fn ($q) => $q->orderBy('order'), 'images' => fn ($q) => $q->orderBy('order')]));
    }

    public function update(StoreProductRequest $request, StoreProduct $product)
    {
        $product->update($request->validated());
        AuditService::record('mini_tienda.producto_actualizado', $product, [
            'slug' => $product->slug,
            'sku' => $product->sku,
            'availability_status' => $product->availability_status,
            'active' => $product->active,
        ]);

        return redirect()->route('admin.store.products.index')->with('status', 'Producto actualizado.');
    }

    public function destroy(StoreProduct $product)
    {
        Gate::authorize('store.manage-products');
        $product->delete();
        AuditService::record('mini_tienda.producto_desactivado', $product, [
            'slug' => $product->slug,
            'sku' => $product->sku,
        ]);

        return back()->with('status', 'Producto eliminado de forma lógica.');
    }

    private function form(StoreProduct $product)
    {
        return view('admin.store.products.form', [
            'product' => $product,
            'categories' => StoreCategory::query()->active()->orderBy('name')->get(),
            'availabilityStatuses' => StoreAvailabilityStatus::ALL,
            'deliveryMethods' => StoreDeliveryMethod::ALL,
        ]);
    }
}
