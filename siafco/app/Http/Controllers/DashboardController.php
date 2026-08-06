<?php

namespace App\Http\Controllers;

use App\Services\DashboardMetricsService;
use App\Services\DashboardActivityPresenter;
use App\Services\SiafcoHealthCheckService;

class DashboardController extends Controller
{
    public function index(
        DashboardMetricsService $dashboard,
        DashboardActivityPresenter $activity,
        SiafcoHealthCheckService $health,
    )
    {
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
