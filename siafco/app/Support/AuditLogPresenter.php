<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class AuditLogPresenter
{
    public static function actionLabel(?string $action): string
    {
        $normalized = self::normalizeAction($action);

        return match ($normalized) {
            'payment_confirmed' => 'Pago confirmado',
            'payment_rejected' => 'Pago rechazado',
            'payment_voided' => 'Pago anulado',
            'payment_manual_created' => 'Pago registrado',
            'affiliate_activated' => 'Afiliado activado',
            'affiliate_access_enabled' => 'Cuenta habilitada',
            'affiliate_access_blocked' => 'Cuenta bloqueada',
            'credential_generated', 'credential_created' => 'Credencial generada',
            'credential_activated' => 'Credencial activada',
            default => self::fallbackLabel($action),
        };
    }

    public static function detail(AuditLog $log): string
    {
        $metadata = is_array($log->metadata) ? $log->metadata : [];
        $paymentId = Arr::get($metadata, 'payment_id');

        if ($paymentId !== null && $paymentId !== '') {
            return 'Pago relacionado: #'.$paymentId;
        }

        return 'Sin detalles';
    }

    public static function origin(AuditLog $log): ?string
    {
        $normalized = self::normalizeAction($log->action);

        if ($log->user_id && in_array($normalized, [
            'payment_confirmed',
            'payment_rejected',
            'payment_voided',
            'payment_manual_created',
            'affiliate_activated',
            'credential_generated',
            'credential_created',
        ], true)) {
            return 'Panel administrativo';
        }

        return null;
    }

    public static function technicalJson(AuditLog $log): string
    {
        $metadata = is_array($log->metadata) ? $log->metadata : [];

        return json_encode(self::redactSensitive($metadata), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
    }

    private static function normalizeAction(?string $action): string
    {
        return Str::of($action ?? '')
            ->trim()
            ->replace(['-', '.', ' '], '_')
            ->lower()
            ->toString();
    }

    private static function fallbackLabel(?string $action): string
    {
        if (! $action) {
            return 'Accion no registrada';
        }

        return Str::of($action)
            ->replace(['_', '-', '.'], ' ')
            ->squish()
            ->headline()
            ->toString();
    }

    private static function redactSensitive(array $metadata): array
    {
        $sensitiveKeys = ['password', 'token', 'access_token', 'verification_token', 'public_token', 'qr'];

        foreach ($metadata as $key => $value) {
            if (in_array((string) $key, $sensitiveKeys, true)) {
                unset($metadata[$key]);

                continue;
            }

            if (is_array($value)) {
                $metadata[$key] = self::redactSensitive($value);
            }
        }

        return $metadata;
    }
}
