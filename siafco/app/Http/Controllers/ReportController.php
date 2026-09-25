<?php

namespace App\Http\Controllers;

use App\Models\Affiliate;
use App\Models\InstitutionalSetting;
use App\Models\Sector;
use App\Models\User;
use App\Services\AffiliationReportSummaryService;
use App\Support\SiafcoDate;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(private readonly AffiliationReportSummaryService $summary) {}

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

    public function jewelDeliveryCsv(Request $request)
    {
        abort_unless($request->user()?->hasPermission('affiliate_jewels.report') || $request->user()?->hasPermission('reports.export'), 403);

        $filename = 'entrega-joyas-'.now()->format('Ymd-His').'.csv';
        $rows = $this->jewelDeliveryQuery($request)
            ->with('sector', 'jewelDeliveredBy')
            ->orderBy('full_name')
            ->get();

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Codigo afiliado', 'Nombre', 'CI', 'Sector', 'Estado de joya', 'Fecha de entrega', 'Entregada por']);

            foreach ($rows as $affiliate) {
                fputcsv($handle, [
                    $affiliate->registration_number,
                    $affiliate->full_name,
                    $affiliate->ci,
                    $affiliate->sector?->name,
                    $affiliate->jewel_delivered_at ? 'ENTREGADA' : 'PENDIENTE',
                    SiafcoDate::dateTime($affiliate->jewel_delivered_at, ''),
                    $affiliate->jewelDeliveredBy?->name,
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function data(Request $request): array
    {
        $from = $request->date('from');
        $to = $request->date('to');

        $summary = $this->summary->summary(
            $from?->format('Y-m-d'),
            $to?->format('Y-m-d'),
        );

        $jewelQuery = $this->jewelDeliveryQuery($request);
        $totalJewelAffiliates = (clone $jewelQuery)->count();
        $deliveredJewels = (clone $jewelQuery)->whereNotNull('jewel_delivered_at')->count();

        return [
            'bySector' => Affiliate::selectRaw('sector_id, count(*) total')->with('sector')->groupBy('sector_id')->get(),
            'byStatus' => Affiliate::selectRaw('status, count(*) total')->groupBy('status')->pluck('total', 'status'),
            'pendingPayments' => $summary['pending_payments'],
            'confirmedPayments' => $summary['confirmed_payments'],
            'credentials' => $summary['credentials'],
            'income' => $summary['confirmed_income'],
            'from' => $from,
            'to' => $to,
            'jewelRows' => (clone $jewelQuery)->with('sector', 'jewelDeliveredBy')->orderBy('full_name')->limit(250)->get(),
            'jewelTotal' => $totalJewelAffiliates,
            'jewelDelivered' => $deliveredJewels,
            'jewelPending' => max(0, $totalJewelAffiliates - $deliveredJewels),
            'jewelPercent' => $totalJewelAffiliates > 0 ? round(($deliveredJewels / $totalJewelAffiliates) * 100, 1) : 0,
            'jewelSectors' => Sector::where('is_active', true)->orderBy('name')->get(),
            'jewelUsers' => User::query()
                ->whereIn('id', Affiliate::query()->whereNotNull('jewel_delivered_by')->select('jewel_delivered_by'))
                ->orderBy('name')
                ->get(),
        ];
    }

    private function jewelDeliveryQuery(Request $request): Builder
    {
        $query = Affiliate::query();

        if ($request->filled('jewel_status')) {
            match ($request->input('jewel_status')) {
                'delivered' => $query->whereNotNull('jewel_delivered_at'),
                'pending' => $query->whereNull('jewel_delivered_at'),
                default => null,
            };
        }

        if ($request->filled('jewel_from')) {
            [$from] = SiafcoDate::utcDayBounds($request->input('jewel_from'));
            $query->where('jewel_delivered_at', '>=', $from);
        }

        if ($request->filled('jewel_to')) {
            [, $to] = SiafcoDate::utcDayBounds($request->input('jewel_to'));
            $query->where('jewel_delivered_at', '<=', $to);
        }

        if ($request->filled('jewel_sector_id')) {
            $query->where('sector_id', $request->integer('jewel_sector_id'));
        }

        if ($request->filled('jewel_delivered_by')) {
            $query->where('jewel_delivered_by', $request->integer('jewel_delivered_by'));
        }

        if ($request->filled('jewel_search')) {
            $search = trim((string) $request->input('jewel_search'));
            $query->where(function ($inner) use ($search) {
                $inner->where('full_name', 'like', "%{$search}%")
                    ->orWhere('ci', 'like', "%{$search}%")
                    ->orWhere('registration_number', 'like', "%{$search}%");
            });
        }

        return $query;
    }
}
