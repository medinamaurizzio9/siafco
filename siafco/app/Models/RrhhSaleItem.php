<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RrhhSaleItem extends Model
{
    protected $fillable = ['sale_id', 'product_id', 'product_code_snapshot', 'product_name_snapshot', 'quantity', 'unit_price', 'line_total', 'commission_type_snapshot', 'commission_value_snapshot', 'commission_amount'];

    protected function casts(): array
    {
        return ['unit_price' => 'decimal:2', 'line_total' => 'decimal:2', 'commission_value_snapshot' => 'decimal:4', 'commission_amount' => 'decimal:2'];
    }

    public function sale()
    {
        return $this->belongsTo(RrhhSale::class, 'sale_id');
    }

    public function product()
    {
        return $this->belongsTo(RrhhProduct::class, 'product_id');
    }

    public function payLine()
    {
        return $this->hasOne(RrhhPayLine::class, 'sale_item_id');
    }
}
