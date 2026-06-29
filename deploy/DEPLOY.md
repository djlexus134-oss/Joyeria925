# Deploy Joyería — VPS + PC de caja

Guía para publicar la aplicación en un **VPS Linux** (PHP + MariaDB + Nginx) y dejar operativos los **agentes de impresión** en una **PC Windows** de tienda (impresoras **USB o red**).

## Arquitectura

```
                    Internet
                        │
                        ▼
              ┌─────────────────┐
              │  VPS (Linux)    │
              │  Nginx + PHP    │
              │  MariaDB        │
              │  cola_impresion │
              └────────┬────────┘
                       │ HTTPS poll (X-Caja-Token)
         ┌─────────────┴─────────────┐
         ▼                           ▼
  ┌──────────────┐            ┌──────────────┐
  │ PC caja      │            │ Móvil / POS  │
  │ Windows      │            │ Navegador    │
  │ print-agent  │            │ (solo web)   │
  │ print-agent- │            └──────────────┘
  │  etiquetas   │
  │ Epson (red)  │
  │ + etiquetas  │
  └──────────────┘
```

| Componente | Dónde corre | SO |
|------------|-------------|-----|
| Web + admin + API | VPS | Ubuntu 22.04/24.04 |
| Base de datos | VPS (localhost) | MariaDB |
| Agente tickets | PC caja | Windows 10/11 |
| Agente etiquetas Argox | PC caja (misma u otra) | Windows 10/11 |

---

## Parte 0 — Qué subir al repositorio

El `.gitignore` excluye secretos, `vendor/`, `node_modules/`, configs locales de agentes, dumps de migración Gema y el build del KPI (se genera en deploy).

**En el repo van:** código PHP/JS fuente, `sql/`, `deploy/`, `print-agent*` (solo `config.example.json`), `config.example.php`, `.env.example`.

**No van:** `config.php`, `.env`, `vendor/`, `print-agent/config.json`, `print-agent-etiquetas/config.json`, `sql/migracion_gema/zipgema_dump/`, `backup_*.sql`, imágenes de piezas subidas, contratos PDF.

### Seguridad antes de `git push`

| Archivo | Riesgo | Acción |
|---------|--------|--------|
| `.env` | Contraseñas BD y SMTP reales | Nunca subir. Si alguna vez se subió, **rota** contraseñas y revoca App Password de Gmail. |
| `config.php` | Igual que `.env` | Solo local / VPS; está en `.gitignore`. |
| `print-agent*/config.json` | Token `X-Caja-Token` y URL producción | Copiar desde `config.example.json`; valores de ejemplo: `cambiar_token_seguro`, `tu-dominio.com`. |
| `backup_joyeria*.sql` | Datos de clientes, ventas, empleados | Transferir con `scp`, no con Git. |
| Admin tokens en BD | Tras deploy, token real solo en VPS y PC caja | No pegar tokens reales en commits ni capturas. |
| `uploads/tmp/*.log` | Datos de pagos MP (webhook) | Nginx debe denegar `/uploads/tmp/`; el log va a `/var/log/joyeria/`. |
| `admin/tests/` | Scripts de prueba ejecutables | En `.gitignore`; Nginx debe devolver 404. |

Comprobar qué se subiría:

```bash
git status
git check-ignore -v .env config.php print-agent/config.json
```

Solo deben aparecer como ignorados (no en la lista de "Changes to be committed").

Antes del primer push:

```powershell
cd D:\PrograWEB\src\Joyeria
git init
git add .
git status   # revisa que no aparezcan secretos ni node_modules
git commit -m "Initial deploy-ready release"
git remote add origin https://github.com/TU_USUARIO/joyeria.git
git push -u origin main
```

Build local del KPI (si aún no lo hiciste y quieres probar antes del VPS):

```powershell
cd admin\kpi-dashboard
npm ci
npm run build
```

---

## Parte 1 — Datos del sistema anterior (Gema)

**Recomendado si el dump completo al VPS falló o mezcló basura:** migrar solo datos Gema en Docker y subir un SQL pequeño.

Guía completa: **[sql/migracion_gema/MIGRACION_SOLO_DATOS.md](../sql/migracion_gema/MIGRACION_SOLO_DATOS.md)**

Resumen:

```powershell
cd D:\PrograWEB\src\Joyeria\sql\migracion_gema
.\migracion_gema_docker.ps1 -Paso todo
.\export_datos_gema_vps.ps1
scp D:\PrograWEB\solo_datos_gema.sql root@IP_VPS:/root/
```

