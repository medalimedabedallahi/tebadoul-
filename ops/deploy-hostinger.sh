#!/usr/bin/env bash
# Point d'entree du deploiement Hostinger mono-hote.
# Configure l'extension PostgreSQL locale, puis delegue toute la procedure sure a deploy.sh.
set -Eeuo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

export COMPOSE_FILE="${COMPOSE_FILE:-$ROOT_DIR/compose.prod.yaml $ROOT_DIR/compose.hostinger.yaml}"
export PG_MODE="${PG_MODE:-compose}"
export BADAL_COMPOSE_ARGS="${BADAL_COMPOSE_ARGS:---env-file $ROOT_DIR/.env.production -f $ROOT_DIR/compose.prod.yaml -f $ROOT_DIR/compose.hostinger.yaml}"
export PG_SERVICE="${PG_SERVICE:-postgres}"
export PGUSER="${PGUSER:-postgres}"
export PGDATABASE="${PGDATABASE:-badal}"

exec "$ROOT_DIR/ops/deploy.sh" "$@"
