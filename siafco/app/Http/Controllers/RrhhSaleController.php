<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRrhhSaleRequest;
use App\Models\RrhhProduct;
use App\Models\RrhhSale;
use App\Models\RrhhWorker;
use App\Services\RrhhSaleService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RrhhSaleController extends Controller
{
    public function __construct(private readonly RrhhSaleService $sales) {}

    public function index(Request $request)
    {
        $filters = $request->validate([
            'worker_id' => ['nullable', 'integer', 'exists:rrhh_workers,id'],
            'product_id' => ['nullable', 'integer', 'exists:rrhh_products,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'status' => ['nullable', Rule::in([RrhhSale::STATUS_PENDING, RrhhSale::STATUS_CONFIRMED, RrhhSale::STATUS_VOIDED])],
        ]);
        $query = RrhhSale::query()->with(['worker', 'confirmer'])
            ->when($filters['worker_id'] ?? null, fn ($q, $id) => $q->where('worker_id', $id))
            ->when($filters['product_id'] ?? null, fn ($q, $id) => $q->whereHas('items', fn ($items) => $items->where('product_id', $id)))
            ->when($filters['date_from'] ?? null, fn ($q, $date) => $q->whereDate('sold_on', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($q, $date) => $q->whereDate('sold_on', '<=', $date))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status));

        return view('rrhh.sales.index', [
            'sales' => $query->orderByDesc('sold_on')->orderByDesc('id')->paginate(15)->withQueryString(),
            'filters' => $filters,
            'workers' => RrhhWorker::orderBy('full_name')->get(['id', 'code', 'full_name']),
            'products' => RrhhProduct::orderBy('name')->get(['id', 'code', 'name']),
        ]);
    }

    public function create()
    {
        return view('rrhh.sales.form', [
            'workers' => RrhhWorker::where('is_active', true)->where('commissions_enabled', true)->orderBy('full_name')->get(),
            'products' => RrhhProduct::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(StoreRrhhSaleRequest $request)
    {
        $sale = $this->sales->create($request->validated(), $request->user());

        return redirect()->route('rrhh.sales.show', $sale)->with('status', 'Venta registrada como pendiente.');
    }

    public function show(RrhhSale $sale)
    {
        return view('rrhh.sales.show', ['sale' => $sale->load(['worker', 'items', 'creator', 'confirmer', 'voider'])]);
    }

    public function confirm(Request $request, RrhhSale $sale)
    {
        $this->sales->confirm($sale, $request->user());

        return back()->with('status', 'Venta confirmada y comisión habilitada.');
    }

    public function void(Request $request, RrhhSale $sale)
    {
        $data = $request->validate(['void_reason' => ['required', 'string', 'min:5', 'max:1000']]);
        $this->sales->void($sale, $request->user(), $data['void_reason']);

        return back()->with('status', 'Venta anulada correctamente.');
    }
}
