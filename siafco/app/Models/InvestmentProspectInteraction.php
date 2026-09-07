<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvestmentProspectInteraction extends Model
{
    protected $fillable = ['investment_prospect_id', 'advisor_id', 'user_id', 'type', 'channel', 'notes', 'occurred_at', 'next_follow_up_at'];
    protected function casts(): array { return ['occurred_at' => 'datetime', 'next_follow_up_at' => 'datetime']; }
    public function advisor() { return $this->belongsTo(InvestmentAdvisor::class); }
    public function user() { return $this->belongsTo(User::class); }
}