En el VPS:

```bash
bash /var/www/joyeria/deploy/scripts/import-datos-gema.sh /etc/joyeria/env /root/solo_datos_gema.sql
mariadb -u joyeria_app -p joyeria < /root/restore_config.sql
```

---

### Alternativa: dump completo de `joyeria`

La migración del sistema anterior también puede exportarse como **dump entero** desde Docker (incluye config local — menos recomendado para producción).

1. Sigue `sql/migracion_gema/00_staging_import.md` (scripts `migracion_gema_docker.ps1`).
2. Cuando `joyeria` esté validada, genera un dump para producción:

```powershell
cd D:\PrograWEB
docker compose exec -T mariadb sh -c "mariadb-dump -uroot -prootpassword --single-transaction --routines --triggers joyeria" | Out-File -Encoding utf8 backup_joyeria_produccion.sql
```

> No redirijas con `>` dentro del contenedor desde PowerShell sin cuidado; el script `backup_joyeria.ps1` del proyecto es la opción segura.

3. Sube `backup_joyeria_produccion.sql` al VPS con `scp` (ver parte 2).

En el VPS **no** necesitas los `.sql` de `zipgema_dump/`; solo el dump final + migraciones incrementales nuevas si las agregas después.

---

## Parte 2 — VPS desde cero (terminal)

Sustituye `tu-dominio.com` y credenciales reales.

### 2.1 Conectar y usuario

```bash
ssh root@IP_DEL_VPS
adduser joyeria-deploy
usermod -aG sudo joyeria-deploy
rsync --archive --chown=joyeria-deploy:joyeria-deploy ~/.ssh /home/joyeria-deploy/
```

### 2.2 Variables de entorno del servidor

```bash
mkdir -p /etc/joyeria
nano /etc/joyeria/env
```

Contenido (basado en `deploy/env.example`):

```bash
JOYERIA_DOMAIN=tu-dominio.com
JOYERIA_WEB_ROOT=/var/www/joyeria
JOYERIA_REPO=https://github.com/TU_USUARIO/joyeria.git
JOYERIA_BRANCH=main

DB_NAME=joyeria
DB_USER=joyeria_app
DB_PASSWORD=password_largo_aleatorio
```

```bash
chmod 600 /etc/joyeria/env
```

### 2.3 Bootstrap (paquetes, MariaDB, firewall)

Copia la carpeta `deploy` al VPS o clona el repo primero; luego:

```bash
# Si aún no hay repo:
apt-get update && apt-get install -y git
git clone https://github.com/djlexus134-oss/joyeria.git /var/www/joyeria

bash /var/www/joyeria/deploy/scripts/vps-bootstrap.sh /etc/joyeria/env
```

### 2.4 Configuración PHP de la app

```bash
cd /var/www/joyeria
cp config.example.php config.php
nano config.php
```

Ajusta `DBHOST`, `DBUSER`, `DBPASSWORD`, `DBNAME` (mismos que en `/etc/joyeria/env`) y SMTP.

En `config.php` define la zona horaria del negocio (evita que las fechas por defecto salgan un dia antes en UTC):

```php
define('JOYERIA_TIMEZONE', 'America/Mexico_City');
```

Zona horaria del servidor (recomendado, una sola vez):

```bash
timedatectl set-timezone America/Mexico_City
```

PHP-FPM (opcional, refuerzo):

```bash
grep -r date.timezone /etc/php/*/fpm/php.ini
# date.timezone = America/Mexico_City
systemctl reload php8.2-fpm
```

```bash
chown www-data:www-data config.php
chmod 640 config.php
```

### 2.5 Importar base de datos

Desde tu PC:

```bash
scp backup_joyeria_produccion.sql root@IP_DEL_VPS:/root/
```

En el VPS:

```bash
bash /var/www/joyeria/deploy/scripts/import-database.sh /etc/joyeria/env /root/backup_joyeria_produccion.sql
```

Si el import falló a medias (p. ej. por collation), recrea la BD e importa de nuevo:

```bash
bash /var/www/joyeria/deploy/scripts/import-database.sh --recreate-db /etc/joyeria/env /root/backup_joyeria_produccion.sql
```

