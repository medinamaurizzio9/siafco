<?php

namespace App\Http\Controllers\Investments;

use App\Http\Controllers\Controller;
use App\Models\InvestmentReturnPeriod;
use App\Services\InvestmentService;
use Illuminate\Http\Request;

class ReturnPeriodController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->status ?: $request->route('status');

        return view('investments.returns.index', [
            'periods' => InvestmentReturnPeriod::with('lot.investor.person', 'receipt')
                ->when($status, fn ($query, $status) => $query->where('status', $status))
                ->orderBy('due_date')
                ->paginate(15)
                ->withQueryString(),
        ]);
    }

    public function show(InvestmentReturnPeriod $period)
    {
        return view('investments.returns.show', ['period' => $period->load('lot.investor.person', 'receipt')]);
    }

    public function prepare(Request $request, InvestmentReturnPeriod $period, InvestmentService $service)
    {
        $service->preparePeriod($period, $request->validate([
            'production_bonus_amount' => ['nullable', 'numeric', 'min:0'],
            'extra_concept' => ['nullable', 'string', 'max:255'],
            'extra_amount' => ['nullable', 'numeric', 'min:0'],
            'deductions_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]));

        return back()->with('status', 'Rendimiento enviado a aprobacion.');
    }

    public function approve(InvestmentReturnPeriod $period, InvestmentService $service)
    {
        $service->approvePeriod($period);

        return back()->with('status', 'Rendimiento aprobado. Caja ya puede emitir el recibo.');
    }

    public function reject(Request $request, InvestmentReturnPeriod $period)
    {
        $data = $request->validate(['notes' => ['required', 'string', 'max:1000']]);
        $period->update(['status' => 'rejected', 'notes' => $data['notes']]);

        return back()->with('status', 'Rendimiento rechazado.');
    }
}
