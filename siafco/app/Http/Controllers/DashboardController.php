<?php

namespace App\Http\Controllers;

use App\Services\DashboardMetricsService;

class DashboardController extends Controller
{
    public function index(DashboardMetricsService $dashboard)
    {
        return view('dashboard', [
            'metrics' => $dashboard->metrics(),
            'recentPayments' => $dashboard->recentPayments(),
        ]);
    }
}
