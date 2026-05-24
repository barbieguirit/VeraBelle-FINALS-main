# Multi-stage Dockerfile for deploying the Symfony app to Railway

# Composer stage: install PHP deps to produce `vendor/` for file: dependencies used by npm
FROM composer:2 AS composer
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --optimize-autoloader

# Build assets with Node (has access to vendor files from composer stage)
FROM node:18 AS node_builder
WORKDIR /app
COPY package.json package-lock.json* ./
COPY --from=composer /app/vendor ./vendor
COPY assets/ ./assets
COPY webpack.config.js ./
RUN npm ci --silent
RUN npm run build

# Final image with PHP + Apache
FROM php:8.2-apache

RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    unzip \
    libicu-dev \
    libzip-dev \
    zlib1g-dev \
    libpng-dev \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install intl pdo pdo_mysql zip opcache


# Copy composer binary from the composer stage
COPY --from=composer /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy composer files and vendor from composer stage
COPY composer.json composer.lock ./
COPY --from=composer /app/vendor ./vendor

# Copy built assets from node stage
COPY --from=node_builder /app/public/build public/build

# Copy the rest of the application
COPY . .

RUN composer dump-autoload --optimize

RUN a2enmod rewrite
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri 's!DocumentRoot /var/www/html!DocumentRoot ${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri 's!<Directory /var/www/html>!<Directory ${APACHE_DOCUMENT_ROOT}>!g' /etc/apache2/apache2.conf /etc/apache2/sites-available/*.conf

RUN chown -R www-data:www-data var public

EXPOSE 80
CMD ["apache2-foreground"]
