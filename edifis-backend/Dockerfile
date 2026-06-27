# EDIFIS backend image (Cloud Brain + Lite Local Node — same image, ADR-004)
# Served by PHP-FPM + Nginx (true concurrency) under supervisor; Horizon overrides CMD.
FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
    linux-headers \
    $PHPIZE_DEPS \
    libpng-dev \
    libzip-dev \
    oniguruma-dev \
    postgresql-dev \
    postgresql-client \
    supervisor \
    nginx \
    brotli-dev \
    icu-dev \
    && docker-php-ext-install pdo_pgsql pgsql zip gd pcntl posix mbstring bcmath intl opcache

# OPcache — compile PHP once and keep it cached (huge speedup for Filament/Laravel).
RUN { \
        echo 'opcache.enable=1'; \
        echo 'opcache.enable_cli=0'; \
        echo 'opcache.memory_consumption=192'; \
        echo 'opcache.interned_strings_buffer=16'; \
        echo 'opcache.max_accelerated_files=20000'; \
        echo 'opcache.validate_timestamps=0'; \
        echo 'opcache.jit=disable'; \
    } > /usr/local/etc/php/conf.d/opcache.ini

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
    && (php artisan storage:link 2>/dev/null || true) \
    # dump-autoload / artisan above run as root and (re)write bootstrap/cache as
    # root:644. PHP-FPM serves as www-data, which must be able to rewrite the
    # compiled view/package caches — otherwise the first stale recompile 500s
    # every page. Hand the cache dirs to www-data as the final build step.
    && chown -R www-data:www-data storage bootstrap/cache

# PHP-FPM + Nginx under supervisor (production HTTP serving).
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/php-fpm-www.conf /usr/local/etc/php-fpm.d/www.conf
RUN mkdir -p /tmp/nginx-client /tmp/nginx-proxy /tmp/nginx-fastcgi /run/nginx

EXPOSE 8000

CMD ["supervisord", "-c", "/etc/supervisord.conf"]
