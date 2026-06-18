# Lean EDIFIS app container — tiny Alpine base (~30MB) + Postgres ext, NO Swoole.
# Smaller download than the Debian image; reliable on slow connections.
FROM php:8.3-cli-alpine

RUN apk add --no-cache postgresql-dev libpng-dev libzip-dev oniguruma-dev \
    && apk add --no-cache --virtual .build $PHPIZE_DEPS \
    && docker-php-ext-install pdo_pgsql gd zip mbstring bcmath \
    && apk del .build

# (composer not needed at runtime — vendor/ is bind-mounted)
WORKDIR /var/www
EXPOSE 8000
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
