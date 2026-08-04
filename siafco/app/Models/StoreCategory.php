<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StoreCategory extends Model
{
    use SoftDeletes;

    protected $fillable = ['slug', 'name', 'description', 'active', 'order'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'order' => 'integer'];
    }

    public function products()
    {
        return $this->hasMany(StoreProduct::class);
    }

    public function couponTargets()
    {
        return $this->hasMany(StoreCouponTarget::class);
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
