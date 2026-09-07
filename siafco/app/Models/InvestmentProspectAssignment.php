<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvestmentProspectAssignment extends Model
{
    protected $fillable = ['investment_prospect_id', 'from_advisor_id', 'to_advisor_id', 'assigned_by_user_id', 'assigned_at', 'reason'];
    protected function casts(): array { return ['assigned_at' => 'datetime']; }
    public function fromAdvisor() { return $this->belongsTo(InvestmentAdvisor::class, 'from_advisor_id'); }
    public function toAdvisor() { return $this->belongsTo(InvestmentAdvisor::class, 'to_advisor_id'); }
    public function assignedBy() { return $this->belongsTo(User::class, 'assigned_by_user_id'); }
}