**Error `Unknown collation: utf8mb4_0900_ai_ci`:** el dump viene de MySQL 8 (Docker) y el VPS usa MariaDB. El script `import-database.sh` convierte automáticamente a `utf8mb4_unicode_ci`. Alternativa manual:

```bash
bash /var/www/joyeria/deploy/scripts/prepare-dump-for-mariadb.sh /root/backup_joyeria_produccion.sql /root/backup_mariadb.sql
bash /var/www/joyeria/deploy/scripts/import-database.sh --recreate-db /etc/joyeria/env /root/backup_mariadb.sql
```

Para futuros respaldos desde Docker, puedes exportar ya compatible:

```powershell
cd D:\PrograWEB
$out = "D:\PrograWEB\backup_joyeria_datos.sql"
$dump = docker compose exec -T mariadb sh -c "mariadb-dump -uroot -prootpassword --single-transaction joyeria"
$dump = ($dump | ForEach-Object { $_ -replace 'utf8mb4_0900_ai_ci','utf8mb4_unicode_ci' }) -join "`n"
$utf8 = New-Object System.Text.UTF8Encoding $false
[System.IO.File]::WriteAllText($out, $dump, $utf8)
```

(Windows PowerShell 5.1 no admite `-Encoding utf8NoBOM`; el bloque de arriba guarda UTF-8 sin BOM. En el VPS, `import-database.sh` también quita BOM por si acaso.)

### 2.5.1 Configuración después del dump (importante)

**No ejecutes** `mariadb ... < configuracion_general_produccion.sql` si ese archivo trae `INSERT` con `id` fijos: la tabla ya viene del dump y dará `ERROR 1062 Duplicate entry`.

Después de un import exitoso (`Import OK.`):

```bash
# 1) Claves faltantes (solo inserta las que no existan)
mariadb -u joyeria_app -p joyeria < /var/www/joyeria/sql/2026_05_20_configuracion_general_plantilla.sql

