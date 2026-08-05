<?php

namespace App\Support;

class PaymentStatus
{
    public const PENDING = 'pending';
    public const UNDER_REVIEW = 'under_review';
    public const CONFIRMED = 'confirmed';
    public const REJECTED = 'rejected';
    public const VOIDED = 'voided';

    public static function pendingValues(): array
    {
        return [self::PENDING, 'pendiente'];
    }

    public static function editableValues(): array
    {
        return [self::PENDING, 'pendiente', self::UNDER_REVIEW];
    }

    public static function confirmedValues(): array
    {
        return [self::CONFIRMED, 'confirmado'];
    }

    public static function rejectedValues(): array
    {
        return [self::REJECTED, 'rechazado'];
    }

    public static function voidedValues(): array
    {
        return [self::VOIDED, 'anulado'];
    }

    public static function allValues(): array
    {
        return array_values(array_unique([
            ...self::pendingValues(),
            self::UNDER_REVIEW,
            ...self::confirmedValues(),
            ...self::rejectedValues(),
            ...self::voidedValues(),
        ]));
    }

    public static function label(?string $status): string
    {
        return match ($status) {
            self::PENDING, 'pendiente' => 'Pendiente',
            self::UNDER_REVIEW => 'En revision',
            self::CONFIRMED, 'confirmado' => 'Confirmado',
            self::REJECTED, 'rechazado' => 'Rechazado',
            self::VOIDED, 'anulado' => 'Anulado',
            default => 'No registrado',
        };
    }

    public static function isEditable(?string $status): bool
    {
        return in_array($status, self::editableValues(), true);
    }

    public static function isConfirmed(?string $status): bool
    {
        return in_array($status, self::confirmedValues(), true);
    }

    public static function isRejected(?string $status): bool
    {
        return in_array($status, self::rejectedValues(), true);
    }

    public static function isVoided(?string $status): bool
    {
        return in_array($status, self::voidedValues(), true);
    }
}
