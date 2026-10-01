#!/usr/bin/env bash
# Fonctions communes aux scripts ops/. A sourcer (". ops/lib.sh"), pas a executer.
#
# PG_MODE (defaut : direct)
#   direct  : binaires PostgreSQL du poste (postgresql-client-17), connexion par les variables libpq standard
#             (PGHOST, PGPORT, PGSSLMODE, PGPASSFILE ou PGSERVICE...). Le mot de passe n'est JAMAIS passe en argument.
#   compose : exec dans le service `postgres` d'une stack compose (developpement / repetition de la procedure).
#             BADAL_COMPOSE_ARGS (defaut "-f compose.yaml"), PG_SERVICE (defaut "postgres").
#   container : exec dans un conteneur PostgreSQL nomme (PG_CONTAINER), ex. un conteneur jetable de test.
#
# Variables : PGUSER (defaut DB_USERNAME puis badal), PGDATABASE (base ACTIVE, defaut DB_DATABASE puis badal),
#             PG_MAINTENANCE_DB (defaut postgres).

# shellcheck disable=SC2034  # variables consommees par les scripts qui sourcent ce fichier

PG_MODE="${PG_MODE:-direct}"
PG_SERVICE="${PG_SERVICE:-postgres}"
PG_MAINTENANCE_DB="${PG_MAINTENANCE_DB:-postgres}"
PGUSER="${PGUSER:-${DB_USERNAME:-badal}}"
ACTIVE_DB="${PGDATABASE:-${DB_DATABASE:-badal}}"
export PGUSER

read -r -a COMPOSE_ARGS <<<"${BADAL_COMPOSE_ARGS:--f compose.yaml}"

log() {
    printf '%s [%s] %s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "${SCRIPT_NAME:-ops}" "$*" >&2
}

die() {
    log "ERREUR : $*"
    exit 1
}

require_cmd() {
    command -v "$1" >/dev/null 2>&1 || die "commande requise introuvable : $1"
}

# Nom de base de donnees sur : minuscules, chiffres, underscore (evite toute injection SQL dans les identifiants).
assert_safe_db_name() {
    [[ "$1" =~ ^[a-z][a-z0-9_]{0,62}$ ]] || die "nom de base invalide : $1"
}

# Execute une commande PostgreSQL (stdin/stdout transmis tels quels).
pg_exec() {
    case "$PG_MODE" in
        direct)
            "$@"
            ;;
        compose)
            require_cmd docker
            docker compose "${COMPOSE_ARGS[@]}" exec -T "$PG_SERVICE" "$@"
            ;;
        container)
            require_cmd docker
            docker exec -i "${PG_CONTAINER:?PG_CONTAINER requis avec PG_MODE=container}" "$@"
            ;;
        *)
            die "PG_MODE inconnu : $PG_MODE (direct|compose|container)"
            ;;
    esac
}

# psql en mode script sur une base donnee : pg_sql <base> <sql>  -> sortie sans alignement ni en-tetes.
pg_sql() {
    local database="$1"
    local sql="$2"
    pg_exec psql --username "$PGUSER" --dbname "$database" --no-psqlrc --quiet --tuples-only --no-align \
        --set ON_ERROR_STOP=1 --command "$sql"
}

# Emet le contenu en clair d'une sauvegarde (dechiffre .age / .gpg a la volee, sans ecrire le clair sur disque).
read_backup() {
    local file="$1"
    case "$file" in
        *.age)
            require_cmd age
            [[ -n "${BACKUP_AGE_IDENTITY:-}" ]] || die "BACKUP_AGE_IDENTITY (fichier de cle privee age) requis pour dechiffrer $file"
            age --decrypt --identity "$BACKUP_AGE_IDENTITY" "$file"
            ;;
        *.gpg)
            require_cmd gpg
            gpg --batch --quiet --decrypt "$file"
            ;;
        *)
            cat "$file"
            ;;
    esac
}

# Controles minimaux d'une base restauree : des tables applicatives et un historique de migrations non vide.
verify_database() {
    local database="$1"
    local table_count migration_count last_migration

    table_count="$(pg_sql "$database" "SELECT count(*) FROM information_schema.tables WHERE table_schema = 'public' AND table_type = 'BASE TABLE'")"
    [[ "$table_count" =~ ^[0-9]+$ && "$table_count" -gt 0 ]] || die "verification : aucune table dans le schema public"

    migration_count="$(pg_sql "$database" "SELECT count(*) FROM migrations")" || die "verification : table migrations illisible"
    [[ "$migration_count" =~ ^[0-9]+$ && "$migration_count" -gt 0 ]] || die "verification : historique de migrations vide"

    last_migration="$(pg_sql "$database" "SELECT migration FROM migrations ORDER BY id DESC LIMIT 1")"
    log "verification OK : ${table_count} tables, ${migration_count} migrations, derniere = ${last_migration}"
}

# --- Deploiement (compose.prod.yaml) ---

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
COMPOSE_FILE="${COMPOSE_FILE:-$ROOT_DIR/compose.prod.yaml}"
BADAL_ENV_FILE="${BADAL_ENV_FILE:-$ROOT_DIR/.env.production}"
BADAL_STATE_DIR="${BADAL_STATE_DIR:-$ROOT_DIR/.deploy-state}"
export BADAL_ENV_FILE

read -r -a DEPLOY_COMPOSE_FILES <<<"$COMPOSE_FILE"
DEPLOY_COMPOSE_ARGS=()
for compose_file in "${DEPLOY_COMPOSE_FILES[@]}"; do
    DEPLOY_COMPOSE_ARGS+=( -f "$compose_file" )
done

dc() {
    docker compose --env-file "$BADAL_ENV_FILE" "${DEPLOY_COMPOSE_ARGS[@]}" "$@"
}

# Valeur non secrete d'une variable de l'env-file (APP_PORT, APP_BIND_ADDRESS...). Ne jamais l'utiliser pour un secret.
env_value() {
    local key="$1" default="${2:-}" value
    value="$(grep -E "^${key}=" "$BADAL_ENV_FILE" 2>/dev/null | tail -n 1 | cut -d= -f2- | tr -d '"' || true)"
    printf '%s' "${value:-$default}"
}

assert_valid_tag() {
    [[ "$1" =~ ^[A-Za-z0-9][A-Za-z0-9._-]{0,127}$ ]] || die "tag d'image invalide : $1"
    [[ "$1" != "latest" ]] || die "le tag 'latest' est interdit : utiliser un tag immuable (sha-<commit>)"
}

# Attend que l'application reponde 200 sur /up (via Nginx). Usage : wait_for_health [timeout_secondes]
wait_for_health() {
    local timeout="${1:-120}" url elapsed=0
    require_cmd curl
    url="${HEALTH_URL:-http://$(env_value APP_BIND_ADDRESS 127.0.0.1):$(env_value APP_PORT 8080)/up}"
    log "attente de ${url} (max ${timeout}s)"
    until curl --silent --show-error --fail --max-time 5 --output /dev/null "$url"; do
        elapsed=$((elapsed + 3))
        [[ "$elapsed" -lt "$timeout" ]] || return 1
        sleep 3
    done
    log "application disponible"
}
