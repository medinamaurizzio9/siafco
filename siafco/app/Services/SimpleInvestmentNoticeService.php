<?php

namespace App\Services;

use App\Models\SimpleInvestmentContract;
use App\Models\SimpleInvestmentScheduleNotice;
use Carbon\CarbonImmutable;

class SimpleInvestmentNoticeService
{
    public function __construct(private readonly SimpleInvestmentScheduleService $schedule, private readonly SimpleInvestorNotificationService $portalNotifications) {}

    public function prepare(?CarbonImmutable $asOf = null): int
    {
        $created = 0;
        SimpleInvestmentContract::query()
            ->where('status', SimpleInvestmentContract::SIGNED)
            ->with('scheduleNotices')
            ->orderBy('id')
            ->chunkById(100, function ($contracts) use ($asOf, &$created): void {
                foreach ($contracts as $contract) {
                    foreach ($this->schedule->projections($contract, $asOf) as $projection) {
                        if ($projection['delivery_confirmed']) {
                            continue;
                        }
                        $kind = $this->kindForDays($projection['days_remaining']);
                        if ($kind === null) {
                            continue;
                        }
                        $notice = SimpleInvestmentScheduleNotice::firstOrCreate(
                            ['idempotency_key' => "simple-investment:{$contract->id}:{$projection['index']}:{$kind}"],
                            [
                                'contract_id' => $contract->id,
                                'schedule_index' => $projection['index'],
                                'scheduled_on' => $projection['date']->toDateString(),
                                'notice_kind' => $kind,
                                'prepared_at' => now(),
                            ]
                        );
                        $created += $notice->wasRecentlyCreated ? 1 : 0;
                    }
                }
            });

        $this->portalNotifications->sync();

        return $created;
    }

    private function kindForDays(int $days): ?string
    {
        return match (true) {
            $days === 7 => 'advance_7',
            $days === 3 => 'advance_3',
            $days === 0 => 'due',
            $days < 0 => 'past',
            default => null,
        };
    }
}
