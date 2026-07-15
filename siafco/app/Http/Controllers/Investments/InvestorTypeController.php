<?php

namespace App\Http\Controllers\Investments;

use App\Http\Controllers\Controller;
use App\Models\InvestorType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InvestorTypeController extends Controller
{
    public function index()
    {
        return view('investments.investor-types.index', [
            'types' => InvestorType::orderBy('order')->orderBy('shares_quantity')->paginate(20),
        ]);
    }

    public function create()
    {
        return view('investments.investor-types.form', ['type' => new InvestorType()]);
    }

    public function store(Request $request)
    {
        InvestorType::create($this->validated($request));

        return redirect()->route('investments.investor-types.index')->with('status', 'Tipo de inversionista creado.');
    }

    public function edit(InvestorType $investorType)
    {
        return view('investments.investor-types.form', ['type' => $investorType]);
    }

    public function update(Request $request, InvestorType $investorType)
    {
        $investorType->update($this->validated($request, $investorType));

        return redirect()->route('investments.investor-types.index')->with('status', 'Tipo actualizado.');
    }

    private function validated(Request $request, ?InvestorType $type = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('investor_types')->ignore($type)],
            'shares_quantity' => ['required', 'integer', 'min:1', 'max:100', Rule::unique('investor_types')->ignore($type)],
            'description' => ['nullable', 'string'],
            'active' => ['nullable', 'boolean'],
            'order' => ['nullable', 'integer', 'min:0'],
        ]) + ['active' => false, 'order' => 0];
    }
}
