# Inversiones Simple - contratos digitales y avisos

Fecha: 2026-10-30
Rama: `feature/inversiones-simple`
Estado: implementado. La aplicacion de migraciones depende de autorizacion
expresa para cada entorno; este documento no ejecuta ni autoriza migraciones.

## Alcance

La ampliacion incorpora documentacion contractual privada y versionada,
verificacion administrativa, proyecciones de fechas y avisos internos
idempotentes. No calcula rendimientos, no registra pagos y no genera
comprobantes.

## Persistencia

Migracion nueva:

`2026_10_30_130000_add_documents_and_schedule_to_simple_investments.php`

Cambios:

- `simple_investment_document_versions`: versiones inmutables de documentos,
  metadatos, SHA-256, responsable de carga y revision.
- `simple_investment_schedule_notices`: fecha programada, tipo de aviso, clave
  idempotente, preparacion, envio futuro y confirmacion futura de entrega.
- `simple_investment_contracts`: snapshots de primer plazo, intervalo, cantidad
  de proyecciones y zona horaria vigentes al firmar.

La migracion debe aplicarse solo en un entorno autorizado, con respaldo y
validacion previa. El codigo no la ejecuta automaticamente durante solicitudes.

## Documentos privados

- Disco: `local`, cuya raiz es `storage/app/private`.
- Tipos reales aceptados: PDF, JPEG, PNG y WEBP.
- Limite configurable: `SIMPLE_INVESTMENT_DOCUMENT_MAX_KB`, 10240 KB por
  defecto.
- Cada sustitucion crea una version nueva con nombre interno UUID.
- Se conserva el archivo anterior y su revision.
- Se registra SHA-256, tamano, MIME real, fecha y usuario.
- Ver y descargar requiere ruta autenticada, permiso backend y pertenencia del
  documento al contrato indicado.
- No se exponen rutas fisicas ni URLs permanentes.
- Si falla el almacenamiento no se crea el registro; si falla la transaccion,
  el archivo nuevo se elimina.

Estados documentales visibles:

- Pendiente de documento.
- Documento cargado.
- Verificado.
- Observado.

La revision documental no cambia el estado del contrato ni acredita firma,
capital o pago.

## Calendario contractual

Al firmar se copian al contrato:

- Primer plazo: configuracion vigente, 40 dias por defecto.
- Intervalo: configuracion vigente, 30 dias exactos por defecto.
- Ventana limitada: 12 fechas por defecto.
- Zona horaria: `America/La_Paz` por defecto.

La ventana puede configurarse con `SIMPLE_INVESTMENT_PROJECTION_COUNT`. Los
cambios posteriores en la configuracion general no recalculan contratos ya
firmados. Las fechas se identifican expresamente como proyecciones y no incluyen
importes.

## Avisos

El servicio prepara registros para:

- 7 dias antes.
- 3 dias antes.
- El dia programado.
- Fecha pasada sin entrega confirmada.

La clave `simple-investment:{contract}:{indice}:{tipo}` evita duplicados. El
comando `simple-investments:prepare-notices` queda programado diariamente a las
08:00. Solo prepara avisos internos: `sent_at` y
`delivery_confirmed_at` permanecen nulos hasta que exista un canal y un modulo
de entregas reales.

## Permisos

- `simple_investments.documents.view`
- `simple_investments.documents.upload`
- `simple_investments.documents.verify`

El Supervisor de Inversiones puede consultar, cargar y verificar. RRHH no
recibe acceso automatico, pero una autorizacion explicita puede conceder
consulta y carga; el limite del rol impide otorgarle verificacion. Los permisos
se validan tanto en middleware como en policy.

## Rutas

Se agregaron cinco rutas autenticadas bajo el contrato:

- Cargar nueva version.
- Ver.
- Descargar.
- Verificar.
- Observar.

El modulo pasa de 18 a 23 rutas `simple-investments.*`. No se agregaron rutas
publicas ni endpoints PWA.

## Integracion PWA

El modelo de avisos distingue fecha programada, aviso preparado, envio y
confirmacion de entrega. El portal privado autoriza por propietario y permite
descargar documentos verificados. No hay descarga publica ni envio externo.

## Pruebas

La suite especifica cubre:

- PDF, JPG, PNG y WEBP.
- MIME real y limite configurable.
- SHA-256 y almacenamiento privado.
- Sustitucion con historial.
- Fallo de almacenamiento sin registro inconsistente.
- Descarga autorizada, 403 sin permiso y 404 entre contratos.
- Carga RRHH autorizada y verificacion por Supervisor.
- Independencia entre estado documental y contractual.
- Snapshots historicos de calendario.
- Cambio de mes y ano, ano bisiesto y frontera UTC/Bolivia.
- Avisos 7/3/0/pasado sin duplicados.
- Ausencia de campos de importe, rendimiento o pago.

## Pendientes

- Autorizar y ejecutar la migracion incremental en el entorno deseado.
- Configurar un canal y consentimiento antes de enviar WhatsApp, correo o push.
- Mantener las pruebas de autenticacion y autorizacion del portal PWA.
- Implementar entregas reales antes de usar `delivery_confirmed_at`.
- Realizar QA visual autenticado con datos de prueba despues de migrar un
  entorno autorizado.
