<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StoreCouponUsage extends Model
{
    protected $fillable = ['store_coupon_id', 'store_order_id', 'affiliate_id', 'amount', 'used_at', 'released_at', 'release_reason', 'released_by_user_id'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'used_at' => 'datetime', 'released_at' => 'datetime'];
    }

    public function coupon()
    {
        return $this->belongsTo(StoreCoupon::class, 'store_coupon_id');
    }

    public function order()
    {
        return $this->belongsTo(StoreOrder::class, 'store_order_id');
    }

    public function affiliate()
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function releaser()
    {
        return $this->belongsTo(User::class, 'released_by_user_id');
    }
}
