# Inversiones Simple: portal PWA del inversionista

Actualizacion 2026-10-31: entrada publica `/mi-inversion`, inicio privado
`/mi-inversion/inicio`, layout unico responsive PC/tablet/movil y soporte
institucional. Validacion y transiciones contractuales en
`docs/inversiones-simple-cierre-p1.md`.

## Arquitectura y alcance

`/mi-inversion` es la entrada publica de un portal privado separado del panel administrativo. Usa `simple_investment_investor_accesses`, claves de identidad en sesion especificas y autorizacion por propietario; no autentica al inversionista como `users`. Comparte la infraestructura de sesiones/cookies de Laravel, no una cookie independiente. El portal es exclusivamente informativo; no calcula rendimientos, no crea pagos y no presenta fechas como obligaciones financieras garantizadas.

## Acceso

Un usuario con `simple_investments.portal_access.manage` habilita o restablece el acceso desde la ficha del inversionista. El sistema genera una contraseña temporal aleatoria, almacena solo su hash y la muestra al operador una sola vez. El primer acceso exige cambiarla. Cinco intentos fallidos bloquean la cuenta 15 minutos y el endpoint tiene rate limiting por IP e identificador. El restablecimiento controlado invalida sesiones anteriores mediante `session_version`. No existe recuperación pública por un canal no verificado.

## Datos y rutas

Las tablas nuevas son `simple_investment_investor_accesses` y `simple_investment_portal_notifications`. El portal ofrece inicio, perfil/contacto, contratos, unidades, calendario y avisos. Los documentos solo se descargan si pertenecen al inversionista y su versión está `verified`.

## Avisos

`SimpleInvestorNotificationService` sincroniza avisos contractuales de forma idempotente. Se distingue generación, visualización, lectura y estado externo. WhatsApp queda preparado conceptualmente, pero `external_status` permanece `not_configured`; no se registra ningún envío inexistente y el texto interno no contiene CI ni importes.

## PWA y caché

El manifest y el codigo del worker estan en `public/investor-pwa`. El worker se sirve por `/mi-inversion/sw.js` con scope limitado a `/mi-inversion/`, usa red obligatoria y nunca cachea respuestas privadas. Solo guarda pantalla offline e iconos. Las respuestas autenticadas llevan `Cache-Control: no-store, private`; logout invalida la sesion sin limpiar caches o storage de otras PWA. No se usan credenciales ni contratos en `localStorage`.

## Pruebas y riesgos

`SimpleInvestorPortalTest` cubre emisión y hash, cambio obligatorio, bloqueo, logout, propiedad, documentos, avisos y política de caché. La instalación PWA exige HTTPS o localhost. Queda pendiente configurar un canal externo verificado (por ejemplo WhatsApp) antes de marcar mensajes como enviados, así como QA físico de instalación en Android.
