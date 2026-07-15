<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvestmentReturnPeriod extends Model
{
    protected $fillable = [
        'investment_lot_id',
        'period_number',
        'period_year',
        'period_month',
        'due_date',
        'invested_capital_snapshot',
        'return_percentage_snapshot',
        'base_return_amount',
        'production_bonus_amount',
        'extra_concept',
        'extra_amount',
        'deductions_amount',
        'total_amount',
        'status',
        'prepared_by',
        'prepared_at',
        'approved_by',
        'approved_at',
        'paid_by',
        'paid_at',
        'receipt_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'prepared_at' => 'datetime',
            'approved_at' => 'datetime',
            'paid_at' => 'datetime',
            'invested_capital_snapshot' => 'decimal:2',
            'return_percentage_snapshot' => 'decimal:2',
            'base_return_amount' => 'decimal:2',
            'production_bonus_amount' => 'decimal:2',
            'extra_amount' => 'decimal:2',
            'deductions_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
        ];
    }

    public function lot()
    {
        return $this->belongsTo(InvestmentLot::class, 'investment_lot_id');
    }

    public function receipt()
    {
        return $this->belongsTo(InvestmentReceipt::class, 'receipt_id');
    }
}
