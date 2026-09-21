FROM php:8.5-fpm-alpine AS base

RUN apk add --no-cache \
        icu-data-full \
        icu-libs \
        libpq \
        libzip \
    && apk add --no-cache --virtual .build-dependencies \
        $PHPIZE_DEPS \
        icu-dev \
        libpq-dev \
        libzip-dev \
    && docker-php-ext-install -j"$(nproc)" intl pcntl pdo_pgsql zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-dependencies

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-badal.ini

WORKDIR /var/www/html

FROM base AS development

RUN apk add --no-cache git

COPY docker/php/entrypoint.sh /usr/local/bin/badal-entrypoint
RUN chmod +x /usr/local/bin/badal-entrypoint

ENTRYPOINT ["badal-entrypoint"]
CMD ["php-fpm"]

FROM node:24-alpine AS assets

WORKDIR /app

COPY package.json package-lock.json .npmrc ./
RUN npm ci

COPY vite.config.js ./
COPY app ./app
COPY resources ./resources
RUN npm run build

FROM base AS production

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts

COPY . .
COPY --from=assets /app/public/build public/build

RUN echo 'opcache.validate_timestamps = 0' > /usr/local/etc/php/conf.d/99-production.ini \
    && composer dump-autoload --no-dev --optimize \
    && chown -R www-data:www-data storage bootstrap/cache

USER www-data

CMD ["php-fpm"]
