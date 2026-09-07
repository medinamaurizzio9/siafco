<?php

namespace App\Http\Controllers\Investments;

use App\Http\Controllers\Controller;
use App\Http\Requests\Investments\StoreInvestmentProspectPublicRequest;
use App\Models\InvestmentAdvisor;
use App\Services\InvestmentProspectService;

class InvestmentAdvisorPublicController extends Controller
{
    public function showByToken(string $token)
    {
        $advisor = InvestmentAdvisor::query()
            ->where('public_token', $token)
            ->where('is_active', true)
            ->firstOrFail();

        return view('investments.advisors.public', [
            'advisor' => $advisor,
            'result' => session('investment_prospect_result'),
        ]);
    }

    public function store(
        StoreInvestmentProspectPublicRequest $request,
        string $token,
        InvestmentProspectService $service
    ) {
        $advisor = InvestmentAdvisor::query()
            ->where('public_token', $token)
            ->where('is_active', true)
            ->firstOrFail();

        $result = $service->createFromAdvisorQr(
            $advisor,
            $request->validated(),
            $request->ip(),
            $request->userAgent()
        );

        session()->flash('investment_prospect_result', [
            'status' => $result['status'],
            'prospect_number' => $result['status'] === 'created'
                ? $result['prospect']->prospect_number
                : null,
        ]);

        return redirect()->route('investments.advisors.public', ['token' => $token]);
    }
}
