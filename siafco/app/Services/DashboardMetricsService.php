<?php

namespace App\Services;

use App\Models\Affiliate;
use App\Models\AffiliationPayment;
use App\Models\DigitalCredential;
use App\Models\Sector;
use App\Support\PaymentStatus;
use Illuminate\Support\Facades\Cache;

class DashboardMetricsService
{
    public const CACHE_KEY = 'dashboard.metrics';

    public function metrics(): array
    {
        return Cache::remember(self::CACHE_KEY, 60, fn () => $this->freshMetrics());
    }

    public function recentPayments(int $limit = 6)
    {
        return AffiliationPayment::with('affiliate')->latest()->limit($limit)->get();
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private function freshMetrics(): array
    {
        $confirmedStatuses = PaymentStatus::confirmedValues();
        $pendingStatuses = [PaymentStatus::PENDING, 'pendiente', PaymentStatus::UNDER_REVIEW];
        $confirmedAmount = AffiliationPayment::whereIn('status', $confirmedStatuses)->get()
            ->sum(fn ($payment) => (float) ($payment->paid_amount ?? $payment->amount));

        return [
            'affiliates' => Affiliate::count(),
            'newAffiliates' => Affiliate::whereDate('created_at', today())->count(),
            'active' => Affiliate::where('status', 'activo')->count(),
            'pendingPayments' => AffiliationPayment::whereIn('status', $pendingStatuses)->count(),
            'confirmedPayments' => AffiliationPayment::whereIn('status', $confirmedStatuses)->count(),
            'todayPayments' => AffiliationPayment::whereDate('created_at', today())->count(),
            'confirmedAmount' => $confirmedAmount,
            'rejectedPayments' => AffiliationPayment::whereIn('status', PaymentStatus::rejectedValues())->count(),
            'voidedPayments' => AffiliationPayment::whereIn('status', PaymentStatus::voidedValues())->count(),
            'pendingBalance' => max(
                (float) Affiliate::with('plan')->get()->sum(fn ($affiliate) => (float) ($affiliate->plan?->total_amount ?? 0)) - $confirmedAmount,
                0
            ),
            'credentials' => DigitalCredential::count(),
            'sectors' => Sector::count(),
        ];
    }
}