# 2) Tus valores de produccion (token, etc.) — copia y edita en el VPS:
cp /var/www/joyeria/deploy/sql/restore_config_produccion.example.sql /root/restore_config.sql
nano /root/restore_config.sql
mariadb -u joyeria_app -p joyeria < /root/restore_config.sql
```

El token de `impresion_caja_token` debe ser **igual** en admin, `print-agent/config.json` y `print-agent-etiquetas/config.json` en la PC caja.

Si importaste un dump **completo y actualizado** (post migración Gema), **no** ejecutes `run-sql-migrations.sh` salvo que hayas añadido archivos `.sql` nuevos después del dump.

Solo para parches posteriores al dump:

```bash
bash /var/www/joyeria/deploy/scripts/run-sql-migrations.sh /etc/joyeria/env
```

### 2.5b Dominio Hostinger → VPS externo

Si el dominio está en **Hostinger** y el VPS en **otro proveedor**, sigue la guía detallada: **[deploy/HOSTINGER-DNS.md](HOSTINGER-DNS.md)**.

Resumen:

1. En hPanel: registro **A** `@` y `www` → IP del VPS.
2. En `/etc/joyeria/env`: `JOYERIA_DOMAIN` y `JOYERIA_VPS_IP`.
3. Scripts en el VPS (en orden):

```bash
bash /var/www/joyeria/deploy/scripts/verify-dns.sh
bash /var/www/joyeria/deploy/scripts/setup-domain.sh
bash /var/www/joyeria/deploy/scripts/setup-ssl.sh
bash /var/www/joyeria/deploy/scripts/validate-domain.sh
```

### 2.6 Nginx (manual, alternativa a los scripts)

Preferible usar `setup-domain.sh` + `setup-ssl.sh` (plantilla [nginx/joyeria-http-init.conf](nginx/joyeria-http-init.conf)).

Si lo haces a mano: copia `joyeria-http-init.conf`, sustituye `__DOMAIN__`, `__WEB_ROOT__`, `__PHP_FPM_SOCK__`, **sin** redirigir HTTP→HTTPS hasta tener certificado.

TLS:

```bash
certbot --nginx -d plateria-el-angel.shop -d www.plateria-el-angel.shop
```

### 2.7 Desplegar / actualizar código

```bash
bash /var/www/joyeria/deploy/scripts/deploy-release.sh /etc/joyeria/env
```

Cada release futuro:

```bash
bash /var/www/joyeria/deploy/scripts/deploy-release.sh /etc/joyeria/env
bash /var/www/joyeria/deploy/scripts/run-sql-migrations.sh /etc/joyeria/env
```

**Error `fatal: detected dubious ownership`:** el clon lo hiciste como `root` pero `deploy-release.sh` ejecuta git como `joyeria-deploy`. Solución en el VPS:

```bash
git config --system --add safe.directory /var/www/joyeria
sudo -u joyeria-deploy git config --global --add safe.directory /var/www/joyeria
chown -R joyeria-deploy:www-data /var/www/joyeria
chmod 640 /var/www/joyeria/config.php
bash /var/www/joyeria/deploy/scripts/deploy-release.sh /etc/joyeria/env
```

(`git config --global` solo como **root** no aplica al usuario `joyeria-deploy`.)

### 2.8 Token de impresión (obligatorio para agentes)

En el panel: **Sistema → Ticket POS** (o vía SQL):

```sql
UPDATE configuracion_general SET valor = 'TOKEN_LARGO_ALEATORIO_32_CHARS'
WHERE clave IN ('impresion_caja_token', 'etiqueta_impresion_token');
```

Usa el **mismo token** en los `config.json` de la PC caja.

Comprueba API desde cualquier máquina:

```bash
curl -s -H "X-Caja-Token: TOKEN_LARGO" \ "https://plateria-el-angel.shop/admin/api/impresion.php?accion=pendientes&destino=ticket"
```

Respuesta esperada: `{"success":true,...}` (aunque no haya trabajos pendientes).

### 2.8a Correo SMTP (empleados, recuperación de contraseña)

**No uses el puerto 25 del VPS** para enviar correo (Hetzner y la mayoría de proveedores lo bloquean o cae en spam). Usa un relay externo: **Gmail** (contraseña de aplicación), **Hostinger Email**, SendGrid, etc.

**Opción A — `config.php` en el VPS** (recomendado; tiene prioridad sobre la BD):

```php
define('JOYERIA_SMTP_HOST', 'smtp.gmail.com');
define('JOYERIA_SMTP_PORT', 465);
define('JOYERIA_SMTP_SECURE', 'ssl');   // o 587 + 'tls'
define('JOYERIA_SMTP_USERNAME', 'tu@gmail.com');
define('JOYERIA_SMTP_PASSWORD', 'xxxx xxxx xxxx xxxx'); // App Password
define('JOYERIA_SMTP_FROM_EMAIL', 'tu@gmail.com');
define('JOYERIA_SMTP_FROM_NAME', 'Platería El Ángel');
define('JOYERIA_APP_URL', 'https://plateria-el-angel.shop');
```

**Opción B — tabla `configuracion_general`:**

```bash
mariadb -u joyeria_app -p joyeria < /var/www/joyeria/sql/2026_05_21_smtp_config_plantilla.sql
```

Luego actualiza las claves `smtp_*` y `app_url` (panel o SQL).

**Probar envío en el VPS:**

```bash
cd /var/www/joyeria
composer install --no-dev --optimize-autoloader
php deploy/scripts/test-mail.php tu-correo@gmail.com
```

Salida esperada: `"success": true`. Si falla:

| Error típico | Qué hacer |
|--------------|-----------|
| `Could not connect to SMTP host` | Firewall del VPS: salida **465/587** permitida (`ufw status`). |
| `Authentication failed` | Gmail: activar verificación en 2 pasos y crear **contraseña de aplicación**. |
| `Correo no configurado` | Rellena `JOYERIA_SMTP_*` en `config.php` o claves `smtp_*` en BD. |
| Timeout / SSL | Prueba `587` + `tls` en lugar de `465` + `ssl`. |

**Correos automáticos de empleados** (`admin/empleado.php`):

- Al **crear** empleado → credenciales con contraseña temporal.
- Al **actualizar** si cambia correo y/o contraseña → aviso con datos nuevos.

El aviso de phishing de Hetzner sobre “cuenta suspendida” **no bloquea** tu SMTP; ignóralo si no pediste nada en un enlace raro.

### 2.9 Endurecimiento rápido (si ya hay sitio en producción)

Si el webhook de Mercado Pago dejó logs bajo `uploads/tmp/`, **borra el archivo** y aplica Nginx + PHP actualizados:

```bash
sudo rm -f /var/www/joyeria/uploads/tmp/joyeria_mp_webhook.log
cd /var/www/joyeria && sudo -u joyeria-deploy git pull
sudo bash /var/www/joyeria/deploy/scripts/apply-nginx-security.sh
# Reinicia PHP-FPM si cambiaste tienda_pago_webhook.php
sudo systemctl reload php8.2-fpm || sudo systemctl reload php8.3-fpm
```

Comprueba que ya no responda 200:

```bash
curl -sI https://tu-dominio.com/uploads/tmp/joyeria_mp_webhook.log | head -1
curl -sI https://tu-dominio.com/admin/tests/smoke_resurtido_logic.php | head -1
```

Debe ser `403` o `404`, no `200`.

### 2.10 Buenas prácticas VPS

| Tema | Acción |
|------|--------|
| SSH | Solo llaves; `PasswordAuthentication no` |
| Firewall | `ufw` — solo 22, 80, 443 |
| MariaDB | Solo `127.0.0.1`; no exponer 3306 |
| Secretos | `config.php` y `/etc/joyeria/env` fuera de Git |
| Backups | Cron diario: `mariadb-dump` + copia off-site |
| Actualizaciones | `apt-get update && apt-get upgrade` mensual |

Ejemplo backup cron (`/etc/cron.daily/joyeria-backup`):

```bash
#!/bin/sh
source /etc/joyeria/env
export MYSQL_PWD="$DB_PASSWORD"
mariadb-dump -u"$DB_USER" "$DB_NAME" | gzip > /var/backups/joyeria-$(date +%F).sql.gz
find /var/backups -name 'joyeria-*.sql.gz' -mtime +14 -delete
```

---

## Parte 3 — PC de caja (Windows, “de fábrica”)

### 3.1 Software base

1. **Windows Update** y usuario administrador local.
2. **Node.js 20 LTS** — https://nodejs.org/
3. **Python 3.11+** — marcar “Add python.exe to PATH”.
4. **Git** (opcional, para clonar solo agentes).

### 3.2 Drivers de impresoras

**Epson TM-T20 (tickets)** — ver también [3.2b Epson por red](#32b-epson-tm-t20-por-red-ethernetwifi) y `print-agent/README.md`:

- **TM-APD** (Advanced Printer Driver) — obligatorio para tickets ESC/POS
- TMUSB Device Driver — solo si también usas USB
- Comprobar nombre en Windows: `EPSON TM-T20 Receipt` (puede variar según el driver instalado)

**Etiquetas (Zebra ZD220 u otra)** — ver `print-agent-etiquetas/README.md`:

- Driver del fabricante (ej. `ZDesigner ZD220-203dpi ZPL` en modo IMAGEN)
- Material: gap 3 mm; etiqueta mariposa 60×10 mm

```powershell
Get-Printer | Format-Table Name, DriverName, PortName
```

### 3.2b Epson TM-T20 por red (Ethernet/Wi‑Fi)

La impresora de tickets puede ir **en la LAN** (sin USB). El sistema **no** escribe a `COM4` ni a la IP desde PHP: el flujo es siempre:

```
POS (navegador) → cola_impresion (VPS) → print-agent (PC caja) → impresora Windows (RAW) → red → Epson
```

El agente solo necesita el **nombre exacto** de la impresora en Windows (`printerName` en `config.json`). El puerto (TCP/IP, COM emulation, USB) lo resuelve Windows.

#### A) Obtener la IP de la impresora

1. Conecta la Epson a la misma red que la PC de caja (Ethernet o Wi‑Fi).
2. Imprime la **hoja de configuración de red** (botón Feed en la impresora o menú Epson).
3. Anota:
   - **IP Address** (ej. `192.168.68.66`)
   - **Gateway** (ej. `192.168.68.1`)
   - **DHCP** (Enable / Disable)

Opcional: abre **WebConfig** en el navegador: `http://IP_DE_LA_IMPRESORA`

