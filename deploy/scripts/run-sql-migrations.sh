#!/usr/bin/env bash
# Aplica migraciones incrementales en sql/ (orden alfabético).
# Requiere esquema base ya importado (dump de desarrollo o instalación previa).
#
# Si importaste un dump que YA incluye todas las migraciones (p. ej. post migración Gema),
# NO ejecutes este script: puede fallar por tablas/claves duplicadas.
# Úsalo solo para SQL nuevos creados DESPUÉS del dump.
set -euo pipefail

ENV_FILE="${1:-/etc/joyeria/env}"
# shellcheck source=/dev/null
source "$ENV_FILE"

WEB_ROOT="${JOYERIA_WEB_ROOT:-/var/www/joyeria}"
SQL_DIR="$WEB_ROOT/sql"

DB_NAME="${DB_NAME:-joyeria}"
DB_USER="${DB_USER:-joyeria_app}"
DB_PASSWORD="${DB_PASSWORD:?}"

export MYSQL_PWD="$DB_PASSWORD"

shopt -s nullglob
files=(
  "$SQL_DIR"/2026_*.sql
  "$SQL_DIR"/contratos_empleados.sql
  "$SQL_DIR"/direcciones_search_indexes.sql
  "$SQL_DIR"/familias_subfamilia_columnas_busqueda.sql
  "$SQL_DIR"/kpi_views.sql
  "$SQL_DIR"/piezas_aumento_y_recalculo_stock.sql
  "$SQL_DIR"/proveedores_direccion_nullable.sql
  "$SQL_DIR"/recuperacion_contrasena.sql
  "$SQL_DIR"/sp_crud_empleado_direccion_opcional.sql
)

normalize_sql_stream() {
  sed \
    -e '1s/^\xEF\xBB\xBF//' \
    -e 's/utf8mb4_0900_ai_ci/utf8mb4_unicode_ci/g' \
    -e 's/utf8mb4_0900_as_cs/utf8mb4_unicode_ci/g' \
    -e 's/utf8mb4_0900_as_ci/utf8mb4_unicode_ci/g' \
    -e 's/utf8mb4_uca1400_ai_ci/utf8mb4_unicode_ci/g' \
    -e 's/DEFINER=`[^`]*`@`[^`]*`//g'
}

failed=0
for f in "${files[@]}"; do
  [[ -f "$f" ]] || continue
  echo ">>> $(basename "$f")"
  if ! normalize_sql_stream <"$f" | mariadb -u"$DB_USER" "$DB_NAME"; then
    echo "WARN: $(basename "$f") fallo (a menudo ya estaba aplicado en el VPS)." >&2
    failed=$((failed + 1))
  fi
done

if [[ "$failed" -gt 0 ]]; then
  echo "Migraciones con advertencias: $failed archivo(s). Ejecuta: bash deploy/scripts/run-sql-critical.sh" >&2
fi

echo "Migraciones SQL aplicadas."

unset MYSQL_PWD
