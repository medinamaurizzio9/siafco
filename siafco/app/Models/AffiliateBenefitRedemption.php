<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use App\Support\BenefitRedemptionStatus;
use App\Support\StoreCodeGenerator;
use Illuminate\Database\Eloquent\Model;

class AffiliateBenefitRedemption extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'affiliate_benefit_id',
        'affiliate_id',
        'status',
        'requested_at',
        'approved_by_user_id',
        'approved_at',
        'used_at',
        'cancelled_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'approved_at' => 'datetime',
            'used_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $redemption): void {
            StoreCodeGenerator::assignUnique($redemption, 'code', [StoreCodeGenerator::class, 'redemptionCode']);
            $redemption->requested_at ??= now();
        });
    }

    public function benefit()
    {
        return $this->belongsTo(AffiliateBenefit::class, 'affiliate_benefit_id');
    }

    public function affiliate()
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function transitionTo(string $status): void
    {
        BenefitRedemptionStatus::assertTransition($this->status, $status);
        $this->forceFill($this->timestampsForStatus($status) + ['status' => $status])->save();
    }

    private function timestampsForStatus(string $status): array
    {
        return match ($status) {
            BenefitRedemptionStatus::APPROVED => ['approved_at' => now()],
            BenefitRedemptionStatus::USED => ['used_at' => now()],
            BenefitRedemptionStatus::CANCELLED => ['cancelled_at' => now()],
            default => [],
        };
    }
}
