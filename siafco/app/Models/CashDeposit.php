<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CashDeposit extends Model
{
    public const UNDER_REVIEW = 'under_review';
    public const CONFIRMED = 'confirmed';
    public const REJECTED = 'rejected';

    protected $fillable = [
        'public_id', 'registered_by', 'amount', 'currency', 'deposited_at',
        'transaction_number', 'voucher_path', 'observations', 'status',
        'reviewed_by', 'reviewed_at', 'confirmed_at', 'rejection_reason',
    ];

    protected static function booted(): void
    {
        static::creating(function (CashDeposit $deposit): void {
            $deposit->public_id ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'deposited_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'confirmed_at' => 'datetime',
        ];
    }

    public function registrar() { return $this->belongsTo(User::class, 'registered_by'); }
    public function reviewer() { return $this->belongsTo(User::class, 'reviewed_by'); }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
