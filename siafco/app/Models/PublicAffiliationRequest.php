<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PublicAffiliationRequest extends Model
{
    protected $fillable = [
        'person_id', 'affiliate_id', 'user_id', 'sector_id', 'affiliation_plan_id',
        'public_token', 'request_code', 'amount_due', 'status', 'submitted_at',
        'payment_submitted_at', 'reviewed_at', 'reviewed_by', 'rejection_reason',
        'observations', 'ip_address', 'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'amount_due' => 'decimal:2',
            'submitted_at' => 'datetime',
            'payment_submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'public_token';
    }

    public function person() { return $this->belongsTo(Person::class); }
    public function affiliate() { return $this->belongsTo(Affiliate::class); }
    public function user() { return $this->belongsTo(User::class); }
    public function sector() { return $this->belongsTo(Sector::class); }
    public function plan() { return $this->belongsTo(AffiliationPlan::class, 'affiliation_plan_id'); }
    public function reviewer() { return $this->belongsTo(User::class, 'reviewed_by'); }
    public function payment() { return $this->hasOne(AffiliationPayment::class); }
}
