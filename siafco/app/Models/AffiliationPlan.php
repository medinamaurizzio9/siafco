<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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

    public function sector() { return $this->belongsTo(Sector::class); }
}
