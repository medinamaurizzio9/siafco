<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SimpleInvestmentInvestor extends Model
{
    protected $fillable = ['code', 'full_name', 'ci', 'ci_complement', 'expedition_department', 'identity_key', 'phone', 'email', 'address', 'photo_path', 'registered_on', 'is_active', 'notes', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return ['registered_on' => 'date', 'is_active' => 'boolean'];
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(SimpleInvestmentContract::class, 'investor_id');
    }

    public function portalAccess(): HasOne
    {
        return $this->hasOne(SimpleInvestmentInvestorAccess::class, 'investor_id');
    }

    public function portalNotifications(): HasMany
    {
        return $this->hasMany(SimpleInvestmentPortalNotification::class, 'investor_id');
    }
}
