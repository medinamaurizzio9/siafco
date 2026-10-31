<?php

namespace App\Http\Controllers;

use App\Models\SimpleInvestmentContract;
use App\Models\SimpleInvestmentInvestor;
use App\Models\SimpleInvestmentSetting;
use App\Services\SimpleInvestmentScheduleService;

class SimpleInvestmentDashboardController extends Controller
{
    public function __invoke(SimpleInvestmentScheduleService $schedule)
    {
        $signedContracts = SimpleInvestmentContract::query()
            ->where('status', SimpleInvestmentContract::SIGNED)
            ->with(['investor', 'scheduleNotices'])
            ->get();

        return view('simple-investments.dashboard', ['settings' => SimpleInvestmentSetting::current(), 'metrics' => [
            'active_investors' => SimpleInvestmentInvestor::where('is_active', true)->count(),
            'draft_contracts' => SimpleInvestmentContract::whereIn('status', [SimpleInvestmentContract::DRAFT, SimpleInvestmentContract::PENDING_SIGNATURE])->count(),
            'signed_contracts' => SimpleInvestmentContract::where('status', SimpleInvestmentContract::SIGNED)->count(),
            'issued_units' => SimpleInvestmentContract::where('status', SimpleInvestmentContract::SIGNED)->sum('quantity'),
        ], 'upcomingDates' => $schedule->dashboardDates($signedContracts)]);
    }
}
