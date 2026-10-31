<?php

namespace Tests\Unit;

use App\Models\SimpleInvestmentContract;
use App\Services\SimpleInvestmentScheduleService;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SimpleInvestorCalendarTest extends TestCase
{
    public static function dates(): array
    {
        return [
            ['2026-12-31', '2027-01-01T03:59:00Z', 0, '2026-12-31'],
            ['2026-12-31', '2027-01-01T04:01:00Z', -1, '2027-01-30'],
            ['2026-10-31', '2026-11-01T03:59:00Z', 0, '2026-10-31'],
            ['2026-10-31', '2026-11-01T04:01:00Z', -1, '2026-11-30'],
            ['2028-02-29', '2028-02-29T04:00:00Z', 0, '2028-02-29'],
            ['2028-02-29', '2028-03-01T04:00:00Z', -1, '2028-03-30'],
            ['2026-10-30', '2026-10-30T03:59:00Z', 1, '2026-10-30'],
        ];
    }

    private function contract(string $first): SimpleInvestmentContract
    {
        $contract = new SimpleInvestmentContract;
        $contract->setRawAttributes(['status' => SimpleInvestmentContract::SIGNED,
            'signed_on' => CarbonImmutable::parse($first)->subDays(40)->format('Y-m-d'),
            'first_schedule_days_snapshot' => 40, 'schedule_interval_days_snapshot' => 30,
            'schedule_projection_count_snapshot' => 3, 'schedule_timezone_snapshot' => 'America/La_Paz']);
        $contract->setRelation('scheduleNotices', collect());

        return $contract;
    }

    #[DataProvider('dates')]
    public function test_contract_local_day_defines_next_date(string $first, string $instant, int $days, string $next): void
    {
        // Use the application test case for Eloquent date casting, but no database connection.
        $service = new SimpleInvestmentScheduleService;
        $contract = $this->contract($first);
        $asOf = CarbonImmutable::parse($instant);
        $this->assertSame($days, $service->projections($contract, $asOf)->first()['days_remaining']);
        $calendar = $service->portalDates(collect([$contract]), $asOf);
        $this->assertSame($next, $calendar['next']['date']->format('Y-m-d'));
        $this->assertCount($days === 0 ? 1 : 0, $calendar['today']);
    }

    public function test_all_past_has_no_next_and_does_not_extend_projection(): void
    {
        $calendar = (new SimpleInvestmentScheduleService)->portalDates(collect([$this->contract('2026-01-01')]), CarbonImmutable::parse('2030-01-01T12:00:00Z'));
        $this->assertNull($calendar['next']);
        $this->assertCount(3, $calendar['past']);
        $this->assertCount(0, $calendar['future']);
    }
}
