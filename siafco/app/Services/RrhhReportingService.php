<?php

namespace App\Services;

use App\Models\RrhhAdjustment;
use App\Models\RrhhPayPeriod;
use App\Models\RrhhProduct;
use App\Models\RrhhSale;
use App\Models\RrhhSaleItem;
use App\Models\RrhhWorker;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class RrhhReportingService
{
    public const TYPES = ['management', 'personal', 'sales', 'commissions', 'liquidations', 'payments', 'adjustments'];

    public const EXPORT_LIMIT = 5000;

    public const PDF_LIMIT = 1000;

    public function filters(Request $request): array
    {
        return Validator::make($request->all(), [
            'type' => ['nullable', Rule::in(self::TYPES)],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'worker_id' => ['nullable', 'integer', 'exists:rrhh_workers,id'],
            'worker_type' => ['nullable', Rule::in(['scheduled', 'virtual'])],
            'product_id' => ['nullable', 'integer', 'exists:rrhh_products,id'],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'pending', 'confirmed', 'voided', 'draft', 'closed', 'paid', 'applied'])],
            'adjustment_type' => ['nullable', Rule::in(['earning', 'deduction', 'manual', 'voided_sale', 'review_pending', 'review_approved', 'review_rejected'])],
            'search' => ['nullable', 'string', 'max:120'],
            'reference' => ['nullable', 'string', 'max:120'],
        ])->validate() + ['type' => 'management'];
    }

    public function report(array $filters, bool $paginate = true, ?int $limit = null): array
    {
        return match ($filters['type']) {
            'personal' => $this->personal($filters, $paginate, $limit),
            'sales' => $this->sales($filters, $paginate, $limit),
            'commissions' => $this->commissions($filters, $paginate, $limit),
            'liquidations' => $this->liquidations($filters, $paginate, $limit),
            'payments' => $this->payments($filters, $paginate, $limit),
            'adjustments' => $this->adjustments($filters, $paginate, $limit),
            default => $this->management($filters, $paginate, $limit),
        };
    }

    public function options(): array
    {
        return [
            'workers' => RrhhWorker::orderBy('full_name')->get(['id', 'code', 'full_name']),
            'products' => RrhhProduct::orderBy('name')->get(['id', 'code', 'name']),
        ];
    }

    private function personal(array $f, bool $paginate, ?int $limit): array
    {
        $query = RrhhWorker::query()->when($f['worker_id'] ?? null, fn ($q, $id) => $q->whereKey($id))->when($f['worker_type'] ?? null, fn ($q, $type) => $q->where('type', $type))->when(isset($f['status']), fn ($q) => $q->where('is_active', $f['status'] === 'active'))->when($f['search'] ?? null, fn ($q, $search) => $q->where(fn ($x) => $x->where('full_name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%")->orWhere('ci', 'like', "%{$search}%")))->orderBy('full_name');

        return $this->make('Informe de personal', 'Fecha de ingreso y estado actual', ['code' => 'Código', 'name' => 'Nombre', 'ci' => 'CI', 'type' => 'Tipo', 'position' => 'Cargo', 'joined' => 'Ingreso', 'mode' => 'Modalidad', 'amount' => 'Importe acordado', 'status' => 'Estado'], $query, fn ($w) => ['code' => $w->code, 'name' => $w->full_name, 'ci' => $w->ci, 'type' => $w->type === 'virtual' ? 'Virtual' : 'Con horario', 'position' => $w->position ?: '—', 'joined' => $w->joined_on?->format('d/m/Y'), 'mode' => $this->mode($w->compensation_mode), 'amount' => $w->agreed_amount ?? '0.00', 'status' => $w->is_active ? 'Activo' : 'Inactivo'], $paginate, $limit, ['Trabajadores' => (string) (clone $query)->count()], ['amount']);
    }

    private function sales(array $f, bool $paginate, ?int $limit): array
    {
        $query = RrhhSale::with(['worker', 'items', 'confirmer'])->when($f['from'] ?? null, fn ($q, $date) => $q->whereDate('sold_on', '>=', $date))->when($f['to'] ?? null, fn ($q, $date) => $q->whereDate('sold_on', '<=', $date))->when($f['worker_id'] ?? null, fn ($q, $id) => $q->where('worker_id', $id))->when($f['product_id'] ?? null, fn ($q, $id) => $q->whereHas('items', fn ($x) => $x->where('product_id', $id)))->when($f['status'] ?? null, fn ($q, $status) => $q->where('status', $status))->latest('sold_on')->latest('id');
        $totals = [];
        foreach (['pending' => 'Ventas pendientes', 'confirmed' => 'Ventas confirmadas', 'voided' => 'Ventas anuladas'] as $status => $label) {
            $totals[$label] = $this->sum((clone $query)->where('status', $status), 'total');
        }
        $totals['Importe vigente'] = $this->sum((clone $query)->where('status', 'confirmed'), 'total');

        return $this->make('Informe de ventas', 'Fecha de venta', ['number' => 'Venta', 'date' => 'Fecha', 'worker' => 'Trabajador', 'products' => 'Productos', 'quantity' => 'Cantidad', 'total' => 'Importe', 'commission' => 'Comisión', 'status' => 'Estado', 'confirmed_by' => 'Confirmó', 'confirmed_at' => 'Fecha confirmación'], $query, fn ($s) => ['number' => $s->number, 'date' => $s->sold_on?->format('d/m/Y'), 'worker' => $s->worker->full_name, 'products' => $s->items->pluck('product_name_snapshot')->join(', '), 'quantity' => $s->items->sum('quantity'), 'total' => $s->total, 'commission' => $s->commission_total, 'status' => $this->saleStatus($s->status), 'confirmed_by' => $s->confirmer?->name ?? '—', 'confirmed_at' => $s->confirmed_at?->format('d/m/Y H:i') ?? '—'], $paginate, $limit, $totals, ['total', 'commission']);
    }

    private function commissions(array $f, bool $paginate, ?int $limit): array
    {
        $query = RrhhSaleItem::with(['sale.worker', 'payLine.payPeriod'])->whereHas('sale', fn ($q) => $q->when($f['from'] ?? null, fn ($x, $d) => $x->whereDate('sold_on', '>=', $d))->when($f['to'] ?? null, fn ($x, $d) => $x->whereDate('sold_on', '<=', $d))->when($f['worker_id'] ?? null, fn ($x, $id) => $x->where('worker_id', $id))->when($f['status'] ?? null, fn ($x, $s) => $x->where('status', $s)))->when($f['product_id'] ?? null, fn ($q, $id) => $q->where('product_id', $id))->latest('id');
        $confirmed = (clone $query)->whereHas('sale', fn ($q) => $q->where('status', 'confirmed'));
        $unsettled = (clone $confirmed)->whereDoesntHave('payLine');

        return $this->make('Informe de comisiones', 'Fecha de venta; la situación indica su liquidación', ['worker' => 'Trabajador', 'sale' => 'Venta', 'product' => 'Producto', 'date' => 'Fecha', 'commission' => 'Comisión', 'sale_status' => 'Venta', 'settlement' => 'Situación'], $query, fn ($i) => ['worker' => $i->sale->worker->full_name, 'sale' => $i->sale->number, 'product' => $i->product_name_snapshot, 'date' => $i->sale->sold_on?->format('d/m/Y'), 'commission' => $i->commission_amount, 'sale_status' => $this->saleStatus($i->sale->status), 'settlement' => $this->commissionSituation($i)], $paginate, $limit, ['Comisiones confirmadas' => $this->sum($confirmed, 'commission_amount'), 'Confirmadas sin liquidar' => $this->sum($unsettled, 'commission_amount')], ['commission']);
    }

    private function liquidations(array $f, bool $paginate, ?int $limit): array
    {
        $query = RrhhPayPeriod::with(['worker', 'payer'])->when($f['from'] ?? null, fn ($q, $d) => $q->whereDate('date_to', '>=', $d))->when($f['to'] ?? null, fn ($q, $d) => $q->whereDate('date_from', '<=', $d))->when($f['worker_id'] ?? null, fn ($q, $id) => $q->where('worker_id', $id))->when($f['status'] ?? null, fn ($q, $s) => $q->where('status', $s))->latest('date_to')->latest('id');

        return $this->make('Informe de liquidaciones', 'Rango liquidado (períodos que intersectan las fechas)', ['number' => 'Liquidación', 'worker' => 'Trabajador', 'period' => 'Período', 'mode' => 'Modalidad', 'base' => 'Base', 'days' => 'Días', 'commissions' => 'Comisiones', 'adjustments' => 'Ajustes', 'calculated' => 'Calculado', 'status' => 'Estado', 'closed' => 'Cierre', 'paid' => 'Pago', 'paid_amount' => 'Pagado'], $query, fn ($p) => ['number' => $p->number, 'worker' => $p->worker->full_name, 'period' => $p->date_from->format('d/m/Y').' - '.$p->date_to->format('d/m/Y'), 'mode' => $this->mode($p->worker->compensation_mode), 'base' => $p->base_amount, 'days' => $p->recognized_days ?? '—', 'commissions' => $p->commission_total, 'adjustments' => $p->adjustment_total, 'calculated' => $p->calculated_total, 'status' => $this->periodStatus($p->status), 'closed' => $p->closed_at?->format('d/m/Y H:i') ?? '—', 'paid' => $p->paid_at?->format('d/m/Y') ?? '—', 'paid_amount' => $p->paid_amount ?? '0.00'], $paginate, $limit, ['Total calculado' => $this->sum($query, 'calculated_total'), 'Total pagado' => $this->sum((clone $query)->where('status', 'paid'), 'paid_amount')], ['base', 'commissions', 'adjustments', 'calculated', 'paid_amount']);
    }

    private function payments(array $f, bool $paginate, ?int $limit): array
    {
        $query = RrhhPayPeriod::with(['worker', 'payer'])->where('status', 'paid')->when($f['from'] ?? null, fn ($q, $d) => $q->whereDate('paid_at', '>=', $d))->when($f['to'] ?? null, fn ($q, $d) => $q->whereDate('paid_at', '<=', $d))->when($f['worker_id'] ?? null, fn ($q, $id) => $q->where('worker_id', $id))->when($f['reference'] ?? null, fn ($q, $r) => $q->where('payment_reference', 'like', "%{$r}%"))->latest('paid_at')->latest('id');

        return $this->make('Informe de pagos registrados', 'Fecha de pago registrada', ['number' => 'Liquidación', 'worker' => 'Trabajador', 'period' => 'Período', 'date' => 'Fecha pago', 'amount' => 'Importe pagado', 'reference' => 'Referencia', 'user' => 'Registrado por'], $query, fn ($p) => ['number' => $p->number, 'worker' => $p->worker->full_name, 'period' => $p->date_from->format('d/m/Y').' - '.$p->date_to->format('d/m/Y'), 'date' => $p->paid_at?->format('d/m/Y'), 'amount' => $p->paid_amount, 'reference' => $p->payment_reference ?: '—', 'user' => $p->payer?->name ?? '—'], $paginate, $limit, ['Total pagado' => $this->sum($query, 'paid_amount')], ['amount']);
    }

    private function adjustments(array $f, bool $paginate, ?int $limit): array
    {
        $query = RrhhAdjustment::with(['worker', 'sourceSale', 'reviewer'])->when($f['from'] ?? null, fn ($q, $d) => $q->whereDate('applies_on', '>=', $d))->when($f['to'] ?? null, fn ($q, $d) => $q->whereDate('applies_on', '<=', $d))->when($f['worker_id'] ?? null, fn ($q, $id) => $q->where('worker_id', $id))->when($f['status'] ?? null, fn ($q, $s) => $q->where('status', $s))->when($f['adjustment_type'] ?? null, function ($q, $type) {
            str_starts_with($type, 'review_') ? $q->where('review_status', substr($type, 7)) : (in_array($type, ['manual', 'voided_sale'], true) ? $q->where('origin', $type) : $q->where('direction', $type));
        })->latest('applies_on')->latest('id');

        return $this->make('Informe de ajustes y correcciones', 'Fecha de aplicación', ['worker' => 'Trabajador', 'date' => 'Fecha', 'concept' => 'Concepto', 'type' => 'Tipo', 'amount' => 'Importe', 'origin' => 'Origen', 'review' => 'Revisión', 'status' => 'Estado', 'responsible' => 'Responsable', 'sale' => 'Venta original'], $query, fn ($a) => ['worker' => $a->worker->full_name, 'date' => $a->applies_on?->format('d/m/Y'), 'concept' => $a->concept, 'type' => $a->direction === 'deduction' ? 'Descuento' : 'Bonificación', 'amount' => ($a->direction === 'deduction' ? '-' : '').$a->amount, 'origin' => $a->origin === 'voided_sale' ? 'Venta anulada' : 'Ordinario', 'review' => $this->reviewStatus($a->review_status), 'status' => $this->adjustmentStatus($a->status), 'responsible' => $a->reviewer?->name ?? '—', 'sale' => $a->sourceSale?->number ?? '—'], $paginate, $limit, ['Bonificaciones' => $this->sum((clone $query)->where('direction', 'earning')->where('review_status', 'approved'), 'amount'), 'Descuentos habilitados' => $this->sum((clone $query)->where('direction', 'deduction')->where('review_status', 'approved'), 'amount'), 'Correcciones pendientes de revisión' => (string) (clone $query)->where('review_status', 'pending')->count()], ['amount']);
    }

    private function management(array $f, bool $paginate, ?int $limit): array
    {
        $salesScope = fn ($q) => $q->where('status', 'confirmed')->when($f['from'] ?? null, fn ($x, $d) => $x->whereDate('sold_on', '>=', $d))->when($f['to'] ?? null, fn ($x, $d) => $x->whereDate('sold_on', '<=', $d));
        $periodScope = fn ($q) => $q->whereIn('status', ['closed', 'paid'])->when($f['from'] ?? null, fn ($x, $d) => $x->whereDate('date_to', '>=', $d))->when($f['to'] ?? null, fn ($x, $d) => $x->whereDate('date_from', '<=', $d));
        $workers = RrhhWorker::with(['sales' => $salesScope, 'payPeriods' => $periodScope])->when($f['worker_id'] ?? null, fn ($q, $id) => $q->whereKey($id))->when($f['worker_type'] ?? null, fn ($q, $type) => $q->where('type', $type))->orderBy('full_name');
        $workerBase = RrhhWorker::query()->when($f['worker_id'] ?? null, fn ($q, $id) => $q->whereKey($id))->when($f['worker_type'] ?? null, fn ($q, $type) => $q->where('type', $type));
        $sales = RrhhSale::query()->where('status', 'confirmed')->when($f['from'] ?? null, fn ($q, $d) => $q->whereDate('sold_on', '>=', $d))->when($f['to'] ?? null, fn ($q, $d) => $q->whereDate('sold_on', '<=', $d))->when($f['worker_id'] ?? null, fn ($q, $id) => $q->where('worker_id', $id))->when($f['worker_type'] ?? null, fn ($q, $type) => $q->whereHas('worker', fn ($x) => $x->where('type', $type)));
        $periods = RrhhPayPeriod::query()->when($f['from'] ?? null, fn ($q, $d) => $q->whereDate('date_to', '>=', $d))->when($f['to'] ?? null, fn ($q, $d) => $q->whereDate('date_from', '<=', $d))->when($f['worker_id'] ?? null, fn ($q, $id) => $q->where('worker_id', $id));
        $unsettled = RrhhSaleItem::query()->whereHas('sale', fn ($q) => $salesScope($q)
            ->when($f['worker_id'] ?? null, fn ($x, $id) => $x->where('worker_id', $id))
            ->when($f['worker_type'] ?? null, fn ($x, $type) => $x->whereHas('worker', fn ($worker) => $worker->where('type', $type))))
            ->whereDoesntHave('payLine');
        $pendingCorrections = RrhhAdjustment::query()->where('review_status', 'pending')
            ->when($f['from'] ?? null, fn ($q, $date) => $q->whereDate('applies_on', '>=', $date))
            ->when($f['to'] ?? null, fn ($q, $date) => $q->whereDate('applies_on', '<=', $date))
            ->when($f['worker_id'] ?? null, fn ($q, $id) => $q->where('worker_id', $id))
            ->when($f['worker_type'] ?? null, fn ($q, $type) => $q->whereHas('worker', fn ($worker) => $worker->where('type', $type)));
        $totals = ['Trabajadores activos' => (string) (clone $workerBase)->where('is_active', true)->count(), 'Con horario' => (string) (clone $workerBase)->where('is_active', true)->where('type', 'scheduled')->count(), 'Virtuales' => (string) (clone $workerBase)->where('is_active', true)->where('type', 'virtual')->count(), 'Ventas confirmadas' => (string) (clone $sales)->count(), 'Importe vendido confirmado' => $this->sum($sales, 'total'), 'Comisiones confirmadas' => $this->sum($sales, 'commission_total'), 'Comisiones sin liquidar' => $this->sum($unsettled, 'commission_amount'), 'Liquidaciones cerradas' => (string) (clone $periods)->where('status', 'closed')->count(), 'Liquidaciones pagadas' => (string) (clone $periods)->where('status', 'paid')->count(), 'Remuneraciones calculadas' => $this->sum((clone $periods)->whereIn('status', ['closed', 'paid']), 'calculated_total'), 'Total pagado registrado' => $this->sum((clone $periods)->where('status', 'paid'), 'paid_amount'), 'Correcciones pendientes' => (string) $pendingCorrections->count()];

        return $this->make('Resumen gerencial', 'Ventas por fecha de venta y liquidaciones por rango liquidado', ['code' => 'Código', 'worker' => 'Trabajador', 'type' => 'Tipo', 'sales' => 'Ventas confirmadas', 'commissions' => 'Comisiones', 'settled' => 'Remuneración liquidada', 'paid' => 'Importe pagado'], $workers, function ($w) use ($f) {
            $sales = $w->sales->filter(fn ($s) => (! isset($f['from']) || $s->sold_on->format('Y-m-d') >= $f['from']) && (! isset($f['to']) || $s->sold_on->format('Y-m-d') <= $f['to']));
            $periods = $w->payPeriods->filter(fn ($p) => (! isset($f['from']) || $p->date_to->format('Y-m-d') >= $f['from']) && (! isset($f['to']) || $p->date_from->format('Y-m-d') <= $f['to']));

            return ['code' => $w->code, 'worker' => $w->full_name, 'type' => $w->type === 'virtual' ? 'Virtual' : 'Con horario', 'sales' => $this->decimalSum($sales->pluck('total')), 'commissions' => $this->decimalSum($sales->pluck('commission_total')), 'settled' => $this->decimalSum($periods->pluck('calculated_total')), 'paid' => $this->decimalSum($periods->where('status', 'paid')->pluck('paid_amount'))];
        }, $paginate, $limit, $totals, ['sales', 'commissions', 'settled', 'paid']);
    }

    private function make(string $title, string $criterion, array $headers, Builder $query, callable $mapper, bool $paginate, ?int $limit, array $totals, array $numeric): array
    {
        $count = (clone $query)->count();
        if ($limit !== null && $count > $limit) {
            abort(422, "El informe contiene {$count} filas; el límite operativo es {$limit}. Ajusta los filtros.");
        }
        $records = $paginate ? $query->paginate(25)->withQueryString() : $query->get();
        $rows = $paginate ? $records->through($mapper) : $records->map($mapper);

        return compact('title', 'criterion', 'headers', 'rows', 'totals', 'numeric', 'count');
    }

    private function sum(Builder $query, string $column): string
    {
        return BigDecimal::of((string) ((clone $query)->sum($column) ?: '0'))->toScale(2, RoundingMode::HalfUp)->__toString();
    }

    private function decimalSum(iterable $values): string
    {
        $sum = BigDecimal::zero()->toScale(2);
        foreach ($values as $value) {
            $sum = $sum->plus((string) ($value ?? '0'));
        }

        return $sum->toScale(2, RoundingMode::HalfUp)->__toString();
    }

    private function mode(string $mode): string
    {
        return ['monthly' => 'Mensual', 'daily' => 'Jornal', 'commission_only' => 'Solo comisiones'][$mode] ?? $mode;
    }

    private function saleStatus(string $status): string
    {
        return ['pending' => 'Pendiente', 'confirmed' => 'Confirmada', 'voided' => 'Anulada'][$status] ?? $status;
    }

    private function periodStatus(string $status): string
    {
        return ['draft' => 'Borrador', 'closed' => 'Cerrada', 'paid' => 'Pagada', 'voided' => 'Anulada'][$status] ?? $status;
    }

    private function adjustmentStatus(string $status): string
    {
        return ['pending' => 'Pendiente', 'applied' => 'Aplicado', 'voided' => 'Anulado'][$status] ?? $status;
    }

    private function reviewStatus(string $status): string
    {
        return ['pending' => 'Pendiente', 'approved' => 'Habilitada', 'rejected' => 'Rechazada'][$status] ?? $status;
    }

    private function commissionSituation(RrhhSaleItem $item): string
    {
        if ($item->sale->status === 'pending') {
            return 'Pendiente de confirmar';
        }
        if ($item->sale->status === 'voided') {
            return 'Venta anulada';
        }
        $status = $item->payLine?->payPeriod?->status;

        return ['draft' => 'Incluida en borrador', 'closed' => 'Liquidación cerrada', 'paid' => 'Liquidación pagada', 'voided' => 'Liquidación anulada'][$status] ?? 'Confirmada sin liquidar';
    }
}
