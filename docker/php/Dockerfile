# Images Badal.
#
#   base              PHP-FPM + extensions, sans composer.
#   development       + composer, git ; le code est monte en volume (compose.yaml).
#   production        image applicative immuable (app, worker, scheduler, migrate), sans composer, non root.
#   nginx-production  Nginx non privilegie embarquant public/ (dernier stage : cible par defaut de `docker build`).
#
# Les images de base sont epinglees par digest ; Dependabot (docker) propose les mises a jour.
# Build : docker build -f docker/php/Dockerfile --target production -t badal-app:local .

FROM php:8.5-fpm-alpine@sha256:fa01fb1645cd0fc566a5f146b099adace33b906571f972f71f2182a7c12d1cd7 AS base

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
    && pecl install redis-6.3.0 \
    && docker-php-ext-enable redis \
    && apk del .build-dependencies \
    && rm -rf /tmp/pear

COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-badal.ini

WORKDIR /var/www/html

FROM composer:2.9@sha256:b09bccd91a78fe8a9ab4b33d707b862e8fe54fec17782e32683ad2a69c46867d AS composer-bin

# Developpement. Reste volontairement en root : le code est monte depuis l'hote (Docker Desktop / Windows)
# et les dossiers storage/ et bootstrap/cache y sont crees par root (non modifiables par un uid 1000,
# verifie). Passer en non-root casserait les ecritures sans possibilite de chown depuis l'hote.
# L'isolation est assuree par l'image de production (USER www-data).
FROM base AS development

RUN apk add --no-cache git unzip

COPY --from=composer-bin /usr/bin/composer /usr/local/bin/composer
COPY docker/php/entrypoint.sh /usr/local/bin/badal-entrypoint
RUN chmod +x /usr/local/bin/badal-entrypoint

ENTRYPOINT ["badal-entrypoint"]
CMD ["php-fpm"]

FROM node:24-alpine@sha256:ebfe2f90462722a7a4de65e91990e97fe0d401c70e0e762c5b53302f905ec1c1 AS assets

WORKDIR /app

COPY package.json package-lock.json .npmrc ./
RUN npm ci

COPY vite.config.js ./
COPY app ./app
COPY resources ./resources
RUN npm run build

# Etape de construction : composer, autoload optimise et caches derives du code (routes, vues, evenements).
# Rien de cette etape n'est copie tel quel dans l'image finale, composer n'y figure donc pas.
FROM base AS build

COPY --from=composer-bin /usr/bin/composer /usr/local/bin/composer

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --no-scripts \
    --no-autoloader

COPY . .

# Aucun .env n'existe a cette etape : composer dump-autoload (hook package:discover) et les commandes artisan
# *:cache demarrent completement l'application, y compris les verifications de securite au boot
# (AppServiceProvider) qui ne s'appliquent qu'en production/staging (SMS_DRIVER, MAIL_MAILER...). APP_ENV=testing
# le temps de cette etape les evite sans avoir a lister chaque variable a mesure qu'elles sont ajoutees cote
# application ; le contenu genere (vues, evenements) ne differe pas selon l'environnement. Jamais copiee
# dans l'image finale (etape `production` ci-dessous repart de `base`, avec APP_ENV=production a l'execution).
# Les routes ne sont PAS mises en cache ici : Livewire derive le prefixe de ses routes de APP_KEY, absente au
# build ; elles le sont au demarrage par l'entrypoint, comme la configuration.
ENV APP_ENV=testing

RUN mkdir -p storage/app/public storage/framework/cache/data storage/framework/sessions \
        storage/framework/testing storage/framework/views storage/logs bootstrap/cache \
    && composer dump-autoload --no-dev --optimize --no-interaction \
    && php artisan view:cache \
    && php artisan event:cache

FROM base AS production

ENV APP_ENV=production

COPY docker/php/php.production.ini /usr/local/etc/php/conf.d/99-production.ini
COPY docker/php/entrypoint.production.sh /usr/local/bin/badal-entrypoint

# Code en lecture seule pour l'utilisateur d'execution ; seuls storage/ et bootstrap/cache sont inscriptibles.
COPY --from=build /var/www/html /var/www/html
COPY --from=assets /app/public/build public/build

RUN chmod +x /usr/local/bin/badal-entrypoint \
    && rm -rf docker \
    && chown -R www-data:www-data storage bootstrap/cache

USER www-data

EXPOSE 9000

HEALTHCHECK --interval=15s --timeout=5s --start-period=30s --retries=3 \
    CMD php -r 'exit(@fsockopen("127.0.0.1", 9000) ? 0 : 1);'

ENTRYPOINT ["badal-entrypoint"]
CMD ["php-fpm"]

# Nginx de production : image non privilegiee (ecoute sur 8080), fichiers statiques embarques.
# Le TLS est termine en frontal ; voir docker/nginx/production.conf.
FROM nginxinc/nginx-unprivileged:1.29-alpine@sha256:0c79d56aee561a1d81c63f00eee5fb5fe29279560cdc55e91425133104c7fbe6 AS nginx-production

# Applique les correctifs de securite Alpine publies depuis la construction de l'image de base (Trivy bloque en CI
# sur HIGH/CRITICAL corrigeables), puis revient a l'utilisateur non privilegie de l'image.
USER root
RUN apk upgrade --no-cache
USER 101

COPY docker/nginx/production.conf /etc/nginx/conf.d/default.conf
COPY docker/nginx/security-headers.conf /etc/nginx/snippets/security-headers.conf
COPY --from=production /var/www/html/public /var/www/html/public

EXPOSE 8080

HEALTHCHECK --interval=15s --timeout=3s --start-period=10s --retries=3 \
    CMD wget -q --spider http://127.0.0.1:8080/nginx-health || exit 1
