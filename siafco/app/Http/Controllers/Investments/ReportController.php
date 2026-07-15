<?php

namespace App\Http\Controllers\Investments;

use App\Http\Controllers\Controller;
use App\Models\InvestmentLot;
use App\Models\InvestmentReceipt;
use App\Models\InvestmentReturnPeriod;
use App\Models\Investor;
use App\Models\ShareReservation;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->only(['from', 'to', 'status', 'ci']);

        return view('investments.reports.index', [
            'filters' => $filters,
            'investors' => $this->investors($request)->paginate(20)->withQueryString(),
            'summary' => [
                'total_invested' => $this->lots($request)->sum('invested_capital'),
                'total_paid' => $this->receipts($request)->where('status', 'paid')->sum('total_amount'),
                'pending_returns' => $this->periods($request)->whereIn('status', ['pending', 'pending_approval', 'approved'])->count(),
                'active_reservations' => $this->reservations($request)->where('status', 'active')->count(),
            ],
        ]);
    }

    public function pdf(Request $request)
    {
        return Pdf::loadView('investments.reports.pdf', [
            'investors' => $this->investors($request)->limit(500)->get(),
            'summary' => [
                'total_invested' => $this->lots($request)->sum('invested_capital'),
                'total_paid' => $this->receipts($request)->where('status', 'paid')->sum('total_amount'),
            ],
        ])->download('reporte-inversiones.pdf');
    }

    public function csv(Request $request)
    {
        $rows = $this->investors($request)->limit(1000)->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Accionista', 'CI', 'Numero', 'Estado', 'Acciones activas', 'Capital']);
            foreach ($rows as $investor) {
                fputcsv($out, [
                    $investor->person->full_name,
                    $investor->person->ci,
                    $investor->investor_number,
                    $investor->status,
                    $investor->lots->sum('shares_quantity'),
                    $investor->lots->sum('invested_capital'),
                ]);
            }
            fclose($out);
        }, 'reporte-inversiones.csv');
    }

    private function investors(Request $request)
    {
        return Investor::with('person', 'lots')
            ->when($request->status, fn ($query, $status) => $query->where('status', $status))
            ->when($request->ci, fn ($query, $ci) => $query->whereHas('person', fn ($person) => $person->where('ci', 'like', "%{$ci}%")))
            ->latest();
    }

    private function lots(Request $request)
    {
        return InvestmentLot::query()
            ->when($request->from, fn ($query, $from) => $query->whereDate('purchase_date', '>=', $from))
            ->when($request->to, fn ($query, $to) => $query->whereDate('purchase_date', '<=', $to));
    }

    private function periods(Request $request)
    {
        return InvestmentReturnPeriod::query()
            ->when($request->from, fn ($query, $from) => $query->whereDate('due_date', '>=', $from))
            ->when($request->to, fn ($query, $to) => $query->whereDate('due_date', '<=', $to));
    }

    private function receipts(Request $request)
    {
        return InvestmentReceipt::query()
            ->when($request->from, fn ($query, $from) => $query->whereDate('issue_date', '>=', $from))
            ->when($request->to, fn ($query, $to) => $query->whereDate('issue_date', '<=', $to));
    }

    private function reservations(Request $request)
    {
        return ShareReservation::query()
            ->when($request->from, fn ($query, $from) => $query->whereDate('reservation_date', '>=', $from))
            ->when($request->to, fn ($query, $to) => $query->whereDate('reservation_date', '<=', $to));
    }
}
