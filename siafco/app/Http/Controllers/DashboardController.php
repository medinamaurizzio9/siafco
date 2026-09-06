<?php

namespace App\Http\Controllers;

use App\Services\DashboardMetricsService;
use App\Services\DashboardActivityPresenter;
use App\Services\SiafcoHealthCheckService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(
        Request $request,
        DashboardMetricsService $dashboard,
        DashboardActivityPresenter $activity,
        SiafcoHealthCheckService $health,
    )
    {
        if ($request->user()?->hasRole(['caja', 'cajero'])) {
            return redirect()->route('cash-deposits.dashboard');
        }

        $metrics = $dashboard->metrics();

        return view('dashboard', [
            'metrics' => $metrics,
            'recentPayments' => $dashboard->recentPayments(),
            'quickActions' => $dashboard->quickActions(request()->user()),
            'recentActivity' => $activity->recent(),
            'healthItems' => $health->statusItems(),
            'healthCheckedAt' => now(),
        ]);
    }
}
