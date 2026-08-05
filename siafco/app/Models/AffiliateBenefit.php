<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AffiliateBenefit extends Model
{
    protected $attributes = [
        'benefit_type' => 'informational',
        'redeemable' => false,
    ];

    protected $fillable = [
        'slug', 'title', 'description', 'icon', 'route_name', 'external_url',
        'benefit_type', 'redeemable', 'starts_at', 'ends_at', 'rules',
        'redemption_limit_per_affiliate', 'active', 'visible_when_pending', 'order',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'visible_when_pending' => 'boolean',
            'redeemable' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'rules' => 'array',
            'redemption_limit_per_affiliate' => 'integer',
        ];
    }

    public function redemptions()
    {
        return $this->hasMany(AffiliateBenefitRedemption::class);
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
