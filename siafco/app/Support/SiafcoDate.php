<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DateTimeZone;
use Illuminate\Validation\ValidationException;

final class SiafcoDate
{
    public static function date(mixed $value, string $empty = 'Sin fecha'): string
    {
        $date = self::parse($value);

        return $date ? $date->format('d/m/Y') : $empty;
    }

    public static function dateTime(mixed $value, string $empty = 'Sin fecha'): string
    {
        $date = self::parse($value);

        return $date ? $date->timezone(self::timezone())->format('d/m/Y H:i') : $empty;
    }

    public static function dateTimeWithSeconds(mixed $value, string $empty = 'Sin fecha'): string
    {
        $date = self::parse($value);

        return $date ? $date->timezone(self::timezone())->format('d/m/Y H:i:s') : $empty;
    }

    public static function time(mixed $value, string $empty = 'Sin hora'): string
    {
        $date = self::parse($value);

        return $date ? $date->timezone(self::timezone())->format('H:i') : $empty;
    }

    public static function inputDateTime(mixed $value): ?string
    {
        return self::parse($value)?->timezone(self::timezone())->format('Y-m-d\TH:i');
    }

    public static function fromLocalInput(?string $value, ?string $timezone = null): ?CarbonImmutable
    {
        if (! filled($value)) {
            return null;
        }

        return CarbonImmutable::parse($value, self::validTimezoneOrDefault($timezone))->utc();
    }

    public static function paymentInputDateTime(mixed $value, string $empty = 'Sin fecha'): string
    {
        if (! $value) {
            return $empty;
        }

        if ($value instanceof CarbonInterface) {
            return self::dateTime($value, $empty);
        }

        $raw = trim((string) $value);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) === 1) {
            return self::date($raw, $empty);
        }

        return self::dateTime($raw, $empty);
    }

    public static function validTimezoneOrDefault(?string $timezone): string
    {
        if (! filled($timezone)) {
            return self::timezone();
        }

        $timezone = trim($timezone);

        if (in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
            return $timezone;
        }

        throw ValidationException::withMessages([
            'browser_timezone' => 'La zona horaria enviada no es válida.',
        ]);
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    public static function utcDayBounds(?string $date): array
    {
        $local = filled($date)
            ? CarbonImmutable::createFromFormat('Y-m-d', $date, self::timezone())
            : CarbonImmutable::now(self::timezone());

        return [$local->startOfDay()->utc(), $local->endOfDay()->utc()];
    }

    public static function timezone(): string
    {
        return config('siafco.display_timezone', 'America/La_Paz');
    }

    private static function parse(mixed $value): ?CarbonImmutable
    {
        if (! $value) {
            return null;
        }

        if ($value instanceof CarbonInterface) {
            return CarbonImmutable::instance($value);
        }

        return CarbonImmutable::parse($value, config('app.timezone', 'UTC'));
    }
}
