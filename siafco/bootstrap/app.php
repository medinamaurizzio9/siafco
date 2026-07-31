<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use App\Http\Responses\MobileApiResponse;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        if (env('SIAFCO_MEASURE_PERFORMANCE', false)) {
            $middleware->web(append: [
                \App\Http\Middleware\MeasureRequestPerformance::class,
            ]);
        }

        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'affiliate.active-access' => \App\Http\Middleware\RestrictPendingAffiliateAccess::class,
            'password.changed' => \App\Http\Middleware\EnsurePasswordIsChanged::class,
            'mobile.affiliate' => \App\Http\Middleware\EnsureMobileAffiliateAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (AuthenticationException $exception, $request) {
            if ($request->is('api/mobile/v1/*')) {
                return MobileApiResponse::error('No autenticado.', 401);
            }

            return null;
        });

        $exceptions->render(function (ThrottleRequestsException $exception, $request) {
            if ($request->is('api/mobile/v1/*')) {
                return MobileApiResponse::error('Demasiados intentos. Intenta nuevamente más tarde.', 429);
            }

            return null;
        });
    })->create();
