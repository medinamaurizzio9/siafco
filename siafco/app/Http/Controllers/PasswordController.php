<?php

namespace App\Http\Controllers;

use App\Http\Requests\ForcePasswordChangeRequest;
use App\Http\Requests\UpdateOwnPasswordRequest;
use App\Services\AffiliatePasswordService;
use App\Services\AuditService;
use App\Services\UserRedirectResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class PasswordController extends Controller
{
    public function updateOwn(
        UpdateOwnPasswordRequest $request,
        AffiliatePasswordService $passwordService
    ) {
        $user = $request->user();
        $passwordService->assertAllowedPassword($request->validated('password'), $user, $user->affiliate);
        $this->savePassword($request, $request->validated('password'));
        AuditService::record('affiliate_password_changed', $user->affiliate, [
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
            'forced' => false,
        ]);

        return back()->with('status', 'Tu contrasena fue actualizada correctamente.');
    }

    public function forceEdit(Request $request, UserRedirectResolver $redirects)
    {
        if (! $request->user()->must_change_password) {
            return $redirects->redirectHome($request);
        }

        return view('auth.force-password');
    }

    public function forceUpdate(
        ForcePasswordChangeRequest $request,
        AffiliatePasswordService $passwordService,
        UserRedirectResolver $redirects
    ) {
        $user = $request->user();
        $passwordService->assertAllowedPassword($request->validated('password'), $user, $user->affiliate);
        $this->savePassword($request, $request->validated('password'));
        AuditService::record('affiliate_password_changed', $user->affiliate, [
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
            'forced' => true,
        ]);

        return $redirects->redirectHome($request, 'Tu contrasena fue actualizada correctamente.');
    }

    private function savePassword(Request $request, string $password): void
    {
        $user = $request->user();
        $user->forceFill([
            'password' => Hash::make($password),
            'must_change_password' => false,
        ])->save();

        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))
                ->where('user_id', $user->id)
                ->where('id', '!=', $request->session()->getId())
                ->delete();
        }

        $request->session()->regenerate();
    }
}
