<?php

namespace App\Services\Store;

final class StoreMoney
{
    public static function cents(mixed $amount): int
    {
        $value = trim((string) $amount);
        if ($value === '') {
            return 0;
        }

        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '+-');
        [$whole, $decimal] = array_pad(explode('.', $value, 2), 2, '');
        $whole = preg_replace('/\D/', '', $whole) ?: '0';
        $decimal = substr(str_pad(preg_replace('/\D/', '', $decimal) ?: '', 2, '0'), 0, 2);
        $cents = ((int) $whole * 100) + (int) $decimal;

        return $negative ? -$cents : $cents;
    }

    public static function decimal(int $cents): string
    {
        $negative = $cents < 0;
        $cents = abs($cents);

        return ($negative ? '-' : '').intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
