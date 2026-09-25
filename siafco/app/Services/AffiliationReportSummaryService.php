<?php

namespace App\Services;

use App\Models\AffiliationPayment;
use App\Models\DigitalCredential;
use App\Support\PaymentStatus;
use App\Support\SiafcoDate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class AffiliationReportSummaryService
{
    public function summary(?string $from = null, ?string $to = null): array
    {
        $pendingQuery = AffiliationPayment::query()
            ->whereIn('status', $this->pendingStatuses());
        $confirmedQuery = AffiliationPayment::query()
            ->whereIn('status', PaymentStatus::confirmedValues());

        $this->applyTimestampDateRange($pendingQuery, $from, $to, 'submitted_at', 'created_at');
        $this->applyTimestampDateRange($confirmedQuery, $from, $to, 'confirmed_at');

        return [
            'pending_payments' => (clone $pendingQuery)->count(),
            'confirmed_payments' => (clone $confirmedQuery)->count(),
            'credentials' => DigitalCredential::query()->count(),
            'confirmed_income' => (clone $confirmedQuery)->sum(DB::raw('COALESCE(paid_amount, amount)')),
        ];
    }

    public function pendingStatuses(): array
    {
        return array_values(array_unique([
            ...PaymentStatus::pendingValues(),
            PaymentStatus::UNDER_REVIEW,
        ]));
    }

    private function applyTimestampDateRange(Builder $query, ?string $from, ?string $to, string $column, ?string $fallbackColumn = null): void
    {
        if ($from) {
            [$fromUtc] = SiafcoDate::utcDayBounds($from);
            $this->whereTimestampBound($query, $column, '>=', $fromUtc, $fallbackColumn);
        }

        if ($to) {
            [, $toUtc] = SiafcoDate::utcDayBounds($to);
            $this->whereTimestampBound($query, $column, '<=', $toUtc, $fallbackColumn);
        }
    }

    private function whereTimestampBound(Builder $query, string $column, string $operator, $value, ?string $fallbackColumn): void
    {
        if (! $fallbackColumn) {
            $query->where($column, $operator, $value);

            return;
        }

        $query->where(function (Builder $inner) use ($column, $operator, $value, $fallbackColumn): void {
            $inner->where($column, $operator, $value)
                ->orWhere(function (Builder $fallback) use ($column, $operator, $value, $fallbackColumn): void {
                    $fallback->whereNull($column)
                        ->where($fallbackColumn, $operator, $value);
                });
        });
    }
}
