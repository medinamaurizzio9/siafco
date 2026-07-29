<?php

namespace App\Http\Controllers;

use App\Models\Affiliate;
use App\Services\AffiliatePasswordService;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AffiliatePasswordController extends Controller
{
    public function reset(Request $request, Affiliate $affiliate, AffiliatePasswordService $service)
    {
        Gate::authorize('resetPassword', $affiliate);
        $request->validate([
            'confirmation' => ['required', 'in:RESTABLECER'],
        ], [
            'confirmation.in' => 'Escribe RESTABLECER exactamente para confirmar.',
        ]);

        try {
            $service->reset($affiliate);
        } catch (ValidationException $exception) {
            AuditService::record('affiliate_password_reset', $affiliate, [
                'result' => 'failed',
                'reason' => array_key_first($exception->errors()),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
            ]);
            throw $exception;
        }

        AuditService::record('affiliate_password_reset', $affiliate, [
            'result' => 'success',
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
        ]);

        return back()->with(
            'status',
            'Contraseña restablecida correctamente. El afiliado deberá cambiarla al iniciar sesión.'
        );
    }
}
