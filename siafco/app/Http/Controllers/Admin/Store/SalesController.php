<?php

namespace App\Http\Controllers\Admin\Store;

use App\Http\Controllers\Controller;
use App\Services\Store\StoreAdminReportService;
use App\Support\StoreDeliveryMethod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SalesController extends Controller
{
    public function index(Request $request, StoreAdminReportService $reports)
    {
        Gate::authorize('store.view');

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'delivery_method' => ['nullable', Rule::in(StoreDeliveryMethod::ALL)],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        return view('admin.store.sales.index', [
            'sales' => $reports->sales($filters),
            'summary' => $reports->salesSummary($filters),
            'filters' => $filters,
            'deliveryMethods' => StoreDeliveryMethod::ALL,
        ]);
    }
}
