<?php

namespace App\Services;

use App\Models\Affiliate;
use App\Models\AffiliationPayment;
use App\Models\CashDeposit;
use App\Models\DigitalCredential;
use App\Models\PublicAffiliationRequest;
use App\Models\Sector;
use App\Models\StoreOrder;
use App\Models\User;
use App\Support\PaymentStatus;
use App\Support\SiafcoDate;
use App\Support\StoreOrderStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

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

    public function quickActions(User $user): array
    {
        return collect([
            ['label' => 'Nueva afiliacion', 'route' => 'affiliates.create', 'permission' => 'affiliates.create', 'icon' => 'user', 'tone' => 'blue'],
            ['label' => 'Registrar pago', 'route' => 'payments.create', 'permission' => 'payments.create', 'icon' => 'credit-card', 'tone' => 'gold'],
            ['label' => 'Credenciales', 'route' => 'credentials.index', 'permission' => 'credentials.view', 'icon' => 'briefcase', 'tone' => 'teal'],
            ['label' => 'Nuevo usuario', 'route' => 'admin.users.create', 'permission' => 'users.create', 'icon' => 'user', 'tone' => 'violet'],
            ['label' => 'Mini tienda', 'route' => 'admin.store.dashboard', 'permission' => 'store.view', 'icon' => 'briefcase', 'tone' => 'cyan'],
            ['label' => 'Reportes', 'route' => 'reports.index', 'permission' => 'reports.view', 'icon' => 'chart', 'tone' => 'slate'],
            ['label' => 'Revisar rendiciones', 'route' => 'cash-deposits.admin.index', 'permission' => 'cash_deposits.view_all', 'icon' => 'credit-card', 'tone' => 'gold'],
        ])->filter(fn (array $action) => $user->hasPermission($action['permission']) && \Illuminate\Support\Facades\Route::has($action['route']))
            ->values()
            ->all();
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private function freshMetrics(): array
    {
        $confirmedStatuses = PaymentStatus::confirmedValues();
        $pendingStatuses = array_values(array_unique([...PaymentStatus::pendingValues(), PaymentStatus::UNDER_REVIEW]));
        $attentionOrderStatuses = [
            StoreOrderStatus::PENDING,
            StoreOrderStatus::RESERVED,
            StoreOrderStatus::WAITING_PAYMENT,
            StoreOrderStatus::PAYMENT_REVIEW,
        ];
        $confirmedPayments = AffiliationPayment::whereIn('status', $confirmedStatuses);
        [$todayStart, $todayEnd] = SiafcoDate::utcDayBounds(null);
        [$trendStart] = SiafcoDate::utcDayBounds(now(SiafcoDate::timezone())->subDays(29)->format('Y-m-d'));
        $confirmedAmount = (clone $confirmedPayments)->get()
            ->sum(fn ($payment) => (float) ($payment->paid_amount ?? $payment->amount));
        $todayRevenue = (clone $confirmedPayments)
            ->whereBetween(DB::raw('COALESCE(confirmed_at, paid_at, created_at)'), [$todayStart, $todayEnd])
            ->get()
            ->sum(fn ($payment) => (float) ($payment->paid_amount ?? $payment->amount));
        $statusDistribution = Affiliate::query()
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($value) => (int) $value)
            ->all();
        $trendDates = collect(range(29, 0))
            ->map(fn (int $days) => now(SiafcoDate::timezone())->startOfDay()->subDays($days))
            ->values();
        $affiliationTrend = $this->trend(
            Affiliate::query()->where('created_at', '>=', $trendStart)->get(['created_at']),
            $trendDates,
            fn ($affiliate) => $affiliate->created_at,
        );
        $revenueTrend = $this->trend(
            AffiliationPayment::query()
                ->whereIn('status', $confirmedStatuses)
                ->where(DB::raw('COALESCE(confirmed_at, paid_at, created_at)'), '>=', $trendStart)
                ->get(['paid_amount', 'amount', 'confirmed_at', 'paid_at', 'created_at']),
            $trendDates,
            fn ($payment) => $payment->confirmed_at ?? $payment->paid_at ?? $payment->created_at,
            fn ($payment) => (float) ($payment->paid_amount ?? $payment->amount),
        );
        $credentialTrend = $this->trend(
            DigitalCredential::query()->where('created_at', '>=', $trendStart)->get(['created_at']),
            $trendDates,
            fn ($credential) => $credential->created_at,
        );
        $storeOrderTrend = $this->trend(
            StoreOrder::query()
                ->whereIn('status', $attentionOrderStatuses)
                ->where('created_at', '>=', $trendStart)
                ->get(['created_at']),
            $trendDates,
            fn ($order) => $order->created_at,
        );
        $accessTrend = $this->trend(
            User::query()
                ->where(fn ($query) => $query->where('user_type', 'internal')->orWhereNull('user_type'))
                ->whereNotNull('last_login_at')
                ->where('last_login_at', '>=', $trendStart)
                ->get(['last_login_at']),
            $trendDates,
            fn ($user) => $user->last_login_at,
        );

        $metrics = [
            'affiliates' => Affiliate::count(),
            'newAffiliates' => Affiliate::whereBetween('created_at', [$todayStart, $todayEnd])->count(),
            'active' => Affiliate::where('status', 'activo')->count(),
            'pendingAffiliations' => PublicAffiliationRequest::whereIn('status', ['submitted', 'payment_submitted', 'under_review', 'pendiente_pago', 'pago_en_revision', 'observed'])->count(),
            'pendingPayments' => AffiliationPayment::whereIn('status', $pendingStatuses)->count(),
            'confirmedPayments' => (clone $confirmedPayments)->count(),
            'todayPayments' => AffiliationPayment::whereBetween('created_at', [$todayStart, $todayEnd])->count(),
            'confirmedAmount' => $confirmedAmount,
            'todayRevenue' => $todayRevenue,
            'rejectedPayments' => AffiliationPayment::whereIn('status', PaymentStatus::rejectedValues())->count(),
            'voidedPayments' => AffiliationPayment::whereIn('status', PaymentStatus::voidedValues())->count(),
            'pendingBalance' => max(
                (float) Affiliate::with('plan')->get()->sum(fn ($affiliate) => (float) ($affiliate->plan?->total_amount ?? 0)) - $confirmedAmount,
                0
            ),
            'credentials' => DigitalCredential::count(),
            'pendingCredentials' => Affiliate::where('status', 'activo')->doesntHave('credential')->count(),
            'activeInternalUsers' => User::query()
                ->where(fn ($query) => $query->where('user_type', 'internal')->orWhereNull('user_type'))
                ->whereIn('role', array_keys(config('internal_roles.labels', [])))
                ->where('is_active', true)
                ->count(),
            'recentAccesses' => User::query()
                ->where(fn ($query) => $query->where('user_type', 'internal')->orWhereNull('user_type'))
                ->whereNotNull('last_login_at')
                ->where('last_login_at', '>=', now(SiafcoDate::timezone())->subDay()->utc())
                ->count(),
            'pendingStoreOrders' => StoreOrder::whereIn('status', $attentionOrderStatuses)->count(),
            'pendingCashDeposits' => CashDeposit::where('status', CashDeposit::UNDER_REVIEW)->count(),
            'pendingCashDepositAmount' => (float) CashDeposit::where('status', CashDeposit::UNDER_REVIEW)->sum('amount'),
            'sectors' => Sector::count(),
            'affiliationTrend' => $affiliationTrend,
            'revenueTrend' => $revenueTrend,
            'credentialTrend' => $credentialTrend,
            'storeOrderTrend' => $storeOrderTrend,
            'accessTrend' => $accessTrend,
            'affiliationStatusDistribution' => $statusDistribution,
        ];

        return $metrics + [
            'active_affiliates' => $metrics['active'],
            'pending_affiliations' => $metrics['pendingAffiliations'],
            'pending_payments' => $metrics['pendingPayments'],
            'today_payments' => $metrics['todayPayments'],
            'confirmed_revenue' => $metrics['confirmedAmount'],
            'issued_credentials' => $metrics['credentials'],
            'pending_credentials' => $metrics['pendingCredentials'],
            'active_internal_users' => $metrics['activeInternalUsers'],
            'recent_accesses' => $metrics['recentAccesses'],
            'pending_store_orders' => $metrics['pendingStoreOrders'],
            'pending_cash_deposits' => $metrics['pendingCashDeposits'],
            'pending_cash_deposit_amount' => $metrics['pendingCashDepositAmount'],
        ];
    }

    private function trend($records, $dates, callable $dateResolver, ?callable $valueResolver = null): array
    {
        $values = $records->groupBy(fn ($record) => Carbon::parse($dateResolver($record))->timezone(SiafcoDate::timezone())->toDateString())
            ->map(fn ($group) => $valueResolver
                ? round($group->sum(fn ($record) => $valueResolver($record)), 2)
                : $group->count()
            );

        return [
            'labels' => $dates->map(fn (Carbon $date) => $date->format('d/m'))->all(),
            'series' => $dates->map(fn (Carbon $date) => $values->get($date->toDateString(), 0))->all(),
        ];
    }
}
