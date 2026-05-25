#!/usr/bin/env bash
set -euo pipefail

cd /var/www/html

# Generate APP_KEY on first boot if missing (compose passes APP_KEY via env normally)
if [ -z "${APP_KEY:-}" ] && [ ! -f .env ]; then
    echo "[entrypoint] APP_KEY not set; generating into runtime .env"
    cp .env.example .env
    php artisan key:generate --force --no-ansi --quiet
fi

# Ensure SQLite file exists when using the default driver (allows volume mount on first boot)
if [ "${DB_CONNECTION:-sqlite}" = "sqlite" ]; then
    DB_FILE="${DB_DATABASE:-/var/lib/nicewatch/database.sqlite}"
    if [ ! -f "$DB_FILE" ]; then
        echo "[entrypoint] Creating SQLite database at $DB_FILE"
        mkdir -p "$(dirname "$DB_FILE")"
        chmod 750 "$(dirname "$DB_FILE")"
        touch "$DB_FILE"
        chown www-data:www-data "$DB_FILE"
        # 600: nobody but the running php user (and root) should read tokens/hashes/secrets
        chmod 600 "$DB_FILE"
    fi
fi

# Only the primary container should run migrations / cache warm-up
if [ "${NICEWATCH_RUN_MIGRATIONS:-true}" = "true" ]; then
    echo "[entrypoint] Running database migrations"
    php artisan migrate --force --no-ansi

    # Seed once after a successful migration. The admin seeder is idempotent —
    # it skips if the account already exists, so re-runs are safe.
    if [ "${NICEWATCH_RUN_SEEDERS:-true}" = "true" ]; then
        echo "[entrypoint] Seeding initial data (idempotent)"
        php artisan db:seed --force --no-ansi --no-interaction
    fi
fi

if [ "${NICEWATCH_CACHE_CONFIG:-true}" = "true" ]; then
    echo "[entrypoint] Caching config / routes / views"
    php artisan config:cache --no-ansi
    php artisan route:cache --no-ansi
    php artisan view:cache --no-ansi
fi

# Hand off to whatever CMD the container was started with.
# PHP-FPM is special: its master process must stay root to fork worker processes
# under www-data (pool config in /usr/local/etc/php-fpm.d/www.conf handles the
# privilege drop for workers — application code never runs as root).
# For artisan commands (queue:work, schedule:work, tinker), drop to www-data
# explicitly so the PHP process itself runs unprivileged.
case "$1" in
    php-fpm|php-fpm,*)
        exec "$@"
        ;;
    *)
        exec su-exec www-data "$@"
        ;;
esac
