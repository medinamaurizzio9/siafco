<?php

namespace App\Http\Controllers;

use App\Models\Affiliate;
use App\Models\AffiliationPayment;
use App\Models\DigitalCredential;
use App\Models\InstitutionalSetting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        return view('reports.index', $this->data($request));
    }

    public function pdf(Request $request)
    {
        $data = $this->data($request);
        $data['institution'] = InstitutionalSetting::current();

        return Pdf::loadView('reports.pdf', $data)
            ->setPaper('letter')
            ->download('reporte-siafco.pdf');
    }

    private function data(Request $request): array
    {
        $from = $request->date('from');
        $to = $request->date('to');

        $incomeQuery = AffiliationPayment::where('status', 'confirmado');
        if ($from) {
            $incomeQuery->whereDate('confirmed_at', '>=', $from);
        }
        if ($to) {
            $incomeQuery->whereDate('confirmed_at', '<=', $to);
        }

        return [
            'bySector' => Affiliate::selectRaw('sector_id, count(*) total')->with('sector')->groupBy('sector_id')->get(),
            'byStatus' => Affiliate::selectRaw('status, count(*) total')->groupBy('status')->pluck('total', 'status'),
            'pendingPayments' => AffiliationPayment::where('status', 'pendiente')->count(),
            'confirmedPayments' => AffiliationPayment::where('status', 'confirmado')->count(),
            'credentials' => DigitalCredential::count(),
            'income' => $incomeQuery->sum('amount'),
            'from' => $from,
            'to' => $to,
        ];
    }
}
