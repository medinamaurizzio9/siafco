<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvestmentCrmSetting extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['singleton_key' => 'boolean'];
    }
}
