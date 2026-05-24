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
# Ensure local UX packages from composer are available to npm/webpack
RUN mkdir -p node_modules/@symfony/ux-turbo \
    && cp -R vendor/symfony/ux-turbo/assets/* node_modules/@symfony/ux-turbo/ || true
RUN npm ci --silent
RUN npm run build

# Final image with PHP + Apache
FROM php:8.2-apache

ENV APP_ENV=prod \
    APP_DEBUG=0

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

# Ensure only one Apache MPM is enabled. Disable common conflicting MPMs and enable prefork
# prefork is compatible with mod_php used in the php:*-apache images.
RUN a2dismod mpm_event mpm_worker || true \
    && rm -f /etc/apache2/mods-enabled/mpm_event.load /etc/apache2/mods-enabled/mpm_event.conf \
    && rm -f /etc/apache2/mods-enabled/mpm_worker.load /etc/apache2/mods-enabled/mpm_worker.conf \
    && a2enmod mpm_prefork || true \
    && a2enmod rewrite
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri 's!DocumentRoot /var/www/html!DocumentRoot ${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri 's!<Directory /var/www/html>!<Directory ${APACHE_DOCUMENT_ROOT}>!g' /etc/apache2/apache2.conf /etc/apache2/sites-available/*.conf

# Send all non-file routes to Symfony's front controller so paths like /login work.
RUN printf '<Directory /var/www/html/public>\n    AllowOverride All\n    FallbackResource /index.php\n</Directory>\n' > /etc/apache2/conf-available/symfony-fallback.conf \
    && a2enconf symfony-fallback

RUN mkdir -p var public && chown -R www-data:www-data var public

# Add entrypoint script to fix Apache MPM conflicts at container start
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 80
ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
CMD ["apache2-foreground"]
