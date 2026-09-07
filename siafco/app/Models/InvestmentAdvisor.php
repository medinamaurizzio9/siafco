<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvestmentAdvisor extends Model
{
    protected $fillable = [
        'advisor_number',
        'public_id',
        'public_token',
        'full_name',
        'phone',
        'email',
        'photo_path',
        'user_id',
        'is_active',
        'created_by',
    ];

    protected $hidden = ['public_token'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function originalProspects()
    {
        return $this->hasMany(InvestmentProspect::class, 'original_advisor_id');
    }

    public function currentProspects()
    {
        return $this->hasMany(InvestmentProspect::class, 'current_advisor_id');
    }

    public function access()
    {
        return $this->hasOne(InvestmentAdvisorAccess::class);
    }

    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function photoUrl(): ?string
    {
        return $this->photo_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($this->photo_path)
            ? \Illuminate\Support\Facades\Storage::disk('public')->url($this->photo_path) : null;
    }

    public function initials(): string
    {
        return str($this->full_name)
            ->squish()
            ->explode(' ')
            ->filter()
            ->take(2)
            ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');
    }

    public function qrPath(): string
    {
        return "investments/advisors/qr/{$this->public_id}.png";
    }
}
