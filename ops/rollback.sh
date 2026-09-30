#!/usr/bin/env bash
# Retour applicatif a une image precedente (compose.prod.yaml). Ne touche PAS a la base de donnees.
#
# Usage : ops/rollback.sh [tag]     sans argument : revient au tag enregistre par le dernier deploiement reussi
#
# Pourquoi la base n'est pas restauree : les migrations respectent expand/contract (voir ops/deploy.sh), l'ancien code
# reste donc compatible avec le schema courant. Si une migration a corrompu des donnees (cas exceptionnel), restaurer
# MANUELLEMENT la sauvegarde pre-deploiement :
#     ops/restore-postgres.sh --overwrite-active --confirm-db <base> <sauvegarde>
# apres avoir arrete app, worker et scheduler. Les ecritures faites depuis le deploiement seraient perdues (RPO).
#
# Variables : COMPOSE_FILE, BADAL_ENV_FILE, BADAL_STATE_DIR, HEALTH_URL, HEALTH_TIMEOUT - voir ops/lib.sh
set -Eeuo pipefail

SCRIPT_NAME="rollback"
# shellcheck source=ops/lib.sh
. "$(dirname "${BASH_SOURCE[0]}")/lib.sh"

case "${1:-}" in
    -h | --help)
        sed -n '2,12p' "${BASH_SOURCE[0]}"
        exit 0
        ;;
esac

require_cmd docker
[[ -r "$BADAL_ENV_FILE" ]] || die "fichier d'environnement introuvable : $BADAL_ENV_FILE"

target="${1:-$(cat "$BADAL_STATE_DIR/previous_tag" 2>/dev/null || true)}"
[[ -n "$target" ]] || die "aucun tag precedent connu : preciser le tag (ex. ops/rollback.sh sha-abc1234)"
assert_valid_tag "$target"

current="$(cat "$BADAL_STATE_DIR/current_tag" 2>/dev/null || true)"
export BADAL_TAG="$target"

log "retour a ${target} (version courante enregistree : ${current:-inconnue})"

dc config -q
# --pull missing : utilise l'image locale si presente, ne la telecharge que si elle a disparu.
dc up -d --no-build --pull missing --remove-orphans --wait --wait-timeout 180 app worker scheduler nginx redis-queue redis-cache \
    || log "AVERTISSEMENT : un service n'est pas devenu sain a temps"

dc exec -T app php artisan queue:restart --no-interaction || log "AVERTISSEMENT : queue:restart a echoue"

wait_for_health "${HEALTH_TIMEOUT:-120}" || die "l'application ne repond pas apres le retour a ${target} : intervention manuelle requise"

mkdir -p "$BADAL_STATE_DIR"
[[ -z "$current" || "$current" == "$target" ]] || printf '%s\n' "$current" >"$BADAL_STATE_DIR/previous_tag"
printf '%s\n' "$target" >"$BADAL_STATE_DIR/current_tag"
printf '%s rollback %s (depuis %s)\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$target" "${current:-unknown}" >>"$BADAL_STATE_DIR/history.log"

log "retour a ${target} termine. La base n'a pas ete modifiee."
