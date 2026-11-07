# Comunicados y notificaciones de SIAFCO

## Operación y permisos

En Comunicaciones → Comunicados, guarde un borrador, revise la lista paginada y confirme el total. Una fecha futura programa el aviso en la zona horaria configurada en Laravel. La audiencia queda fijada al confirmar, también para avisos programados. Se incluyen afiliados activos con cuenta activa de tipo affiliate y rol afiliado. Los identificadores repetidos entre sectores y selección individual se resuelven una sola vez. El emisor revisa nuevamente el total si cambió. La consulta del buzón y el envío de push comprueban la elegibilidad vigente.

Los permisos son `communications.manage` para administrar, `communications.publish` para publicar, programar y archivar y `communications.report` para reportes. Publicación y reportes también requieren administración. Los roles que reciben el catálogo completo heredan los permisos. Los demás se asignan en la administración de roles.

La primera apertura determina el indicador leído. La confirmación explícita tiene su propia fecha. Archivar o alcanzar el vencimiento retira el comunicado y sus archivos de la bandeja. Para corregir una publicación, archive la versión original y emita otra que haga referencia al original. La bitácora registra los actores y fechas de ambas publicaciones y del archivo.

## Despliegue manual

1. Respaldar la base y verificar la restauración en staging. Revisar `php artisan migrate:status` y las migraciones pendientes del proyecto antes de autorizar cambios.
2. Verificar en staging la migración incremental `2026_11_07_000001_create_communication_tables.php`. Crea seis tablas. La reversión elimina las tablas del módulo y sus contenidos. Desactivar la navegación, el planificador y los workers del módulo antes de retirar su esquema.
3. Aplicar la migración en la ventana de despliegue autorizada. El portal requiere el esquema nuevo al activar esta versión. En despliegues con varios servidores, compartir el disco local privado de Laravel entre instancias web.
4. Habilitar Firebase Cloud Messaging API en el proyecto Firebase. Proveer al backend una cuenta de servicio con permiso cloudmessaging.messages.create. Instalar su JSON fuera del repositorio y del directorio público, con acceso limitado al usuario del worker. Configurar `FCM_CREDENTIALS_PATH` con su ruta absoluta. Conservar `APP_KEY` para descifrar tokens de dispositivo y proteger el almacén de caché que contiene el token OAuth temporal.
5. Configurar `QUEUE_CONNECTION` con una conexión asíncrona disponible (por ejemplo database con su tabla jobs). Configurar `CACHE_STORE` compartido con soporte de locks si hay varios procesos. Verificar configuración efectiva, zona horaria y cachés de Laravel según el despliegue.
6. Ejecutar el scheduler habitual de Laravel cada minuto (`php artisan schedule:run`). Supervisar un worker para `php artisan queue:work --queue=communications --timeout=60`. Reiniciar los workers al activar una versión nueva. El comando `php artisan communications:process` publica los avisos vencidos de programación y despacha el trabajo pendiente.
7. Probar con dos cuentas de staging, una incluida y otra excluida, y un Android físico con consentimiento. Verificar detalle, descarga privada, contador, expiración, confirmación y recepción con la aplicación en segundo plano.

El trabajador registra sent, failed, skipped o unknown y el resultado técnico en communication_push_deliveries. Los fallos temporales tienen hasta cinco intentos con esperas crecientes. Un proceso interrumpido durante el envío deja un resultado incierto que pasa a unknown al caducar la reserva. Revisar esos registros antes de intentar una recuperación manual. La disponibilidad del contenido depende de la publicación y del acceso del afiliado. La recepción de un push depende además de Google, el dispositivo y la configuración del usuario. Un fallo de red con respuesta perdida puede provocar entregas repetidas.

## Android

Registrar la aplicación Android con su identificador de paquete en el mismo proyecto Firebase. Añadir su configuración de cliente google-services.json, el plugin Google Services y Firebase Messaging mediante el Firebase Android BoM. Declarar el servicio FirebaseMessagingService para el evento com.google.firebase.MESSAGING_EVENT. Crear un canal de notificaciones y solicitar POST_NOTIFICATIONS en Android 13 y versiones posteriores. Comprobar el permiso, el canal y las preferencias locales antes de mostrar un aviso. [Configuración de FCM en Android](https://firebase.google.com/docs/cloud-messaging/android/client)

Autenticar mediante el login móvil existente. Enviar Authorization Bearer con el token Sanctum y Accept application/json. Usar un UUID persistente por instalación. Al iniciar sesión, al cambiar el permiso o la preferencia, y desde onNewToken, registrar el token actual y enabled. Revocar la instalación mientras todavía se dispone del token Sanctum al cerrar sesión o cambiar de cuenta. El backend almacena el token cifrado, admite varios dispositivos y desactiva tokens que FCM identifica como UNREGISTERED.

Todas las rutas usan el prefijo `/api/mobile/v1`.

| Método y ruta | Uso |
|---|---|
| GET /communications | Lista paginada en data.data con page, q y filter=all, read o unread |
| GET /communications/unread-count | data.unread_count |
| GET /communications/{id} | Detalle en data.communication y data.attachments. Registra primera apertura |
| GET /communications/{id}/attachments/{file} | Descarga autorizada, también para cover_id |
| POST /communications/{id}/read | Registra lectura y confirma si se solicitó. Mostrar la acción de confirmación por separado |
| POST /push-devices | JSON con device_id (UUID), token (cadena), enabled (booleano) |
| DELETE /push-devices/{device_id} | Revoca el dispositivo propio |

Una respuesta 401 requiere iniciar sesión, 403 indica acceso restringido, 404 indica recurso indisponible y 422 incluye los errores de validación. Ante 429 posponer el intento. Cargar la lista al entrar y al volver al primer plano, incluso si no llegaron notificaciones.

El mensaje data de FCM contiene type=communication, communication_id, title y summary. Mostrarlo solo si la sesión sigue activa y corresponde a la cuenta registrada, tras comprobar el acceso mediante la API. Usar un identificador de notificación estable por cuenta y comunicado para agrupar duplicados. Al pulsarla, abrir el detalle autenticado. El título y resumen deben ser apropiados para una pantalla bloqueada. Mantener la clave de servicio exclusivamente en el backend. [Envío HTTP v1](https://firebase.google.com/docs/cloud-messaging/send/v1-api) · [Errores FCM](https://firebase.google.com/docs/cloud-messaging/error-codes)

## Inventario

Los archivos nuevos de aplicación son el modelo Communication, CommunicationPolicy, los controladores CommunicationAdminController y CommunicationInboxController, CommunicationMobileAccess, los servicios Communications y CommunicationFcm, el trabajo SendCommunicationPush y el comando ProcessCommunications. Se añaden config/communication_push.php, la migración, routes/communications.php, routes/mobile_communications.php, lang/es/communications.php, public/js/communication-audience.js, tests/Feature/CommunicationsTest.php y las vistas admin-list, edit, preview, report, inbox, detail, bell y card en resources/views/communications. La documentación se reparte entre comunicados-compatibilidad.md y este archivo.

Los archivos existentes modificados son routes/web.php y routes/api.php para cargar las nuevas rutas, routes/console.php para registrar el horario, config/internal_roles.php y app/Services/RolePermissionService.php para el catálogo y su agrupación, y resources/views/layouts/app.blade.php para los accesos de navegación.
