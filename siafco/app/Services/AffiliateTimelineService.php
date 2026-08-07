<?php

namespace App\Services;

use App\Models\Affiliate;
use App\Models\AuditLog;
use App\Support\AuditLogPresenter;

class AffiliateTimelineService
{
    public function forAffiliate(Affiliate $affiliate, int $limit = 30)
    {
        return AuditLog::query()
            ->where(function ($query) use ($affiliate) {
                $query->where('auditable_type', $affiliate::class)->where('auditable_id', $affiliate->id)
                    ->orWhere(fn ($q) => $q->whereJsonContains('metadata->affiliate_id', $affiliate->id));
            })
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (AuditLog $log) => [
                'type' => $log->action,
                'label' => AuditLogPresenter::actionLabel($log->action),
                'occurred_at' => $log->created_at,
                'metadata' => $log->metadata ?? [],
            ]);
    }
}
