# One container that serves HTTP itself (nginx → PHP-FPM), as Render and most PaaS hosts expect.

# 1. Build the Vue frontend.
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json vite.config.js ./
RUN npm ci
COPY resources ./resources
RUN npm run build

# 2. PHP-FPM + nginx with the PostgreSQL driver and production dependencies only.
#    OPcache is built into PHP 8.5, so only pdo_pgsql is compiled.
FROM php:8.5-fpm-alpine
RUN apk add --no-cache nginx postgresql-dev \
 && docker-php-ext-install pdo_pgsql
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist
COPY . .
COPY --from=assets /app/public/build ./public/build
RUN composer dump-autoload --optimize \
 && chown -R www-data:www-data storage bootstrap/cache
COPY docker/nginx.conf.template /etc/nginx/stockroom.conf.template
COPY docker/entrypoint.sh /usr/local/bin/entrypoint
COPY docker/start.sh /usr/local/bin/start
ENV PORT=8080
EXPOSE 8080
ENTRYPOINT ["entrypoint"]
CMD ["start"]
