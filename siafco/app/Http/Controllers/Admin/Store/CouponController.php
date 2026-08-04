<?php

namespace App\Http\Controllers\Admin\Store;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Store\StoreCouponRequest;
use App\Models\StoreCategory;
use App\Models\StoreCoupon;
use App\Models\StoreProduct;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CouponController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('store.view');
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'type' => ['nullable', 'string', 'max:30'],
            'active' => ['nullable', 'in:0,1'],
        ]);

        return view('admin.store.coupons.index', [
            'coupons' => StoreCoupon::query()
                ->withCount('usages')
                ->when($filters['search'] ?? null, fn ($query, $search) => $query->where('code_hint', 'like', "%{$search}%"))
                ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
                ->when(array_key_exists('active', $filters), fn ($query) => $query->where('active', (bool) $filters['active']))
                ->latest()
                ->paginate(15)->withQueryString(),
            'filters' => $filters,
            'types' => $this->types(),
        ]);
    }

    public function create()
    {
        Gate::authorize('store.manage-coupons');

        return $this->form(new StoreCoupon([
            'type' => StoreCoupon::TYPE_PERCENTAGE,
            'minimum_amount' => 0,
            'active' => true,
        ]));
    }

    public function store(StoreCouponRequest $request)
    {
        $coupon = DB::transaction(function () use ($request): StoreCoupon {
            $coupon = StoreCoupon::create($request->couponData() + [
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);
            $this->syncTargets($coupon, $request->targetProductIds(), $request->targetCategoryIds());

            return $coupon;
        });

        AuditService::record('mini_tienda.cupon_creado', $coupon, $this->auditMetadata($coupon));

        return redirect()->route('admin.store.coupons.index')->with('status', 'Cupón creado.');
    }

    public function edit(StoreCoupon $coupon)
    {
        Gate::authorize('store.manage-coupons');

        return $this->form($coupon->load('targets'));
    }

    public function update(StoreCouponRequest $request, StoreCoupon $coupon)
    {
        DB::transaction(function () use ($request, $coupon): void {
            $coupon->update($request->couponData() + ['updated_by' => $request->user()->id]);
            $this->syncTargets($coupon, $request->targetProductIds(), $request->targetCategoryIds());
        });

        AuditService::record('mini_tienda.cupon_actualizado', $coupon->fresh(), $this->auditMetadata($coupon->fresh()));

        return redirect()->route('admin.store.coupons.index')->with('status', 'Cupón actualizado.');
    }

    public function destroy(StoreCoupon $coupon)
    {
        Gate::authorize('store.manage-coupons');
        $coupon->delete();
        AuditService::record('mini_tienda.cupon_desactivado', $coupon, $this->auditMetadata($coupon));

        return back()->with('status', 'Cupón eliminado de forma lógica.');
    }

    private function form(StoreCoupon $coupon)
    {
        return view('admin.store.coupons.form', [
            'coupon' => $coupon,
            'types' => $this->types(),
            'products' => StoreProduct::query()->active()->orderBy('name')->get(['id', 'name', 'sku']),
            'categories' => StoreCategory::query()->active()->orderBy('name')->get(['id', 'name']),
            'selectedProducts' => $coupon->exists ? $coupon->targets()->whereNotNull('store_product_id')->pluck('store_product_id')->all() : [],
            'selectedCategories' => $coupon->exists ? $coupon->targets()->whereNotNull('store_category_id')->pluck('store_category_id')->all() : [],
        ]);
    }

    private function syncTargets(StoreCoupon $coupon, array $productIds, array $categoryIds): void
    {
        $coupon->targets()->delete();

        foreach ($productIds as $productId) {
            $coupon->targets()->create(['store_product_id' => $productId]);
        }

        foreach ($categoryIds as $categoryId) {
            $coupon->targets()->create(['store_category_id' => $categoryId]);
        }
    }

    private function types(): array
    {
        return [
            StoreCoupon::TYPE_PERCENTAGE => 'Porcentaje',
            StoreCoupon::TYPE_FIXED => 'Monto fijo',
        ];
    }

    private function auditMetadata(StoreCoupon $coupon): array
    {
        return [
            'code_hint' => $coupon->code_hint,
            'type' => $coupon->type,
            'value' => $coupon->value,
            'active' => $coupon->active,
            'starts_at' => $coupon->starts_at?->toDateTimeString(),
            'ends_at' => $coupon->ends_at?->toDateTimeString(),
            'target_count' => $coupon->targets()->count(),
        ];
    }
}
