#!/bin/sh
set -e

[ -f .env ] || cp .env.example .env

# Only the web container prepares the (shared) SQLite database; the queue worker waits for it.
if [ "${RUN_SETUP:-true}" = "true" ]; then
    DB_FILE="${DB_DATABASE:-database/database.sqlite}"
    if [ ! -f "$DB_FILE" ]; then
        mkdir -p "$(dirname "$DB_FILE")"
        touch "$DB_FILE"
        php artisan migrate --seed --force
    else
        php artisan migrate --force
    fi
fi

exec "$@"
