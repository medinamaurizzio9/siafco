<?php

namespace App\Models;

use App\Support\StoreAvailabilityStatus;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StoreProduct extends Model
{
    use HasPublicUuid;
    use SoftDeletes;

    public const PUBLIC_UUID_COLUMN = 'public_code';

    protected $fillable = [
        'store_category_id',
        'slug',
        'sku',
        'name',
        'short_description',
        'description',
        'regular_price',
        'affiliate_price',
        'promo_price',
        'promo_starts_at',
        'promo_ends_at',
        'availability_status',
        'delivery_modes',
        'max_quantity_per_order',
        'featured',
        'active',
        'order',
    ];

    protected function casts(): array
    {
        return [
            'regular_price' => 'decimal:2',
            'affiliate_price' => 'decimal:2',
            'promo_price' => 'decimal:2',
            'promo_starts_at' => 'datetime',
            'promo_ends_at' => 'datetime',
            'delivery_modes' => 'array',
            'max_quantity_per_order' => 'integer',
            'featured' => 'boolean',
            'active' => 'boolean',
            'order' => 'integer',
        ];
    }

    public function category()
    {
        return $this->belongsTo(StoreCategory::class, 'store_category_id');
    }

    public function variants()
    {
        return $this->hasMany(StoreProductVariant::class);
    }

    public function images()
    {
        return $this->hasMany(StoreProductImage::class);
    }

    public function orderItems()
    {
        return $this->hasMany(StoreOrderItem::class);
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeVisible($query)
    {
        return $query->active()->whereIn('availability_status', [
            StoreAvailabilityStatus::AVAILABLE,
            StoreAvailabilityStatus::SOLD_OUT,
            StoreAvailabilityStatus::COMING_SOON,
        ]);
    }

    public function scopeAvailable($query)
    {
        return $query->visible()->where('availability_status', StoreAvailabilityStatus::AVAILABLE);
    }
}
