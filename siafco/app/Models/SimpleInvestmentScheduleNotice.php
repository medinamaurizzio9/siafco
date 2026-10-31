<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SimpleInvestmentScheduleNotice extends Model
{
    protected $fillable = ['contract_id', 'schedule_index', 'scheduled_on', 'notice_kind', 'idempotency_key', 'prepared_at', 'channel', 'sent_at', 'delivery_confirmed_at'];

    protected function casts(): array
    {
        return ['scheduled_on' => 'date', 'prepared_at' => 'datetime', 'sent_at' => 'datetime', 'delivery_confirmed_at' => 'datetime'];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(SimpleInvestmentContract::class, 'contract_id');
    }

    public function statusLabel(): string
    {
        if ($this->delivery_confirmed_at) {
            return 'Entrega confirmada';
        }

        if ($this->sent_at) {
            return 'Aviso enviado';
        }

        return 'Aviso preparado, canal pendiente';
    }
}
