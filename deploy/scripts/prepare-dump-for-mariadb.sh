#!/usr/bin/env bash
# Convierte un .sql exportado desde MySQL 8 a collations compatibles con MariaDB.
# Uso local o en VPS antes de importar:
#   bash prepare-dump-for-mariadb.sh backup.sql backup_mariadb.sql
set -euo pipefail

IN="${1:-}"
OUT="${2:-}"

if [[ -z "$IN" || ! -f "$IN" ]]; then
  echo "Uso: $0 entrada.sql [salida.sql]"
  echo "  Si omites salida.sql, se usa entrada.sql.mariadb"
  exit 1
fi

if [[ -z "$OUT" ]]; then
  OUT="${IN}.mariadb"
fi

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
  "$IN" >"$OUT"

echo "Listo: $OUT"
grep -c 'utf8mb4_0900' "$OUT" 2>/dev/null && echo "AVISO: aún quedan collations 0900" || echo "Sin collations MySQL 8 restantes."
