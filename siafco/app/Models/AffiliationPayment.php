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
        'registered_by',
        'rejected_by',
        'voided_by',
        'amount',
        'expected_amount',
        'paid_amount',
        'currency',
        'institutional_qr_path',
        'transaction_number',
        'reference_number',
        'voucher_path',
        'payment_date',
        'paid_at',
        'payment_method',
        'bank_name',
        'payer_name',
        'observations',
        'submitted_at',
        'status',
        'source',
        'rejection_reason',
        'confirmed_at',
        'rejected_at',
        'voided_at',
        'void_reason',
        'receipt_number',
    ];

    protected function casts(): array
    {
        return [
            'confirmed_at' => 'datetime',
            'rejected_at' => 'datetime',
            'voided_at' => 'datetime',
            'submitted_at' => 'datetime',
            'payment_date' => 'date',
            'paid_at' => 'datetime',
        ];
    }

    public function affiliate()
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function cashier()
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function registrar()
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    public function rejector()
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function voider()
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function publicRequest() { return $this->belongsTo(PublicAffiliationRequest::class, 'public_affiliation_request_id'); }
    public function plan() { return $this->belongsTo(AffiliationPlan::class, 'affiliation_plan_id'); }
}
