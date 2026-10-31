# Inversiones Simple: transiciones y cierre P1

## Causa raiz

markPending validaba una instancia Eloquent potencialmente obsoleta y escribia
sin bloqueo. Una solicitud que habia leido draft podia sobrescribir una firma
confirmada por otra solicitud. void presentaba el mismo patron de lectura local.

## Solucion

markPending y void leen la fila vigente mediante lockForUpdate dentro de
DB::transaction. sign ya utilizaba ese mismo bloqueo y conserva esa estrategia.
Se serializan todas las transiciones sobre la misma fila. Error de validacion
controlado cuando el estado actual no permite la operacion. La auditoria se
guarda en la transaccion; rechazos no generan eventos ni cambian correlativos.
No se alteran cantidades, precios, reglas comerciales o aritmetica financiera.

| Estado vigente | Operacion | Resultado |
| --- | --- | --- |
| draft | pendiente | pending_signature |
| pending_signature | firma | signed, unidades y snapshots |
| signed | repetir firma | idempotente, sin nueva fecha ni unidades |
| draft / pending_signature / signed | anular | void, preservando historicos |
| void | repetir anular | idempotente, sin cambiar motivo/autor originales |
| signed / void / pending_signature | pendiente | rechazo controlado |
| draft / void | firma | rechazo controlado |

No existe transicion de reactivacion desde void. La instancia recibida sirve
solo para identificar el registro; no decide su estado vigente.

## Pruebas

La regresion original permanece en SimpleInvestmentFinalAuditTest. Se agregan
SimpleInvestmentTransitionsTest, ContractRaceWorker y VerifyContractConcurrency.
SQLite prueba estados, dos objetos obsoletos, idempotencia, fecha de firma,
snapshots, unidades, secuencias y ausencia de auditoria/efectos en rechazos.

Validacion de esta entrega:

- Suite Laravel: 617 pruebas, 4696 aserciones, aprobada.
- Suite Inversiones Simple: 46 pruebas, 366 aserciones, aprobada.
- Regresiones/transiciones/fase1: 20 pruebas, 134 aserciones, aprobadas.
- Service Worker Node: 4 pruebas aprobadas.
- Composer validate, Blade view:cache, Vite, Pint dirigido y diff --check: correctos.
- Vite mantiene la advertencia previa del chunk ApexCharts >500kB.

MySQL real 8.4.3: tres carreras con DOS esperas simultaneas comprobadas en
performance_schema.data_lock_waits, usando procesos PHP independientes y el
servicio real. No es una simulacion de bloqueos SQLite.

1. Pendiente vs pendiente: una aceptada, otra rechazada.
2. Firma vs pendiente obsoleto: firma aceptada; retroceso rechazado; fecha y
   snapshots 40/30 conservados, dos unidades unicas.
3. Firma vs anulacion: en la ejecucion registrada, anulacion aceptada primero y
   firma rechazada; estado final void. Si firma obtiene antes el bloqueo, puede
   completarse antes de anular; nunca se reactiva un contrato anulado.

Se verifico ausencia de unidades duplicadas y trazabilidad de los eventos. Cuenta
SQL restringida al esquema temporal, estructura sin datos personales copiados,
fixtures sinteticos. Sin ejecutar migraciones: solo DDL para preparar el esquema
efimero. Cuenta y esquema temporales eliminados; las 72 tablas de la base local
conservaron checksums. El runner requiere entorno/host/rama locales exactos y
no admite un nombre de esquema arbitrario.

Ejecucion optativa local: php tests/Support/VerifyContractConcurrency.php.
Requiere MySQL local 8.4.3 y privilegios para crear/eliminar el esquema y la
cuenta temporales, inspeccionar metadata y performance_schema. No usar en
produccion. No forma parte de la suite automatica estandar de CI.

## Formato

Corregidos los cuatro archivos senalados en auditoria:

- app/Http/Controllers/SimpleInvestorAccessController.php
- app/Models/SimpleInvestmentPortalNotification.php
- app/Services/SimpleInvestorNotificationService.php
- app/Http/Middleware/EnsureSimpleInvestorAuthenticated.php

Adicionalmente Pint se limito al servicio/pruebas modificados y a orden de imports
pendiente en AppServiceProvider y bootstrap/app.php, integraciones del modulo.
No hubo reformateo global ni modificacion de composer.lock.

## Portal y limites

Entrada publica exclusiva /mi-inversion; inicio privado /mi-inversion/inicio.
Credenciales emitidas por operador autorizado; sin autorregistro. Sesion de
inversionista no concede autenticacion administrativa. Infraestructura Laravel
de sesiones compartida: no se implementan cookies independientes.

Un solo layout responsive: sidebar PC, navegacion inferior movil, tablas que se
apilan en pantallas pequenas. Scope /mi-inversion/, caches aisladas, red
obligatoria para contenido privado. No se validaron instalacion fisica Android
ni cookies HTTPS de produccion. Sin rendimientos, pagos ni envios externos.

Persisten como riesgos posteriores no resueltos por este P1: concurrencia de
resets/login y session_version, atomicidad de fotografia y validacion de canales
de contacto. No se declaran pruebas de carrera de autenticacion por haber
probado exclusivamente las transiciones contractuales.

## Preparacion Git

El manifiesto inversiones-simple-commit-files.txt enumera las rutas exactas
seleccionadas desde la raiz de la aplicacion. No autoriza commit, push o deploy.
Se excluyen lockfile previo, cambios RRHH, respaldos, .env/secretos, reportes
locales, scripts manuales artifacts/, capturas y documentacion operativa privada.
Las migraciones incluidas son las tres existentes del modulo, no nuevas y no
ejecutadas contra la base principal en esta tarea.

Las pruebas completas se ejecutaron sobre el working tree, que conserva una
correccion de fechas RRHH ajena, excluida del indice. No equivalen a ejecutar una
instalacion limpia desde un commit futuro ni a validar CI con otras dependencias.
