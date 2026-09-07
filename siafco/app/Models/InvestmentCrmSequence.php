<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvestmentCrmSequence extends Model
{
    protected $fillable = ['key', 'next_number'];

    protected function casts(): array
    {
        return ['next_number' => 'integer'];
    }
}
