#!/usr/bin/env bash
# Sauvegarde logique de la base PostgreSQL de Badal (pg_dump -Fc), horodatee, verifiee, chiffree, avec retention.
#
# Usage : ops/backup-postgres.sh [--label ETIQUETTE] [--output-dir DIR]
#   Ecrit UNIQUEMENT le chemin du fichier produit sur stdout (les journaux vont sur stderr).
#
# Variables :
#   BACKUP_DIR                    dossier de sortie (defaut ./backups, cree en 0700)
#   RETENTION_DAYS                suppression des sauvegardes badal-*.dump* plus anciennes (defaut 14)
#   BACKUP_AGE_RECIPIENTS_FILE    fichier de cles publiques age  -> chiffrement age (recommande)
#   BACKUP_GPG_RECIPIENT          identifiant de cle GPG          -> chiffrement gpg
#   BACKUP_ALLOW_UNENCRYPTED=1    autorise un dump en clair (repetition locale uniquement : il contient des donnees personnelles)
#   PG_MODE, PGUSER, PGDATABASE, PGHOST...  voir ops/lib.sh
#
# Le script ne modifie JAMAIS la base : lecture seule (pg_dump).
set -Eeuo pipefail

SCRIPT_NAME="backup-postgres"
# shellcheck source=ops/lib.sh
. "$(dirname "${BASH_SOURCE[0]}")/lib.sh"

label=""
BACKUP_DIR="${BACKUP_DIR:-./backups}"
RETENTION_DAYS="${RETENTION_DAYS:-14}"

while [[ $# -gt 0 ]]; do
    case "$1" in
        --label)
            label="${2:?--label requiert une valeur}"
            shift 2
            ;;
        --output-dir)
            BACKUP_DIR="${2:?--output-dir requiert une valeur}"
            shift 2
            ;;
        -h | --help)
            sed -n '2,17p' "${BASH_SOURCE[0]}"
            exit 0
            ;;
        *)
            die "argument inconnu : $1"
            ;;
    esac
done

[[ "$RETENTION_DAYS" =~ ^[0-9]+$ ]] || die "RETENTION_DAYS doit etre un entier"
[[ -z "$label" || "$label" =~ ^[A-Za-z0-9._-]{1,64}$ ]] || die "etiquette invalide : $label"

encryption="none"
if [[ -n "${BACKUP_AGE_RECIPIENTS_FILE:-}" && -n "${BACKUP_GPG_RECIPIENT:-}" ]]; then
    die "definir un seul mode de chiffrement (age OU gpg)"
elif [[ -n "${BACKUP_AGE_RECIPIENTS_FILE:-}" ]]; then
    require_cmd age
    [[ -r "$BACKUP_AGE_RECIPIENTS_FILE" ]] || die "fichier de destinataires age illisible"
    encryption="age"
elif [[ -n "${BACKUP_GPG_RECIPIENT:-}" ]]; then
    require_cmd gpg
    encryption="gpg"
elif [[ "${BACKUP_ALLOW_UNENCRYPTED:-0}" != "1" ]]; then
    die "aucun chiffrement configure : definir BACKUP_AGE_RECIPIENTS_FILE ou BACKUP_GPG_RECIPIENT (ou BACKUP_ALLOW_UNENCRYPTED=1 en repetition locale)"
fi

umask 077
mkdir -p "$BACKUP_DIR"
chmod 700 "$BACKUP_DIR"

timestamp="$(date -u +%Y%m%dT%H%M%SZ)"
base="badal${label:+-$label}-${timestamp}.dump"
plain="$BACKUP_DIR/.${base}.partial"
final="$BACKUP_DIR/$base"

cleanup() {
    rm -f -- "$plain" "$BACKUP_DIR/.${base}.enc.partial"
}
trap cleanup EXIT

started="$(date +%s)"
log "dump de la base '${ACTIVE_DB}' (mode ${PG_MODE}, chiffrement ${encryption})"
pg_exec pg_dump --username "$PGUSER" --dbname "$ACTIVE_DB" --format=custom --compress=6 --no-owner --no-acl >"$plain"

# Un dump tronque ou corrompu doit echouer ici, pas le jour de la restauration.
pg_exec pg_restore --list <"$plain" >/dev/null || die "le dump produit est illisible (pg_restore --list a echoue)"
[[ -s "$plain" ]] || die "le dump produit est vide"

case "$encryption" in
    age)
        age --encrypt --recipients-file "$BACKUP_AGE_RECIPIENTS_FILE" --output "$BACKUP_DIR/.${base}.enc.partial" "$plain"
        final="${final}.age"
        mv -- "$BACKUP_DIR/.${base}.enc.partial" "$final"
        rm -f -- "$plain"
        ;;
    gpg)
        gpg --batch --yes --trust-model always --encrypt --recipient "$BACKUP_GPG_RECIPIENT" \
            --output "$BACKUP_DIR/.${base}.enc.partial" "$plain"
        final="${final}.gpg"
        mv -- "$BACKUP_DIR/.${base}.enc.partial" "$final"
        rm -f -- "$plain"
        ;;
    none)
        mv -- "$plain" "$final"
        ;;
esac

(cd "$BACKUP_DIR" && sha256sum -- "$(basename "$final")" >"$(basename "$final").sha256")

# Retention : uniquement nos fichiers, et seulement apres la creation reussie d'une nouvelle sauvegarde.
find "$BACKUP_DIR" -maxdepth 1 -type f -name 'badal-*.dump*' -mtime +"$RETENTION_DAYS" -print -delete >&2 || true

log "sauvegarde terminee en $(($(date +%s) - started)) s : $final ($(du -h -- "$final" | cut -f1))"
printf '%s\n' "$final"
