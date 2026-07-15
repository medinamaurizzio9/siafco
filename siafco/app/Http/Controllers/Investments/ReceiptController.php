<?php

namespace App\Http\Controllers\Investments;

use App\Http\Controllers\Controller;
use App\Models\InvestmentReceipt;
use App\Models\InvestmentReturnPeriod;
use App\Services\InvestmentService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class ReceiptController extends Controller
{
    public function index()
    {
        return view('investments.receipts.index', [
            'receipts' => InvestmentReceipt::with('investor.person', 'lot')->latest()->paginate(15),
        ]);
    }

    public function issue(Request $request, InvestmentReturnPeriod $period, InvestmentService $service)
    {
        $receipt = $service->issueReceipt($period, $request->validate([
            'payment_method' => ['required', 'string', 'max:80'],
            'payment_reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]));

        return redirect()->route('investments.receipts.show', $receipt)->with('status', 'Recibo definitivo emitido.');
    }

    public function show(InvestmentReceipt $receipt)
    {
        return view('investments.receipts.show', ['receipt' => $receipt->load('investor.person', 'lot', 'period')]);
    }

    public function pdf(InvestmentReceipt $receipt)
    {
        $pdf = Pdf::loadView('investments.receipts.pdf', ['receipt' => $receipt->load('investor.person', 'lot', 'period')])
            ->setPaper('letter');

        return $pdf->download($receipt->receipt_number.'.pdf');
    }

    public function void(Request $request, InvestmentReceipt $receipt)
    {
        $data = $request->validate(['void_reason' => ['required', 'string', 'max:1000']]);

        $receipt->update([
            'status' => 'voided',
            'void_reason' => $data['void_reason'],
            'voided_by' => auth()->id(),
            'voided_at' => now(),
        ]);

        return back()->with('status', 'Recibo anulado sin eliminarse.');
    }
}
