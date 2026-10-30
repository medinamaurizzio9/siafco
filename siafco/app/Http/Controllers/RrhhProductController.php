<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpsertRrhhProductRequest;
use App\Models\RrhhProduct;
use App\Services\RrhhProductService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RrhhProductController extends Controller
{
    public function __construct(private readonly RrhhProductService $products) {}

    public function index(Request $request)
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:150'], 'status' => ['nullable', Rule::in(['active', 'inactive'])]]);
        $query = RrhhProduct::query()
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where(fn ($nested) => $nested->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%")))
            ->when(($filters['status'] ?? null) === 'active', fn ($q) => $q->where('is_active', true))
            ->when(($filters['status'] ?? null) === 'inactive', fn ($q) => $q->where('is_active', false));

        return view('rrhh.products.index', ['products' => $query->orderBy('name')->paginate(15)->withQueryString(), 'filters' => $filters]);
    }

    public function create()
    {
        return view('rrhh.products.form', ['product' => new RrhhProduct(['commission_type' => RrhhProduct::COMMISSION_FIXED, 'is_active' => true])]);
    }

    public function store(UpsertRrhhProductRequest $request)
    {
        $product = $this->products->create($request->validated(), $request->user());

        return redirect()->route('rrhh.products.show', $product)->with('status', 'Producto registrado correctamente.');
    }

    public function show(RrhhProduct $product)
    {
        return view('rrhh.products.show', compact('product'));
    }

    public function edit(RrhhProduct $product)
    {
        return view('rrhh.products.form', compact('product'));
    }

    public function update(UpsertRrhhProductRequest $request, RrhhProduct $product)
    {
        $this->products->update($product, $request->validated(), $request->user());

        return redirect()->route('rrhh.products.show', $product)->with('status', 'Producto actualizado correctamente.');
    }

    public function toggle(Request $request, RrhhProduct $product)
    {
        $this->products->toggle($product, $request->user());

        return back()->with('status', 'Estado del producto actualizado.');
    }
}
