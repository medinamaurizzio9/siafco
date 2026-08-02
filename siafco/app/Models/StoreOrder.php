<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use App\Support\StoreCodeGenerator;
use App\Support\StoreOrderStatus;
use Illuminate\Database\Eloquent\Model;

class StoreOrder extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'affiliate_id',
        'status',
        'delivery_method',
        'department',
        'city',
        'zone',
        'delivery_address',
        'subtotal',
        'discount_total',
        'shipping_total',
        'total',
        'currency',
        'coupon_snapshot',
        'shipping_snapshot',
        'payment_snapshot',
        'whatsapp_number_snapshot',
        'whatsapp_opened_at',
        'confirmed_at',
        'delivered_at',
        'cancelled_at',
    ];

    protected $hidden = [
        'delivery_address',
        'whatsapp_number_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'shipping_total' => 'decimal:2',
            'total' => 'decimal:2',
            'coupon_snapshot' => 'array',
            'shipping_snapshot' => 'array',
            'payment_snapshot' => 'array',
            'whatsapp_number_snapshot' => 'encrypted',
            'whatsapp_opened_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'delivered_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $order): void {
            StoreCodeGenerator::assignUnique($order, 'code', [StoreCodeGenerator::class, 'orderCode']);
        });
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    public function affiliate()
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function items()
    {
        return $this->hasMany(StoreOrderItem::class);
    }

    public function receipts()
    {
        return $this->hasMany(StoreOrderReceipt::class);
    }

    public function couponUsage()
    {
        return $this->hasOne(StoreCouponUsage::class);
    }

    public function statusHistories()
    {
        return $this->hasMany(StoreOrderStatusHistory::class);
    }

    public function transitionTo(string $status): void
    {
        StoreOrderStatus::assertTransition($this->status, $status);
        $this->forceFill($this->timestampsForStatus($status) + ['status' => $status])->save();
    }

    private function timestampsForStatus(string $status): array
    {
        return match ($status) {
            StoreOrderStatus::CONFIRMED => ['confirmed_at' => now()],
            StoreOrderStatus::DELIVERED => ['delivered_at' => now()],
            StoreOrderStatus::CANCELLED => ['cancelled_at' => now()],
            default => [],
        };
    }
}
