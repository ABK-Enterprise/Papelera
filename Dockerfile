FROM php:8.4-cli-bookworm

# Variables de entorno para Node, Composer y Chromium/PDF
ENV DEBIAN_FRONTEND=noninteractive \
    COMPOSER_ALLOW_SUPERUSER=1 \
    NODE_VERSION=22 \
    PUPPETEER_SKIP_CHROMIUM_DOWNLOAD=true \
    PUPPETEER_EXECUTABLE_PATH=/usr/bin/chromium \
    LARAVEL_PDF_CHROME_PATH=/usr/bin/chromium \
    LARAVEL_PDF_NO_SANDBOX=true \
    LARAVEL_PDF_NODE_MODULES_PATH=/var/www/html/node_modules

# 1. Instalar dependencias del sistema, extensiones de PHP y Chromium para PDFs
RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    curl \
    unzip \
    libpq-dev \
    libzip-dev \
    libpng-dev \
    libicu-dev \
    libxml2-dev \
    openssl \
    ca-certificates \
    chromium \
    fonts-liberation \
    fonts-noto-color-emoji \
    && docker-php-ext-install -j$(nproc) \
    pdo \
    pdo_pgsql \
    pgsql \
    zip \
    bcmath \
    intl \
    soap \
    pcntl \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# 2. Instalar Composer oficial
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# 3. Instalar Node.js y pnpm
RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && npm install -g pnpm \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# 4. Configurar directorio de trabajo
WORKDIR /var/www/html

# 5. Copiar composer.json y lock para cachear dependencias PHP
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --no-autoloader --prefer-dist

# 6. Copiar package.json, pnpm-lock y configuración de pnpm para cachear dependencias Frontend
COPY package.json pnpm-lock.yaml pnpm-workspace.yaml* .npmrc* ./
RUN pnpm install --frozen-lockfile --dangerously-allow-all-builds || pnpm install --frozen-lockfile

# 7. Copiar el resto del código de la aplicación
COPY . .

# 8. Generar autoloader optimizado de Composer
RUN composer dump-autoload --optimize --no-dev --classmap-authoritative

# 9. Compilar los assets con Vite + Svelte (inyectando build args desde Render)
ARG VITE_BROADCASTER=pusher
ARG VITE_PUSHER_APP_KEY
ARG VITE_PUSHER_APP_CLUSTER=sa1
ENV VITE_BROADCASTER=$VITE_BROADCASTER \
    VITE_PUSHER_APP_KEY=$VITE_PUSHER_APP_KEY \
    VITE_PUSHER_APP_CLUSTER=$VITE_PUSHER_APP_CLUSTER
RUN pnpm run build

# 10. Permisos para storage y bootstrap/cache
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod +x /var/www/html/docker/entrypoint.sh

# 11. Puerto por defecto expuesto
EXPOSE 8080

# 12. Script de inicio
ENTRYPOINT ["/var/www/html/docker/entrypoint.sh"]
