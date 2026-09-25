<?php

namespace App\Support;

final class AffiliateSupportChannel
{
    public const WEB = 'web';
    public const EXPRESS = 'express';
    public const OFFICE = 'office';
    public const INTERNAL = 'internal';
    public const UNKNOWN = 'unknown';

    public static function label(?string $channel): string
    {
        return match ($channel) {
            self::WEB => 'WEB',
            self::EXPRESS => 'EXPRESS',
            self::OFFICE => 'OFICINA',
            self::INTERNAL => 'INTERNO',
            default => 'DESCONOCIDO',
        };
    }

    public static function badgeClasses(?string $channel): string
    {
        return match ($channel) {
            self::WEB, self::EXPRESS => 'bg-sky-100 text-sky-800',
            self::OFFICE, self::INTERNAL => 'bg-amber-100 text-amber-900',
            default => 'bg-slate-100 text-slate-700',
        };
    }
}
