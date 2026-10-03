<?php

namespace App\Http\Controllers;

use App\Models\Affiliate;
use App\Models\Sector;
use App\Models\User;
use App\Services\AffiliationReportService;
use App\Services\SimpleXlsxWriter;
use App\Support\AffiliationStatusPresenter;
use App\Support\SiafcoDate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AffiliationReportController extends Controller
{
    public function __construct(private readonly AffiliationReportService $reports) {}

    public function index(Request $request)
    {
        abort_unless($request->user()?->hasPermission('reports.affiliations.view'), 403);

        $filters = $this->reports->filters($request);
        $affiliates = $this->reports->query($filters)->paginate(25)->withQueryString();

        return view('reports.affiliations.index', [
            'filters' => $filters,
            'affiliates' => $affiliates,
            'report' => $this->reports,
            'sectors' => Sector::query()->orderBy('name')->get(['id', 'name']),
            'users' => $this->userOptions(),
            'origins' => $this->reports->originOptions(),
            'statuses' => Affiliate::query()->select('status')->distinct()->orderBy('status')->pluck('status')->filter()->values(),
            'canExport' => $request->user()?->hasPermission('reports.affiliations.export'),
        ]);
    }

    public function export(Request $request, SimpleXlsxWriter $xlsx): Response
    {
        abort_unless($request->user()?->hasPermission('reports.affiliations.export'), 403);

        $filters = $this->reports->filters($request);
        $headers = [
            'Fecha afiliación',
            'Código afiliado',
            'Nombre afiliado',
            'CI',
            'Organización',
            'Sindicato',
            'Origen',
            'Registrado por',
            'Gestionado por',
            'Aprobado por',
            'Fecha aprobación',
            'Estado',
        ];

        $rows = function () use ($filters): \Generator {
            foreach ($this->reports->query($filters)->lazy(500) as $affiliate) {
                $row = $this->reports->row($affiliate);
                yield [
                    SiafcoDate::dateTime($row['affiliation_date'], ''),
                    $row['code'],
                    $row['name'],
                    $row['ci'],
                    $row['organization'],
                    $row['sector'],
                    $row['origin'],
                    $row['registered_by'],
                    $row['managed_by'],
                    $row['approved_by'],
                    SiafcoDate::dateTime($row['approved_at'], ''),
                    AffiliationStatusPresenter::label($row['status']),
                ];
            }
        };

        $filename = 'reporte_afiliaciones_'.now()->format('Y-m-d').'.xlsx';

        return response($xlsx->output($headers, $rows(), 'Afiliaciones'), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    private function userOptions()
    {
        return User::query()
            ->where(fn ($query) => $query->where('user_type', 'internal')->orWhereNull('user_type'))
            ->orderBy('name')
            ->get(['id', 'name', 'role']);
    }
}
