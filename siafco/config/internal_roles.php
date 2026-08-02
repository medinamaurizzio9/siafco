<?php

$permissions = [
    'users.view', 'users.create', 'users.update', 'users.block', 'users.activate',
    'users.reset-password', 'users.delete', 'users.restore', 'users.assign-role',
    'affiliates.view', 'affiliates.create', 'affiliates.update', 'affiliates.approve',
    'affiliates.reject', 'affiliates.delete', 'affiliates.restore', 'affiliates.reset-password',
    'payments.view', 'payments.create', 'payments.update', 'payments.verify',
    'payments.cancel', 'payments.receipt',
    'credentials.view', 'credentials.download', 'credentials.print',
    'settings.view', 'settings.update', 'reports.view', 'reports.export',
    'audit.view', 'credits.view', 'credits.create', 'credits.update', 'credits.approve',
    'investors.view', 'investors.create', 'investors.update',
    'store.view', 'store.manage-products', 'store.manage-settings', 'store.manage-shipping',
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
            'users.view', 'affiliates.view', 'payments.view', 'credentials.view',
            'settings.view', 'reports.view', 'reports.export', 'audit.view',
            'credits.view', 'investors.view', 'store.view',
        ],
        'secretaria' => [
            'users.view', 'affiliates.view', 'affiliates.create', 'affiliates.update',
            'affiliates.approve', 'affiliates.reject', 'affiliates.reset-password',
            'payments.view', 'payments.create', 'payments.update', 'payments.receipt',
            'credentials.view', 'credentials.download', 'credentials.print',
            'reports.view', 'settings.view', 'store.view', 'store.manage-products', 'store.manage-shipping',
        ],
        'cajero' => [
            'affiliates.view', 'payments.view', 'payments.create', 'payments.update',
            'payments.verify', 'payments.receipt', 'credits.view',
        ],
        'administrador_sector' => [
            'affiliates.view', 'affiliates.create', 'affiliates.update',
            'payments.view', 'credentials.view', 'credentials.download',
            'credentials.print', 'reports.view',
        ],
        'consulta' => ['affiliates.view', 'payments.view', 'reports.view', 'credits.view', 'store.view'],
    ],
];
