<?php

namespace App\Http\Middleware;

use App\Http\Responses\MobileApiResponse;
use Closure;
use Illuminate\Http\Request;

class CommunicationMobileAccess
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->user()->affiliate?->status !== 'activo') {
            return MobileApiResponse::error('Se requiere afiliación activa.', 403);
        }
        $response = $next($request);
        if ($response->getStatusCode() === 404) {
            return MobileApiResponse::error('Recurso no disponible.', 404);
        }

        return $response;
    }
}
