<?php

namespace App\Services;

use App\Models\SimpleInvestmentSequence;

class SimpleInvestmentSequenceService
{
    public function next(string $key, string $prefix): string
    {
        $sequence = SimpleInvestmentSequence::query()->whereKey($key)->lockForUpdate()->firstOrFail();
        $sequence->increment('last_value');

        return $prefix.'-'.str_pad((string) $sequence->fresh()->last_value, 6, '0', STR_PAD_LEFT);
    }
}
