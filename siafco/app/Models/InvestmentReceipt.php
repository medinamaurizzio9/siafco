<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvestmentReceipt extends Model
{
    protected $fillable = [
        'receipt_number',
        'investor_id',
        'investment_lot_id',
        'return_period_id',
        'issue_date',
        'company_name_snapshot',
        'company_nit_snapshot',
        'company_address_snapshot',
        'company_phone_snapshot',
        'company_email_snapshot',
        'logo_path_snapshot',
        'investor_name_snapshot',
        'investor_ci_snapshot',
        'investor_number_snapshot',
        'purchase_number_snapshot',
        'shares_quantity_snapshot',
        'share_unit_price_snapshot',
        'invested_capital_snapshot',
        'return_percentage_snapshot',
        'base_return_amount',
        'production_bonus_amount',
        'extra_concept',
        'extra_amount',
        'deductions_amount',
        'total_amount',
        'payment_method',
        'payment_reference',
        'notes',
        'verification_token',
        'status',
        'approved_by',
        'approved_at',
        'issued_by',
        'issued_at',
        'voided_by',
        'voided_at',
        'void_reason',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'approved_at' => 'datetime',
            'issued_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    public function investor()
    {
        return $this->belongsTo(Investor::class);
    }

    public function lot()
    {
        return $this->belongsTo(InvestmentLot::class, 'investment_lot_id');
    }

    public function period()
    {
        return $this->belongsTo(InvestmentReturnPeriod::class, 'return_period_id');
    }
}
