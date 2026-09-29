<?php

namespace App\Support;

use App\Models\AffiliationPayment;
use Illuminate\Database\Eloquent\Builder;

final class PaymentTransactionNumber
{
    public static function resolve(?AffiliationPayment $payment): ?string
    {
        if (! $payment) {
            return null;
        }

        return self::normalize($payment->transaction_number)
            ?? self::normalize($payment->reference_number);
    }

    public static function normalize(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    public static function hasDuplicate(mixed $value, ?int $exceptPaymentId = null): bool
    {
        $number = self::normalize($value);
        if (! $number) {
            return false;
        }

        return AffiliationPayment::query()
            ->when($exceptPaymentId, fn (Builder $query) => $query->whereKeyNot($exceptPaymentId))
            ->where(function (Builder $query) use ($number): void {
                $query->where('transaction_number', $number)
                    ->orWhere('reference_number', $number);
            })
            ->exists();
    }

    public static function duplicateMessage(): string
    {
        return 'Este número de transacción ya fue registrado en otro pago.';
    }
}
