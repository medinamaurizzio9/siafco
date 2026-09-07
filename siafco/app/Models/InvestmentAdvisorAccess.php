<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvestmentAdvisorAccess extends Model
{
    protected $fillable = ['investment_advisor_id', 'login_code', 'pin_hash', 'is_enabled', 'must_change_pin', 'failed_attempts', 'locked_until', 'last_login_at', 'last_login_ip', 'pin_changed_at'];
    protected $hidden = ['pin_hash'];

    protected function casts(): array
    {
        return ['is_enabled' => 'boolean', 'must_change_pin' => 'boolean', 'locked_until' => 'datetime', 'last_login_at' => 'datetime', 'pin_changed_at' => 'datetime'];
    }

    public function advisor() { return $this->belongsTo(InvestmentAdvisor::class, 'investment_advisor_id'); }
}
