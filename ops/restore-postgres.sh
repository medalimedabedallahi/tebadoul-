#!/usr/bin/env bash
# Restauration d'une sauvegarde Badal (format custom, eventuellement chiffre .age / .gpg).
#
# Usage :
#   ops/restore-postgres.sh FICHIER                   verification : restaure dans une base ephemere, controle, puis la supprime
#   ops/restore-postgres.sh --keep FICHIER            idem, mais conserve la base de verification et affiche son nom
#   ops/restore-postgres.sh --target-db NOM FICHIER   restaure dans une NOUVELLE base NOM (refuse si elle existe)
#   ops/restore-postgres.sh --overwrite-active --confirm-db NOM FICHIER
#                                                     ECRASE la base active (NOM doit etre exactement son nom)
#
# Securites :
#   - par defaut la base active n'est jamais touchee ;
#   - ecraser la base active exige --overwrite-active ET --confirm-db <nom exact> ; une sauvegarde de securite
#     (etiquette pre-restore) est prise avant. Arreter app, worker et scheduler AVANT (sinon verrous et ecritures perdues) ;
#   - les noms de bases systeme (postgres, template0, template1) sont refuses.
#
# Variables : PG_MODE, PGUSER, PGDATABASE (base active), PG_MAINTENANCE_DB, BACKUP_AGE_IDENTITY (cle privee age),
#             BACKUP_AGE_RECIPIENTS_FILE / BACKUP_ALLOW_UNENCRYPTED (pour la sauvegarde de securite) - voir ops/lib.sh
set -Eeuo pipefail

SCRIPT_NAME="restore-postgres"
# shellcheck source=ops/lib.sh
. "$(dirname "${BASH_SOURCE[0]}")/lib.sh"

target_db=""
keep=0
overwrite_active=0
confirm_db=""
backup_file=""

while [[ $# -gt 0 ]]; do
    case "$1" in
        --target-db)
            target_db="${2:?--target-db requiert une valeur}"
            shift 2
            ;;
        --keep)
            keep=1
            shift
            ;;
        --overwrite-active)
            overwrite_active=1
            shift
            ;;
        --confirm-db)
            confirm_db="${2:?--confirm-db requiert une valeur}"
            shift 2
            ;;
        -h | --help)
            sed -n '2,19p' "${BASH_SOURCE[0]}"
            exit 0
            ;;
        -*)
            die "option inconnue : $1"
            ;;
        *)
            [[ -z "$backup_file" ]] || die "un seul fichier de sauvegarde attendu"
            backup_file="$1"
            shift
            ;;
    esac
done

[[ -n "$backup_file" ]] || die "fichier de sauvegarde requis (voir --help)"
[[ -r "$backup_file" ]] || die "fichier illisible : $backup_file"

checksum_file="${backup_file}.sha256"
if [[ -r "$checksum_file" ]]; then
    (cd "$(dirname "$backup_file")" && sha256sum --check --quiet -- "$(basename "$checksum_file")") \
        || die "somme de controle invalide : la sauvegarde est alteree"
    log "somme de controle SHA-256 verifiee"
else
    log "AVERTISSEMENT : pas de fichier .sha256, integrite non verifiee"
fi

if [[ "$overwrite_active" -eq 1 ]]; then
    [[ -z "$target_db" || "$target_db" == "$ACTIVE_DB" ]] || die "--overwrite-active ne s'utilise pas avec --target-db different de la base active"
    [[ "$confirm_db" == "$ACTIVE_DB" ]] || die "--confirm-db doit valoir exactement le nom de la base active ('${ACTIVE_DB}')"
    target_db="$ACTIVE_DB"
    mode="overwrite"
elif [[ -n "$target_db" ]]; then
    assert_safe_db_name "$target_db"
    [[ "$target_db" != "$ACTIVE_DB" ]] || die "'${target_db}' est la base active : utiliser --overwrite-active --confirm-db ${ACTIVE_DB}"
    mode="new"
else
    target_db="${ACTIVE_DB}_verify_$(date -u +%Y%m%d%H%M%S)_$$"
    mode="verify"
fi

assert_safe_db_name "$target_db"
case "$target_db" in
    postgres | template0 | template1) die "base systeme refusee : $target_db" ;;
esac

created_here=0
cleanup() {
    local status=$?
    if [[ "$created_here" -eq 1 && "$mode" == "verify" && "$keep" -eq 0 ]]; then
        log "suppression de la base de verification ${target_db}"
        pg_sql "$PG_MAINTENANCE_DB" "DROP DATABASE IF EXISTS \"${target_db}\"" >/dev/null 2>&1 || log "AVERTISSEMENT : suppression de ${target_db} impossible, a faire manuellement"
    fi
    exit "$status"
}
trap cleanup EXIT

restore_flags=(--no-owner --no-acl --exit-on-error)

if [[ "$mode" == "overwrite" ]]; then
    log "ATTENTION : la base active '${ACTIVE_DB}' va etre ECRASEE. Sauvegarde de securite en cours..."
    safety="$("$(dirname "${BASH_SOURCE[0]}")/backup-postgres.sh" --label pre-restore)" || die "sauvegarde de securite impossible : restauration annulee"
    log "sauvegarde de securite : ${safety}"
    restore_flags+=(--clean --if-exists --single-transaction)
else
    existing="$(pg_sql "$PG_MAINTENANCE_DB" "SELECT 1 FROM pg_database WHERE datname = '${target_db}'")"
    [[ -z "$existing" ]] || die "la base ${target_db} existe deja : refus de l'ecraser"
    log "creation de la base ${target_db}"
    pg_sql "$PG_MAINTENANCE_DB" "CREATE DATABASE \"${target_db}\"" >/dev/null
    created_here=1
fi

started="$(date +%s)"
log "restauration de $(basename "$backup_file") vers '${target_db}' (mode ${mode})"
read_backup "$backup_file" | pg_exec pg_restore --username "$PGUSER" --dbname "$target_db" "${restore_flags[@]}"
duration=$(($(date +%s) - started))
log "restauration terminee en ${duration} s (mesure utile pour valider le RTO)"

verify_database "$target_db"

if [[ "$mode" == "verify" && "$keep" -eq 1 ]]; then
    log "base de verification conservee : ${target_db} (a supprimer apres examen)"
elif [[ "$mode" == "new" ]]; then
    log "base restauree : ${target_db}"
elif [[ "$mode" == "overwrite" ]]; then
    log "base active restauree. Verifier puis redemarrer app, worker, scheduler ; executer queue:restart."
fi
