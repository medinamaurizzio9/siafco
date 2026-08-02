<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class HomeRedirectController extends Controller
{
    private const ADMIN_DASHBOARD_ROLES = [
        'administrador',
        'superadministrador',
        'administrador_sector',
        'secretaria',
        'cajero',
        'consulta',
    ];

    public function root(Request $request): RedirectResponse
    {
        if (! $request->user()) {
            return redirect()->route('login');
        }

        return redirect()->route($this->homeRouteFor($request));
    }

    public function dashboard(Request $request, DashboardController $dashboard)
    {
        if ($this->isAffiliate($request)) {
            return redirect()->route('affiliate.panel');
        }

        abort_unless(
            $request->user()?->hasRole(self::ADMIN_DASHBOARD_ROLES),
            403,
            'No tiene permisos para acceder a esta seccion.'
        );

        return $dashboard->index();
    }

    private function homeRouteFor(Request $request): string
    {
        if ($this->isAffiliate($request)) {
            return 'affiliate.panel';
        }

        abort_unless(
            $request->user()?->hasRole(self::ADMIN_DASHBOARD_ROLES),
            403,
            'No tiene permisos para acceder a esta seccion.'
        );

        return 'admin.dashboard';
    }

    private function isAffiliate(Request $request): bool
    {
        $user = $request->user();

        return $user?->user_type === 'affiliate'
            && $user->role === 'afiliado'
            && $user->affiliate()->exists();
    }
}
