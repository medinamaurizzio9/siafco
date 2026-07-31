<?php

namespace App\Http\Middleware;

use App\Http\Responses\MobileApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMobileAffiliateAccess
{
    private const ALLOWED_STATUSES = ['pendiente_pago', 'pago_en_revision', 'observado', 'activo'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $affiliate = $user?->affiliate;

        if (! $user || $user->user_type !== 'affiliate' || $user->role !== 'afiliado' || ! $affiliate) {
            return MobileApiResponse::error('La API móvil solo está disponible para afiliados.', 403);
        }

        if (! $user->is_active) {
            return MobileApiResponse::error('La cuenta no está activa.', 403);
        }

        if (! in_array($affiliate->status, self::ALLOWED_STATUSES, true)) {
            return MobileApiResponse::error('El estado de afiliación no permite acceso móvil.', 403, [
                'status' => [$affiliate->status],
            ]);
        }

        return $next($request);
    }
}
