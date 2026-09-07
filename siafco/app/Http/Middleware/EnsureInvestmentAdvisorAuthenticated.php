<?php

namespace App\Http\Middleware;

use App\Services\InvestmentAdvisorAuthService;
use Closure;
use Illuminate\Http\Request;

class EnsureInvestmentAdvisorAuthenticated
{
    public function handle(Request $request, Closure $next, bool $allowPinChange = false)
    {
        $auth=app(InvestmentAdvisorAuthService::class); $access=$auth->access($request);
        if(!$access || !$access->is_enabled || !$access->advisor?->is_active) { $auth->logout($request); return redirect()->route('investment-crm.advisor.login'); }
        if($access->must_change_pin && !$allowPinChange) return redirect()->route('investment-crm.advisor.pin.edit');
        return $next($request);
    }
}
