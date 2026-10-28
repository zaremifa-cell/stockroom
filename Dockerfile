# 1. Build the Vue frontend.
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json vite.config.js ./
RUN npm ci
COPY resources ./resources
RUN npm run build

# 2. PHP-FPM with the PostgreSQL driver and production dependencies only.
FROM php:8.5-fpm-alpine AS app
RUN apk add --no-cache postgresql-dev libzip-dev \
 && docker-php-ext-install pdo_pgsql opcache
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist
COPY . .
COPY --from=assets /app/public/build ./public/build
RUN composer dump-autoload --optimize \
 && chown -R www-data:www-data storage bootstrap/cache
COPY docker/entrypoint.sh /usr/local/bin/entrypoint
ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]
