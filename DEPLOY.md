# Despliegue de SIAFCO en cPanel

Guia para desplegar SIAFCO en hosting compartido con cPanel, PHP 8.4 y MySQL/MariaDB.

## Requisitos del hosting

- PHP 8.4 con extensiones: `pdo_mysql`, `mbstring`, `fileinfo`, `gd`, `zip`, `dom`.
- MySQL o MariaDB 10.6+.
- Composer disponible por SSH.
- Git disponible por SSH o Git Version Control de cPanel.
- El servidor no necesita Node.js. Los assets de Vite se compilan en GitHub Actions.

## Variables de entorno

Crear `.env` en la carpeta Laravel y usar valores de produccion:

```env
APP_NAME="SIAFCO Tierra Bendita"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://tu-dominio.com

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=usuario_siafco
DB_USERNAME=usuario_siafco
DB_PASSWORD=clave-segura

FILESYSTEM_DISK=public
SESSION_DRIVER=database
QUEUE_CONNECTION=sync
CACHE_STORE=database

SIAFCO_SEED_DEMO_DATA=false
SIAFCO_SEED_SUPPORT_USERS=false
SIAFCO_ADMIN_EMAIL=admin@tu-dominio.com
SIAFCO_ADMIN_PASSWORD=clave-inicial-segura
```

Ejecutar una sola vez:

```bash
php artisan key:generate
```

## Estructura recomendada

Opcion con Git:

```text
/home/usuario/repositories/SIAFCO
/home/usuario/repositories/SIAFCO/siafco
/home/usuario/public_html
```

Configurar el document root del dominio o subdominio hacia:

```text
/home/usuario/repositories/SIAFCO/siafco/public
```

Si el hosting no permite cambiar el document root, copiar el contenido de `public/` a `public_html` y ajustar `public_html/index.php` para apuntar a la carpeta real del proyecto.

## Assets de Vite

El servidor no tiene Node.js. Por eso:

- GitHub Actions ejecuta `npm install` y `npm run build`.
- El directorio `public/build` debe subirse al servidor.
- `public/build/manifest.json` debe existir para evitar `ViteManifestNotFoundException`.
- `.gitignore` no ignora `public/build`.

En cada release de GitHub se adjuntan:

- `public-build.zip`: solo assets compilados.
- `release.zip`: paquete de produccion con `vendor` y `public/build`.

## Despliegue con git pull

Desde SSH, ubicarse en la raiz del repositorio:

```bash
cd /home/usuario/repositories/SIAFCO
chmod +x deploy.sh
./deploy.sh
```

El script ejecuta:

```bash
git pull origin main
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan storage:link || true
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Primer despliegue

1. Crear base de datos MySQL/MariaDB desde cPanel.
2. Crear usuario de base de datos y asignar permisos.
3. Clonar el repositorio.
4. Crear `.env`.
5. Ejecutar:

```bash
composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan migrate --force --seed
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

6. Descargar `public-build.zip` desde el release de GitHub y extraerlo dentro de `public/`, o desplegar `release.zip`.

## Permisos

En cPanel normalmente basta con:

```bash
chmod -R 775 storage bootstrap/cache
```

Si el proveedor usa permisos restrictivos, revisar propietario/grupo del usuario de cPanel.

## Storage, imagenes, QR y PDFs

SIAFCO usa `storage/app/public` para:

- Fotos de afiliados.
- Comprobantes.
- QR institucional.
- QR de credenciales.
- Credenciales PNG/PDF.
- Logos.

Debe existir el enlace:

```bash
public/storage -> storage/app/public
```

Se crea con:

```bash
php artisan storage:link
```

DomPDF y los QR no requieren Node.js.

## Seguridad

- Usar `APP_ENV=production`.
- Usar `APP_DEBUG=false`.
- No subir `.env` al repositorio.
- Cambiar `SIAFCO_ADMIN_PASSWORD` antes del primer seed.
- Mantener `SIAFCO_SEED_DEMO_DATA=false` en produccion.
- Mantener `SIAFCO_SEED_SUPPORT_USERS=false` en produccion salvo que se necesiten usuarios de prueba temporalmente.

## Verificacion posterior

Ejecutar:

```bash
php artisan about
php artisan route:list
php artisan migrate:status
```

Probar en navegador:

- `/login`
- `/dashboard`
- `/afiliados`
- `/inversiones/dashboard`
- descarga de credencial PDF
- descarga de recibo PDF
- reportes PDF
- `/verificar/{token}`

## Solucion a ViteManifestNotFoundException

Si aparece:

```text
ViteManifestNotFoundException: Vite manifest not found
```

Verificar:

```bash
ls -la public/build/manifest.json
```

Si no existe, descargar `public-build.zip` del release de GitHub y extraerlo en `public/`.
