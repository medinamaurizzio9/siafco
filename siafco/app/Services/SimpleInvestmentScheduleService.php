<?php

namespace App\Services;

use App\Models\SimpleInvestmentContract;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class SimpleInvestmentScheduleService
{
    public function projections(SimpleInvestmentContract $contract, ?CarbonImmutable $asOf = null): Collection
    {
        if ($contract->status !== SimpleInvestmentContract::SIGNED
            || ! $contract->signed_on
            || ! $contract->first_schedule_days_snapshot
            || ! $contract->schedule_interval_days_snapshot) {
            return collect();
        }

        $timezone = $contract->schedule_timezone_snapshot ?: config('simple_investments.schedule_timezone', 'America/La_Paz');
        $today = ($asOf ?? CarbonImmutable::now($timezone))->setTimezone($timezone)->startOfDay();
        $signedOn = CarbonImmutable::createFromFormat('Y-m-d', $contract->signed_on->format('Y-m-d'), $timezone)->startOfDay();
        $first = $signedOn->addDays((int) $contract->first_schedule_days_snapshot);
        $count = max(1, (int) ($contract->schedule_projection_count_snapshot ?: config('simple_investments.projection_count', 12)));
        $notices = $contract->relationLoaded('scheduleNotices') ? $contract->scheduleNotices : $contract->scheduleNotices()->get();

        return collect(range(1, $count))->map(function (int $index) use ($contract, $first, $today, $notices) {
            $date = $first->addDays(($index - 1) * (int) $contract->schedule_interval_days_snapshot);
            $days = (int) $today->diffInDays($date, false);
            $indexNotices = $notices->where('schedule_index', $index);
            $notice = $indexNotices->first(fn ($item) => $item->delivery_confirmed_at !== null)
                ?? $indexNotices->first(fn ($item) => $item->sent_at !== null)
                ?? $indexNotices->sortByDesc('prepared_at')->first();
            $deliveryConfirmed = $indexNotices->contains(fn ($item) => $item->delivery_confirmed_at !== null);

            return [
                'index' => $index,
                'date' => $date,
                'days_remaining' => $days,
                'state' => $deliveryConfirmed ? 'Entrega confirmada' : ($days < 0 ? 'Fecha pasada sin entrega confirmada' : 'Fecha programada'),
                'notice_status' => $notice?->statusLabel() ?? 'Aviso no preparado',
                'delivery_confirmed' => $deliveryConfirmed,
                'contract' => $contract,
            ];
        });
    }

    public function dashboardDates(Collection $contracts, ?CarbonImmutable $asOf = null, int $limit = 12): Collection
    {
        return $contracts->flatMap(fn (SimpleInvestmentContract $contract) => $this->projections($contract, $asOf))
            ->sortBy(fn (array $projection) => $projection['date']->format('Y-m-d'))
            ->take($limit)
            ->values();
    }

    /** Classify using each contract's local day, before applying any presentation limit. */
    public function portalDates(Collection $contracts, ?CarbonImmutable $asOf = null): array
    {
        $dates = $contracts->flatMap(fn (SimpleInvestmentContract $contract) => $this->projections($contract, $asOf))
            ->sortBy(fn (array $date) => $date['date']->getTimestamp())->values();

        return [
            'next' => $dates->first(fn (array $date) => $date['days_remaining'] >= 0),
            'today' => $dates->filter(fn (array $date) => $date['days_remaining'] === 0)->values(),
            'future' => $dates->filter(fn (array $date) => $date['days_remaining'] > 0)->values(),
            'past' => $dates->filter(fn (array $date) => $date['days_remaining'] < 0)->values(),
        ];
    }
}
