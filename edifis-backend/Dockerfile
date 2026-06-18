# EDIFIS backend image (Cloud Brain + Lite Local Node — same image, ADR-004)
FROM php:8.3-cli-alpine

RUN apk add --no-cache \
    linux-headers \
    $PHPIZE_DEPS \
    libpng-dev \
    libzip-dev \
    postgresql-dev \
    postgresql-client \
    supervisor \
    brotli-dev \
    && docker-php-ext-install pdo_pgsql pgsql zip gd

RUN pecl install redis swoole \
    && docker-php-ext-enable redis swoole

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

COPY composer.json composer.lock ./

RUN composer install --no-dev --no-interaction --optimize-autoloader

COPY . .

RUN php artisan storage:link 2>/dev/null || true

EXPOSE 8000

CMD ["php", "artisan", "octane:start", "--host=0.0.0.0", "--port=8000"]
