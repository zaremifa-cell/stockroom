#!/bin/sh
set -e
sed "s/__PORT__/${PORT:-8080}/" /etc/nginx/stockroom.conf.template > /etc/nginx/http.d/default.conf
php-fpm -D
exec nginx -g 'daemon off;'
