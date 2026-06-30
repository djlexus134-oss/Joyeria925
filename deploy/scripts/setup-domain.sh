#!/usr/bin/env bash
# Instala Nginx para Joyería (solo HTTP). Ejecutar DESPUÉS de que DNS apunte al VPS.
set -euo pipefail

ENV_FILE="${1:-/etc/joyeria925/env}"
# shellcheck source=/dev/null
source "$ENV_FILE"

DOMAIN="${JOYERIA_DOMAIN:?Define JOYERIA_DOMAIN en $ENV_FILE}"
WEB_ROOT="${JOYERIA_WEB_ROOT:-/var/www/joyeria925}"
TEMPLATE="${WEB_ROOT}/deploy/nginx/joyeria-http-init.conf"
TARGET="/etc/nginx/sites-available/joyeria925"

if [[ ! -f "$TEMPLATE" ]]; then
  echo "No existe plantilla: $TEMPLATE"
  exit 1
fi

PHP_SOCK="${JOYERIA_PHP_FPM_SOCK:-}"
if [[ -z "$PHP_SOCK" ]]; then
  PHP_SOCK="$(ls /run/php/php*-fpm.sock 2>/dev/null | head -1 || true)"
fi
if [[ -z "$PHP_SOCK" || ! -S "$PHP_SOCK" ]]; then
  echo "No se encontró socket PHP-FPM. Instala php-fpm o define JOYERIA_PHP_FPM_SOCK en env."
  exit 1
fi

sed -e "s|__DOMAIN__|${DOMAIN}|g" \
    -e "s|__WEB_ROOT__|${WEB_ROOT}|g" \
    -e "s|__PHP_FPM_SOCK__|${PHP_SOCK}|g" \
    "$TEMPLATE" >"$TARGET"

ln -sf "$TARGET" /etc/nginx/sites-enabled/joyeria925
rm -f /etc/nginx/sites-enabled/default

nginx -t
systemctl reload nginx

echo "Nginx listo para $DOMAIN (HTTP)."
echo "Prueba: curl -sI http://${DOMAIN}/ | head -1"
echo "Siguiente: bash ${WEB_ROOT}/deploy/scripts/setup-ssl.sh"
