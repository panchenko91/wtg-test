FROM php:8.5.3-fpm

RUN apt-get update && apt-get install -y \
    libzip-dev \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install pdo pdo_mysql mysqli zip

COPY --from=composer /usr/bin/composer /usr/bin/composer
