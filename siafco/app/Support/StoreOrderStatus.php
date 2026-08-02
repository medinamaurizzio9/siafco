<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

final class StoreOrderStatus
{
    public const PENDING = 'pendiente';
    public const RESERVED = 'reservado';
    public const WAITING_PAYMENT = 'esperando_pago';
    public const PAYMENT_REVIEW = 'pago_en_revision';
    public const CONFIRMED = 'confirmado';
    public const PREPARING = 'preparando';
    public const SHIPPED = 'enviado';
    public const READY_FOR_PICKUP = 'listo_para_recoger';
    public const DELIVERED = 'entregado';
    public const CANCELLED = 'cancelado';
    public const REJECTED = 'rechazado';

    public const ALL = [
        self::PENDING,
        self::RESERVED,
        self::WAITING_PAYMENT,
        self::PAYMENT_REVIEW,
        self::CONFIRMED,
        self::PREPARING,
        self::SHIPPED,
        self::READY_FOR_PICKUP,
        self::DELIVERED,
        self::CANCELLED,
        self::REJECTED,
    ];

    private const TRANSITIONS = [
        self::PENDING => [self::WAITING_PAYMENT, self::RESERVED, self::CANCELLED],
        self::RESERVED => [self::WAITING_PAYMENT, self::CONFIRMED, self::CANCELLED],
        self::WAITING_PAYMENT => [self::PAYMENT_REVIEW, self::CANCELLED],
        self::PAYMENT_REVIEW => [self::CONFIRMED, self::REJECTED, self::CANCELLED],
        self::CONFIRMED => [self::PREPARING, self::CANCELLED],
        self::PREPARING => [self::READY_FOR_PICKUP, self::SHIPPED],
        self::SHIPPED => [self::DELIVERED],
        self::READY_FOR_PICKUP => [self::DELIVERED],
        self::DELIVERED => [],
        self::CANCELLED => [],
        self::REJECTED => [],
    ];

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    public static function assertTransition(string $from, string $to): void
    {
        if (! self::canTransition($from, $to)) {
            throw ValidationException::withMessages([
                'status' => "No se puede cambiar el pedido de {$from} a {$to}.",
            ]);
        }
    }
}
