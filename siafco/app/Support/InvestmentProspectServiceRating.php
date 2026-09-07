<?php

namespace App\Support;

class InvestmentProspectServiceRating
{
    public const POOR = 'poor'; public const REGULAR = 'regular'; public const GOOD = 'good'; public const VERY_GOOD = 'very_good';
    public static function values(): array { return [self::POOR, self::REGULAR, self::GOOD, self::VERY_GOOD]; }
    public static function labels(): array { return [self::POOR => 'Mala', self::REGULAR => 'Regular', self::GOOD => 'Buena', self::VERY_GOOD => 'Muy buena']; }
    public static function emojis(): array { return [self::POOR => '😞', self::REGULAR => '😐', self::GOOD => '🙂', self::VERY_GOOD => '😁']; }
    public static function label(?string $value): string { return self::labels()[$value] ?? 'Sin calificación'; }
    public static function emoji(?string $value): string { return self::emojis()[$value] ?? ''; }
}
