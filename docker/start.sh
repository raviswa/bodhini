#!/bin/sh
set -e

if [ -z "$APP_KEY" ]; then
    echo "Railway is missing APP_KEY. Generate one with: php artisan key:generate --show"
    exit 1
fi

if [ -z "$DATABASE_URL" ] && [ -z "$DB_HOST" ]; then
    echo "Railway is missing the Supabase database. Set DATABASE_URL to the Postgres connection string."
    exit 1
fi

php artisan migrate --force --no-interaction
exec php artisan serve --host=0.0.0.0 --port="${PORT:-8080}" --no-reload
