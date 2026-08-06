<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class AuditLogQueryService
{
    public function query(array $filters): Builder
    {
        return AuditLog::query()
            ->with('user:id,name,role,user_type')
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '<=', $date))
            ->when($filters['user_id'] ?? null, fn (Builder $query, string $id) => $query->where('user_id', $id))
            ->when($filters['role'] ?? null, fn (Builder $query, string $role) => $query->whereHas('user', fn (Builder $user) => $user->where('role', $role)))
            ->when($filters['action'] ?? null, fn (Builder $query, string $action) => $query->where('action', 'like', "%{$action}%"))
            ->when($filters['module'] ?? null, fn (Builder $query, string $module) => $query->where('action', 'like', $this->modulePrefix($module).'%'))
            ->when($filters['entity'] ?? null, fn (Builder $query, string $entity) => $query->where('auditable_type', 'like', "%{$entity}%"))
            ->when($filters['ip'] ?? null, fn (Builder $query, string $ip) => $query->where('ip_address', 'like', "%{$ip}%"))
            ->when($filters['request_id'] ?? null, fn (Builder $query, string $requestId) => $query->where('metadata->request_id', $requestId))
            ->when($filters['q'] ?? null, function (Builder $query, string $text) {
                $query->where(function (Builder $nested) use ($text) {
                    $nested->where('action', 'like', "%{$text}%")
                        ->orWhere('auditable_type', 'like', "%{$text}%")
                        ->orWhere('ip_address', 'like', "%{$text}%");
                });
            })
            ->latest();
    }

    public function paginate(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->query($filters)->paginate($perPage)->withQueryString();
    }

    public function modules(): array
    {
        return [
            'mobile' => 'API movil',
            'mini_tienda' => 'Mini Tienda',
            'payment' => 'Tesoreria',
            'affiliate' => 'Afiliacion',
            'credential' => 'Credenciales',
            'internal_user' => 'Usuarios internos',
            'role' => 'Roles y permisos',
            'configuracion' => 'Configuracion',
        ];
    }

    public function moduleFor(string $action): string
    {
        foreach ($this->modules() as $prefix => $label) {
            if (str_starts_with($action, $prefix)) {
                return $label;
            }
        }

        return 'Otros';
    }

    private function modulePrefix(string $module): string
    {
        return match ($module) {
            'mobile' => 'mobile_',
            'mini_tienda' => 'mini_tienda.',
            'payment' => 'payment',
            'affiliate' => 'affiliate',
            'credential' => 'credential',
            'internal_user' => 'internal_user',
            'role' => 'role_',
            'configuracion' => 'configuracion',
            default => $module,
        };
    }
}
