<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use App\Http\Responses\MobileApiResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

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
            'affiliate.store.active' => \App\Http\Middleware\EnsureActiveStoreAffiliate::class,
            'mobile.affiliate.active' => \App\Http\Middleware\EnsureActiveMobileStoreAffiliate::class,
            'password.changed' => \App\Http\Middleware\EnsurePasswordIsChanged::class,
            'mobile.affiliate' => \App\Http\Middleware\EnsureMobileAffiliateAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (HttpException $exception, $request) {
            if ($exception->getStatusCode() === 419
                && $request->isMethod('post')
                && trim($request->path(), '/') === 'logout') {
                $request->session()->regenerateToken();

                if (Auth::check()) {
                    return redirect()->route('logout.confirm')
                        ->with('warning', 'No se pudo cerrar sesión porque el formulario expiró. Confirma nuevamente para salir.');
                }

                return redirect()->route('login')->with('warning', 'La sesión expiró.');
            }

            return null;
        });

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

        $exceptions->render(function (ValidationException $exception, $request) {
            if ($request->is('api/mobile/v1/*')) {
                return MobileApiResponse::error('Los datos enviados no son válidos.', 422, $exception->errors());
            }

            return null;
        });
    })->create();