#### B) Comprobar conectividad desde la PC de caja

```powershell
ping 192.168.68.66
```

Debe responder. Si no:

- PC e impresora en la **misma subred** (misma Wi‑Fi / mismo switch).
- Revisa firewall del router o VLAN distinta.

#### C) Instalar la impresora en Windows

**Opción recomendada — puerto TCP/IP**

1. **Configuración → Impresoras y escáneres → Agregar dispositivo → Agregar manualmente**.
2. **TCP/IP** → dirección `192.168.68.66`, puerto **9100** (RAW, habitual en Epson).
3. Driver: fabricante **EPSON** → **`EPSON TM-T20 ReceiptE4`**
   - **ReceiptE4** = ESC/POS (México/LATAM) — el correcto para tickets del POS.
   - **ReceiptJ4** = variante Japón — no usar.
   - **TM-T20-42C** — solo si la etiqueta física del equipo dice `-42C`.
4. Nombre sugerido: **`EPSON TM-T20 Receipt`**.
5. Imprime **página de prueba** desde Windows.

**Opción alternativa — EPSON COM Port Emulation**

1. Instala la utilidad **EPSON COM Port Emulation**.
2. Mapea **COM4** (u otro COM libre) → IP `192.168.68.66`.
3. **Test Print** en la utilidad → debe salir ticket.
4. En la impresora en Windows: **Propiedades → Puertos** → marca **COM4: (EPSON COM Emulation TCP/IP Port)**.

