FROM composer:2 AS composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --prefer-dist \
    --optimize-autoloader

FROM node:22-alpine AS frontend

WORKDIR /app

COPY package.json package-lock.json* ./
RUN npm install --ignore-scripts

COPY resources ./resources
COPY vite.config.js ./
RUN npm run build

FROM php:8.4-fpm-bookworm AS app

ENV APP_ENV=production

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        curl \
        gnupg \
        libicu72 \
        libicu-dev \
        libsqlite3-0 \
        libsqlite3-dev \
        libzip4 \
        libzip-dev \
        unzip \
    && curl -fsSL https://packages.microsoft.com/keys/microsoft.asc \
        | gpg --dearmor -o /usr/share/keyrings/microsoft-prod.gpg \
    && curl -fsSL https://packages.microsoft.com/config/debian/12/prod.list \
        > /etc/apt/sources.list.d/microsoft-prod.list \
    && apt-get update \
    && ACCEPT_EULA=Y apt-get install -y --no-install-recommends msodbcsql18 unixodbc-dev \
    && docker-php-ext-install -j"$(nproc)" bcmath intl opcache pcntl pdo_mysql pdo_sqlite zip \
    && pecl install sqlsrv pdo_sqlsrv \
    && docker-php-ext-enable sqlsrv pdo_sqlsrv \
    && apt-get purge -y --auto-remove gnupg libicu-dev libsqlite3-dev libzip-dev unixodbc-dev \
    && rm -rf /var/lib/apt/lists/* /tmp/pear

WORKDIR /var/www/html

COPY . .
COPY --from=composer /app/vendor ./vendor
COPY --from=frontend /app/public/build ./public/build
COPY docker/php/entrypoint.sh /usr/local/bin/app-entrypoint
COPY docker/php/uploads.ini /usr/local/etc/php/conf.d/uploads.ini

RUN chmod +x /usr/local/bin/app-entrypoint \
    && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs \
    && php artisan package:discover --ansi \
    && chown -R www-data:www-data storage bootstrap/cache

ENTRYPOINT ["app-entrypoint"]
CMD ["php-fpm"]

FROM nginx:1.27-alpine AS nginx

WORKDIR /var/www/html

COPY --from=app /var/www/html/public ./public
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
