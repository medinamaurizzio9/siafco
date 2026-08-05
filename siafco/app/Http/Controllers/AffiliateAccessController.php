<?php

namespace App\Http\Controllers;

use App\Models\Affiliate;
use App\Services\AuditService;
use App\Services\UserAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AffiliateAccessController extends Controller
{
    public function __construct(private readonly UserAccessService $access) {}

    public function block(Request $request, Affiliate $affiliate)
    {
        Gate::authorize('blockAccess', $affiliate);
        $user = $this->linkedUser($affiliate);

        DB::transaction(function () use ($affiliate, $request, $user): void {
            $this->access->block($user);
            AuditService::record('affiliate_access_blocked', $affiliate, [
                'result' => 'success',
                'user_id' => $user->id,
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
            ]);
        });

        return back()->with('status', 'La cuenta de acceso del afiliado fue bloqueada. La afiliacion permanece intacta.');
    }

    public function activate(Request $request, Affiliate $affiliate)
    {
        Gate::authorize('activateAccess', $affiliate);
        $user = $this->linkedUser($affiliate);

        DB::transaction(function () use ($affiliate, $request, $user): void {
            $this->access->activate($user);
            AuditService::record('affiliate_access_activated', $affiliate, [
                'result' => 'success',
                'user_id' => $user->id,
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
            ]);
        });

        return back()->with('status', 'La cuenta de acceso del afiliado fue activada. Debe iniciar sesion nuevamente.');
    }

    public function revokeSessions(Request $request, Affiliate $affiliate)
    {
        Gate::authorize('revokeSessions', $affiliate);
        $user = $this->linkedUser($affiliate);

        DB::transaction(function () use ($affiliate, $request, $user): void {
            $this->access->invalidateSessions($user);
            AuditService::record('affiliate_sessions_revoked', $affiliate, [
                'result' => 'success',
                'user_id' => $user->id,
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
            ]);
        });

        return back()->with('status', 'Las sesiones web y tokens moviles del afiliado fueron cerrados.');
    }

    private function linkedUser(Affiliate $affiliate)
    {
        $affiliate->loadMissing('user');

        if (! $affiliate->user) {
            throw ValidationException::withMessages([
                'affiliate' => 'Este afiliado no tiene una cuenta de acceso vinculada.',
            ]);
        }

        return $affiliate->user;
    }
}
