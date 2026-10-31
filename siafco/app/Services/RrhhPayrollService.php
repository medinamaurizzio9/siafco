<?php

namespace App\Services;

use App\Models\RrhhAdjustment;
use App\Models\RrhhPayPeriod;
use App\Models\RrhhSale;
use App\Models\RrhhSaleItem;
use App\Models\RrhhWorker;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RrhhPayrollService
{
    public function calculate(array $data, User $actor, ?RrhhPayPeriod $period = null): RrhhPayPeriod
    {
        return DB::transaction(function () use ($data, $actor, $period) {
            $worker = RrhhWorker::query()->lockForUpdate()->findOrFail($data['worker_id']);
            $locked = $period ? RrhhPayPeriod::query()->lockForUpdate()->findOrFail($period->id) : null;
            if ($locked && $locked->status !== RrhhPayPeriod::STATUS_DRAFT) {
                throw ValidationException::withMessages(['period' => 'Solo un borrador puede recalcularse.']);
            }
            if ($locked && $locked->worker_id !== $worker->id) {
                throw ValidationException::withMessages(['worker_id' => 'No se puede cambiar el trabajador de una liquidación.']);
            }
            if ($data['date_from'] > $data['date_to']) {
                throw ValidationException::withMessages(['date_to' => 'La fecha final debe ser igual o posterior a la inicial.']);
            }
            $this->guardBaseOverlap($worker, $data['date_from'], $data['date_to'], $locked?->id);
            $base = $this->baseAmount($worker, $data['recognized_days'] ?? null, $data['date_from'], $data['date_to']);

            if ($locked) {
                RrhhAdjustment::query()->whereIn('id', $locked->lines()->whereNotNull('adjustment_id')->pluck('adjustment_id'))->where('status', RrhhAdjustment::STATUS_APPLIED)->update(['status' => RrhhAdjustment::STATUS_PENDING]);
                $locked->lines()->delete();
            } else {
                $locked = RrhhPayPeriod::create([
                    'number' => 'TMP-'.(string) Str::uuid(),
                    'worker_id' => $worker->id,
                    'status' => RrhhPayPeriod::STATUS_DRAFT,
                    'calculated_by' => $actor->id,
                    'calculated_at' => now(),
                    'date_from' => $data['date_from'], 'date_to' => $data['date_to'],
                ]);
                $locked->update(['number' => 'LIQ-'.str_pad((string) $locked->id, 6, '0', STR_PAD_LEFT)]);
            }

            $lines = [['type' => 'base', 'description' => $this->baseDescription($worker, $data['recognized_days'] ?? null), 'amount' => (string) $base]];
            $commission = BigDecimal::zero()->toScale(2);
            $items = RrhhSaleItem::query()->whereHas('sale', fn ($q) => $q->where('worker_id', $worker->id)->where('status', RrhhSale::STATUS_CONFIRMED)->whereDate('sold_on', '>=', $data['date_from'])->whereDate('sold_on', '<=', $data['date_to']))
                ->whereDoesntHave('payLine')->lockForUpdate()->get();
            foreach ($items as $item) {
                $amount = BigDecimal::of($item->commission_amount)->toScale(2, RoundingMode::HalfUp);
                $commission = $commission->plus($amount);
                $lines[] = ['type' => 'commission', 'sale_item_id' => $item->id, 'description' => "Comisión {$item->product_name_snapshot}", 'amount' => (string) $amount];
            }

            $adjustmentTotal = BigDecimal::zero()->toScale(2);
            $adjustmentIds = array_values(array_unique(array_map('intval', $data['adjustment_ids'] ?? [])));
            $adjustments = RrhhAdjustment::query()->whereIn('id', $adjustmentIds)->where('worker_id', $worker->id)->where('status', RrhhAdjustment::STATUS_PENDING)->where('review_status', RrhhAdjustment::REVIEW_APPROVED)->whereDate('applies_on', '>=', $data['date_from'])->whereDate('applies_on', '<=', $data['date_to'])->whereDoesntHave('payLine')->lockForUpdate()->get();
            if ($adjustments->count() !== count($adjustmentIds)) {
                throw ValidationException::withMessages(['adjustment_ids' => 'Uno o más ajustes no están disponibles o no fueron revisados.']);
            }
            foreach ($adjustments as $adjustment) {
                $amount = BigDecimal::of($adjustment->amount)->toScale(2, RoundingMode::HalfUp);
                if ($adjustment->direction === 'deduction') {
                    $amount = $amount->negated();
                }
                $adjustmentTotal = $adjustmentTotal->plus($amount);
                $lines[] = ['type' => 'adjustment', 'adjustment_id' => $adjustment->id, 'description' => $adjustment->concept, 'amount' => (string) $amount];
            }
            $total = $base->plus($commission)->plus($adjustmentTotal)->toScale(2, RoundingMode::HalfUp);
            $locked->update([
                'date_from' => $data['date_from'], 'date_to' => $data['date_to'], 'recognized_days' => $data['recognized_days'] ?? null,
                'base_amount' => (string) $base, 'commission_total' => (string) $commission,
                'adjustment_total' => (string) $adjustmentTotal, 'calculated_total' => (string) $total,
                'calculated_by' => $actor->id, 'calculated_at' => now(),
            ]);
            $locked->lines()->createMany($lines);
            AuditService::record('rrhh_pay_period_calculated', $locked, ['number' => $locked->number, 'total' => (string) $total]);

            return $locked->fresh(['worker', 'lines.adjustment', 'lines.saleItem.sale']);
        }, 3);
    }

    public function close(RrhhPayPeriod $period, User $actor): RrhhPayPeriod
    {
        return DB::transaction(function () use ($period, $actor) {
            $locked = RrhhPayPeriod::query()->with('lines')->lockForUpdate()->findOrFail($period->id);
            if ($locked->status !== RrhhPayPeriod::STATUS_DRAFT) {
                throw ValidationException::withMessages(['period' => 'Solo un borrador puede cerrarse.']);
            }
            $locked->update(['status' => RrhhPayPeriod::STATUS_CLOSED, 'closed_by' => $actor->id, 'closed_at' => now()]);
            RrhhAdjustment::query()->whereIn('id', $locked->lines->pluck('adjustment_id')->filter())->update(['status' => RrhhAdjustment::STATUS_APPLIED]);
            AuditService::record('rrhh_pay_period_closed', $locked, ['number' => $locked->number, 'total' => $locked->calculated_total]);

            return $locked->fresh(['worker', 'lines']);
        });
    }

    public function pay(RrhhPayPeriod $period, array $data, User $actor): RrhhPayPeriod
    {
        return DB::transaction(function () use ($period, $data, $actor) {
            $locked = RrhhPayPeriod::query()->lockForUpdate()->findOrFail($period->id);
            if ($locked->status !== RrhhPayPeriod::STATUS_CLOSED) {
                throw ValidationException::withMessages(['period' => 'Solo una liquidación cerrada puede marcarse como pagada.']);
            }
            $total = BigDecimal::of($locked->calculated_total)->toScale(2, RoundingMode::HalfUp);
            $paid = BigDecimal::of($data['paid_amount'])->toScale(2, RoundingMode::HalfUp);
            if ($total->isNegative() || ! $paid->isEqualTo($total)) {
                throw ValidationException::withMessages(['paid_amount' => 'El importe pagado debe coincidir exactamente con el total positivo calculado.']);
            }
            $locked->update(['status' => RrhhPayPeriod::STATUS_PAID, 'paid_amount' => (string) $paid, 'payment_reference' => $data['payment_reference'] ?? null, 'paid_by' => $actor->id, 'paid_at' => $data['paid_at']]);
            AuditService::record('rrhh_pay_period_paid', $locked, ['number' => $locked->number, 'paid_amount' => (string) $paid, 'payment_reference' => $locked->payment_reference]);

            return $locked->fresh(['worker', 'lines']);
        });
    }

    public function void(RrhhPayPeriod $period, User $actor, string $reason): RrhhPayPeriod
    {
        return DB::transaction(function () use ($period, $actor, $reason) {
            $locked = RrhhPayPeriod::query()->with('lines')->lockForUpdate()->findOrFail($period->id);
            if (! in_array($locked->status, [RrhhPayPeriod::STATUS_DRAFT, RrhhPayPeriod::STATUS_CLOSED], true)) {
                throw ValidationException::withMessages(['period' => 'Una liquidación pagada o anulada no puede anularse.']);
            }
            RrhhAdjustment::query()->whereIn('id', $locked->lines->pluck('adjustment_id')->filter())->where('status', RrhhAdjustment::STATUS_APPLIED)->update(['status' => RrhhAdjustment::STATUS_PENDING]);
            $locked->lines()->delete();
            $locked->update(['status' => RrhhPayPeriod::STATUS_VOIDED, 'voided_by' => $actor->id, 'voided_at' => now(), 'void_reason' => trim($reason)]);
            AuditService::record('rrhh_pay_period_voided', $locked, ['number' => $locked->number, 'reason' => trim($reason)]);

            return $locked->fresh();
        });
    }

    private function baseAmount(RrhhWorker $worker, ?int $days, string $from, string $to): BigDecimal
    {
        return match ($worker->compensation_mode) {
            RrhhWorker::MODE_MONTHLY => BigDecimal::of($worker->agreed_amount ?? '0')->toScale(2, RoundingMode::HalfUp),
            RrhhWorker::MODE_DAILY => $this->dailyBase($worker, $days, $from, $to),
            RrhhWorker::MODE_COMMISSION_ONLY => BigDecimal::zero()->toScale(2),
            default => throw ValidationException::withMessages(['worker_id' => 'Modalidad de remuneración no soportada.']),
        };
    }

    private function dailyBase(RrhhWorker $worker, ?int $days, string $from, string $to): BigDecimal
    {
        $rangeDays = CarbonImmutable::parse($from)->diffInDays(CarbonImmutable::parse($to)) + 1;
        if ($days === null || $days < 0 || $days > $rangeDays) {
            throw ValidationException::withMessages(['recognized_days' => 'Los días reconocidos deben ser enteros y no superar el rango.']);
        }

        return BigDecimal::of($worker->agreed_amount ?? '0')->multipliedBy($days)->toScale(2, RoundingMode::HalfUp);
    }

    private function guardBaseOverlap(RrhhWorker $worker, string $from, string $to, ?int $ignore): void
    {
        if ($worker->compensation_mode !== RrhhWorker::MODE_MONTHLY) {
            return;
        }
        $exists = RrhhPayPeriod::query()->where('worker_id', $worker->id)->whereIn('status', [RrhhPayPeriod::STATUS_DRAFT, RrhhPayPeriod::STATUS_CLOSED, RrhhPayPeriod::STATUS_PAID])->when($ignore, fn ($q) => $q->whereKeyNot($ignore))->where('date_from', '<=', $to)->where('date_to', '>=', $from)->lockForUpdate()->exists();
        if ($exists) {
            throw ValidationException::withMessages(['date_from' => 'El trabajador mensual ya tiene una liquidación vigente que se superpone con este período.']);
        }
    }

    private function baseDescription(RrhhWorker $worker, ?int $days): string
    {
        return match ($worker->compensation_mode) {
            RrhhWorker::MODE_MONTHLY => 'Remuneración mensual acordada',
            RrhhWorker::MODE_DAILY => "Jornal por {$days} días reconocidos",
            default => 'Sin remuneración base',
        };
    }
}