No hace falta configurar `COM4` en PHP ni en `print-agent`; solo importa que la impresora de Windows use un puerto que llegue a la IP correcta.

#### D) Verificar nombre para el agente

```powershell
Get-Printer | Select-Object Name, PortName, DriverName
```

Copia el valor de **Name** (ej. `EPSON TM-T20 Receipt`) a `print-agent/config.json` → `printerName`.

#### E) Configurar `print-agent` (tickets)

**Producción (VPS + HTTPS):**

```json
{
  "serverUrl": "https://tu-dominio.com/admin",
  "serverUrlUseLocalhost": false,
  "cajaToken": "TOKEN_LARGO_IGUAL_AL_ADMIN",
  "printerName": "EPSON TM-T20 Receipt",
  "pollIntervalMs": 1500,
  "idTiendaCaja": 1
}
```

**Desarrollo local (Docker en la misma PC, puerto 8080):**

```json
{
  "serverUrl": "auto",
  "serverUrlUseLocalhost": true,
  "serverScheme": "http",
  "serverPort": 8080,
  "serverPath": "/Joyeria925/admin",
  "cajaToken": "TOKEN_LARGO_IGUAL_AL_ADMIN",
  "printerName": "EPSON TM-T20 Receipt",
  "pollIntervalMs": 1500,
  "idTiendaCaja": 1
}
```

En el admin: **Configuración → Ticket POS** → activar **Encolar tickets al confirmar venta** y el **mismo token** que `cajaToken`.

Prueba:

```powershell
cd C:\Joyeria\print-agent
npm install
npm start
```

Haz una venta de prueba en el POS; en consola debe aparecer `Ticket impreso venta #...`.

#### F) Fijar la IP (evitar “desconfiguración” tras apagones)

Un **apagón de luz no borra** el driver ni el `config.json` del agente. La impresora guarda su configuración en memoria flash.

El **riesgo real** es que, con **DHCP activo**, el router asigne **otra IP** al encender y Windows siga apuntando a la IP antigua → deja de imprimir hasta actualizar el puerto o reservar la IP.

| Medida | Acción |
|--------|--------|
| **Reserva DHCP (recomendado)** | En el router (`192.168.68.1` o el gateway de la hoja): reservar la MAC de la Epson → IP fija (ej. `192.168.68.66`). |
| **IP estática en la impresora** | WebConfig → desactivar DHCP → IP, máscara y gateway manuales. |
| **Tras un apagón (checklist 30 s)** | 1) ¿Impresora encendida? 2) `ping` a la IP esperada 3) Si falla, imprimir hoja de red y actualizar puerto Windows si cambió la IP. |

No hace falta reinstalar drivers ni reconfigurar el agente por cada corte de luz si la IP está reservada o es estática.

#### G) Solución de problemas — Epson en red

| Síntoma | Causa habitual | Qué hacer |
|---------|----------------|-----------|
| Test Print Epson OK, POS no imprime | Agente apagado o token distinto | `npm start` / servicio NSSM; igualar token admin y `config.json` |
| `Impresora no encontrada` en agente | `printerName` incorrecto | `Get-Printer` → copiar nombre exacto |
| Ping falla | Red distinta o IP cambió | Hoja de red; reserva DHCP |
| Ticket basura o en blanco | Driver incorrecto | Usar **ReceiptE4**, no J4 |
| Cola pendiente, agente silencioso | URL del VPS inalcanzable | `curl` a `/admin/api/impresion.php`; firewall Windows permite Node.js |

### 3.3 Clonar proyecto en la caja

