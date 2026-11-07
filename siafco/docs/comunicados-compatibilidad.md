# Compatibilidad antes de la implementación

Laravel 12.70.2, Sanctum 4 y API /api/mobile/v1 con mobile.affiliate y MobileApiResponse. El portal affiliate-panel usa layouts.app. RolePermissionService consume internal_roles y admite excepciones por rol. Affiliate usa SoftDeletes, user_id, sector_id, registration_number, full_name, ci y status. La elegibilidad del módulo exigirá estado activo y cuenta activa de afiliado. Las claves de users, sectors y affiliates son enteros grandes. Existen colas de Laravel, almacenamiento local y notificaciones de dominio. Se mantendrá independiente el transporte FCM.

La rama feature/inversiones-simple tiene cambios previos en composer.lock, recursos compilados e informes locales. PHPUnit está configurado para SQLite en memoria. Se inició la suite base antes de cambiar código.
