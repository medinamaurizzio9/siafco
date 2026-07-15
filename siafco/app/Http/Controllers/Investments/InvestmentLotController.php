<?php

namespace App\Http\Controllers\Investments;

use App\Http\Controllers\Controller;
use App\Models\InvestmentLot;
use App\Models\Investor;
use App\Services\InvestmentService;
use Illuminate\Http\Request;

class InvestmentLotController extends Controller
{
    public function index(Request $request)
    {
        return view('investments.lots.index', [
            'lots' => InvestmentLot::with('investor.person')
                ->when($request->status, fn ($query, $status) => $query->where('status', $status))
                ->latest()
                ->paginate(12)
                ->withQueryString(),
        ]);
    }

    public function create(Request $request)
    {
        return view('investments.lots.form', [
            'lot' => new InvestmentLot(),
            'investors' => Investor::with('person')->orderByDesc('id')->limit(100)->get(),
            'selectedInvestor' => $request->investor_id,
        ]);
    }

    public function store(Request $request, InvestmentService $service)
    {
        $data = $request->validate([
            'investor_id' => ['required', 'exists:investors,id'],
            'purchase_date' => ['nullable', 'date'],
            'shares_quantity' => ['required', 'integer', 'min:1'],
            'payment_method' => ['required', 'string', 'max:80'],
            'payment_reference' => ['nullable', 'string', 'max:255'],
            'payment_receipt' => ['nullable', 'file', 'max:4096'],
            'notes' => ['nullable', 'string'],
        ]);

        if ($request->hasFile('payment_receipt')) {
            $data['payment_receipt'] = $request->file('payment_receipt')->store('investments/payments', 'public');
        }

        $lot = $service->createLot(Investor::findOrFail($data['investor_id']), $data);

        return redirect()->route('investments.lots.show', $lot)->with('status', 'Venta registrada como lote pendiente de aprobacion.');
    }

    public function show(InvestmentLot $lot)
    {
        return view('investments.lots.show', ['lot' => $lot->load('investor.person', 'periods')]);
    }

    public function approve(InvestmentLot $lot, InvestmentService $service)
    {
        $service->approveLot($lot);

        return back()->with('status', 'Lote aprobado y calendario de rendimientos generado.');
    }
}
