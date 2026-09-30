#!/usr/bin/env bash
# Test periodique de restauration (a planifier, ex. hebdomadaire) : prend la sauvegarde la plus recente,
# controle sa fraicheur et son integrite, la restaure dans une base ephemere puis la supprime.
# N'ecrase JAMAIS la base active. Sortie : une ligne JSON sur stdout (a envoyer a la supervision), code retour != 0 en cas d'echec.
#
# Usage : ops/verify-restore.sh [FICHIER]
# Variables : BACKUP_DIR (defaut ./backups), MAX_BACKUP_AGE_HOURS (defaut 26, ~RPO quotidien + marge),
#             PG_MODE, BACKUP_AGE_IDENTITY... voir ops/lib.sh et ops/restore-postgres.sh
set -Eeuo pipefail

SCRIPT_NAME="verify-restore"
# shellcheck source=ops/lib.sh
. "$(dirname "${BASH_SOURCE[0]}")/lib.sh"

BACKUP_DIR="${BACKUP_DIR:-./backups}"
MAX_BACKUP_AGE_HOURS="${MAX_BACKUP_AGE_HOURS:-26}"
[[ "$MAX_BACKUP_AGE_HOURS" =~ ^[0-9]+$ ]] || die "MAX_BACKUP_AGE_HOURS doit etre un entier"

report() {
    printf '{"check":"badal-restore","status":"%s","backup":"%s","backup_age_hours":%s,"duration_seconds":%s}\n' \
        "$1" "$2" "$3" "$4"
}

backup_file="${1:-}"
if [[ -z "$backup_file" ]]; then
    backup_file="$(find "$BACKUP_DIR" -maxdepth 1 -type f -name 'badal-*.dump*' ! -name '*.sha256' -printf '%T@ %p\n' 2>/dev/null | sort -rn | head -n 1 | cut -d' ' -f2-)"
fi
[[ -n "$backup_file" && -r "$backup_file" ]] || {
    report "no-backup" "" 0 0
    die "aucune sauvegarde trouvee dans ${BACKUP_DIR}"
}

age_hours=$((($(date +%s) - $(stat -c %Y -- "$backup_file")) / 3600))
if [[ "$age_hours" -gt "$MAX_BACKUP_AGE_HOURS" ]]; then
    report "stale" "$(basename "$backup_file")" "$age_hours" 0
    die "la derniere sauvegarde a ${age_hours} h (max ${MAX_BACKUP_AGE_HOURS} h) : les sauvegardes ne tournent plus ?"
fi

started="$(date +%s)"
if "$(dirname "${BASH_SOURCE[0]}")/restore-postgres.sh" "$backup_file"; then
    report "ok" "$(basename "$backup_file")" "$age_hours" "$(($(date +%s) - started))"
else
    report "failed" "$(basename "$backup_file")" "$age_hours" "$(($(date +%s) - started))"
    exit 1
fi
