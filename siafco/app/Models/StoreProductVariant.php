<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StoreProductVariant extends Model
{
    use HasPublicUuid;
    use SoftDeletes;

    public const PUBLIC_UUID_COLUMN = 'public_code';

    protected $fillable = [
        'store_product_id',
        'type',
        'name',
        'sku_suffix',
        'price_delta',
        'active',
        'order',
    ];

    protected function casts(): array
    {
        return [
            'price_delta' => 'decimal:2',
            'active' => 'boolean',
            'order' => 'integer',
        ];
    }

    public function product()
    {
        return $this->belongsTo(StoreProduct::class, 'store_product_id');
    }

    public function orderItems()
    {
        return $this->hasMany(StoreOrderItem::class);
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
