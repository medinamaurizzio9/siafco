<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StoreOrderStatusHistory extends Model
{
    protected $fillable = [
        'store_order_id',
        'actor_user_id',
        'from_status',
        'to_status',
        'admin_note',
        'changed_at',
    ];

    protected function casts(): array
    {
        return ['changed_at' => 'datetime'];
    }

    public function order()
    {
        return $this->belongsTo(StoreOrder::class, 'store_order_id');
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
