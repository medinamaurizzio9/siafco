<?php

namespace App\Http\Middleware;

use App\Models\SimpleInvestmentInvestorAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSimpleInvestorAuthenticated
{
    public function handle(Request $request, Closure $next, bool $allowPasswordChange = false): Response
    {
        $access = SimpleInvestmentInvestorAccess::query()->with('investor')->find($request->session()->get('simple_investor_access_id'));
        if (! $access || ! $access->is_active || ! $access->investor?->is_active || (int) $request->session()->get('simple_investor_session_version') !== $access->session_version) {
            $request->session()->forget(['simple_investor_access_id', 'simple_investor_session_version']);

            return redirect()->route('simple-investor.login');
        }

        $request->attributes->set('simpleInvestorAccess', $access);
        $request->attributes->set('simpleInvestor', $access->investor);
        if ($access->must_change_password && ! $allowPasswordChange) {
            return redirect()->route('simple-investor.password.edit');
        }

        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store, private, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'same-origin');

        return $response;
    }
}
