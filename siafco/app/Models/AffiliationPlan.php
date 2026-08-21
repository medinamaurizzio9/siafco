<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class AffiliationPlan extends Model
{
    protected $fillable = [
        'sector_id', 'name', 'type', 'affiliation_fee', 'credential_fee', 'currency',
        'valid_from', 'valid_until', 'description', 'payment_instructions', 'is_active',
    ];

    protected function casts(): array
    {
        return ['valid_from' => 'date', 'valid_until' => 'date', 'is_active' => 'boolean'];
    }

    public function getTotalAmountAttribute(): float
    {
        return (float) $this->affiliation_fee + (float) $this->credential_fee;
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(fn (Builder $builder) => $builder->whereNull('valid_from')->orWhereDate('valid_from', '<=', today()))
            ->where(fn (Builder $builder) => $builder->whereNull('valid_until')->orWhereDate('valid_until', '>=', today()));
    }

    public function sector() { return $this->belongsTo(Sector::class); }
}
