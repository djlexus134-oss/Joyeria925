#!/usr/bin/env bash
# Importa un dump completo (.sql) en MariaDB (VPS).
# Convierte collations de MySQL 8 (utf8mb4_0900_*) a utf8mb4_unicode_ci si hace falta.
set -euo pipefail

ENV_FILE="${1:-/etc/joyeria/env}"
DUMP="${2:-}"
RECREATE_DB=0

usage() {
  echo "Uso: $0 [--recreate-db] [/etc/joyeria/env] /ruta/backup_joyeria.sql"
  echo ""
  echo "  --recreate-db   Borra y recrea la BD antes de importar (recomendado si falló a medias)."
  exit 1
}

args=()
for arg in "$@"; do
  case "$arg" in
    --recreate-db) RECREATE_DB=1 ;;
    -h|--help) usage ;;
    *) args+=("$arg") ;;
  esac
done

if [[ ${#args[@]} -ge 2 ]]; then
  ENV_FILE="${args[0]}"
  DUMP="${args[1]}"
elif [[ ${#args[@]} -eq 1 ]]; then
  DUMP="${args[0]}"
else
  usage
fi

if [[ -z "$DUMP" || ! -f "$DUMP" ]]; then
  echo "Archivo no encontrado: $DUMP"
  usage
fi

# shellcheck source=/dev/null
source "$ENV_FILE"

DB_NAME="${DB_NAME:-joyeria}"
DB_USER="${DB_USER:-root}"
DB_PASSWORD="${DB_PASSWORD:?}"
MARIADB_COLLATION="${JOYERIA_DB_COLLATION:-utf8mb4_unicode_ci}"

export MYSQL_PWD="$DB_PASSWORD"

needs_collation_fix() {
  grep -q 'utf8mb4_0900' "$DUMP" 2>/dev/null
}

normalize_dump_stream() {
  # BOM (PowerShell), collations MySQL 8, DEFINER root (requiere SUPER en VPS).
  sed \
    -e '1s/^\xEF\xBB\xBF//' \
    -e 's/utf8mb4_0900_ai_ci/utf8mb4_unicode_ci/g' \
    -e 's/utf8mb4_0900_as_cs/utf8mb4_unicode_ci/g' \
    -e 's/utf8mb4_0900_as_ci/utf8mb4_unicode_ci/g' \
    -e 's/utf8mb4_0900_bin/utf8mb4_unicode_ci/g' \
    -e 's/DEFINER=`[^`]*`@`[^`]*`//g' \
    -e 's/DEFINER=[^ *]*@[^ *]*//g' \
    -e 's/SQL SECURITY DEFINER/SQL SECURITY INVOKER/g' \
    -e '/^CREATE USER /d' \
    -e '/^GRANT /d' \
    "$DUMP"
}

stream_dump() {
  if needs_collation_fix; then
    echo "Detectado dump MySQL 8 (utf8mb4_0900_*). Convirtiendo a ${MARIADB_COLLATION} ..." >&2
  fi
  if grep -q 'DEFINER=' "$DUMP" 2>/dev/null; then
    echo "Eliminando DEFINER (triggers/vistas/procedures) para import sin privilegio SUPER ..." >&2
  fi
  normalize_dump_stream
}

if [[ "$RECREATE_DB" -eq 1 ]]; then
  echo "Recreando base de datos ${DB_NAME} ..."
  mariadb -u"$DB_USER" -e "DROP DATABASE IF EXISTS \`${DB_NAME}\`;"
  mariadb -u"$DB_USER" -e "CREATE DATABASE \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE ${MARIADB_COLLATION};"
fi

echo "Importando $DUMP en ${DB_NAME} ..." >&2
stream_dump | mariadb -u"$DB_USER" "$DB_NAME"
echo "Import OK." >&2

unset MYSQL_PWD
