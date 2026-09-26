FROM php:8.3-fpm

COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

RUN apt-get update \
 && apt-get install -y libpq-dev git unzip curl libcurl4-openssl-dev \
    libonig-dev libxml2-dev libzip-dev libpng-dev libjpeg-dev libfreetype6-dev \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install pdo pdo_pgsql curl mbstring xml bcmath ctype zip gd fileinfo

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

WORKDIR /var/www/html

COPY .docker/php/entrypoint.sh /usr/local/bin/docker-custom-entrypoint
RUN chmod +x /usr/local/bin/docker-custom-entrypoint

ENTRYPOINT ["docker-custom-entrypoint"]
CMD ["php-fpm"]
