<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StoreOrderItem extends Model
{
    protected $fillable = [
        'store_order_id',
        'store_product_id',
        'store_product_variant_id',
        'sku_snapshot',
        'name_snapshot',
        'variant_snapshot',
        'unit_price',
        'quantity',
        'discount_total',
        'line_total',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'quantity' => 'integer',
            'discount_total' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function order()
    {
        return $this->belongsTo(StoreOrder::class, 'store_order_id');
    }

    public function product()
    {
        return $this->belongsTo(StoreProduct::class, 'store_product_id');
    }

    public function variant()
    {
        return $this->belongsTo(StoreProductVariant::class, 'store_product_variant_id');
    }
}
