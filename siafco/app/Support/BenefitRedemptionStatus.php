<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

final class BenefitRedemptionStatus
{
    public const PENDING = 'pending';
    public const APPROVED = 'approved';
    public const USED = 'used';
    public const CANCELLED = 'cancelled';

    public const ALL = [self::PENDING, self::APPROVED, self::USED, self::CANCELLED];

    private const TRANSITIONS = [
        self::PENDING => [self::APPROVED, self::CANCELLED],
        self::APPROVED => [self::USED, self::CANCELLED],
        self::USED => [],
        self::CANCELLED => [],
    ];

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    public static function assertTransition(string $from, string $to): void
    {
        if (! self::canTransition($from, $to)) {
            throw ValidationException::withMessages([
                'status' => "No se puede cambiar el canje de {$from} a {$to}.",
            ]);
        }
    }
}
