<?php

namespace App\Support;

final class InvestmentProspectStatus
{
    public const CAPTURED = 'captured';

    public const IN_TRANSITION = 'in_transition';

    public const CLOSED = 'closed';

    public const LOST = 'lost';

    public static function labels(): array
    {
        return [
            self::CAPTURED => 'CAPTADO',
            self::IN_TRANSITION => 'EN TRANSICIÓN',
            self::CLOSED => 'CERRADO',
            self::LOST => 'PERDIDO',
        ];
    }

    public static function values(): array
    {
        return array_keys(self::labels());
    }

    public static function label(string $status): string
    {
        return self::labels()[$status] ?? str($status)->replace('_', ' ')->upper()->toString();
    }
}
