<?php

namespace App\Support;

final class PaymentMethodPresenter
{
    public static function label(?string $method): string
    {
        return match (strtolower(trim((string) $method))) {
            'efectivo' => 'Efectivo',
            'qr' => 'QR',
            'transferencia' => 'Transferencia',
            'deposito' => 'Depósito',
            'pos' => 'POS',
            'otro' => 'Otro',
            default => 'No registrado',
        };
    }

    public static function showsTransactionNumber(?string $method): bool
    {
        return in_array(strtolower(trim((string) $method)), ['qr', 'transferencia', 'deposito', 'pos'], true);
    }
}
