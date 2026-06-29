# Migración Gema → Joyería (Docker / MariaDB 11)

Servicio: **`mariadb`** en `D:\PrograWEB\docker-compose.yml`  
Cliente en el contenedor: **`mariadb`** y **`mariadb-dump`** (no existen `mysql` / `mysqldump`).

| Parámetro | Valor |
|-----------|--------|
| Root | `root` / `rootpassword` |
| BD Joyería | `joyeria` |
| BD staging | `gema_staging` |
| Puerto host | `63306` |

---

## Script definitivo (todo el flujo)

Ya copiaste los `.sql` a `zipgema_dump`. Desde PowerShell:

```powershell
cd D:\PrograWEB\src\Joyeria\sql\migracion_gema

# Todo: backup + staging + migración 01-07 + validación
.\migracion_gema_docker.ps1 -Paso todo

# O paso a paso:
.\migracion_gema_docker.ps1 -Paso backup
.\migracion_gema_docker.ps1 -Paso staging
.\migracion_gema_docker.ps1 -Paso migrate
.\migracion_gema_docker.ps1 -Paso validate
```

Prueba en clon:

```powershell
.\migracion_gema_docker.ps1 -Paso todo -TargetDatabase joyeria_mig_test
```

Rollback:

```powershell
.\run_migracion_gema.ps1 -Action rollback -TargetDatabase joyeria
```

---

## Paso 0 — Copiar dumps (si aún no lo hiciste)

```powershell
$dest = "D:\PrograWEB\src\Joyeria\sql\migracion_gema\zipgema_dump"
New-Item -ItemType Directory -Force -Path $dest | Out-Null
Copy-Item "d:\zipgema\FAMILIAS.sql","d:\zipgema\TIPOARTI.sql","d:\zipgema\PROV.sql",
  "d:\zipgema\ARTIC.sql","d:\zipgema\PIEZAS.sql","d:\zipgema\CLIEN.sql" -Destination $dest
```

> Los archivos deben importarse al contenedor **`mariadb`** con `docker compose cp` (el volumen `/var/www/html` está en **`web`**, no en `mariadb`).

---

## Comandos manuales (referencia)

### Respaldo

```powershell
cd D:\PrograWEB\src\Joyeria\sql\migracion_gema
.\backup_joyeria.ps1
```

Equivalente manual:

```powershell
cd D:\PrograWEB
docker compose exec -T mariadb sh -c "mariadb-dump -uroot -prootpassword --single-transaction --routines --triggers joyeria > /tmp/backup_joyeria.sql"
docker compose cp mariadb:/tmp/backup_joyeria.sql .\backup_joyeria_antes_mig_gema.sql
```

No uses `> archivo.sql` en PowerShell (UTF-16 / basura en el dump).

### Staging

```powershell
cd D:\PrograWEB\src\Joyeria\sql\migracion_gema
.\import_gema_staging.ps1 -UseDocker
# (usa zipgema_dump junto al script; no pases ".\zipgema_dump" relativo tras cd a D:\PrograWEB)
```

### Migración 01–07

```powershell
.\run_migracion_gema.ps1 -TargetDatabase joyeria -Action migrate
```

### Verificar staging

```powershell
cd D:\PrograWEB
docker compose exec -T mariadb mariadb -uroot -prootpassword gema_staging -e "
SELECT 'piezas' t, COUNT(*) n FROM piezas
UNION ALL SELECT 'clien', COUNT(*) FROM clien;"
```

### Consola SQL

```powershell
docker compose exec -it mariadb mariadb -uroot -prootpassword joyeria
```

---

## Scripts del paquete

| Script | Función |
|--------|---------|
| `migracion_gema_docker.ps1` | Orquestador (backup → staging → migrate → validate) |
| `backup_joyeria.ps1` | Respaldo con `mariadb-dump` |
| `import_gema_staging.ps1` | Import a `gema_staging` vía `docker compose cp` |
| `run_migracion_gema.ps1` | Ejecuta `01`–`07` o `99` |
