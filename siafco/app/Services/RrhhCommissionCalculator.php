<?php

namespace App\Services;

use App\Models\RrhhProduct;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

class RrhhCommissionCalculator
{
    public function line(string $unitPrice, int $quantity, string $type, string $value): array
    {
        $lineTotal = BigDecimal::of($unitPrice)->multipliedBy($quantity)->toScale(2, RoundingMode::HalfUp);
        $commission = match ($type) {
            RrhhProduct::COMMISSION_FIXED => BigDecimal::of($value)->multipliedBy($quantity),
            RrhhProduct::COMMISSION_PERCENTAGE => $lineTotal->multipliedBy($value)->dividedBy(100, 2, RoundingMode::HalfUp),
            default => throw new \InvalidArgumentException('Tipo de comisión no soportado.'),
        };

        return [
            'line_total' => (string) $lineTotal,
            'commission_amount' => (string) $commission->toScale(2, RoundingMode::HalfUp),
        ];
    }
}
