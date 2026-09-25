<?php

namespace App\Services\Store;

use App\Models\StoreOrder;
use App\Support\SiafcoDate;
use App\Support\StoreOrderStatus;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class StoreAdminReportService
{
    public const SALE_EXCLUDED_STATUSES = [
        StoreOrderStatus::CANCELLED,
        StoreOrderStatus::REJECTED,
    ];

    public const ATTENTION_STATUSES = [
        StoreOrderStatus::PENDING,
        StoreOrderStatus::RESERVED,
        StoreOrderStatus::WAITING_PAYMENT,
        StoreOrderStatus::PAYMENT_REVIEW,
    ];

    public function dashboard(): array
    {
        $salesQuery = $this->salesQuery();
        [$todayStart, $todayEnd] = SiafcoDate::utcDayBounds(null);

        return [
            'confirmed_sales' => (clone $salesQuery)->count(),
            'sold_amount' => (float) (clone $salesQuery)->sum('total'),
            'pending_orders' => StoreOrder::query()->whereIn('status', self::ATTENTION_STATUSES)->count(),
            'payment_review_orders' => StoreOrder::query()->where('status', StoreOrderStatus::PAYMENT_REVIEW)->count(),
            'today_orders' => StoreOrder::query()->whereBetween('created_at', [$todayStart, $todayEnd])->count(),
        ];
    }

    public function recentOrders(int $limit = 8)
    {
        return StoreOrder::query()
            ->with(['affiliate', 'items'])
            ->latest()
            ->limit($limit)
            ->get();
    }

    public function salesSummary(array $filters = []): array
    {
        $salesQuery = $this->filteredSalesQuery($filters);
        [$todayStart, $todayEnd] = SiafcoDate::utcDayBounds(null);
        $todaySalesQuery = $this->salesQuery()->whereBetween('confirmed_at', [$todayStart, $todayEnd]);

        return [
            'registered_sales' => (clone $salesQuery)->count(),
            'total_amount' => (float) (clone $salesQuery)->sum('total'),
            'today_sales' => (clone $todaySalesQuery)->count(),
        ];
    }

    public function sales(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->filteredSalesQuery($filters)
            ->with(['affiliate', 'items'])
            ->latest('confirmed_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function salesQuery(): Builder
    {
        return StoreOrder::query()
            ->whereNotNull('confirmed_at')
            ->whereNotIn('status', self::SALE_EXCLUDED_STATUSES);
    }

    private function filteredSalesQuery(array $filters): Builder
    {
        return $this->salesQuery()
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $nested) use ($search): void {
                    $nested->where('code', 'like', "%{$search}%")
                        ->orWhereHas('affiliate', fn (Builder $affiliate) => $affiliate->where('full_name', 'like', "%{$search}%"));
                });
            })
            ->when($filters['delivery_method'] ?? null, fn (Builder $query, string $method) => $query->where('delivery_method', $method))
            ->when($filters['from'] ?? null, function (Builder $query, string $from): void {
                [$fromUtc] = SiafcoDate::utcDayBounds($from);
                $query->where('confirmed_at', '>=', $fromUtc);
            })
            ->when($filters['to'] ?? null, function (Builder $query, string $to): void {
                [, $toUtc] = SiafcoDate::utcDayBounds($to);
                $query->where('confirmed_at', '<=', $toUtc);
            });
    }
}
