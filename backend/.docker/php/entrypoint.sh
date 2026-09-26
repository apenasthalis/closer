#!/bin/sh
set -e

if [ ! -d "vendor" ]; then
    composer install --no-interaction --optimize-autoloader --no-scripts
fi

chown -R www-data:www-data storage bootstrap/cache

exec docker-php-entrypoint "$@"
