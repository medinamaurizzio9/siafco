<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AffiliateBenefit extends Model
{
    protected $fillable = [
        'title', 'description', 'icon', 'route_name', 'external_url',
        'active', 'visible_when_pending', 'order',
    ];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'visible_when_pending' => 'boolean'];
    }
}
