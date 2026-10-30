<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RrhhSale extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_VOIDED = 'voided';

    protected $fillable = ['number', 'sold_on', 'worker_id', 'status', 'subtotal', 'total', 'commission_total', 'notes', 'created_by', 'confirmed_by', 'confirmed_at', 'voided_by', 'voided_at', 'void_reason'];

    protected function casts(): array
    {
        return ['sold_on' => 'date', 'subtotal' => 'decimal:2', 'total' => 'decimal:2', 'commission_total' => 'decimal:2', 'confirmed_at' => 'datetime', 'voided_at' => 'datetime'];
    }

    public function worker()
    {
        return $this->belongsTo(RrhhWorker::class, 'worker_id');
    }

    public function items()
    {
        return $this->hasMany(RrhhSaleItem::class, 'sale_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function confirmer()
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function voider()
    {
        return $this->belongsTo(User::class, 'voided_by');
    }
}
