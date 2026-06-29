#!/usr/bin/env bash
# Comprueba que el dominio sirve web, admin y API de impresión.
set -euo pipefail

ENV_FILE="${1:-/etc/joyeria/env}"
# shellcheck source=/dev/null
source "$ENV_FILE"

DOMAIN="${JOYERIA_DOMAIN:?}"
BASE="https://${DOMAIN}"
TOKEN="${JOYERIA_CAJA_TOKEN:-}"

check_url() {
  local url="$1"
  local expect="$2"
  local code
  code="$(curl -sS -o /dev/null -w '%{http_code}' -L --max-time 20 "$url" || echo "000")"
  if [[ "$code" == "$expect" ]] || [[ "$expect" == "2xx" && "$code" =~ ^2 ]]; then
    echo "OK    $url -> HTTP $code"
    return 0
  fi
  echo "FAIL  $url -> HTTP $code (esperado $expect)"
  return 1
}

echo "=== Validación HTTPS: $DOMAIN ==="
fail=0

check_url "${BASE}/" "2xx" || fail=1
check_url "${BASE}/admin/" "2xx" || fail=1

api_code="$(curl -sS -o /dev/null -w '%{http_code}' --max-time 20 \
  "${BASE}/admin/api/impresion.php?accion=pendientes&destino=ticket" || echo "000")"
if [[ "$api_code" == "401" || "$api_code" == "200" ]]; then
  echo "OK    API impresión -> HTTP $api_code"
else
  echo "FAIL  API impresión -> HTTP $api_code (esperado 401 sin token o 200 con token)"
  fail=1
fi

if [[ -n "$TOKEN" ]]; then
  api_auth="$(curl -sS -o /dev/null -w '%{http_code}' --max-time 20 \
    -H "X-Caja-Token: ${TOKEN}" \
    "${BASE}/admin/api/impresion.php?accion=pendientes&destino=ticket" || echo "000")"
  if [[ "$api_auth" == "200" ]]; then
    echo "OK    API con token -> HTTP 200"
  else
    echo "WARN  API con token -> HTTP $api_auth (revisa impresion_caja_token en BD)"
    fail=1
  fi
else
  echo "INFO  Define JOYERIA_CAJA_TOKEN en /etc/joyeria/env para probar API autenticada."
fi

if [[ $fail -ne 0 ]]; then
  exit 1
fi

echo ""
echo "Dominio válido para Joyería."
echo "PC caja: serverUrl = \"${BASE}/admin\" en print-agent/config.json"
