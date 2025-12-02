FROM composer:2 AS composer

FROM php:8.4-fpm

RUN apt-get update \
    && apt-get install -y \
        git \
        curl \
        zip \
        unzip \
        libpng-dev \
        libonig-dev \
        libxml2-dev \
        libzip-dev \
        libicu-dev \
    && docker-php-ext-install pdo_mysql mbstring zip exif pcntl bcmath intl \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . /var/www/html

RUN composer install --no-interaction --prefer-dist --optimize-autoloader

CMD ["php-fpm"]
