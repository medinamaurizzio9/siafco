<?php

namespace App\Http\Controllers;

use App\Models\SimpleInvestmentInvestorAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class SimpleInvestorAuthController extends Controller
{
    public function create(Request $request)
    {
        if ($request->session()->has('simple_investor_access_id')) {
            $access = SimpleInvestmentInvestorAccess::with('investor')->find($request->session()->get('simple_investor_access_id'));
            if ($access && $access->is_active && $access->investor?->is_active
                && (int) $request->session()->get('simple_investor_session_version') === (int) $access->session_version) {
                return redirect()->route($access->must_change_password ? 'simple-investor.password.edit' : 'simple-investor.home');
            }
            $request->session()->forget(['simple_investor_access_id', 'simple_investor_session_version']);
        }

        return response()->view('simple-investor.auth.login')->header('Cache-Control', 'no-store, private, max-age=0');
    }

    public function store(Request $request)
    {
        $data = $request->validate(['ci' => ['required', 'string', 'max:30'], 'password' => ['required', 'string', 'max:200']]);
        $ci = mb_strtoupper(trim($data['ci']));
        $matches = SimpleInvestmentInvestorAccess::query()->whereHas('investor', fn ($q) => $q->where('ci', $ci))->with('investor')->get();
        $access = $matches->count() === 1 ? $matches->first() : null;
        $invalid = ! $access || ! $access->is_active || ! $access->investor->is_active || ($access->locked_until && $access->locked_until->isFuture()) || ! Hash::check($data['password'], $access->password);
        if ($invalid) {
            if ($access && (! $access->locked_until || $access->locked_until->isPast())) {
                $attempts = $access->failed_attempts + 1;
                $access->update(['failed_attempts' => $attempts >= 5 ? 0 : $attempts, 'locked_until' => $attempts >= 5 ? now()->addMinutes(15) : null]);
            }

            return back()->withErrors(['ci' => 'Las credenciales no son válidas o el acceso está temporalmente bloqueado.'])->onlyInput('ci');
        }
        $access->update(['failed_attempts' => 0, 'locked_until' => null, 'last_login_at' => now()]);
        $request->session()->regenerate();
        $request->session()->put(['simple_investor_access_id' => $access->id, 'simple_investor_session_version' => $access->session_version]);

        return redirect()->route($access->must_change_password ? 'simple-investor.password.edit' : 'simple-investor.home');
    }

    public function editPassword()
    {
        return view('simple-investor.auth.password');
    }

    public function updatePassword(Request $request)
    {
        $data = $request->validate(['password' => ['required', 'confirmed', Password::min(10)->mixedCase()->numbers()]]);
        $access = $request->attributes->get('simpleInvestorAccess');
        $access->update(['password' => Hash::make($data['password']), 'must_change_password' => false, 'password_changed_at' => now(), 'session_version' => $access->session_version + 1]);
        $request->session()->regenerate();
        $request->session()->put('simple_investor_session_version', $access->session_version);

        return redirect()->route('simple-investor.home')->with('status', 'Contraseña actualizada de forma segura.');
    }

    public function logout(Request $request)
    {
        $request->session()->forget(['simple_investor_access_id', 'simple_investor_session_version']);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('simple-investor.login')->withHeaders(['Cache-Control' => 'no-store, private, max-age=0']);
    }
}
