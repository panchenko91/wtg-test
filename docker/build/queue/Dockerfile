FROM php:8.5.3-cli

RUN apt-get update && apt-get install -y \
    libzip-dev \
    dos2unix \
    && docker-php-ext-install pdo pdo_mysql zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY start-queue /usr/local/bin/

RUN dos2unix /usr/local/bin/start-queue \
    && chmod +x /usr/local/bin/start-queue \
    && apt-get --purge remove -y dos2unix

WORKDIR /var/www/html

ENTRYPOINT ["start-queue"]

CMD []
