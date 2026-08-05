<?php

namespace App\Http\Controllers;

use App\Models\InstitutionalSetting;
use App\Services\UserRedirectResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login', [
            'institution' => InstitutionalSetting::current(),
        ]);
    }

    public function login(Request $request, UserRedirectResolver $redirects)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (Auth::attempt([...$credentials, 'is_active' => true], $request->boolean('remember'))) {
            $request->session()->regenerate();
            Auth::user()->forceFill([
                'last_login_at' => now(),
                'last_login_ip' => $request->ip(),
            ])->save();

            return $redirects->redirectAfterLogin($request, Auth::user());
        }

        return back()->withErrors(['email' => 'Las credenciales no son validas.'])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
