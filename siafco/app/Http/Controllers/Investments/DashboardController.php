<?php

namespace App\Http\Controllers\Investments;

use App\Http\Controllers\Controller;
use App\Models\InvestmentLot;
use App\Models\InvestmentReceipt;
use App\Models\InvestmentReturnPeriod;
use App\Models\Investor;
use App\Models\ShareReservation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $metrics = Cache::remember('investments.dashboard.metrics', 300, fn () => [
            'total_investors' => Investor::count(),
            'active_investors' => Investor::where('status', 'active')->count(),
            'sold_shares' => InvestmentLot::whereIn('status', ['active_waiting', 'active_earning', 'completed'])->sum('shares_quantity'),
            'invested_capital' => InvestmentLot::whereIn('status', ['active_waiting', 'active_earning', 'completed'])->sum('invested_capital'),
            'active_reservations' => ShareReservation::where('status', 'active')->count(),
            'expired_reservations' => ShareReservation::where('status', 'expired')->count(),
            'waiting_lots' => InvestmentLot::where('status', 'active_waiting')->count(),
            'earning_lots' => InvestmentLot::where('status', 'active_earning')->count(),
            'month_returns' => InvestmentReturnPeriod::whereYear('due_date', now()->year)->whereMonth('due_date', now()->month)->sum('total_amount'),
            'pending_returns' => InvestmentReturnPeriod::whereIn('status', ['pending', 'prepared', 'pending_approval'])->count(),
            'month_bonus' => InvestmentReturnPeriod::whereYear('due_date', now()->year)->whereMonth('due_date', now()->month)->sum('production_bonus_amount'),
            'issued_receipts' => InvestmentReceipt::whereIn('status', ['issued', 'paid'])->count(),
            'contracts_ending' => InvestmentLot::whereDate('contract_end_date', '<=', now()->addDays(60))->whereIn('status', ['active_waiting', 'active_earning'])->count(),
        ]);

        $monthExpression = DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', purchase_date)"
            : "DATE_FORMAT(purchase_date, '%Y-%m')";

        $salesByMonth = InvestmentLot::query()
            ->selectRaw("{$monthExpression} as month, SUM(invested_capital) as total")
            ->where('purchase_date', '>=', now()->subMonths(6)->startOfMonth())
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return view('investments.dashboard', compact('metrics', 'salesByMonth'));
    }
}
