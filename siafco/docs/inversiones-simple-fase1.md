# Inversiones Simple - Fase 1

## Alcance

Módulo independiente para registrar inversionistas, contratos y unidades individuales de acciones. Usa exclusivamente tablas `simple_investment_*`, rutas `/inversiones-simple` y permisos `simple_investments.*`.

No reutiliza tablas del módulo retirado de inversiones formales ni modifica el CRM de Inversiones. Tampoco registra pagos, recibos, rendimientos, obligaciones o cronogramas financieros.

## Modelo y seguridad

- `simple_investment_settings`: configuración vigente del producto, precio y límites.
- `simple_investment_investors`: ficha independiente con fotografía privada.
- `simple_investment_contracts`: instantánea histórica del producto y precio.
- `simple_investment_units`: unidades `ACC-000001` emitidas al firmar.
- `simple_investment_sequences`: correlativos bloqueados con `lockForUpdate()`.

La firma es transaccional e idempotente. Los importes se calculan en centavos enteros. Los contratos firmados no se eliminan: pueden anularse conservando unidades y auditoría.

El rol `supervisor_inversiones` administra el módulo. Superadministrador conserva acceso total. RRHH no recibe acceso automáticamente; solo puede obtenerlo mediante una concesión explícita en la matriz de permisos. Gerencia y afiliados no reciben permisos en esta fase.

## Flujo

1. Registrar inversionista.
2. Crear contrato en borrador con precio vigente copiado como instantánea.
3. Pasar a pendiente de firma.
4. Registrar firma y emitir unidades únicas.
5. Consultar o imprimir el resumen, marcado expresamente como no comprobante de pago.

## Fases futuras

Pagos, recibos, obligaciones, cronogramas, rendimientos e informes financieros quedan fuera de esta fase y requieren diseño y autorización independientes.
