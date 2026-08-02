<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\StoreCategory;
use App\Models\StoreProduct;
use App\Support\StoreAvailabilityStatus;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:180'],
        ]);

        $category = isset($filters['category'])
            ? StoreCategory::query()->active()->where('slug', $filters['category'])->first()
            : null;

        return view('store.catalog.index', [
            'products' => StoreProduct::query()
                ->with(['category', 'images' => fn ($query) => $query->orderByDesc('is_primary')->orderBy('order')])
                ->active()
                ->whereHas('category', fn ($query) => $query->active())
                ->where('availability_status', '!=', StoreAvailabilityStatus::HIDDEN)
                ->when($category, fn ($query) => $query->where('store_category_id', $category->id))
                ->when($filters['search'] ?? null, function ($query, $search): void {
                    $query->where(function ($nested) use ($search): void {
                        $nested->where('name', 'like', "%{$search}%")
                            ->orWhere('short_description', 'like', "%{$search}%")
                            ->orWhere('description', 'like', "%{$search}%");
                    });
                })
                ->orderByDesc('featured')
                ->orderBy('order')
                ->orderBy('name')
                ->paginate(12)->withQueryString(),
            'categories' => StoreCategory::query()->active()->orderBy('order')->orderBy('name')->get(),
            'filters' => $filters,
        ]);
    }

    public function show(string $slug)
    {
        $product = StoreProduct::query()
            ->with(['category', 'variants' => fn ($query) => $query->active()->orderBy('order'), 'images' => fn ($query) => $query->orderByDesc('is_primary')->orderBy('order')])
            ->active()
            ->where('slug', $slug)
            ->where('availability_status', '!=', StoreAvailabilityStatus::HIDDEN)
            ->whereHas('category', fn ($query) => $query->active())
            ->firstOrFail();

        return view('store.catalog.show', compact('product'));
    }
}
