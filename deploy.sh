#!/usr/bin/env bash
set -euo pipefail

if [ -f artisan ]; then
    APP_DIR="."
elif [ -f siafco/artisan ]; then
    APP_DIR="siafco"
else
    echo "No Laravel artisan file was found in the current directory or ./siafco." >&2
    exit 1
fi

git pull origin main

cd "$APP_DIR"

composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan storage:link || true
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
