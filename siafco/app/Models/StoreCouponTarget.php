<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class StoreCouponTarget extends Model
{
    protected $fillable = ['store_coupon_id', 'store_product_id', 'store_category_id'];

    protected static function booted(): void
    {
        static::saving(function (self $target): void {
            if (! $target->store_product_id && ! $target->store_category_id) {
                throw ValidationException::withMessages([
                    'target' => 'El cupón debe apuntar a un producto o una categoría.',
                ]);
            }
        });
    }

    public function coupon()
    {
        return $this->belongsTo(StoreCoupon::class, 'store_coupon_id');
    }

    public function product()
    {
        return $this->belongsTo(StoreProduct::class, 'store_product_id');
    }

    public function category()
    {
        return $this->belongsTo(StoreCategory::class, 'store_category_id');
    }
}
