<?php

namespace App\Http\Controllers;

use App\Models\InstitutionalSetting;
use App\Models\RrhhAdjustment;
use App\Models\RrhhPayPeriod;
use App\Models\RrhhWorker;
use App\Services\RrhhAdjustmentService;
use App\Services\RrhhPayrollService;
use App\Services\RrhhReportingService;
use App\Services\SimpleXlsxWriter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class RrhhReportController extends Controller
{
    public function __construct(private readonly RrhhPayrollService $payroll, private readonly RrhhAdjustmentService $adjustments, private readonly RrhhReportingService $reporting) {}

    public function index(Request $request)
    {
        abort_unless($request->user()->hasPermission('rrhh.reports.view'), 403);
        $filters = $this->reporting->filters($request);

        return view('rrhh.reports.index', array_merge($this->reporting->report($filters), $this->reporting->options(), ['filters' => $filters]));
    }

    public function pdf(Request $request): Response
    {
        abort_unless($request->user()->hasPermission('rrhh.reports.print'), 403);
        $filters = $this->reporting->filters($request);
        $report = $this->reporting->report($filters, false, RrhhReportingService::PDF_LIMIT);

        return Pdf::loadView('rrhh.reports.pdf', array_merge($report, ['filters' => $filters, 'filterSummary' => $this->filterSummary($filters), 'institution' => InstitutionalSetting::current()]))
            ->setPaper('a4', count($report['headers']) > 8 ? 'landscape' : 'portrait')
            ->download($this->filename($filters['type'], 'pdf'));
    }

    public function excel(Request $request, SimpleXlsxWriter $xlsx): Response
    {
        abort_unless($request->user()->hasPermission('rrhh.reports.print'), 403);
        $filters = $this->reporting->filters($request);
        $report = $this->reporting->report($filters, false, RrhhReportingService::EXPORT_LIMIT);
        $rows = $report['rows']->map(function (array $row) use ($report, $xlsx) {
            foreach ($report['numeric'] as $key) {
                if (isset($row[$key]) && preg_match('/^-?\d+(\.\d+)?$/', (string) $row[$key])) {
                    $row[$key] = $xlsx->number($row[$key]);
                }
            }

            return $row;
        });
        $metadata = ['Informe' => $report['title'], 'Criterio temporal' => $report['criterion'], 'Período' => ($filters['from'] ?? 'Sin inicio').' a '.($filters['to'] ?? 'Sin fin'), 'Filtros' => $this->filterSummary($filters), 'Emitido' => now()->format('d/m/Y H:i')];
        foreach ($report['totals'] as $label => $value) {
            $metadata[$label] = $value;
        }

        return response($xlsx->output(array_values($report['headers']), $rows, 'Personal y Comisiones', $metadata), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$this->filename($filters['type'], 'xlsx').'"',
        ]);
    }

    public function periods(Request $request)
    {
        abort_unless($request->user()->hasPermission('rrhh.payments.view'), 403);

        return view('rrhh.reports.periods-index', [
            'periods' => RrhhPayPeriod::with('worker')->latest('id')->paginate(15, ['*'], 'periods'),
        ]);
    }

    public function adjustments(Request $request)
    {
        abort_unless($request->user()->hasPermission('rrhh.reports.view'), 403);

        return view('rrhh.reports.adjustments-index', [
            'adjustments' => RrhhAdjustment::with(['worker', 'sourceSale'])->latest('id')->paginate(15, ['*'], 'adjustments'),
            'workers' => RrhhWorker::where('is_active', true)->orderBy('full_name')->get(),
            'submissionKey' => (string) Str::uuid(),
        ]);
    }

    public function createPeriod(Request $request)
    {
        abort_unless($request->user()->hasPermission('rrhh.payments.prepare'), 403);

        return view('rrhh.reports.period-form', ['workers' => RrhhWorker::where('is_active', true)->orderBy('full_name')->get(), 'adjustments' => RrhhAdjustment::with('worker')->where('status', 'pending')->where('review_status', 'approved')->orderBy('applies_on')->get(), 'period' => null]);
    }

    public function storePeriod(Request $request)
    {
        abort_unless($request->user()->hasPermission('rrhh.payments.prepare'), 403);
        $data = $this->periodData($request);
        $period = $this->payroll->calculate($data, $request->user());

        return redirect()->route('rrhh.reports.periods.show', $period)->with('status', 'Liquidación calculada como borrador.');
    }

    public function showPeriod(Request $request, RrhhPayPeriod $period)
    {
        abort_unless($request->user()->hasPermission('rrhh.payments.view'), 403);

        return view('rrhh.reports.period-show', ['period' => $period->load(['worker', 'lines.saleItem.sale', 'lines.adjustment', 'calculator', 'closer', 'payer'])]);
    }

    public function editPeriod(Request $request, RrhhPayPeriod $period)
    {
        abort_unless($request->user()->hasPermission('rrhh.payments.prepare'), 403);
        abort_unless($period->status === RrhhPayPeriod::STATUS_DRAFT, 404);

        return view('rrhh.reports.period-form', [
            'workers' => RrhhWorker::whereKey($period->worker_id)->get(),
            'adjustments' => RrhhAdjustment::where('worker_id', $period->worker_id)->where('status', 'pending')->where('review_status', 'approved')->whereBetween('applies_on', [$period->date_from, $period->date_to])->get(),
            'period' => $period->load('lines'),
        ]);
    }

    public function updatePeriod(Request $request, RrhhPayPeriod $period)
    {
        abort_unless($request->user()->hasPermission('rrhh.payments.prepare'), 403);
        $updated = $this->payroll->calculate($this->periodData($request), $request->user(), $period);

        return redirect()->route('rrhh.reports.periods.show', $updated)->with('status', 'Borrador recalculado.');
    }

    public function closePeriod(Request $request, RrhhPayPeriod $period)
    {
        abort_unless($request->user()->hasPermission('rrhh.payments.prepare'), 403);
        $this->payroll->close($period, $request->user());

        return back()->with('status', 'Liquidación cerrada.');
    }

    public function payPeriod(Request $request, RrhhPayPeriod $period)
    {
        abort_unless($request->user()->hasPermission('rrhh.payments.mark_paid'), 403);
        $data = $request->validate(['paid_at' => ['required', 'date'], 'paid_amount' => ['required', 'decimal:0,2', 'min:0'], 'payment_reference' => ['nullable', 'string', 'max:120']]);
        $this->payroll->pay($period, $data, $request->user());

        return back()->with('status', 'Pago manual registrado.');
    }

    public function voidPeriod(Request $request, RrhhPayPeriod $period)
    {
        abort_unless($request->user()->hasPermission('rrhh.payments.prepare'), 403);
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);
        $this->payroll->void($period, $request->user(), $data['reason']);

        return redirect()->route('rrhh.reports.index')->with('status', 'Liquidación anulada con trazabilidad.');
    }

    public function storeAdjustment(Request $request)
    {
        abort_unless($request->user()->hasPermission('rrhh.adjustments.manage'), 403);
        $data = $request->validate(['worker_id' => ['required', 'exists:rrhh_workers,id'], 'applies_on' => ['required', 'date'], 'direction' => ['required', Rule::in(['earning', 'deduction'])], 'concept' => ['required', 'string', 'max:180'], 'amount' => ['required', 'decimal:0,2', 'min:0.01'], 'notes' => ['nullable', 'string', 'max:1000'], 'submission_key' => ['required', 'uuid']]);
        $this->adjustments->create($data, $request->user());

        return back()->with('status', 'Ajuste registrado.');
    }

    public function reviewAdjustment(Request $request, RrhhAdjustment $adjustment)
    {
        abort_unless($request->user()->hasPermission('rrhh.adjustments.manage'), 403);
        $data = $request->validate(['decision' => ['required', Rule::in(['approved', 'rejected'])], 'notes' => ['nullable', 'string', 'max:1000']]);
        $this->adjustments->review($adjustment, $request->user(), $data['decision'], $data['notes'] ?? null);

        return back()->with('status', 'Corrección revisada.');
    }

    public function voidAdjustment(Request $request, RrhhAdjustment $adjustment)
    {
        abort_unless($request->user()->hasPermission('rrhh.adjustments.manage'), 403);
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);
        $this->adjustments->void($adjustment, $request->user(), $data['reason']);

        return back()->with('status', 'Ajuste anulado.');
    }

    private function periodData(Request $request): array
    {
        return $request->validate(['worker_id' => ['required', 'exists:rrhh_workers,id'], 'date_from' => ['required', 'date'], 'date_to' => ['required', 'date', 'after_or_equal:date_from'], 'recognized_days' => ['nullable', 'integer', 'min:0'], 'adjustment_ids' => ['array'], 'adjustment_ids.*' => ['integer', 'distinct']]);
    }

    private function filename(string $type, string $extension): string
    {
        return 'personal-comisiones-'.$type.'-'.now()->format('Y-m-d').'.'.$extension;
    }

    private function filterSummary(array $filters): string
    {
        $labels = ['worker_id' => 'Trabajador', 'worker_type' => 'Tipo de trabajador', 'product_id' => 'Producto', 'status' => 'Estado', 'adjustment_type' => 'Tipo de ajuste', 'search' => 'Búsqueda', 'reference' => 'Referencia'];
        $active = [];
        foreach ($labels as $key => $label) {
            if (filled($filters[$key] ?? null)) {
                $active[] = $label.': '.$filters[$key];
            }
        }

        return $active === [] ? 'Sin filtros adicionales' : implode(' · ', $active);
    }
}
