#!/bin/sh
# EDIFIS lab entrypoint. Same script for cloud + every node — behaviour differs
# only by the env passed in (ADR-004: one codebase, mode via .env).
set -e
cd /var/www

echo "[lab:$EDIFIS_MODE] waiting for ${DB_HOST}:${DB_PORT} ..."
until php -r "try { new PDO('pgsql:host='.getenv('DB_HOST').';port='.getenv('DB_PORT').';dbname='.getenv('DB_DATABASE'), getenv('DB_USERNAME'), getenv('DB_PASSWORD')); } catch (Throwable \$e) { exit(1); }" 2>/dev/null; do
  sleep 2
done
echo "[lab:$EDIFIS_MODE] database is up."

# vendor is installed once by the 'init' service and shared via the bind mount.
if [ ! -d vendor ]; then
  echo "[lab] vendor missing — installing (fallback) ..."
  composer install --no-interaction --prefer-dist
fi

# Each server has its OWN database, so migrate runs per-server.
php artisan migrate --force

echo "[lab:$EDIFIS_MODE] starting Octane on :8000 ..."
exec php artisan octane:start --host=0.0.0.0 --port=8000
