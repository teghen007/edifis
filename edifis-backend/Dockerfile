# EDIFIS backend image (Cloud Brain + Lite Local Node — same image, ADR-004)
FROM php:8.3-cli-alpine

RUN apk add --no-cache \
    linux-headers \
    $PHPIZE_DEPS \
    libpng-dev \
    libzip-dev \
    oniguruma-dev \
    postgresql-dev \
    postgresql-client \
    supervisor \
    brotli-dev \
    icu-dev \
    && docker-php-ext-install pdo_pgsql pgsql zip gd pcntl posix mbstring bcmath intl

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

COPY composer.json composer.lock ./

RUN composer install --no-dev --no-interaction --no-scripts --prefer-dist --optimize-autoloader \
    --ignore-platform-reqs

COPY . .

# App is present now: finish autoload (runs package discovery) + storage link.
# NOTE: list dirs explicitly — Docker RUN uses /bin/sh, which does NOT expand
# braces, so storage/framework/{cache,...} would make one literal folder and
# storage/framework/views would be missing ("Please provide a valid cache path").
RUN mkdir -p \
        bootstrap/cache \
        storage/framework/cache \
        storage/framework/sessions \
        storage/framework/views \
        storage/framework/testing \
        storage/logs \
    && chmod -R 777 storage bootstrap/cache \
    && composer dump-autoload --optimize --no-dev \
    && (php artisan storage:link 2>/dev/null || true)

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
