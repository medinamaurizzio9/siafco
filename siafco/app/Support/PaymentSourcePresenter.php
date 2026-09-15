<?php

namespace App\Support;

final class PaymentSourcePresenter
{
    public static function channel(?string $source): string
    {
        return match (strtolower(trim((string) $source))) {
            'manual_admin', 'office_cash', 'office_qr' => 'OFICINA',
            'mobile' => 'APP',
            default => 'WEB',
        };
    }

    public static function label(?string $source): string
    {
        return match (strtolower(trim((string) $source))) {
            'manual_admin' => 'Oficina',
            'office_cash' => 'Efectivo en oficina',
            'office_qr' => 'QR en oficina',
            'mobile' => 'App',
            'web' => 'Web',
            default => 'Web',
        };
    }

    public static function badgeClasses(?string $source): string
    {
        return match (self::channel($source)) {
            'OFICINA' => 'bg-amber-100 text-amber-900',
            'APP' => 'bg-indigo-100 text-indigo-800',
            default => 'bg-sky-100 text-sky-800',
        };
    }
}
