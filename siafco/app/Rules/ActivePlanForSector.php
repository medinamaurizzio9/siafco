<?php

namespace App\Rules;

use App\Models\AffiliationPlan;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ActivePlanForSector implements ValidationRule
{
    public function __construct(private readonly mixed $sectorId)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $valid = AffiliationPlan::query()
            ->available()
            ->whereKey($value)
            ->where('sector_id', $this->sectorId)
            ->exists();

        if (! $valid) {
            $fail('El plan seleccionado no pertenece al sector indicado o no está disponible.');
        }
    }
}
