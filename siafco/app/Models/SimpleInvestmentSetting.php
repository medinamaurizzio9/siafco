<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SimpleInvestmentSetting extends Model
{
    protected $fillable = ['product_name', 'unit_price', 'currency', 'minimum_units', 'maximum_units', 'first_schedule_days', 'schedule_interval_days', 'is_active', 'updated_by'];

    protected function casts(): array
    {
        return ['unit_price' => 'decimal:2', 'is_active' => 'boolean'];
    }

    public static function current(): self
    {
        return static::query()->firstOrFail();
    }
}
