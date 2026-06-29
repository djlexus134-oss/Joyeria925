#!/usr/bin/env bash
# MariaDB 11 en Docker: cliente mariadb (no mysql).
set -euo pipefail

COMPOSE_DIR="${COMPOSE_DIR:-$(cd "$(dirname "$0")/../../../../.." && pwd)}"
DB_SERVICE="${DB_SERVICE:-mariadb}"
MYSQL_USER="${MYSQL_USER:-root}"
MYSQL_PASSWORD="${MYSQL_PASSWORD:-rootpassword}"
TARGET_DB="${TARGET_DB:-joyeria}"
ACTION="${1:-migrate}"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"

cd "$COMPOSE_DIR"

run_sql() {
  local file="$1"
  local base
  base="$(basename "$file")"
  local tmp="/tmp/mig_${base}"
  echo ">>> $base -> $TARGET_DB"
  docker compose cp "$SCRIPT_DIR/$base" "${DB_SERVICE}:${tmp}"
  docker compose exec -T "$DB_SERVICE" sh -c "mariadb -u${MYSQL_USER} -p${MYSQL_PASSWORD} ${TARGET_DB} < ${tmp}"
  docker compose exec -T "$DB_SERVICE" sh -c "rm -f ${tmp}"
}

case "$ACTION" in
  migrate)
    for f in 01_mig_tablas_mapeo.sql 02_mig_catalogos.sql 03_mig_clientes.sql \
             04_mig_piezas.sql 05_mig_piezas_stock.sql 06_mig_movimientos_entrada.sql \
             07_validacion.sql; do
      run_sql "$f"
    done
    ;;
  validate) run_sql "07_validacion.sql" ;;
  rollback) run_sql "99_rollback.sql" ;;
  *) echo "Uso: $0 [migrate|validate|rollback]"; exit 1 ;;
esac

echo "Listo: $ACTION en $TARGET_DB"
