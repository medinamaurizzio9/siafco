<?php

use App\Http\Middleware\EnsureActiveMobileStoreAffiliate;
use App\Http\Middleware\EnsureActiveStoreAffiliate;
use App\Http\Middleware\EnsureAffiliateProfileIsComplete;
use App\Http\Middleware\EnsureInvestmentAdvisorAuthenticated;
use App\Http\Middleware\EnsureMobileAffiliateAccess;
use App\Http\Middleware\EnsurePasswordIsChanged;
use App\Http\Middleware\EnsureSimpleInvestorAuthenticated;
use App\Http\Middleware\MeasureRequestPerformance;
use App\Http\Middleware\PermissionMiddleware;
use App\Http\Middleware\RestrictPendingAffiliateAccess;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Responses\MobileApiResponse;
use App\Services\UserRedirectResolver;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->redirectUsersTo(function ($request) {
            $user = $request->user();

            if ($user?->must_change_password) {
                return route('password.force.edit');
            }

            $homeRoute = $user
                ? app(UserRedirectResolver::class)->homeRoute($user)
                : null;

            return $homeRoute ? route($homeRoute) : url('/');
        });

        if (env('SIAFCO_MEASURE_PERFORMANCE', false)) {
            $middleware->web(append: [
                MeasureRequestPerformance::class,
            ]);
        }

        $middleware->alias([
            'permission' => PermissionMiddleware::class,
            'role' => RoleMiddleware::class,
            'affiliate.active-access' => RestrictPendingAffiliateAccess::class,
            'affiliate.profile.complete' => EnsureAffiliateProfileIsComplete::class,
            'affiliate.store.active' => EnsureActiveStoreAffiliate::class,
            'mobile.affiliate.active' => EnsureActiveMobileStoreAffiliate::class,
            'password.changed' => EnsurePasswordIsChanged::class,
            'mobile.affiliate' => EnsureMobileAffiliateAccess::class,
            'investment.crm.advisor' => EnsureInvestmentAdvisorAuthenticated::class,
            'simple.investor' => EnsureSimpleInvestorAuthenticated::class,
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
