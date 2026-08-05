<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DigitalCredential extends Model
{
    protected $fillable = [
        'affiliate_id',
        'status',
        'qr_path',
        'pdf_path',
        'png_path',
        'generated_at',
        'suspended_at',
        'suspended_by',
        'suspension_reason',
        'files_invalidated_at',
    ];

    protected function casts(): array
    {
        return [
            'generated_at' => 'datetime',
            'suspended_at' => 'datetime',
            'files_invalidated_at' => 'datetime',
        ];
    }

    public function affiliate()
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function isActive(): bool
    {
        return ($this->status ?? 'vigente') === 'vigente';
    }
}
