<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

final class InvestmentCrmDate
{
    public static function format(?CarbonInterface $date, string $format = 'd/m/Y H:i'): string
    {
        return $date?->copy()->timezone(config('investment_crm.timezone'))->format($format) ?? 'No registrado';
    }

    public static function fromInput(?string $value): ?CarbonImmutable
    {
        if (! filled($value)) {
            return null;
        }

        return CarbonImmutable::createFromFormat('Y-m-d\TH:i', $value, config('investment_crm.timezone'))->utc();
    }

    public static function inputValue(?CarbonInterface $date): ?string
    {
        return $date?->copy()->timezone(config('investment_crm.timezone'))->format('Y-m-d\TH:i');
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    public static function dayBounds(?string $date = null): array
    {
        $local = $date
            ? CarbonImmutable::createFromFormat('Y-m-d', $date, config('investment_crm.timezone'))
            : CarbonImmutable::now(config('investment_crm.timezone'));

        return [$local->startOfDay()->utc(), $local->endOfDay()->utc()];
    }
}
