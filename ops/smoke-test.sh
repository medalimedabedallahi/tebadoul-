#!/usr/bin/env bash
# Test de fumee des images de production (CI et poste local). N'utilise ni base ni Redis : sessions/cache en memoire.
#
# Usage : ops/smoke-test.sh <image-php> <image-nginx>      ex. ops/smoke-test.sh badal-app:ci badal-nginx:ci
#
# Ressources creees (toutes nommees badal-smoke-*, supprimees en sortie) : un reseau et deux conteneurs --rm.
# Verifie : utilisateur non root, absence de composer, extensions PHP, refus de APP_DEBUG=true / APP_KEY vide,
# healthchecks, /up et /api/v1/health via Nginx, script Livewire, en-tetes de securite, blocage des .php et dotfiles.
set -Eeuo pipefail

SCRIPT_NAME="smoke-test"
# shellcheck source=ops/lib.sh
. "$(dirname "${BASH_SOURCE[0]}")/lib.sh"

app_image="${1:?image PHP requise}"
nginx_image="${2:?image Nginx requise}"

require_cmd docker
require_cmd curl

suffix="$$"
network="badal-smoke-net-${suffix}"
app_container="badal-smoke-app-${suffix}"
web_container="badal-smoke-web-${suffix}"
failures=0

cleanup() {
    if [[ "$failures" -ne 0 ]]; then
        docker logs --tail 60 "$app_container" >&2 2>&1 || true
        docker logs --tail 60 "$web_container" >&2 2>&1 || true
    fi
    docker stop "$web_container" "$app_container" >/dev/null 2>&1 || true
    docker network rm "$network" >/dev/null 2>&1 || true
}
trap cleanup EXIT

check() {
    local description="$1"
    shift
    if "$@" >/dev/null 2>&1; then
        log "OK      ${description}"
    else
        log "ECHEC   ${description}"
        failures=$((failures + 1))
    fi
}

wait_healthy() {
    local container="$1" status
    for _ in $(seq 1 40); do
        status="$(docker inspect --format '{{if .State.Health}}{{.State.Health.Status}}{{else}}none{{end}}' "$container" 2>/dev/null || echo missing)"
        [[ "$status" == "healthy" ]] && return 0
        sleep 2
    done
    return 1
}

http_status() {
    curl --silent --output /dev/null --write-out '%{http_code}' --max-time 10 "$1"
}

app_key="base64:$(head -c 32 /dev/urandom | base64 | tr -d '\r\n')"

# Configuration minimale qui passe les garde-fous de demarrage de production (AppServiceProvider : APP_DEBUG,
# MAIL_MAILER). Chaque controle negatif ne change qu'UNE variable par rapport a elle : il echoue
# ainsi pour la raison qu'il annonce, et non parce qu'un autre garde-fou refuse deja le demarrage.
valid_env=(
    -e APP_KEY="$app_key" -e APP_DEBUG=false
    -e MAIL_MAILER=smtp -e MAIL_HOST=mail.invalid
)

# Vrai si l'image refuse de demarrer avec la configuration valide modifiee par les arguments -e donnes.
refuses_to_boot() {
    ! docker run --rm "${valid_env[@]}" "$@" "$app_image" php artisan --version
}

# --- Controles sans conteneur long ---
check "l'image PHP tourne en non-root" bash -c "[[ \"\$(docker run --rm --entrypoint id '$app_image' -u)\" != 0 ]]"
check "composer absent de l'image PHP" bash -c "! docker run --rm --entrypoint which '$app_image' composer"
check "extensions PHP (redis, pdo_pgsql, intl, pcntl, zip, opcache)" \
    docker run --rm --entrypoint php "$app_image" -r 'exit(count(array_filter(["redis","pdo_pgsql","intl","pcntl","zip","Zend OPcache"], "extension_loaded")) === 6 ? 0 : 1);'
check "demarrage OK avec une configuration valide" \
    docker run --rm "${valid_env[@]}" "$app_image" php artisan --version
