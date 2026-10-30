<?php

namespace App\Services;

use App\Models\RrhhAdjustment;
use App\Models\RrhhWorker;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RrhhAdjustmentService
{
    public function create(array $data, User $actor): RrhhAdjustment
    {
        return DB::transaction(function () use ($data, $actor) {
            $existing = RrhhAdjustment::query()->where('submission_key', $data['submission_key'])->first();
            if ($existing) {
                return $existing;
            }

            $worker = RrhhWorker::query()->lockForUpdate()->findOrFail($data['worker_id']);
            $amount = BigDecimal::of($data['amount'])->toScale(2, RoundingMode::HalfUp);
            if ($amount->isLessThanOrEqualTo(0)) {
                throw ValidationException::withMessages(['amount' => 'El importe debe ser mayor que cero.']);
            }

            $adjustment = RrhhAdjustment::create([
                ...$data,
                'amount' => (string) $amount,
                'status' => RrhhAdjustment::STATUS_PENDING,
                'origin' => RrhhAdjustment::ORIGIN_MANUAL,
                'review_status' => RrhhAdjustment::REVIEW_APPROVED,
                'created_by' => $actor->id,
            ]);
            AuditService::record('rrhh_adjustment_created', $adjustment, ['worker_code' => $worker->code, 'direction' => $adjustment->direction, 'amount' => $adjustment->amount]);

            return $adjustment->load('worker');
        });
    }

    public function review(RrhhAdjustment $adjustment, User $actor, string $decision, ?string $notes): RrhhAdjustment
    {
        return DB::transaction(function () use ($adjustment, $actor, $decision, $notes) {
            $locked = RrhhAdjustment::query()->lockForUpdate()->findOrFail($adjustment->id);
            if ($locked->origin !== RrhhAdjustment::ORIGIN_VOIDED_SALE || $locked->review_status !== RrhhAdjustment::REVIEW_PENDING || $locked->status !== RrhhAdjustment::STATUS_PENDING) {
                throw ValidationException::withMessages(['adjustment' => 'La corrección ya fue revisada o no requiere revisión.']);
            }
            $locked->update([
                'review_status' => $decision,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'review_notes' => $notes,
                'updated_by' => $actor->id,
                'status' => $decision === RrhhAdjustment::REVIEW_REJECTED ? RrhhAdjustment::STATUS_VOIDED : RrhhAdjustment::STATUS_PENDING,
            ]);
            AuditService::record('rrhh_adjustment_reviewed', $locked, ['decision' => $decision, 'source_sale_id' => $locked->source_sale_id]);

            return $locked->fresh(['worker', 'sourceSale']);
        });
    }

    public function void(RrhhAdjustment $adjustment, User $actor, string $reason): RrhhAdjustment
    {
        return DB::transaction(function () use ($adjustment, $actor, $reason) {
            $locked = RrhhAdjustment::query()->lockForUpdate()->findOrFail($adjustment->id);
            if ($locked->status !== RrhhAdjustment::STATUS_PENDING || $locked->payLine()->exists()) {
                throw ValidationException::withMessages(['adjustment' => 'Solo puede anularse un ajuste pendiente que no esté incluido en una liquidación.']);
            }
            $locked->update(['status' => RrhhAdjustment::STATUS_VOIDED, 'review_notes' => trim($reason), 'updated_by' => $actor->id]);
            AuditService::record('rrhh_adjustment_voided', $locked, ['reason' => trim($reason)]);

            return $locked->fresh();
        });
    }
}
