#!/usr/bin/env bash
# Deploiement de production (compose.prod.yaml) d'une image taguee par SHA. A lancer sur le serveur, UNIQUEMENT apres
# autorisation explicite (le workflow release.yml l'appelle derriere un environnement a approbation manuelle).
#
# Usage : ops/deploy.sh [--yes] [--skip-backup] <tag>      ex. ops/deploy.sh sha-3d3c42e5aac5
#
# Sequence (arret au premier echec, l'ancienne version continue de tourner tant que l'etape 6 n'est pas atteinte) :
#   1. validation du tag, de la configuration compose et pull des images ;
#   2. affichage des migrations en attente, confirmation (sauf --yes) ;
#   3. sauvegarde PostgreSQL pre-deploiement (obligatoire sauf --skip-backup, deconseille) ;
#   4. migrations en tache one-shot avec la NOUVELLE image ;
#   5. bascule de app, worker, scheduler, nginx sur le nouveau tag ;
#   6. queue:restart, controle de sante ; en cas d'echec : retour automatique a l'image precedente.
#
# Regle expand/contract : les migrations du deploiement N doivent rester compatibles avec le code du deploiement N-1
# (ajout de colonnes/tables nullable ou avec defaut, pas de renommage/suppression). Les suppressions (contract) sont
# livrees dans un deploiement ulterieur, une fois que plus aucun code n'utilise l'ancien schema. C'est ce qui rend le
# retour a l'image precedente sans restauration de base.
#
# Variables : COMPOSE_FILE, BADAL_ENV_FILE, BADAL_STATE_DIR, PG_MODE, HEALTH_URL, HEALTH_TIMEOUT (defaut 120) - voir ops/lib.sh
set -Eeuo pipefail

SCRIPT_NAME="deploy"
# shellcheck source=ops/lib.sh
. "$(dirname "${BASH_SOURCE[0]}")/lib.sh"

assume_yes=0
skip_backup=0
tag=""

while [[ $# -gt 0 ]]; do
    case "$1" in
        --yes)
            assume_yes=1
            shift
            ;;
        --skip-backup)
            skip_backup=1
            shift
            ;;
        -h | --help)
            sed -n '2,20p' "${BASH_SOURCE[0]}"
            exit 0
            ;;
        -*)
            die "option inconnue : $1"
            ;;
        *)
            [[ -z "$tag" ]] || die "un seul tag attendu"
            tag="$1"
            shift
            ;;
    esac
done

[[ -n "$tag" ]] || die "tag requis (voir --help)"
assert_valid_tag "$tag"
require_cmd docker
[[ -r "$BADAL_ENV_FILE" ]] || die "fichier d'environnement introuvable : $BADAL_ENV_FILE"

mkdir -p "$BADAL_STATE_DIR"
if command -v flock >/dev/null 2>&1; then
    exec 9>"$BADAL_STATE_DIR/.lock"
    flock -n 9 || die "un autre deploiement est en cours"
fi

previous_tag="$(cat "$BADAL_STATE_DIR/current_tag" 2>/dev/null || true)"
export BADAL_TAG="$tag"

log "deploiement du tag ${tag} (precedent : ${previous_tag:-aucun})"

dc config -q
dc pull --quiet app nginx

# Une extension mono-hote (par exemple compose.hostinger.yaml) peut fournir PostgreSQL.
# Il doit etre sain avant les migrations et la sauvegarde du premier deploiement.
if dc config --services | grep -qx postgres; then
    log "demarrage de PostgreSQL local"
    dc up -d --wait --wait-timeout 120 postgres
fi

log "migrations en attente :"
dc run --rm --no-deps -T migrate php artisan migrate:status --pending --no-interaction || log "impossible de lister les migrations en attente"

if [[ "$assume_yes" -ne 1 ]]; then
    read -r -p "Continuer le deploiement de ${tag} ? [y/N] " answer
    [[ "$answer" == "y" || "$answer" == "Y" ]] || die "deploiement annule"
fi

backup_file=""
if [[ "$skip_backup" -eq 1 ]]; then
    log "AVERTISSEMENT : --skip-backup, aucun point de retour pour la base"
else
    backup_file="$("$(dirname "${BASH_SOURCE[0]}")/backup-postgres.sh" --label "pre-deploy-${tag}")" \
        || die "sauvegarde pre-deploiement impossible : deploiement annule (rien n'a ete modifie)"
    log "sauvegarde pre-deploiement : ${backup_file}"
fi

log "migrations (tache one-shot, nouvelle image)"
dc run --rm --no-deps -T migrate || die "migration en echec : l'ancienne version continue de tourner. Sauvegarde : ${backup_file:-aucune}"

log "bascule des services"
dc up -d --no-build --remove-orphans --wait --wait-timeout 180 app worker scheduler nginx redis-queue redis-cache \
    || log "AVERTISSEMENT : un service n'est pas devenu sain a temps"

dc exec -T app php artisan queue:restart --no-interaction || log "AVERTISSEMENT : queue:restart a echoue"

if ! wait_for_health "${HEALTH_TIMEOUT:-120}"; then
    log "controle de sante en echec apres bascule"
    if [[ -n "$previous_tag" ]]; then
        log "retour automatique a ${previous_tag}"
        "$(dirname "${BASH_SOURCE[0]}")/rollback.sh" "$previous_tag" || log "ECHEC du retour automatique : intervention manuelle requise"
    else
        log "aucune version precedente connue : intervention manuelle requise"
    fi
    die "deploiement de ${tag} en echec. Sauvegarde pre-deploiement : ${backup_file:-aucune}"
fi

[[ -z "$previous_tag" ]] || printf '%s\n' "$previous_tag" >"$BADAL_STATE_DIR/previous_tag"
printf '%s\n' "$tag" >"$BADAL_STATE_DIR/current_tag"
printf '%s deploy %s (precedent %s, sauvegarde %s)\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$tag" "${previous_tag:-none}" "${backup_file:-none}" >>"$BADAL_STATE_DIR/history.log"

log "deploiement de ${tag} termine"
