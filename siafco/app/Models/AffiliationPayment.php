<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AffiliationPayment extends Model
{
    protected $fillable = [
        'affiliate_id',
        'public_affiliation_request_id',
        'affiliation_plan_id',
        'confirmed_by',
        'amount',
        'expected_amount',
        'paid_amount',
        'institutional_qr_path',
        'transaction_number',
        'voucher_path',
        'payment_date',
        'payment_method',
        'bank_name',
        'payer_name',
        'observations',
        'submitted_at',
        'status',
        'rejection_reason',
        'confirmed_at',
    ];

    protected function casts(): array
    {
        return ['confirmed_at' => 'datetime', 'submitted_at' => 'datetime', 'payment_date' => 'date'];
    }

    public function affiliate()
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function cashier()
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function publicRequest() { return $this->belongsTo(PublicAffiliationRequest::class, 'public_affiliation_request_id'); }
    public function plan() { return $this->belongsTo(AffiliationPlan::class, 'affiliation_plan_id'); }
}
