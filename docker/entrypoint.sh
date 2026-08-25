#!/bin/sh
set -e

echo "Optimizando configuración de Laravel..."
php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true

echo "Ejecutando migraciones de base de datos..."
php artisan migrate --force || true

echo "Creando symlink de storage..."
php artisan storage:link || true

PORT_TO_USE=${PORT:-8080}
echo "Arrancando servidor Laravel en el puerto $PORT_TO_USE..."
exec php artisan serve --host=0.0.0.0 --port=$PORT_TO_USE
