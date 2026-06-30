#!/usr/bin/env bash
# Emite certificado Let's Encrypt con certbot (requiere DNS OK y Nginx HTTP activo).
set -euo pipefail

ENV_FILE="${1:-/etc/joyeria925/env}"
# shellcheck source=/dev/null
source "$ENV_FILE"

DOMAIN="${JOYERIA_DOMAIN:?}"
WEB_ROOT="${JOYERIA_WEB_ROOT:-/var/www/joyeria925}"
EMAIL="${JOYERIA_SSL_EMAIL:-}"

if ! command -v certbot >/dev/null 2>&1; then
  apt-get update
  apt-get install -y certbot python3-certbot-nginx
fi

CERTBOT_ARGS=(--nginx -d "$DOMAIN" -d "www.$DOMAIN" --agree-tos --no-eff-email)
if [[ -n "$EMAIL" ]]; then
  CERTBOT_ARGS+=(--email "$EMAIL")
else
  CERTBOT_ARGS+=(--register-unsafely-without-email)
fi

if [[ "${JOYERIA_CERTBOT_STAGING:-0}" == "1" ]]; then
  CERTBOT_ARGS+=(--staging)
  echo "Modo staging (certificado de prueba)."
fi

certbot "${CERTBOT_ARGS[@]}"

nginx -t
systemctl reload nginx

echo ""
echo "HTTPS activo: https://${DOMAIN}/"
echo "Renovación de prueba: certbot renew --dry-run"
echo "Validar app: bash ${WEB_ROOT}/deploy/scripts/validate-domain.sh"
