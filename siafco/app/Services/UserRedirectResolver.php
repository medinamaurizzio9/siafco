<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserRedirectResolver
{
    public function redirectAfterLogin(Request $request, User $user): RedirectResponse
    {
        $intended = $request->session()->pull('url.intended');

        if ($this->isSafeIntendedUrl($request, $user, $intended)) {
            return redirect()->to($intended);
        }

        return $this->redirectHome($request);
    }

    public function redirectHome(Request $request, ?string $status = null): RedirectResponse
    {
        $route = $this->homeRoute($request->user());

        if (! $route) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'Tu cuenta no tiene una ruta interna autorizada. Contacta al administrador.',
            ]);
        }

        $redirect = redirect()->route($route);

        return $status ? $redirect->with('status', $status) : $redirect;
    }

    public function homeRoute(User $user): ?string
    {
        if ($user->user_type === 'affiliate' || $user->role === 'afiliado') {
            return 'affiliate.panel';
        }

        if ($user->role === 'accionista') {
            return 'investments.panel';
        }

        return $this->firstAuthorizedInternalRoute($user);
    }

    private function isSafeIntendedUrl(Request $request, User $user, ?string $url): bool
    {
        if (! $url) {
            return false;
        }

        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
        if ($path === '') {
            return false;
        }

        $host = parse_url($url, PHP_URL_HOST);
        if ($host && $host !== $request->getHost()) {
            return false;
        }

        if ($user->user_type === 'affiliate' || $user->role === 'afiliado') {
            return $path === 'panel-afiliado'
                || str_starts_with($path, 'mi-perfil')
                || str_starts_with($path, 'afiliado/credencial')
                || str_starts_with($path, 'cambiar-contrasena-obligatoria');
        }

        if ($user->role === 'accionista') {
            return $path === 'panel-accionista' || str_starts_with($path, 'inversiones');
        }

        return $this->internalPathIsAuthorized($user, $path);
    }

    private function firstAuthorizedInternalRoute(User $user): ?string
    {
        foreach ([
            'dashboard.view' => 'admin.dashboard',
            'affiliates.view' => 'affiliates.index',
            'payments.view' => 'payments.index',
            'credentials.view' => 'credentials.index',
            'reports.view' => 'reports.index',
            'users.view' => 'admin.users.index',
        ] as $permission => $route) {
            if ($user->hasPermission($permission)) {
                return $route;
            }
        }

        return null;
    }

    private function internalPathIsAuthorized(User $user, string $path): bool
    {
        if (in_array($path, ['panel-afiliado', 'mi-perfil', 'afiliado/credencial', 'panel-accionista'], true)
            || str_starts_with($path, 'mi-perfil/')
            || str_starts_with($path, 'afiliado/credencial/')) {
            return false;
        }

        return match (true) {
            $path === 'dashboard' => $user->hasPermission('dashboard.view'),
            str_starts_with($path, 'afiliados') => $user->hasPermission('affiliates.view'),
            str_starts_with($path, 'pagos') => $user->hasPermission('payments.view'),
            str_starts_with($path, 'credenciales') => $user->hasPermission('credentials.view'),
            str_starts_with($path, 'reportes') => $user->hasPermission('reports.view'),
            str_starts_with($path, 'administracion/usuarios') => $user->hasPermission('users.view'),
            default => false,
        };
    }
}