```powershell
git clone https://github.com/TU_USUARIO/joyeria.git C:\Joyeria
```

Solo necesitas las carpetas `print-agent` y `print-agent-etiquetas` (puedes clonar completo).

### 3.4 Script automático de configuración

PowerShell **como Administrador**:

```powershell
cd C:\Joyeria
Set-ExecutionPolicy -Scope Process Bypass
.\deploy\scripts\setup-caja-windows.ps1 `
  -ServerUrl "https://plateria-el-angel.shop/admin" `
  -CajaToken "TOKEN_LARGO_ALEATORIO_32_CHARS" `
  -EpsonPrinterName "EPSON TM-T20 Receipt" `
  -ArgoxPrinterName "Argox OS-2140 PPLA" `
  -RepoRoot "C:\Joyeria"
```

**Errores frecuentes en PowerShell:**

| Mensaje | Solución |
|---------|----------|
| *la ejecución de scripts está deshabilitada* | Ejecuta primero `Set-ExecutionPolicy -Scope Process Bypass` en la misma ventana (no hace falta `Unrestricted` global). |
| *El operador '\<' está reservado* / *Falta la cadena en el terminador* | Actualiza el repo (`git pull`) o copia el `setup-caja-windows.ps1` corregido; versiones viejas tenían `<tu-repo>` y `>` en textos que PowerShell interpreta mal. |

Usa el **mismo token** que en el VPS (`impresion_caja_token`), no el texto de ejemplo `cambiar_token_seguro`.

### 3.5 Probar agentes manualmente

Terminal 1 — tickets:

```powershell
cd C:\Joyeria\print-agent
npm start
```

Terminal 2 — etiquetas:

```powershell
cd C:\Joyeria\print-agent-etiquetas
npm start
npm run test-print   # prueba local Argox
```

Haz una venta o encola etiquetas desde el admin; la cola debe pasar a **impreso**.

### 3.6 Servicios Windows (arranque automático al encender la PC)

Asi no hace falta abrir PowerShell ni `npm start` cada dia.

**1. Instala NSSM** (solo una vez):

```powershell
winget install NSSM.NSSM
```

O descarga [nssm.cc](https://nssm.cc/download) y pon `nssm.exe` en `C:\Tools\nssm\`.

**2. Configura agentes** (`config.json` en ambas carpetas) y prueba manual una vez con `npm start`.

**3. En `print-agent-etiquetas\config.json`** agrega la ruta completa de Python (el servicio no siempre tiene `py` en PATH):

```json
"pythonPath": "C:\\Users\\TU_USUARIO\\AppData\\Local\\Programs\\Python\\Python311\\python.exe"
```

(Ajusta usuario y version; comprueba con `where python` o `py -3 -c "import sys; print(sys.executable)"`.)

**4. Instala los dos servicios** (PowerShell **como Administrador**):

```powershell
cd C:\Joyeria
Set-ExecutionPolicy -Scope Process Bypass
.\deploy\scripts\install-caja-services.ps1 -RepoRoot "C:\Joyeria"
```

NSSM pedira la **contrasena de Windows** de Beatriz (o el usuario que use la PC). Sin contrasena veras `ObjectName requires both username and password` y el servicio queda en **Paused**.

Si falla con usuario, prueba temporalmente: `.\install-caja-services.ps1 -UseLocalSystem` (a veces no ve impresoras USB).

Arreglo manual sin reinstalar:

```powershell
C:\Tools\nssm\nssm.exe set JoyeriaPrintTicket ObjectName DESKTOP-NCPAVI2\Beatriz TU_CONTRASEÑA_WINDOWS
C:\Tools\nssm\nssm.exe set JoyeriaPrintEtiquetas ObjectName DESKTOP-NCPAVI2\Beatriz TU_CONTRASEÑA_WINDOWS
C:\Tools\nssm\nssm.exe start JoyeriaPrintTicket
C:\Tools\nssm\nssm.exe start JoyeriaPrintEtiquetas
```

O interfaz grafica: `C:\Tools\nssm\nssm.exe edit JoyeriaPrintTicket` → pestana **Log on** → cuenta + contrasena.

**5. Comprobar:**

```powershell
Get-Service JoyeriaPrintTicket, JoyeriaPrintEtiquetas
```

Deben estar `Running` y `StartType` automatico. Logs en `print-agent\logs\` y `print-agent-etiquetas\logs\`.

**Reinicia la PC** y haz una venta de prueba sin abrir terminales.

| Comando | Uso |
|---------|-----|
| `nssm restart JoyeriaPrintTicket` | Reiniciar agente tickets |
| `nssm restart JoyeriaPrintEtiquetas` | Reiniciar agente etiquetas |
| `.\deploy\scripts\install-caja-services.ps1 -Remove` | Quitar servicios |

Instalacion manual (alternativa sin script): [NSSM](https://nssm.cc/download) con `nssm install JoyeriaPrintTicket ...` — ver revisiones antiguas del repo si lo necesitas.

### 3.7 Red y seguridad en tienda

- La PC caja solo necesita **salida HTTPS (443)** al dominio del VPS.
- No hace falta abrir puertos entrantes en la tienda.
- Si el firewall de Windows pregunta, permite **Node.js** en redes privadas.

---

## Parte 4 — Checklist final

### VPS

- [ ] `https://tu-dominio.com` carga el catálogo
- [ ] `https://tu-dominio.com/admin` login OK
- [ ] `config.php` con BD correcta
- [ ] Migraciones / dump importado
- [ ] Token impresión configurado
- [ ] Carpetas `uploads/` y `admin/imagenes/piezas/` escribibles

