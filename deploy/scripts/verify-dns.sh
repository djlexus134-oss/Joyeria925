#!/usr/bin/env bash
# Verifica que el dominio resuelve a la IP esperada del VPS.
set -euo pipefail

ENV_FILE="${1:-/etc/joyeria925/env}"
# shellcheck source=/dev/null
source "$ENV_FILE"

DOMAIN="${JOYERIA_DOMAIN:?}"
EXPECTED_IP="${JOYERIA_VPS_IP:-}"

resolve_a() {
  local host="$1"
  if command -v dig >/dev/null 2>&1; then
    dig +short "$host" A | head -1
  elif command -v host >/dev/null 2>&1; then
    host -t A "$host" 2>/dev/null | awk '/has address/ { print $4; exit }'
  else
    getent ahosts "$host" 2>/dev/null | awk '/STREAM/ { print $1; exit }'
  fi
}

check_host() {
  local host="$1"
  local ip
  ip="$(resolve_a "$host")"
  if [[ -z "$ip" ]]; then
    echo "FAIL  $host — sin registro A (propagación pendiente o DNS mal configurado)"
    return 1
  fi
  if [[ -n "$EXPECTED_IP" && "$ip" != "$EXPECTED_IP" ]]; then
    echo "WARN  $host -> $ip (esperabas $EXPECTED_IP)"
    return 1
  fi
  echo "OK    $host -> $ip"
  return 0
}

echo "=== DNS: $DOMAIN ==="
ok=0
check_host "$DOMAIN" || ok=1
check_host "www.$DOMAIN" || ok=1

if [[ $ok -ne 0 ]]; then
  echo ""
  echo "Revisa en Hostinger hPanel: registro A @ y www apuntando a la IP del VPS."
  echo "Herramienta externa: https://dnschecker.org/#A/${DOMAIN}"
  exit 1
fi

echo ""
echo "DNS correcto. Puedes ejecutar setup-domain.sh y setup-ssl.sh."
