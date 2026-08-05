<?php

$permissions = [
    'dashboard.view',
    'users.view', 'users.create', 'users.update', 'users.block', 'users.activate',
    'users.reset-password', 'users.delete', 'users.restore', 'users.assign-role',
    'affiliates.view', 'affiliates.create', 'affiliates.update', 'affiliates.approve',
    'affiliates.reject', 'affiliates.delete', 'affiliates.restore', 'affiliates.reset-password',
    'affiliate_access.view', 'affiliate_access.block', 'affiliate_access.activate',
    'affiliate_access.reset_password', 'affiliate_access.revoke_sessions',
    'affiliate_access.change_email',
    'payments.view', 'payments.create', 'payments.update', 'payments.verify',
    'payments.cancel', 'payments.receipt',
    'credentials.view', 'credentials.download', 'credentials.print',
    'settings.view', 'settings.update', 'reports.view', 'reports.export',
    'audit.view', 'credits.view', 'credits.create', 'credits.update', 'credits.approve',
    'investors.view', 'investors.create', 'investors.update',
];

return [
    'permissions' => $permissions,
    'labels' => [
        'superadministrador' => 'Super Administrador',
        'gerente' => 'Gerente',
        'secretaria' => 'Secretaría',
        'cajero' => 'Cajero',
        'administrador' => 'Administrador (legado)',
        'administrador_sector' => 'Administrador de sector',
        'consulta' => 'Consulta',
    ],
    'assignable' => ['superadministrador', 'gerente', 'secretaria', 'cajero'],
    'roles' => [
        'superadministrador' => $permissions,
        'administrador' => $permissions,
        'gerente' => [
            'dashboard.view', 'users.view', 'affiliates.view', 'payments.view', 'credentials.view',
            'settings.view', 'reports.view', 'reports.export', 'audit.view',
            'affiliate_access.view',
            'credits.view', 'investors.view',
        ],
        'secretaria' => [
            'dashboard.view', 'users.view', 'affiliates.view', 'affiliates.create', 'affiliates.update',
            'affiliates.approve', 'affiliates.reject', 'affiliates.reset-password',
            'affiliate_access.view', 'affiliate_access.block', 'affiliate_access.activate',
            'affiliate_access.reset_password', 'affiliate_access.revoke_sessions',
            'affiliate_access.change_email',
            'payments.view', 'payments.create', 'payments.update', 'payments.receipt',
            'credentials.view', 'credentials.download', 'credentials.print',
            'reports.view', 'settings.view',
        ],
        'cajero' => [
            'dashboard.view', 'affiliates.view', 'payments.view', 'payments.create', 'payments.update',
            'payments.verify', 'payments.receipt', 'credits.view',
        ],
        'administrador_sector' => [
            'dashboard.view', 'affiliates.view', 'affiliates.create', 'affiliates.update',
            'payments.view', 'credentials.view', 'credentials.download',
            'credentials.print', 'reports.view',
        ],
        'consulta' => ['dashboard.view', 'affiliates.view', 'payments.view', 'reports.view', 'credits.view'],
    ],
];
