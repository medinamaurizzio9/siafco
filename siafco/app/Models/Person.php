<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Person extends Model
{
    protected $fillable = [
        'full_name',
        'ci',
        'ci_complement',
        'issued_in',
        'phone',
        'email',
        'address',
        'birth_date',
        'marital_status',
        'photo',
    ];

    protected function casts(): array
    {
        return ['birth_date' => 'date'];
    }

    public function affiliate()
    {
        return $this->hasOne(Affiliate::class);
    }

    public function investor()
    {
        return $this->hasOne(Investor::class);
    }

    public function user()
    {
        return $this->hasOne(User::class);
    }
}
