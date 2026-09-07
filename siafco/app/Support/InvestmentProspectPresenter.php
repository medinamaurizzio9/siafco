<?php

namespace App\Support;

use Carbon\CarbonInterface;

final class InvestmentProspectPresenter
{
    public static function capturedAt(CarbonInterface $date, bool $withSeparator = false): string
    {
        return InvestmentCrmDate::format($date, $withSeparator ? 'd/m/Y - H:i' : 'd/m/Y H:i');
    }
}
