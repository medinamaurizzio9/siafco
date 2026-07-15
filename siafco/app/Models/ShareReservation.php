<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShareReservation extends Model
{
    protected $fillable = [
        'investor_id',
        'shares_quantity',
        'share_unit_price',
        'total_amount',
        'reservation_date',
        'expiration_date',
        'amount_paid',
        'payment_reference',
        'payment_method',
        'status',
        'notes',
        'closure_reason',
        'support_document',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'reservation_date' => 'date',
            'expiration_date' => 'date',
            'approved_at' => 'datetime',
            'share_unit_price' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'amount_paid' => 'decimal:2',
        ];
    }

    public function investor()
    {
        return $this->belongsTo(Investor::class);
    }
}
