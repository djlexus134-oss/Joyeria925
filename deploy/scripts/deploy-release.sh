#!/usr/bin/env bash
# Actualiza código en el VPS (pull o clone), dependencias, build KPI, permisos.
set -euo pipefail

ENV_FILE="${1:-/etc/joyeria925/env}"
# shellcheck source=/dev/null
source "$ENV_FILE"

WEB_ROOT="${JOYERIA_WEB_ROOT:-/var/www/joyeria925}"
REPO="${JOYERIA_REPO:?Define JOYERIA_REPO en env}"
BRANCH="${JOYERIA_BRANCH:-main}"
DEPLOY_USER="${JOYERIA_DEPLOY_USER:-joyeria925-deploy}"

ensure_git_safe_directory() {
  git config --system --add safe.directory "$WEB_ROOT" 2>/dev/null || true
  if id "$DEPLOY_USER" &>/dev/null; then
    sudo -u "$DEPLOY_USER" git config --global --add safe.directory "$WEB_ROOT" 2>/dev/null || true
  fi
}

ensure_repo_ownership() {
  if [[ ! -d "$WEB_ROOT/.git" ]]; then
    return 0
  fi
  local owner
  owner="$(stat -c '%U' "$WEB_ROOT" 2>/dev/null || echo "")"
  if [[ "$owner" == "root" ]] && id "$DEPLOY_USER" &>/dev/null; then
    echo "El repo es de root; asignando a ${DEPLOY_USER} (evita dubious ownership) ..." >&2
    chown -R "$DEPLOY_USER":www-data "$WEB_ROOT"
    if [[ -f "$WEB_ROOT/config.php" ]]; then
      chmod 640 "$WEB_ROOT/config.php"
    fi
  fi
}

ensure_git_safe_directory
ensure_repo_ownership

if [[ ! -d "$WEB_ROOT/.git" ]]; then
  sudo -u "$DEPLOY_USER" git clone --branch "$BRANCH" "$REPO" "$WEB_ROOT"
else
  cd "$WEB_ROOT"
  sudo -u "$DEPLOY_USER" git fetch origin
  sudo -u "$DEPLOY_USER" git checkout "$BRANCH"
  sudo -u "$DEPLOY_USER" git pull --ff-only origin "$BRANCH"
fi

cd "$WEB_ROOT"

if [[ ! -f config.php ]]; then
  echo "Crea config.php desde config.example.php en $WEB_ROOT"
  exit 1
fi

sudo -u "$DEPLOY_USER" composer install --no-dev --optimize-autoloader --no-interaction

if [[ -f admin/kpi-dashboard/package.json ]]; then
  cd admin/kpi-dashboard
  sudo -u "$DEPLOY_USER" npm ci
  sudo -u "$DEPLOY_USER" npm run build
  cd "$WEB_ROOT"
fi

mkdir -p uploads/contratos admin/imagenes/piezas
chown -R www-data:www-data uploads admin/imagenes/piezas
find uploads admin/imagenes/piezas -type d -exec chmod 775 {} \;

chmod -R o-rwx "$WEB_ROOT/deploy" "$WEB_ROOT/sql" 2>/dev/null || true

echo "Deploy de código OK. Ejecuta migraciones SQL si aplica:"
echo "  sudo bash deploy/scripts/run-sql-migrations.sh"
