<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAffiliateProfileIsComplete
{
    public function handle(Request $request, Closure $next): Response
    {
        $affiliate = $request->user()?->affiliate;

        if (
            $request->user()?->hasRole('afiliado')
            && $affiliate
            && str_ends_with((string) $request->user()->email, '@siafco.com')
            && $this->isIncomplete($affiliate)
            && ! $request->routeIs('affiliate.profile.*', 'password.force.*', 'logout', 'logout.confirm')
        ) {
            return redirect()->route('affiliate.profile.show')
                ->with('warning', 'Completa tu perfil para continuar usando tu panel.');
        }

        return $next($request);
    }

    private function isIncomplete($affiliate): bool
    {
        return blank($affiliate->address)
            || blank($affiliate->birth_date)
            || blank($affiliate->marital_status)
            || blank($affiliate->photo_path);
    }
}
