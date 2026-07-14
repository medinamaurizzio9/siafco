# SIAFCO

Sistema Integral de Afiliacion Cooperativa Tierra Bendita.

## Requisitos

- PHP 8.2 o superior
- Composer
- MySQL
- Node.js y npm
- Laragon en Windows

## Instalacion local en Windows con Laragon

Crear una base de datos MySQL llamada `siafco` y ejecutar:

```powershell
cd "C:\Users\M. Medina\Documents\SIAFCO\siafco"
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
npm install
npm run build
php artisan serve
```

Si PHP no esta en el PATH, use la ruta de Laragon:

```powershell
$env:PATH="C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64;" + $env:PATH
```

## Acceso inicial

- Correo: `admin@siafco.test`
- Password: `admin123456`

Tambien se crean usuarios de apoyo para `administrador_sector`, `secretaria`, `cajero` y `consulta`, todos con password `admin123456`.

## Modulos incluidos

- Autenticacion con login por correo.
- Roles: administrador, administrador_sector, secretaria, cajero, afiliado y consulta.
- Panel administrador responsive con metricas principales.
- Panel afiliado responsive.
- CRUD de sectores.
- CRUD de planes y precios de afiliacion.
- Registro de afiliados con CI unico y correo obligatorio.
- Numero correlativo por sector, por ejemplo `MAG-RUR-000001`.
- Estados de afiliado: pendiente_pago, activo, inactivo, observado.
- Pago inicial de afiliacion/credencial con QR institucional fijo.
- Numero de transaccion y comprobante opcional.
- Confirmacion o rechazo por caja.
- Activacion automatica del afiliado al confirmar pago.
- Credencial digital PDF con QR de verificacion.
- Credencial digital descargable en PDF y PNG, con vista de impresion.
- Verificacion publica en `/verificar/{token}` sin CI, celular, correo, direccion ni foto.
- Configuracion institucional en `/admin/configuracion-institucional` para nombre, logo, colores, correo, telefono, direccion y QR bancario fijo.
- Reportes basicos y auditoria basica.

## Preparado para fase posterior

Quedan tablas y modelos base para creditos, simulador, solicitud, cuotas, mora por atraso y reportes financieros.

## Rutas principales nuevas

- `/admin/configuracion-institucional`
- `/admin/credenciales/{afiliado}/preview`
- `/admin/credenciales/{afiliado}/pdf`
- `/admin/credenciales/{afiliado}/png`
- `/afiliado/credencial`
- `/afiliado/credencial/pdf`
- `/afiliado/credencial/png`
- `/verificar/{token}`

Solo administracion y secretaria pueden descargar credenciales desde administracion. El afiliado solo puede descargar su propia credencial cuando su estado es `activo`.
