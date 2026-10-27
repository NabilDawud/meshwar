# 1. Frontend Asset Build
FROM node:24-alpine AS frontend
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY . .
RUN npm run build

# 2. Production Runner (PHP 8.4 + FrankenPHP)
FROM dunglas/frankenphp:1-php8.4-alpine AS runner

# Copy official Composer binary
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN install-php-extensions \
    pdo_mysql \
    bcmath \
    opcache \
    intl \
    zip \
    pcntl

WORKDIR /app

# Copy application files
COPY . .
COPY --from=frontend /app/public/build ./public/build

# Install production dependencies
ENV COMPOSER_ALLOW_SUPERUSER=1
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Ensure storage and bootstrap/cache permissions
RUN chown -R www-data:www-data storage bootstrap/cache && \
    chmod -R 775 storage bootstrap/cache

ENV PORT=80

# Startup: ensure key, migrate database, seed demo data, optimize caches, then boot server
CMD ["sh", "-c", "if [ -z \"$APP_KEY\" ]; then php artisan key:generate --force; fi && php artisan migrate --force && php artisan db:seed --force && php artisan optimize && frankenphp run"]

