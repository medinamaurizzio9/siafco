<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PermissionMiddleware
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (! $user || ! collect($permissions)->contains(fn (string $permission) => $user->hasPermission($permission))) {
            abort(403, 'No tiene permisos para acceder a esta seccion.');
        }

        return $next($request);
    }
}
