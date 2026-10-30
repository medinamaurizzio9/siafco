<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RrhhProduct extends Model
{
    public const COMMISSION_FIXED = 'fixed';

    public const COMMISSION_PERCENTAGE = 'percentage';

    protected $fillable = ['code', 'name', 'description', 'reference_price', 'currency', 'commission_type', 'commission_value', 'is_active', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return ['reference_price' => 'decimal:2', 'commission_value' => 'decimal:4', 'is_active' => 'boolean'];
    }

    public function saleItems()
    {
        return $this->hasMany(RrhhSaleItem::class, 'product_id');
    }
}
