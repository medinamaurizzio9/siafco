<?php

namespace App\Models;

use App\Support\InvestmentProspectStatus;
use Illuminate\Database\Eloquent\Model;

class InvestmentProspect extends Model
{
    protected $fillable = [
        'prospect_number',
        'public_id',
        'full_name',
        'phone',
        'phone_normalized',
        'requested_shares',
        'preferred_contact_method',
        'original_advisor_id',
        'current_advisor_id',
        'status',
        'source',
        'service_rating',
        'service_rating_at',
        'service_rating_source',
        'captured_at',
        'contact_consent_at',
        'capture_ip',
        'capture_user_agent',
        'investor_id',
        'converted_at',
        'last_interaction_at',
        'next_follow_up_at',
    ];

    protected $hidden = ['public_id', 'capture_ip', 'capture_user_agent'];

    protected function casts(): array
    {
        return [
            'requested_shares' => 'integer',
            'captured_at' => 'datetime',
            'service_rating_at' => 'datetime',
            'contact_consent_at' => 'datetime',
            'converted_at' => 'datetime',
            'last_interaction_at' => 'datetime',
            'next_follow_up_at' => 'datetime',
        ];
    }

    public function originalAdvisor()
    {
        return $this->belongsTo(InvestmentAdvisor::class, 'original_advisor_id');
    }

    public function currentAdvisor()
    {
        return $this->belongsTo(InvestmentAdvisor::class, 'current_advisor_id');
    }

    public function investor()
    {
        return $this->belongsTo(Investor::class);
    }

    public function interactions() { return $this->hasMany(InvestmentProspectInteraction::class); }
    public function statusHistory() { return $this->hasMany(InvestmentProspectStatusHistory::class); }
    public function assignments() { return $this->hasMany(InvestmentProspectAssignment::class); }

    public function isCaptured(): bool
    {
        return $this->status === InvestmentProspectStatus::CAPTURED;
    }

    public function isInTransition(): bool
    {
        return $this->status === InvestmentProspectStatus::IN_TRANSITION;
    }

    public function isClosed(): bool
    {
        return $this->status === InvestmentProspectStatus::CLOSED;
    }

    public function isLost(): bool
    {
        return $this->status === InvestmentProspectStatus::LOST;
    }

    public function isConverted(): bool
    {
        return $this->investor_id !== null && $this->converted_at !== null;
    }
}
