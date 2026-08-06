<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StoreProductImage extends Model
{
    protected $fillable = ['store_product_id', 'path', 'alt', 'is_primary', 'order'];

    protected $hidden = ['path'];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean', 'order' => 'integer'];
    }

    public function product()
    {
        return $this->belongsTo(StoreProduct::class, 'store_product_id');
    }
}
