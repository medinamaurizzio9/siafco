<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestrictPendingAffiliateAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $affiliate = $user?->role === 'afiliado' ? $user->affiliate : null;

        if (
            $affiliate
            && $affiliate->status !== 'activo'
            && ! $request->routeIs('affiliate.panel', 'affiliate.profile.*', 'password.force.*', 'logout', 'payments.proof')
        ) {
            return redirect()->route('affiliate.panel')
                ->with('status', 'Tu acceso está limitado al seguimiento mientras Secretaría revisa tu afiliación.');
        }

        return $next($request);
    }
}
