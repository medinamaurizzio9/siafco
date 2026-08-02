<?php

namespace App\Http\Controllers\Admin\Store;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Store\StoreCategoryRequest;
use App\Models\StoreCategory;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('store.view');
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:120']]);

        return view('admin.store.categories.index', [
            'categories' => StoreCategory::query()
                ->withCount('products')
                ->when($filters['search'] ?? null, fn ($q, $search) => $q->where('name', 'like', "%{$search}%")->orWhere('slug', 'like', "%{$search}%"))
                ->orderBy('order')->orderBy('name')
                ->paginate(15)->withQueryString(),
            'filters' => $filters,
        ]);
    }

    public function create()
    {
        Gate::authorize('store.manage-products');

        return view('admin.store.categories.form', ['category' => new StoreCategory(['active' => true, 'order' => 0])]);
    }

    public function store(StoreCategoryRequest $request)
    {
        $category = StoreCategory::create($request->validated());
        AuditService::record('mini_tienda.categoria_creada', $category, [
            'slug' => $category->slug,
            'active' => $category->active,
        ]);

        return redirect()->route('admin.store.categories.index')->with('status', 'Categoría creada.');
    }

    public function edit(StoreCategory $category)
    {
        Gate::authorize('store.manage-products');

        return view('admin.store.categories.form', compact('category'));
    }

    public function update(StoreCategoryRequest $request, StoreCategory $category)
    {
        $category->update($request->validated());
        AuditService::record('mini_tienda.categoria_actualizada', $category, [
            'slug' => $category->slug,
            'active' => $category->active,
        ]);

        return redirect()->route('admin.store.categories.index')->with('status', 'Categoría actualizada.');
    }

    public function destroy(StoreCategory $category)
    {
        Gate::authorize('store.manage-products');

        if ($category->products()->exists()) {
            return back()->withErrors(['category' => 'No se puede eliminar una categoría con productos. Desactívala si ya no debe mostrarse.']);
        }

        $category->delete();
        AuditService::record('mini_tienda.categoria_desactivada', $category, ['slug' => $category->slug]);

        return back()->with('status', 'Categoría eliminada de forma lógica.');
    }
}
