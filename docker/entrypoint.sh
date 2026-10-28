#!/bin/sh
set -e
# Apply migrations on start; seeding is idempotent (updateOrCreate), so demo data stays consistent.
php artisan migrate --force
php artisan db:seed --force
php artisan config:cache
php artisan route:cache
exec "$@"
