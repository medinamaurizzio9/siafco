<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvestorType extends Model
{
    protected $fillable = ['name', 'shares_quantity', 'description', 'active', 'order'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function investors()
    {
        return $this->hasMany(Investor::class);
    }
}
