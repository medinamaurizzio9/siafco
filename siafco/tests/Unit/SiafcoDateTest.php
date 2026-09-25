<?php

namespace Tests\Unit;

use App\Support\SiafcoDate;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class SiafcoDateTest extends TestCase
{
    public function test_real_timestamp_is_displayed_in_bolivia_time(): void
    {
        $date = CarbonImmutable::parse('2026-09-24 02:13:03', 'UTC');

        $this->assertSame('23/09/2026 22:13', SiafcoDate::dateTime($date));
        $this->assertSame('23/09/2026 22:13:03', SiafcoDate::dateTimeWithSeconds($date));
        $this->assertSame('22:13', SiafcoDate::time($date));
    }

    public function test_civil_date_is_formatted_without_timezone_shift(): void
    {
        $date = CarbonImmutable::parse('2026-09-24 00:30:00', 'UTC');

        $this->assertSame('24/09/2026', SiafcoDate::date($date));
    }

    public function test_local_day_bounds_are_converted_to_utc(): void
    {
        [$start, $end] = SiafcoDate::utcDayBounds('2026-09-24');

        $this->assertSame('2026-09-24 04:00:00', $start->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-25 03:59:59', $end->format('Y-m-d H:i:s'));
    }

    public function test_local_datetime_input_is_stored_as_utc(): void
    {
        $date = SiafcoDate::fromLocalInput('2026-09-24T10:30');

        $this->assertSame('2026-09-24 14:30:00', $date?->format('Y-m-d H:i:s'));
    }
}
