<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UserRedirectResolver
{
    public function redirectAfterLogin(Request $request, User $user): RedirectResponse
    {
        $intended = $request->session()->pull('url.intended');

        if ($this->isSafeIntendedUrl($request, $user, $intended)) {
            return redirect()->to($intended);
        }

        return redirect()->route($this->homeRoute($user));
    }

    public function redirectHome(Request $request, ?string $status = null): RedirectResponse
    {
        $redirect = redirect()->route($this->homeRoute($request->user()));

        return $status ? $redirect->with('status', $status) : $redirect;
    }

    public function homeRoute(User $user): string
    {
        if ($user->user_type === 'affiliate' || $user->role === 'afiliado') {
            return 'affiliate.panel';
        }

        if ($user->role === 'accionista') {
            return 'investments.panel';
        }

        return 'admin.dashboard';
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

        return ! in_array($path, ['panel-afiliado', 'mi-perfil', 'afiliado/credencial', 'panel-accionista'], true)
            && ! str_starts_with($path, 'mi-perfil/')
            && ! str_starts_with($path, 'afiliado/credencial/');
    }
}
