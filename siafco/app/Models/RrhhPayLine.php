<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RrhhPayLine extends Model
{
    protected $fillable = ['pay_period_id', 'type', 'sale_item_id', 'adjustment_id', 'description', 'amount'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function payPeriod()
    {
        return $this->belongsTo(RrhhPayPeriod::class, 'pay_period_id');
    }

    public function saleItem()
    {
        return $this->belongsTo(RrhhSaleItem::class, 'sale_item_id');
    }

    public function adjustment()
    {
        return $this->belongsTo(RrhhAdjustment::class, 'adjustment_id');
    }
}
