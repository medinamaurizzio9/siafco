<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AffiliationPayment extends Model
{
    protected $fillable = [
        'affiliate_id',
        'confirmed_by',
        'amount',
        'institutional_qr_path',
        'transaction_number',
        'voucher_path',
        'status',
        'rejection_reason',
        'confirmed_at',
    ];

    protected function casts(): array
    {
        return ['confirmed_at' => 'datetime'];
    }

    public function affiliate()
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function cashier()
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }
}
