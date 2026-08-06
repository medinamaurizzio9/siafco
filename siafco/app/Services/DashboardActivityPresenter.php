<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;

class DashboardActivityPresenter
{
    private const HIDDEN_ACTIONS = [
        'role_user_count_viewed',
        'mobile_login',
    ];

    public function recent(int $limit = 5): array
    {
        return AuditLog::query()
            ->with('user:id,name,role,user_type')
            ->whereNotIn('action', self::HIDDEN_ACTIONS)
            ->latest()
            ->limit($limit * 3)
            ->get()
            ->map(fn (AuditLog $log) => $this->present($log))
            ->filter()
            ->take($limit)
            ->values()
            ->all();
    }

    public function present(AuditLog $log): ?array
    {
        $label = $this->label($log->action);
        if ($label === null) {
            return null;
        }

        return [
            'label' => $label,
            'description' => $this->description($log),
            'actor' => $log->user?->name ?? 'Sistema',
            'role' => $log->user instanceof User ? $this->cleanRoleLabel($log->user) : null,
            'entity' => $this->entity($log),
            'time' => $log->created_at?->diffForHumans(short: true) ?? '',
            'icon' => $this->icon($log->action),
            'tone' => $this->tone($log->action),
        ];
    }

    private function label(string $action): ?string
    {
        return match ($action) {
            'payment_registered', 'payment_manual_created', 'pago.comprobante_registrado' => 'Pago registrado',
            'payment_confirmed' => 'Pago confirmado',
            'payment_rejected' => 'Pago rechazado',
            'payment_voided' => 'Pago anulado',
            'internal_user_created' => 'Usuario creado',
            'internal_user_updated' => 'Usuario actualizado',
            'internal_user_role_changed' => 'Rol actualizado',
            'credential_created', 'affiliate_credential_files_regenerated' => 'Credencial emitida',
            'credential_activated', 'affiliate_credential_reactivated' => 'Credencial activada',
            'credential_revoked', 'affiliate_credential_suspended' => 'Credencial suspendida',
            'affiliate_status_changed' => 'Estado actualizado',
            'affiliate_personal_data_updated', 'affiliate_institutional_data_updated', 'afiliado.actualizado' => 'Afiliado actualizado',
            'afiliado.registrado' => 'Afiliado registrado',
            'login' => 'Inicio de sesion',
            'store_order_created', 'mini_tienda.pedido_creado' => 'Pedido recibido',
            'mini_tienda.comprobante_confirmado' => 'Pago de tienda confirmado',
            'mini_tienda.comprobante_rechazado' => 'Pago de tienda rechazado',
            'role_permissions_updated' => 'Permisos actualizados',
            default => str_starts_with($action, 'mini_tienda.') ? 'Actividad de tienda' : str($action)->replace(['_', '.'], ' ')->headline()->toString(),
        };
    }

    private function description(AuditLog $log): string
    {
        $entity = $this->entity($log);
        $actor = $log->user?->name ?? 'Sistema';

        return match (true) {
            str_contains($log->action, 'payment'), str_contains($log->action, 'pago') => "{$entity} por {$actor}",
            str_contains($log->action, 'credential') => "{$entity} actualizado",
            str_contains($log->action, 'user') => "Gestionado por {$actor}",
            str_contains($log->action, 'role_permissions') => "Matriz de permisos actualizada",
            str_contains($log->action, 'mini_tienda'), str_contains($log->action, 'store_order') => "{$entity} en Mini Tienda",
            default => "{$entity} por {$actor}",
        };
    }

    private function entity(AuditLog $log): string
    {
        $metadata = $log->metadata ?? [];

        return data_get($metadata, 'order_code')
            ?? data_get($metadata, 'registration_number')
            ?? data_get($metadata, 'receipt_number')
            ?? class_basename($log->auditable_type ?: '')
            ?: 'Registro del sistema';
    }

    private function icon(string $action): string
    {
        return match (true) {
            str_contains($action, 'payment'), str_contains($action, 'pago') => 'credit-card',
            str_contains($action, 'credential') => 'briefcase',
            str_contains($action, 'user') => 'user',
            str_contains($action, 'mini_tienda') => 'briefcase',
            default => 'bell',
        };
    }

    private function tone(string $action): string
    {
        return match (true) {
            str_contains($action, 'rejected'), str_contains($action, 'voided'), str_contains($action, 'rechazado') => 'danger',
            str_contains($action, 'pending'), str_contains($action, 'actualizado') => 'warning',
            str_contains($action, 'confirmed'), str_contains($action, 'created'), str_contains($action, 'registrado') => 'success',
            default => 'info',
        };
    }

    private function cleanRoleLabel(User $user): string
    {
        return str_replace(' (legado)', '', $user->roleLabel());
    }
}
