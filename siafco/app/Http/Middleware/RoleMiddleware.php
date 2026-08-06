<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        $internalRoles = array_keys(config('internal_roles.labels', []));

        if ($user?->isInternal()
            && $user->hasRole('superadministrador')
            && array_intersect($roles, $internalRoles) !== []) {
            return $next($request);
        }

        if (! $user || ! $user->hasRole($roles)) {
            abort(403, 'No tiene permisos para acceder a esta seccion.');
        }

        return $next($request);
    }
}
