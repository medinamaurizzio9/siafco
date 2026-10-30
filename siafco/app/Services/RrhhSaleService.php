<?php

namespace App\Services;

use App\Models\RrhhAdjustment;
use App\Models\RrhhPayLine;
use App\Models\RrhhPayPeriod;
use App\Models\RrhhProduct;
use App\Models\RrhhSale;
use App\Models\RrhhWorker;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RrhhSaleService
{
    public function __construct(private readonly RrhhCommissionCalculator $calculator) {}

    public function create(array $data, User $actor): RrhhSale
    {
        return DB::transaction(function () use ($data, $actor) {
            $worker = RrhhWorker::query()->lockForUpdate()->findOrFail($data['worker_id']);
            if (! $worker->is_active || ! $worker->commissions_enabled) {
                throw ValidationException::withMessages(['worker_id' => 'Selecciona un trabajador activo con comisiones habilitadas.']);
            }

            $products = RrhhProduct::query()->whereIn('id', collect($data['items'])->pluck('product_id'))->lockForUpdate()->get()->keyBy('id');
            $subtotal = BigDecimal::zero()->toScale(2);
            $commissionTotal = BigDecimal::zero()->toScale(2);
            $items = [];

            foreach ($data['items'] as $input) {
                $product = $products->get((int) $input['product_id']);
                if (! $product || ! $product->is_active) {
                    throw ValidationException::withMessages(['items' => 'Todos los productos deben estar activos.']);
                }
                $calculated = $this->calculator->line($product->reference_price, (int) $input['quantity'], $product->commission_type, $product->commission_value);
                $subtotal = $subtotal->plus($calculated['line_total']);
                $commissionTotal = $commissionTotal->plus($calculated['commission_amount']);
                $items[] = [
                    'product_id' => $product->id,
                    'product_code_snapshot' => $product->code,
                    'product_name_snapshot' => $product->name,
                    'quantity' => (int) $input['quantity'],
                    'unit_price' => $product->reference_price,
                    'line_total' => $calculated['line_total'],
                    'commission_type_snapshot' => $product->commission_type,
                    'commission_value_snapshot' => $product->commission_value,
                    'commission_amount' => $calculated['commission_amount'],
                ];
            }

            $last = RrhhSale::query()->lockForUpdate()->orderByDesc('id')->first();
            $sale = RrhhSale::create([
                'number' => 'VEN-'.str_pad((string) (($last?->id ?? 0) + 1), 6, '0', STR_PAD_LEFT),
                'sold_on' => $data['sold_on'],
                'worker_id' => $worker->id,
                'status' => RrhhSale::STATUS_PENDING,
                'subtotal' => (string) $subtotal->toScale(2, RoundingMode::HalfUp),
                'total' => (string) $subtotal->toScale(2, RoundingMode::HalfUp),
                'commission_total' => (string) $commissionTotal->toScale(2, RoundingMode::HalfUp),
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->id,
            ]);
            $sale->items()->createMany($items);
            AuditService::record('rrhh_sale_created', $sale, ['sale_number' => $sale->number, 'worker_code' => $worker->code]);

            return $sale->load('items', 'worker');
        });
    }

    public function confirm(RrhhSale $sale, User $actor): RrhhSale
    {
        return DB::transaction(function () use ($sale, $actor) {
            $locked = RrhhSale::query()->with('items')->lockForUpdate()->findOrFail($sale->id);
            if ($locked->status !== RrhhSale::STATUS_PENDING) {
                throw ValidationException::withMessages(['sale' => 'Solo una venta pendiente puede confirmarse.']);
            }

            [$total, $commission] = $this->totals($locked, true);
            $locked->update([
                'subtotal' => $total,
                'total' => $total,
                'commission_total' => $commission,
                'status' => RrhhSale::STATUS_CONFIRMED,
                'confirmed_by' => $actor->id,
                'confirmed_at' => now(),
            ]);
            AuditService::record('rrhh_sale_confirmed', $locked, ['sale_number' => $locked->number, 'commission_total' => $commission]);

            return $locked->fresh(['items', 'worker', 'confirmer']);
        });
    }

    public function void(RrhhSale $sale, User $actor, string $reason): RrhhSale
    {
        return DB::transaction(function () use ($sale, $actor, $reason) {
            $locked = RrhhSale::query()->with('items')->lockForUpdate()->findOrFail($sale->id);
            if (! in_array($locked->status, [RrhhSale::STATUS_PENDING, RrhhSale::STATUS_CONFIRMED], true)) {
                throw ValidationException::withMessages(['sale' => 'La venta ya fue anulada.']);
            }
            $payLines = RrhhPayLine::query()->with('payPeriod')->whereIn('sale_item_id', $locked->items->pluck('id'))->lockForUpdate()->get();
            $statuses = $payLines->pluck('payPeriod.status')->unique();
            if ($statuses->contains(RrhhPayPeriod::STATUS_CLOSED)) {
                throw ValidationException::withMessages(['sale' => 'La venta pertenece a una liquidación cerrada. Anula primero esa liquidación para liberar sus comisiones.']);
            }
            if ($statuses->contains(RrhhPayPeriod::STATUS_DRAFT)) {
                foreach ($payLines->where('payPeriod.status', RrhhPayPeriod::STATUS_DRAFT)->groupBy('pay_period_id') as $periodId => $lines) {
                    RrhhPayLine::query()->whereIn('id', $lines->pluck('id'))->delete();
                    $period = RrhhPayPeriod::query()->lockForUpdate()->findOrFail($periodId);
                    $commission = RrhhPayLine::query()->where('pay_period_id', $periodId)->where('type', 'commission')->sum('amount');
                    $period->update(['commission_total' => $commission, 'calculated_total' => BigDecimal::of($period->base_amount)->plus($commission)->plus($period->adjustment_total)->toScale(2, RoundingMode::HalfUp)]);
                }
            }

            $locked->update(['status' => RrhhSale::STATUS_VOIDED, 'voided_by' => $actor->id, 'voided_at' => now(), 'void_reason' => trim($reason)]);
            $paidCommission = $payLines->where('payPeriod.status', RrhhPayPeriod::STATUS_PAID)->reduce(
                fn (BigDecimal $total, RrhhPayLine $line) => $total->plus($line->amount),
                BigDecimal::zero()->toScale(2)
            );
            if ($paidCommission->isPositive()) {
                RrhhAdjustment::query()->firstOrCreate(
                    ['source_sale_id' => $locked->id],
                    [
                        'worker_id' => $locked->worker_id,
                        'applies_on' => now()->toDateString(),
                        'direction' => 'deduction',
                        'concept' => "Corrección por venta anulada {$locked->number}",
                        'amount' => (string) $paidCommission->toScale(2, RoundingMode::HalfUp),
                        'notes' => trim($reason),
                        'status' => RrhhAdjustment::STATUS_PENDING,
                        'origin' => RrhhAdjustment::ORIGIN_VOIDED_SALE,
                        'review_status' => RrhhAdjustment::REVIEW_PENDING,
                        'submission_key' => (string) Str::uuid(),
                        'created_by' => $actor->id,
                    ]
                );
            }
            AuditService::record('rrhh_sale_voided', $locked, ['sale_number' => $locked->number, 'reason' => trim($reason), 'previously_confirmed' => $locked->confirmed_at !== null]);

            return $locked->fresh(['items', 'worker', 'voider']);
        });
    }

    private function totals(RrhhSale $sale, bool $persistLines = false): array
    {
        $total = BigDecimal::zero()->toScale(2);
        $commission = BigDecimal::zero()->toScale(2);
        foreach ($sale->items as $item) {
            $calculated = $this->calculator->line($item->unit_price, $item->quantity, $item->commission_type_snapshot, $item->commission_value_snapshot);
            if ($persistLines) {
                $item->update($calculated);
            }
            $total = $total->plus($calculated['line_total']);
            $commission = $commission->plus($calculated['commission_amount']);
        }

        return [(string) $total->toScale(2, RoundingMode::HalfUp), (string) $commission->toScale(2, RoundingMode::HalfUp)];
    }
}
