<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RrhhAdjustment extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPLIED = 'applied';

    public const STATUS_VOIDED = 'voided';

    public const ORIGIN_MANUAL = 'manual';

    public const ORIGIN_VOIDED_SALE = 'voided_sale';

    public const REVIEW_PENDING = 'pending';

    public const REVIEW_APPROVED = 'approved';

    public const REVIEW_REJECTED = 'rejected';

    protected $fillable = ['worker_id', 'applies_on', 'direction', 'concept', 'amount', 'notes', 'status', 'origin', 'review_status', 'source_sale_id', 'submission_key', 'created_by', 'updated_by', 'reviewed_by', 'reviewed_at', 'review_notes'];

    protected function casts(): array
    {
        return ['applies_on' => 'date', 'amount' => 'decimal:2', 'reviewed_at' => 'datetime'];
    }

    public function worker()
    {
        return $this->belongsTo(RrhhWorker::class, 'worker_id');
    }

    public function sourceSale()
    {
        return $this->belongsTo(RrhhSale::class, 'source_sale_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function payLine()
    {
        return $this->hasOne(RrhhPayLine::class, 'adjustment_id');
    }
}
