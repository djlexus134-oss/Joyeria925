#!/usr/bin/env bash
# Aplica reglas de endurecimiento en Nginx (logs en uploads, admin/tests, extensiones sensibles).
# Ejecutar en el VPS tras git pull:
#   sudo bash /var/www/joyeria925/deploy/scripts/apply-nginx-security.sh
set -euo pipefail

ENV_FILE="${1:-/etc/joyeria925/env}"
# shellcheck source=/dev/null
if [[ -f "$ENV_FILE" ]]; then
  source "$ENV_FILE"
fi

WEB_ROOT="${JOYERIA_WEB_ROOT:-/var/www/joyeria925}"
TEMPLATE="${WEB_ROOT}/deploy/nginx/joyeria-http-init.conf"
TARGET="/etc/nginx/sites-available/joyeria925"

if [[ ! -f "$TEMPLATE" ]]; then
  echo "No existe plantilla: $TEMPLATE"
  exit 1
fi

DOMAIN="${JOYERIA_DOMAIN:-}"
if [[ -z "$DOMAIN" && -f "$TARGET" ]]; then
  DOMAIN="$(grep -m1 'server_name' "$TARGET" | sed 's/.*server_name \([^;]*\);.*/\1/' | awk '{print $1}')"
fi
if [[ -z "$DOMAIN" ]]; then
  echo "Define JOYERIA_DOMAIN en $ENV_FILE o deja sites-available/joyeria925 con server_name."
  exit 1
fi

PHP_SOCK="${JOYERIA_PHP_FPM_SOCK:-}"
if [[ -z "$PHP_SOCK" ]]; then
  PHP_SOCK="$(grep -m1 'fastcgi_pass unix:' "$TARGET" 2>/dev/null | sed 's/.*unix:\([^;]*\);.*/\1/' || true)"
fi
if [[ -z "$PHP_SOCK" ]]; then
  PHP_SOCK="$(ls /run/php/php*-fpm.sock 2>/dev/null | head -1 || true)"
fi
if [[ -z "$PHP_SOCK" || ! -S "$PHP_SOCK" ]]; then
  echo "No se encontró socket PHP-FPM."
  exit 1
fi

sed -e "s|__DOMAIN__|${DOMAIN}|g" \
    -e "s|__WEB_ROOT__|${WEB_ROOT}|g" \
    -e "s|__PHP_FPM_SOCK__|${PHP_SOCK}|g" \
    "$TEMPLATE" >"$TARGET"

nginx -t
systemctl reload nginx

echo "Nginx actualizado con reglas de seguridad."
echo "Elimina el log expuesto (si existía): rm -f ${WEB_ROOT}/uploads/tmp/joyeria_mp_webhook.log"
mkdir -p /var/log/joyeria925
chown www-data:www-data /var/log/joyeria925 2>/dev/null || chown root:www-data /var/log/joyeria925
chmod 750 /var/log/joyeria925
