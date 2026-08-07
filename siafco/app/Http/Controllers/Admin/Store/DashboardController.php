<?php

namespace App\Http\Controllers\Admin\Store;

use App\Http\Controllers\Controller;
use App\Models\StoreCategory;
use App\Models\StoreProduct;
use App\Models\StoreSetting;
use App\Models\StoreShippingRate;
use App\Services\Store\StoreAdminReportService;
use App\Support\StoreAvailabilityStatus;
use Illuminate\Support\Facades\Gate;

class DashboardController extends Controller
{
    public function __invoke(StoreAdminReportService $reports)
    {
        Gate::authorize('store.view');

        return view('admin.store.dashboard.index', [
            'setting' => StoreSetting::current(),
            'metrics' => [
                'active_products' => StoreProduct::query()->active()->count(),
                'featured_products' => StoreProduct::query()->where('featured', true)->count(),
                'categories' => StoreCategory::query()->count(),
                'sold_out_products' => StoreProduct::query()->where('availability_status', StoreAvailabilityStatus::SOLD_OUT)->count(),
                'active_shipping_rates' => StoreShippingRate::query()->active()->count(),
            ],
            'storeMetrics' => $reports->dashboard(),
            'recentOrders' => $reports->recentOrders(),
        ]);
    }
}
