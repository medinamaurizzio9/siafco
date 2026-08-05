<?php

namespace App\Listeners;

use App\Services\DashboardMetricsService;

class UpdateDashboardStatisticsListener
{
    public function __construct(private DashboardMetricsService $metrics) {}

    public function handle(object $event): void
    {
        $this->metrics->forget();
    }
}
