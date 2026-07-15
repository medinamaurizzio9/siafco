<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Investor extends Model
{
    protected $fillable = [
        'person_id',
        'investor_number',
        'investor_type_id',
        'status',
        'start_date',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return ['start_date' => 'date'];
    }

    public function person()
    {
        return $this->belongsTo(Person::class);
    }

    public function type()
    {
        return $this->belongsTo(InvestorType::class, 'investor_type_id');
    }

    public function reservations()
    {
        return $this->hasMany(ShareReservation::class);
    }

    public function lots()
    {
        return $this->hasMany(InvestmentLot::class);
    }

    public function receipts()
    {
        return $this->hasMany(InvestmentReceipt::class);
    }

    public function activeShares(): int
    {
        return (int) $this->lots()
            ->whereIn('status', ['active_waiting', 'active_earning'])
            ->sum('shares_quantity');
    }
}
