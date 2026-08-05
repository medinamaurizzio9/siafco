<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveStoreAffiliate
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $affiliate = $user?->affiliate;

        if (
            ! $user
            || $user->user_type !== 'affiliate'
            || ! $user->is_active
            || ! $affiliate
            || $affiliate->trashed()
            || $affiliate->status !== 'activo'
        ) {
            return redirect()->route('affiliate.panel')
                ->with('warning', 'La Mini tienda está disponible únicamente para afiliados activos.');
        }

        return $next($request);
    }
}
