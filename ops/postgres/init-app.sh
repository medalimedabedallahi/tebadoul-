#!/bin/sh
# Cree un role applicatif non-superutilisateur lors de l'initialisation du volume PostgreSQL.
set -eu

case "${BADAL_DB_USERNAME:-}" in
    ''|*[!a-z0-9_]*)
        echo "Nom de role applicatif invalide: ${BADAL_DB_USERNAME:-vide}" >&2
        exit 1
        ;;
esac

app_password="$(cat /run/secrets/db_password)"

psql --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" \
    --set=ON_ERROR_STOP=1 \
    --set=app_user="$BADAL_DB_USERNAME" \
    --set=app_db="$POSTGRES_DB" \
    --set=app_password="$app_password" <<-'SQL'
CREATE ROLE :"app_user" LOGIN PASSWORD :'app_password' NOSUPERUSER NOCREATEDB NOCREATEROLE;
ALTER DATABASE :"app_db" OWNER TO :"app_user";
SQL
