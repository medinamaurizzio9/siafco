# SIAFCO Design System

Este sistema visual centraliza la interfaz web de SIAFCO para mantener una experiencia consistente, accesible y reutilizable.

## Tokens

Los tokens viven en `resources/css/app.css`:

- Colores: primario institucional, dorado, turquesa, fondos, texto y estados.
- Tipografia: jerarquia basada en `Instrument Sans` y clases `ds-title-*`.
- Espaciado: escala de 4, 8, 12, 16, 24, 32, 40, 48, 64 y 80 px mediante variables `--ds-space-*`.
- Bordes y radios: `--ds-radius-sm`, `--ds-radius-md`, `--ds-radius-lg`.
- Sombras: `--ds-shadow-sm`, `--ds-shadow-md`.
- Animaciones: 150 a 200 ms mediante `--ds-duration-*`.

## Componentes Blade

Componentes disponibles:

- `x-ui.button`: botones primary, secondary, outline, ghost, success, warning, danger e icon.
- `x-ui.card`: tarjetas default, form, summary, action y kpi.
- `x-ui.alert`: alertas success, warning, danger e info.
- `x-ui.empty-state`: estados vacios con icono, mensaje y accion opcional.
- `x-ui.skeleton`: skeleton loaders reutilizables.
- `x-ui.modal`: modal small, medium, large y fullscreen.
- `x-ui.table`: contenedor responsivo de tablas.
- `x-ui.icon`: iconografia unica del sistema.

## Buenas Practicas

- Usar componentes Blade antes de escribir estilos nuevos.
- Evitar colores hexadecimales directos en vistas nuevas.
- Usar `btn-*`, `form-input`, `section-card`, `table`, `badge` cuando se requiera compatibilidad con vistas existentes.
- Mantener foco visible en botones, enlaces y campos.
- No usar tablas vacias: preferir `x-ui.empty-state`.
- No mezclar librerias de iconos; usar `x-ui.icon`.
- Animar solo transiciones discretas de 150 a 250 ms.

## Responsive

El layout base cubre vistas desde 360 px hasta escritorio amplio. Las tablas deben envolver su contenido con `x-ui.table` o `.table-wrap` para mantener scroll horizontal controlado.

## Dashboard Ejecutivo

El dashboard administrativo usa el Design System como centro de operaciones institucional:

- Header ejecutivo con saludo contextual, fecha, breadcrumb, buscador preparado, rol, avatar y notificaciones placeholder.
- KPIs con `x-ui.card`, `x-ui.badge`, `x-ui.button` e iconografia unica.
- Centro de alertas basado en datos existentes y placeholders elegantes cuando falta un dato del controlador.
- Timeline operativo con pagos recientes y `x-ui.empty-state` cuando no existen registros.
- Resumen financiero sin recalcular fuera de los datos disponibles en la vista.
- Estado del sistema preparado para `SiafcoHealthCheckService`.
- Graficos con ApexCharts cargados de forma diferida desde `resources/js/dashboard.js`.

Reglas:

- No consultar ni inventar datos en nuevas tarjetas si el controlador no los entrega.
- Usar "Sin datos" para integraciones pendientes.
- Mantener microanimaciones discretas y carga diferida de graficos.
- No agregar scroll horizontal en resoluciones moviles.
