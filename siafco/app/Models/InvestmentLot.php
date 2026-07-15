<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvestmentLot extends Model
{
    protected $fillable = [
        'investor_id',
        'reservation_id',
        'purchase_number',
        'purchase_date',
        'shares_quantity',
        'share_unit_price',
        'invested_capital',
        'return_percentage',
        'waiting_months',
        'contract_years',
        'maturity_date',
        'contract_end_date',
        'renewal_status',
        'status',
        'payment_method',
        'payment_reference',
        'payment_receipt',
        'settings_snapshot',
        'notes',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'maturity_date' => 'date',
            'contract_end_date' => 'date',
            'approved_at' => 'datetime',
            'settings_snapshot' => 'array',
            'share_unit_price' => 'decimal:2',
            'invested_capital' => 'decimal:2',
            'return_percentage' => 'decimal:2',
        ];
    }

    public function investor()
    {
        return $this->belongsTo(Investor::class);
    }

    public function reservation()
    {
        return $this->belongsTo(ShareReservation::class);
    }

    public function periods()
    {
        return $this->hasMany(InvestmentReturnPeriod::class);
    }
}
