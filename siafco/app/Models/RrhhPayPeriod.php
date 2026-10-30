<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RrhhPayPeriod extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_PAID = 'paid';

    public const STATUS_VOIDED = 'voided';

    protected $fillable = ['number', 'worker_id', 'date_from', 'date_to', 'status', 'recognized_days', 'base_amount', 'commission_total', 'adjustment_total', 'calculated_total', 'paid_amount', 'payment_reference', 'calculated_by', 'calculated_at', 'closed_by', 'closed_at', 'paid_by', 'paid_at', 'voided_by', 'voided_at', 'void_reason'];

    protected function casts(): array
    {
        return ['date_from' => 'date', 'date_to' => 'date', 'base_amount' => 'decimal:2', 'commission_total' => 'decimal:2', 'adjustment_total' => 'decimal:2', 'calculated_total' => 'decimal:2', 'paid_amount' => 'decimal:2', 'calculated_at' => 'datetime', 'closed_at' => 'datetime', 'paid_at' => 'datetime', 'voided_at' => 'datetime'];
    }

    public function worker()
    {
        return $this->belongsTo(RrhhWorker::class, 'worker_id');
    }

    public function lines()
    {
        return $this->hasMany(RrhhPayLine::class, 'pay_period_id');
    }

    public function calculator()
    {
        return $this->belongsTo(User::class, 'calculated_by');
    }

    public function closer()
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function payer()
    {
        return $this->belongsTo(User::class, 'paid_by');
    }
}
