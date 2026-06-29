#!/usr/bin/env bash
# Importa solo_datos_gema.sql (sin configuracion_general).
# Trunca tablas de datos Gema y carga el export. Mantiene config y usuarios admin no incluidos en el SQL.
set -euo pipefail

ENV_FILE="${1:-/etc/joyeria/env}"
DUMP="${2:-}"

if [[ -z "$DUMP" || ! -f "$DUMP" ]]; then
  echo "Uso: $0 [/etc/joyeria/env] /root/solo_datos_gema.sql"
  exit 1
fi

# shellcheck source=/dev/null
source "$ENV_FILE"

DB_NAME="${DB_NAME:-joyeria}"
DB_USER="${DB_USER:-joyeria_app}"
DB_PASSWORD="${DB_PASSWORD:?}"

export MYSQL_PWD="$DB_PASSWORD"

REPO_ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
LIMPIAR_SQL="${LIMPIAR_SQL:-$REPO_ROOT/sql/migracion_gema/limpiar_todas_tablas_datos_gema.sql}"

normalize_stream() {
  sed \
    -e '1s/^\xEF\xBB\xBF//' \
    -e 's/utf8mb4_0900_ai_ci/utf8mb4_unicode_ci/g' \
    -e 's/utf8mb4_0900_as_cs/utf8mb4_unicode_ci/g' \
    -e 's/utf8mb4_0900_as_ci/utf8mb4_unicode_ci/g' \
    -e 's/DEFINER=`[^`]*`@`[^`]*`//g' \
    -e 's/SQL SECURITY DEFINER/SQL SECURITY INVOKER/g'
}

if [[ ! -f "$LIMPIAR_SQL" ]]; then
  echo "No se encontro limpieza: $LIMPIAR_SQL" >&2
  exit 1
fi

echo "Limpieza previa (todas las tablas de datos, conserva empleados y configuracion_general) ..." >&2
{
  echo "SET NAMES utf8mb4;"
  echo "SET FOREIGN_KEY_CHECKS = 0;"
  cat "$LIMPIAR_SQL"
  echo "SET FOREIGN_KEY_CHECKS = 1;"
} | mariadb -u"$DB_USER" "$DB_NAME"

echo "Vaciando clientes antes del dump (ids fijos Gema) ..." >&2
{
  echo "SET NAMES utf8mb4;"
  echo "SET FOREIGN_KEY_CHECKS = 0;"
  echo "TRUNCATE TABLE cliente_credito_consumos;"
  echo "TRUNCATE TABLE cliente_creditos;"
  echo "TRUNCATE TABLE clientes;"
  echo "DELETE ur FROM usuario_rol ur"
  echo "LEFT JOIN empleados e ON e.id_usuario_FK = ur.id_usuario_FK"
  echo "WHERE e.id_empleado IS NULL;"
  echo "DELETE u FROM usuarios u"
  echo "LEFT JOIN empleados e ON e.id_usuario_FK = u.id_usuario"
  echo "WHERE e.id_empleado IS NULL;"
  echo "SET FOREIGN_KEY_CHECKS = 1;"
} | mariadb -u"$DB_USER" "$DB_NAME"

echo "Importando datos Gema en ${DB_NAME} ..." >&2
normalize_stream <"$DUMP" | mariadb -u"$DB_USER" "$DB_NAME"

echo "Import datos Gema OK." >&2
echo "Siguiente: restaurar token con deploy/sql/restore_config_produccion.example.sql" >&2

unset MYSQL_PWD
