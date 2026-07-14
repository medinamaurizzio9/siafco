<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sector extends Model
{
    protected $fillable = ['name', 'code', 'regional', 'institution', 'current_sequence', 'is_active'];

    public function affiliates()
    {
        return $this->hasMany(Affiliate::class);
    }
}
