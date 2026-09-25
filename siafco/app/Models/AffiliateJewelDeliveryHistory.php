<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AffiliateJewelDeliveryHistory extends Model
{
    public const ACTION_DELIVERED = 'delivered';
    public const ACTION_REVERTED = 'reverted';
    public const ACTION_DATE_CHANGED = 'date_changed';

    protected $fillable = [
        'affiliate_id',
        'action',
        'delivery_date_before',
        'delivery_date_after',
        'performed_by',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'delivery_date_before' => 'datetime',
            'delivery_date_after' => 'datetime',
        ];
    }

    public function affiliate()
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function performer()
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
