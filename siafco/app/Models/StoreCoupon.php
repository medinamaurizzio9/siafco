<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use App\Services\StoreCouponCodeService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StoreCoupon extends Model
{
    use HasPublicUuid;
    use SoftDeletes;

    public const PUBLIC_UUID_COLUMN = 'public_code';

    public const TYPE_PERCENTAGE = 'percentage';
    public const TYPE_FIXED = 'fixed';

    protected $fillable = [
        'code_encrypted',
        'type',
        'value',
        'starts_at',
        'ends_at',
        'minimum_amount',
        'global_limit',
        'per_affiliate_limit',
        'active',
        'created_by',
        'updated_by',
    ];

    protected $hidden = [
        'code_hash',
        'code_encrypted',
    ];

    protected function casts(): array
    {
        return [
            'code_encrypted' => 'encrypted',
            'value' => 'decimal:2',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'minimum_amount' => 'decimal:2',
            'global_limit' => 'integer',
            'per_affiliate_limit' => 'integer',
            'active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $coupon): void {
            if ($coupon->isDirty('code_encrypted')) {
                $service = app(StoreCouponCodeService::class);
                $plain = (string) $coupon->code_encrypted;
                $coupon->code_hash = $service->hash($plain);
                $coupon->code_hint = $service->hint($plain);
                $coupon->code_encrypted = $service->normalize($plain);
            }
        });
    }

    public function targets()
    {
        return $this->hasMany(StoreCouponTarget::class);
    }

    public function usages()
    {
        return $this->hasMany(StoreCouponUsage::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function appliesToAllProducts(): bool
    {
        return ! $this->targets()->exists();
    }
}
