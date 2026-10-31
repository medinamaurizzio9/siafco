<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SimpleInvestmentDocumentVersion extends Model
{
    public const UPLOADED = 'uploaded';

    public const VERIFIED = 'verified';

    public const OBSERVED = 'observed';

    protected $fillable = ['contract_id', 'version', 'original_name', 'path', 'mime', 'size_bytes', 'sha256', 'status', 'uploaded_by', 'uploaded_at', 'reviewed_by', 'reviewed_at', 'review_notes'];

    protected $hidden = ['path', 'sha256'];

    protected function casts(): array
    {
        return ['size_bytes' => 'integer', 'uploaded_at' => 'datetime', 'reviewed_at' => 'datetime'];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(SimpleInvestmentContract::class, 'contract_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
