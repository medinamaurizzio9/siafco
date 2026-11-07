<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Communication extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['audience' => 'array', 'requires_confirmation' => 'boolean', 'publish_at' => 'datetime', 'expires_at' => 'datetime'];
}
