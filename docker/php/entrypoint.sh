#!/bin/sh
set -eu

if [ ! -f vendor/autoload.php ]; then
    composer install --no-interaction --prefer-dist
fi

# storage/ et bootstrap/cache/ sont montes depuis l'hote (bind mount, y compris Windows) : les sous-dossiers
# peuvent manquer ou appartenir a root (checkout Git, `docker exec` lance en root...). PHP-FPM sert les requetes
# avec le pool "www" (utilisateur/groupe www-data, /usr/local/etc/php-fpm.d/www.conf) ; sans droit d'ecriture pour
# www-data, le premier rendu d'une vue neuve (compilation Blade dans storage/framework/views) echoue. Idempotent :
# rejoue a chaque demarrage pour rattraper un bind mount remonte avec des permissions par defaut.
mkdir -p storage/app/public storage/framework/cache/data storage/framework/sessions \
    storage/framework/testing storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache

exec "$@"
