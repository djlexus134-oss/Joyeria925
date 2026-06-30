#!/usr/bin/env bash
# Instalación inicial del VPS (Ubuntu 22.04/24.04). Ejecutar como root una sola vez.
set -euo pipefail

ENV_FILE="${1:-/etc/joyeria925/env}"
if [[ ! -f "$ENV_FILE" ]]; then
  echo "Crea $ENV_FILE desde deploy/env.example y vuelve a ejecutar."
  exit 1
fi
# shellcheck source=/dev/null
source "$ENV_FILE"

export DEBIAN_FRONTEND=noninteractive

apt-get update
apt-get upgrade -y

apt-get install -y \
  nginx \
  mariadb-server \
  php-fpm php-cli php-mysql php-gd php-mbstring php-xml php-zip php-intl php-curl \
  composer \
  git \
  curl \
  ufw \
  certbot python3-certbot-nginx

# Node solo para compilar KPI en deploy (opcional: quitar después)
if ! command -v node >/dev/null 2>&1; then
  curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
  apt-get install -y nodejs
fi

systemctl enable nginx mariadb php8.3-fpm
systemctl start nginx mariadb php8.3-fpm

# Usuario de despliegue (distinto a joyeria-deploy si ya existe otro proyecto)
DEPLOY_USER="${JOYERIA_DEPLOY_USER:-joyeria925-deploy}"
id "$DEPLOY_USER" &>/dev/null || useradd -m -s /bin/bash "$DEPLOY_USER"
usermod -aG www-data "$DEPLOY_USER"

mkdir -p /var/www

# Base de datos
mysql -e "CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';"
mysql -e "GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';"
mysql -e "FLUSH PRIVILEGES;"

mkdir -p /etc/joyeria925
chmod 700 /etc/joyeria925

# Firewall
ufw allow OpenSSH
ufw allow 'Nginx Full'
ufw --force enable

echo "Bootstrap listo. Siguiente: deploy/scripts/deploy-release.sh"
