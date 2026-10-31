<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SimpleInvestmentUnit extends Model
{
    protected $fillable = ['code', 'contract_id', 'investor_id', 'position', 'issued_at'];

    protected function casts(): array
    {
        return ['issued_at' => 'datetime'];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(SimpleInvestmentContract::class, 'contract_id');
    }
}
