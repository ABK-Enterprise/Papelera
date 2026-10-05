#!/bin/sh
set -e

echo "Optimizando configuración de Laravel..."
php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true

if [ "$MIGRATE_FRESH" = "true" ]; then
    echo "⚠️ MIGRATE_FRESH=true detectado: Reseteando base de datos completa con seeders..."
    php artisan migrate:fresh --force || true
    php artisan db:seed --class=BaseSeeder --force || true
    php artisan db:seed --class=ArgentinaGeodataSeeder --force || true
    php artisan db:seed --class=TestSeeder --force || true
else
    echo "Ejecutando migraciones incrementales de base de datos..."
    php artisan migrate --force || true
fi

if [ "$RUN_SEEDERS" = "true" ]; then
    echo "⚠️ RUN_SEEDERS=true detectado: Ejecutando seeders de plataforma..."
    php artisan db:seed --class=BaseSeeder --force || true
    php artisan db:seed --class=ArgentinaGeodataSeeder --force || true
fi

echo "Creando symlink de storage..."
php artisan storage:link || true

PORT_TO_USE=${PORT:-8080}
export PORT=$PORT_TO_USE
echo "Arrancando FrankenPHP en el puerto $PORT_TO_USE..."
exec frankenphp run --config /etc/caddy/Caddyfile
