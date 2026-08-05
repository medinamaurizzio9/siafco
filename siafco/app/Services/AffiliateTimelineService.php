<?php

namespace App\Services;

use App\Models\Affiliate;
use App\Models\AuditLog;

class AffiliateTimelineService
{
    public function forAffiliate(Affiliate $affiliate, int $limit = 30)
    {
        return AuditLog::query()
            ->where(function ($query) use ($affiliate) {
                $query->where('auditable_type', $affiliate::class)->where('auditable_id', $affiliate->id)
                    ->orWhere(fn ($q) => $q->whereJsonContains('metadata->affiliate_id', $affiliate->id));
            })
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (AuditLog $log) => [
                'type' => $log->action,
                'label' => $this->label($log->action),
                'occurred_at' => $log->created_at,
                'metadata' => $log->metadata ?? [],
            ]);
    }

    private function label(string $action): string
    {
        return match ($action) {
            'mobile_login' => 'Inicio de sesion',
            'affiliate_password_changed', 'mobile_affiliate_password_changed' => 'Cambio de contrasena',
            'payment_manual_created' => 'Pago registrado',
            'payment_confirmed' => 'Pago confirmado',
            'payment_rejected' => 'Pago rechazado',
            'payment_voided' => 'Pago anulado',
            'credential_created' => 'Credencial generada',
            'credential_activated' => 'Credencial activada',
            'affiliate_access_blocked' => 'Cuenta bloqueada',
            'affiliate_access_enabled' => 'Cuenta habilitada',
            default => str($action)->replace('_', ' ')->headline()->toString(),
        };
    }
}
