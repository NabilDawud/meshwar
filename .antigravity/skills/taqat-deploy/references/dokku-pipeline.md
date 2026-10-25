# Dokku Pipeline & Laravel Dockerfile Specification

## 1. Multi-Stage Production Dockerfile (Laravel 13 + PHP 8.4)
```dockerfile
# 1. Frontend Asset Build
FROM node:24-alpine AS frontend
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY . .
RUN npm run build

# 2. Production Runner (PHP 8.4 FPM + Nginx or FrankenPHP)
FROM dunglas/frankenphp:1-php8.4-alpine AS runner

RUN install-php-extensions \
    pdo_mysql \
    bcmath \
    opcache \
    intl \
    zip

WORKDIR /app

# Copy Composer dependencies & app files
COPY . .
COPY --from=frontend /app/public/build ./public/build

# Install production dependencies
ENV COMPOSER_ALLOW_SUPERUSER=1
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Production optimizations
RUN php artisan config:cache && \
    php artisan route:cache && \
    php artisan view:cache

ENV PORT=80
ENV FRANKENPHP_CONFIG="worker ./public/index.php"

# Startup: run migrations; stop immediately on failure, then boot server
CMD php artisan migrate --force && frankenphp run
```

## 2. Health Endpoint (`routes/web.php` or `routes/api.php`)
```php
use Illuminate\Support\Facades\Route;

Route::get('/api/health', function () {
    return response()->json([
        'ok' => true,
        'commit' => env('GIT_REV', 'unknown'),
    ]);
});
```

## 3. Secure Secret Transfer Pattern via SSH
When setting Dokku environment configuration:
```bash
# Push config variables silently without echoing to terminal
ssh -T dokku@taqat.academy config:set --no-restart mishwar < .env.production > /dev/null 2>&1
```
