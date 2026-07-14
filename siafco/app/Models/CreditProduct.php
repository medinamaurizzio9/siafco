<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreditProduct extends Model
{
    protected $fillable = ['name', 'annual_rate', 'is_active'];
}
