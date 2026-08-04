<?php

namespace App\Http\Middleware;

use App\Http\Responses\MobileApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveMobileStoreAffiliate
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $affiliate = $user?->affiliate;

        if (! $user || $user->user_type !== 'affiliate' || $user->role !== 'afiliado' || ! $affiliate) {
            return MobileApiResponse::error('La tienda movil solo esta disponible para afiliados.', 403);
        }

        if (! $user->is_active || $affiliate->trashed() || $affiliate->status !== 'activo') {
            return MobileApiResponse::error('La tienda movil solo esta disponible para afiliados activos.', 403, [
                'status' => [$affiliate->status],
            ]);
        }

        return $next($request);
    }
}