check "demarrage refuse avec APP_DEBUG=true" refuses_to_boot -e APP_DEBUG=true
check "demarrage refuse sans APP_KEY" refuses_to_boot -e APP_KEY=
check "demarrage refuse avec MAIL_MAILER=log" refuses_to_boot -e MAIL_MAILER=log

# --- Pile applicative ---
docker network create "$network" >/dev/null
docker run -d --rm --name "$app_container" --network "$network" --network-alias app \
    "${valid_env[@]}" -e APP_ENV=production -e APP_URL=http://localhost \
    -e SESSION_DRIVER=array -e CACHE_STORE=array -e QUEUE_CONNECTION=sync -e LOG_STACK=stderr \
    "$app_image" >/dev/null
docker run -d --rm --name "$web_container" --network "$network" \
    --read-only --tmpfs /tmp --cap-drop ALL --security-opt no-new-privileges:true \
    -p 127.0.0.1::8080 "$nginx_image" >/dev/null

check "healthcheck du conteneur PHP" wait_healthy "$app_container"
check "healthcheck du conteneur Nginx" wait_healthy "$web_container"

port="$(docker port "$web_container" 8080/tcp | head -n 1 | sed 's/.*://')"
base="http://127.0.0.1:${port}"
log "Nginx joignable sur ${base}"

check "GET /up -> 200" bash -c "[[ \"\$(curl -s -o /dev/null -w '%{http_code}' '$base/up')\" == 200 ]]"
check "GET /api/v1/health -> 200 avec X-Request-ID" \
    bash -c "curl -sf -D - -o /dev/null '$base/api/v1/health' | grep -qi '^x-request-id:'"
check "/.env -> 403" bash -c "[[ \"\$(curl -s -o /dev/null -w '%{http_code}' '$base/.env')\" == 403 ]]"
check "/autre.php -> 404 (seul index.php est execute)" bash -c "[[ \"\$(curl -s -o /dev/null -w '%{http_code}' '$base/autre.php')\" == 404 ]]"
check "/build/ n'expose pas de listing" bash -c "[[ \"\$(curl -s -o /dev/null -w '%{http_code}' '$base/build/')\" != 200 ]]"

# Les pages pointent vers le script Livewire sous un prefixe derive de APP_KEY : les routes en cache doivent avoir
# ete construites avec la meme cle, sinon le script et l'envoi des formulaires repondent 404.
livewire_script="$(docker exec "$app_container" php -r 'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); echo Livewire\Mechanisms\HandleRequests\EndpointResolver::scriptPath(true);' 2>/dev/null || true)"
check "script Livewire servi (${livewire_script:-chemin introuvable})" \
    bash -c "[[ -n '$livewire_script' && \"\$(curl -s -o /dev/null -w '%{http_code}' '$base$livewire_script')\" == 200 ]]"

headers="$(curl -s -D - -o /dev/null "$base/up" || true)"
check "en-tete X-Content-Type-Options" grep -qi '^x-content-type-options: nosniff' <<<"$headers"
check "en-tete X-Frame-Options" grep -qi '^x-frame-options: deny' <<<"$headers"
check "en-tete Referrer-Policy" grep -qi '^referrer-policy:' <<<"$headers"
check "en-tete Permissions-Policy" grep -qi '^permissions-policy:' <<<"$headers"
check "CSP en mode rapport uniquement" grep -qi '^content-security-policy-report-only:' <<<"$headers"
check "version de Nginx masquee" bash -c "! grep -qiE '^server: nginx/' <<<\"\$1\"" _ "$headers"
check "X-Powered-By absent" bash -c "! grep -qi '^x-powered-by:' <<<\"\$1\"" _ "$headers"
check "HSTS emis uniquement derriere un frontal HTTPS" bash -c "! grep -qi '^strict-transport-security:' <<<\"\$1\"" _ "$headers"
check "HSTS emis si X-Forwarded-Proto: https" \
    bash -c "curl -s -D - -o /dev/null -H 'X-Forwarded-Proto: https' '$base/up' | grep -qi '^strict-transport-security:'"

if [[ "$failures" -ne 0 ]]; then
    die "${failures} verification(s) en echec"
fi
log "test de fumee reussi"
