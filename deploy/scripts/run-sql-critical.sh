#!/usr/bin/env bash
# Rutinas y tablas criticas para RH/contratos (ejecutar aunque run-sql-migrations falle a medias).
set -euo pipefail

ENV_FILE="${1:-/etc/joyeria/env}"
# shellcheck source=/dev/null
source "$ENV_FILE"

WEB_ROOT="${JOYERIA_WEB_ROOT:-/var/www/joyeria}"
DB_NAME="${DB_NAME:-joyeria}"
DB_USER="${DB_USER:-joyeria_app}"
DB_PASSWORD="${DB_PASSWORD:?}"

export MYSQL_PWD="$DB_PASSWORD"

normalize_sql_stream() {
  sed \
    -e '1s/^\xEF\xBB\xBF//' \
    -e 's/utf8mb4_0900_ai_ci/utf8mb4_unicode_ci/g' \
    -e 's/utf8mb4_0900_as_cs/utf8mb4_unicode_ci/g' \
    -e 's/utf8mb4_0900_as_ci/utf8mb4_unicode_ci/g' \
    -e 's/utf8mb4_uca1400_ai_ci/utf8mb4_unicode_ci/g' \
    -e 's/DEFINER=`[^`]*`@`[^`]*`//g'
}

apply_sql_file() {
  local f="$1"
  echo ">>> $(basename "$f")"
  normalize_sql_stream <"$f" | mariadb -u"$DB_USER" "$DB_NAME"
}

for f in \
  "$WEB_ROOT/sql/sp_crud_empleado_direccion_opcional.sql" \
  "$WEB_ROOT/sql/contratos_empleados.sql"
do
  if [[ -f "$f" ]]; then
    apply_sql_file "$f"
  fi
done

if [[ -f /root/joyeria_routines.sql ]]; then
  if grep -q 'mig_fn_ci\|CREATE  FUNCTION' /root/joyeria_routines.sql 2>/dev/null; then
    echo "ERROR: /root/joyeria_routines.sql es el dump VIEJO (funciones Gema / lineas duplicadas)." >&2
    echo "Regenera en PC: deploy/scripts/export-routines-docker.ps1 y scp de nuevo." >&2
    exit 1
  fi
  echo ">>> joyeria_routines.sql (procedimientos desde Docker)"
  normalize_sql_stream </root/joyeria_routines.sql | mariadb -u"$DB_USER" "$DB_NAME"
fi

echo "SQL critico aplicado."
unset MYSQL_PWD
