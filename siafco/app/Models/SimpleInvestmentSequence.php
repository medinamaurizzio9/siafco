<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SimpleInvestmentSequence extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'last_value'];
}
