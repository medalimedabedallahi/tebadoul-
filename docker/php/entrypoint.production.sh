#!/bin/sh
# Entrypoint de l'image de production.
#
# - charge les secrets fournis sous forme de fichiers (NOM_FILE=/run/secrets/...), typiquement Docker secrets ;
# - refuse un demarrage manifestement dangereux (APP_KEY absente, debug actif en production) ;
# - met la configuration et les routes Laravel en cache au demarrage (elles dependent de l'environnement : les
#   routes Livewire sont prefixees d'un hash de APP_KEY), donc pas au build ;
# - ne lance JAMAIS de migration : voir le service one-shot `migrate` de compose.prod.yaml.
#
# Les commandes qui ne sont pas php-fpm / php artisan (ex. `php -m`, `sh`) passent sans preparation.
set -eu

fail() {
    echo "badal-entrypoint: $1" >&2
    exit 1
}

case "${1:-}" in
    php-fpm) ;;
    php)
        if [ "${2:-}" != "artisan" ]; then
            exec "$@"
        fi
        ;;
    *) exec "$@" ;;
esac

for name in APP_KEY DB_PASSWORD REDIS_PASSWORD REDIS_CACHE_PASSWORD REDIS_PERSISTENT_PASSWORD MAIL_PASSWORD AWS_SECRET_ACCESS_KEY; do
    eval "secret_file=\${${name}_FILE:-}"
    if [ -n "$secret_file" ]; then
        [ -r "$secret_file" ] || fail "secret illisible pour ${name} : ${secret_file}"
        secret_value=$(cat "$secret_file")
        export "${name}=${secret_value}"
        unset "${name}_FILE"
    fi
done

[ -n "${APP_KEY:-}" ] || fail "APP_KEY est vide (fournir APP_KEY ou APP_KEY_FILE)."

if [ "${APP_ENV:-production}" = "production" ] && [ "${APP_DEBUG:-false}" = "true" ]; then
    fail "APP_DEBUG=true est interdit avec APP_ENV=production."
fi

php artisan config:cache --no-interaction
php artisan route:cache --no-interaction

exec "$@"
