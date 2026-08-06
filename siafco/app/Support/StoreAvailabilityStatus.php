<?php

namespace App\Support;

final class StoreAvailabilityStatus
{
    public const AVAILABLE = 'disponible';
    public const SOLD_OUT = 'agotado';
    public const COMING_SOON = 'proximamente';
    public const HIDDEN = 'oculto';

    public const ALL = [
        self::AVAILABLE,
        self::SOLD_OUT,
        self::COMING_SOON,
        self::HIDDEN,
    ];

    public static function isVisible(string $status): bool
    {
        return in_array($status, [self::AVAILABLE, self::SOLD_OUT, self::COMING_SOON], true);
    }
}
