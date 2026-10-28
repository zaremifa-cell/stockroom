#!/bin/sh
set -e

# Hosts often provide DATABASE_URL; Laravel reads DB_URL.
if [ -n "$DATABASE_URL" ]; then
    export DB_CONNECTION=pgsql DB_URL="$DATABASE_URL"
fi

# Accept a Laravel key ("base64:…") or any random secret, which is turned into a valid 32-byte key.
case "$APP_KEY" in
    base64:*) ;;
    "") echo "APP_KEY is not set" >&2; exit 1 ;;
    *) APP_KEY=$(php -r 'echo "base64:".base64_encode(hash("sha256", getenv("APP_KEY"), true));'); export APP_KEY ;;
esac

php artisan demo:prepare
php artisan config:cache
php artisan route:cache
php artisan view:cache
exec "$@"
