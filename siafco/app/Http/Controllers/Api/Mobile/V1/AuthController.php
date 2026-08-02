<?php

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Mobile\V1\LoginRequest;
use App\Http\Resources\Api\Mobile\V1\MobileProfileResource;
use App\Http\Responses\MobileApiResponse;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    private const ALLOWED_STATUSES = ['pendiente_pago', 'pago_en_revision', 'observado', 'activo'];

    public function login(LoginRequest $request)
    {
        $data = $request->validated();
        $user = User::query()
            ->with('affiliate.sector', 'affiliate.plan')
            ->where('email', mb_strtolower($data['email']))
            ->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return MobileApiResponse::error('Las credenciales no son válidas.', 401);
        }

        if ($user->user_type !== 'affiliate' || $user->role !== 'afiliado' || ! $user->affiliate) {
            return MobileApiResponse::error('La API móvil solo está disponible para afiliados.', 403);
        }

        if (! $user->is_active) {
            return MobileApiResponse::error('La cuenta no está activa.', 403);
        }

        if (! in_array($user->affiliate->status, self::ALLOWED_STATUSES, true)) {
            return MobileApiResponse::error('El estado de afiliación no permite acceso móvil.', 403, [
                'status' => [$user->affiliate->status],
            ]);
        }

        $token = $user->createToken($data['device_name'] ?? 'SIAFCO Android', ['mobile'])->plainTextToken;
        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'mobile_login',
            'auditable_type' => $user->affiliate::class,
            'auditable_id' => $user->affiliate->id,
            'metadata' => [
                'device_name' => mb_substr((string) ($data['device_name'] ?? 'SIAFCO Android'), 0, 120),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
            ],
            'ip_address' => $request->ip(),
        ]);

        return MobileApiResponse::success([
            'token_type' => 'Bearer',
            'access_token' => $token,
            'profile' => new MobileProfileResource($user->fresh('affiliate.sector', 'affiliate.plan')),
        ], 'Sesión iniciada.');
    }

    public function logout(Request $request)
    {
        $token = $request->user()->currentAccessToken();
        $token?->delete();

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'mobile_logout',
            'auditable_type' => $request->user()->affiliate::class,
            'auditable_id' => $request->user()->affiliate->id,
            'metadata' => ['scope' => 'current_token'],
            'ip_address' => $request->ip(),
        ]);

        return MobileApiResponse::success(message: 'Sesión cerrada.');
    }

    public function logoutAll(Request $request)
    {
        $request->user()->tokens()->delete();

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'mobile_logout_all',
            'auditable_type' => $request->user()->affiliate::class,
            'auditable_id' => $request->user()->affiliate->id,
            'metadata' => ['scope' => 'all_tokens'],
            'ip_address' => $request->ip(),
        ]);

        return MobileApiResponse::success(message: 'Todas las sesiones móviles fueron cerradas.');
    }
}
