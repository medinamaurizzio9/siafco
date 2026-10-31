<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SimpleInvestmentContract extends Model
{
    public const DRAFT = 'draft';

    public const PENDING_SIGNATURE = 'pending_signature';

    public const SIGNED = 'signed';

    public const VOID = 'void';

    protected $fillable = ['contract_number', 'investor_id', 'product_name_snapshot', 'quantity', 'unit_price_snapshot', 'total_amount', 'currency', 'registered_on', 'signed_on', 'first_schedule_days_snapshot', 'schedule_interval_days_snapshot', 'schedule_projection_count_snapshot', 'schedule_timezone_snapshot', 'status', 'responsible_user_id', 'notes', 'signed_by', 'signed_at', 'voided_by', 'voided_at', 'void_reason'];

    protected function casts(): array
    {
        return ['unit_price_snapshot' => 'decimal:2', 'total_amount' => 'decimal:2', 'registered_on' => 'date', 'signed_on' => 'date', 'signed_at' => 'datetime', 'voided_at' => 'datetime'];
    }

    public function investor(): BelongsTo
    {
        return $this->belongsTo(SimpleInvestmentInvestor::class, 'investor_id');
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function units(): HasMany
    {
        return $this->hasMany(SimpleInvestmentUnit::class, 'contract_id');
    }

    public function documentVersions(): HasMany
    {
        return $this->hasMany(SimpleInvestmentDocumentVersion::class, 'contract_id');
    }

    public function currentDocument(): HasOne
    {
        return $this->hasOne(SimpleInvestmentDocumentVersion::class, 'contract_id')->ofMany('version', 'max');
    }

    public function scheduleNotices(): HasMany
    {
        return $this->hasMany(SimpleInvestmentScheduleNotice::class, 'contract_id');
    }
}
