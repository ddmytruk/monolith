FROM php:8.4-fpm


RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        git unzip libzip-dev libpq-dev libicu-dev; \
    docker-php-ext-configure intl; \
    docker-php-ext-install -j$(nproc) pdo_pgsql bcmath intl zip; \
    rm -rf /var/lib/apt/lists/*

RUN pecl install redis \
    && docker-php-ext-enable redis

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY php.ini /usr/local/etc/php/conf.d/99-app.ini
