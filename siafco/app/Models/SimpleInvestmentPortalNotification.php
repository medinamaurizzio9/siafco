<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SimpleInvestmentPortalNotification extends Model
{
    protected $fillable = ['investor_id', 'contract_id', 'schedule_notice_id', 'type', 'title', 'body', 'reference_date', 'idempotency_key', 'generated_at', 'displayed_at', 'read_at', 'external_status', 'external_sent_at'];

    protected function casts(): array
    {
        return ['reference_date' => 'date', 'generated_at' => 'datetime', 'displayed_at' => 'datetime', 'read_at' => 'datetime', 'external_sent_at' => 'datetime'];
    }

    public function investor(): BelongsTo
    {
        return $this->belongsTo(SimpleInvestmentInvestor::class, 'investor_id');
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(SimpleInvestmentContract::class, 'contract_id');
    }

    public function scheduleNotice(): BelongsTo
    {
        return $this->belongsTo(SimpleInvestmentScheduleNotice::class, 'schedule_notice_id');
    }
}