### PC caja

- [ ] Drivers Epson (ReceiptE4) + etiquetas instalados
- [ ] Epson en red: ping a IP, página de prueba Windows OK
- [ ] IP reservada en router o estática en impresora (recomendado)
- [ ] `config.json` en ambos agentes con URL HTTPS y token
- [ ] `npm start` imprime ticket y etiqueta de prueba
- [ ] Servicios NSSM en automático (opcional)

### Flujo negocio

- [ ] Venta desde móvil → ticket en Epson vía cola
- [ ] Encolar etiquetas stock → Argox imprime
- [ ] Apartado alta/abono → ticket apartado (si aplica migración `2026_05_17_*`)

---

## Solución de problemas

| Síntoma | Causa habitual | Qué hacer |
|---------|----------------|-----------|
| Agente 401 | Token distinto al del admin | Igualar `cajaToken` y BD |
| Cola pendiente, no imprime | Agente apagado o sin internet | `npm start`, ping al dominio |
| Ticket OK antes, tras apagón no | IP Epson cambió (DHCP) | Hoja de red; reserva DHCP; actualizar puerto Windows |
| Epson red: ping falla | PC e impresora en redes distintas | Misma LAN; revisar IP en WebConfig |
| SSL error en agente | Certificado inválido en VPS | `certbot`, revisar cadena |
| KPI vacío en dashboard | Falta build | `deploy-release.sh` o `npm run build` en `admin/kpi-dashboard` |
| Error GD / fuentes etiquetas | PHP sin `php-gd` o fuentes | `apt install php-gd fonts-dejavu-core` |
| `Unknown collation utf8mb4_0900_ai_ci` | Dump MySQL 8 en MariaDB VPS | `import-database.sh` (convierte solo) o `--recreate-db` si falló a medias |
| `Access denied ... SUPER` en trigger/vista | `DEFINER=root` en el dump | `git pull` + `import-database.sh` (quita DEFINER) o importar con usuario `root` solo en VPS |
| `dubious ownership` en git | Repo clonado como `root`, script usa `joyeria-deploy` | Ver abajo |

---

## Archivos de esta carpeta

| Archivo | Uso |
|---------|-----|
| `env.example` | Plantilla `/etc/joyeria/env` |
| `nginx/joyeria.conf` | Virtual host |
| `scripts/vps-bootstrap.sh` | Instalación inicial VPS |
| `scripts/deploy-release.sh` | Pull + composer + build KPI |
| `scripts/run-sql-migrations.sh` | SQL incrementales |
| `scripts/import-database.sh` | Restaurar dump (MySQL 8 → MariaDB) |
| `scripts/prepare-dump-for-mariadb.sh` | Convertir .sql antes de importar |
| `scripts/setup-caja-windows.ps1` | PC caja nueva |
| `HOSTINGER-DNS.md` | Dominio Hostinger + VPS externo |
| `scripts/verify-dns.sh` | Comprobar registros A |
| `scripts/setup-domain.sh` | Nginx HTTP inicial |
| `scripts/setup-ssl.sh` | Certbot Let's Encrypt |
| `scripts/validate-domain.sh` | Probar HTTPS, admin y API |
