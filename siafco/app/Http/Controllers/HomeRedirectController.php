<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Services\UserRedirectResolver;

class HomeRedirectController extends Controller
{
    public function root(Request $request, UserRedirectResolver $redirects): RedirectResponse
    {
        if (! $request->user()) {
            return redirect()->route('login');
        }

        return $redirects->redirectHome($request);
    }

    public function dashboard(Request $request, DashboardController $dashboard, UserRedirectResolver $redirects)
    {
        $homeRoute = $redirects->homeRoute($request->user());

        if ($homeRoute && $homeRoute !== 'admin.dashboard') {
            return redirect()->route($homeRoute);
        }

        abort_unless(
            $request->user()?->hasPermission('dashboard.view'),
            403,
            'No tiene permisos para acceder a esta seccion.'
        );

        return app()->call([$dashboard, 'index']);
    }
}
