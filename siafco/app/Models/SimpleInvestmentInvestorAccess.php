<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SimpleInvestmentInvestorAccess extends Model
{
    protected $fillable = ['investor_id', 'password', 'must_change_password', 'is_active', 'failed_attempts', 'locked_until', 'session_version', 'last_login_at', 'password_changed_at', 'enabled_by', 'enabled_at'];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return ['must_change_password' => 'boolean', 'is_active' => 'boolean', 'locked_until' => 'datetime', 'last_login_at' => 'datetime', 'password_changed_at' => 'datetime', 'enabled_at' => 'datetime'];
    }

    public function investor(): BelongsTo
    {
        return $this->belongsTo(SimpleInvestmentInvestor::class, 'investor_id');
    }
}
