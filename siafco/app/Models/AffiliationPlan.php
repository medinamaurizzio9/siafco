<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AffiliationPlan extends Model
{
    protected $fillable = ['name', 'affiliation_fee', 'credential_fee', 'description', 'is_active'];

    public function getTotalAmountAttribute(): float
    {
        return (float) $this->affiliation_fee + (float) $this->credential_fee;
    }
}
