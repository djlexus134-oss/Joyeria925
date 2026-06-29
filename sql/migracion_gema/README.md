# Migración Gema → Joyería (Docker)

MariaDB 11 en Docker usa **`mariadb`** / **`mariadb-dump`**, no `mysql` / `mysqldump`.

## Inicio rápido

```powershell
cd D:\PrograWEB\src\Joyeria\sql\migracion_gema

# 1) Copiar dumps (una vez)
$dest = ".\zipgema_dump"
New-Item -ItemType Directory -Force $dest | Out-Null
Copy-Item d:\zipgema\FAMILIAS.sql,d:\zipgema\TIPOARTI.sql,d:\zipgema\PROV.sql,
  d:\zipgema\ARTIC.sql,d:\zipgema\PIEZAS.sql,d:\zipgema\CLIEN.sql $dest

# 2) Flujo completo
.\migracion_gema_docker.ps1 -Paso todo
```

Detalle: [00_staging_import.md](00_staging_import.md)

## Scripts

| Archivo | Uso |
|---------|-----|
| **migracion_gema_docker.ps1** | Orquestador principal |
| backup_joyeria.ps1 | Solo respaldo |
| import_gema_staging.ps1 | Solo `gema_staging` |
| run_migracion_gema.ps1 | Solo SQL `01`–`07` / rollback |

## BD y credenciales

- Compose: `D:\PrograWEB`, servicio `mariadb`
- Root: `root` / `rootpassword`
- Destino: `joyeria` (cambiar con `-TargetDatabase`)

## Clientes migrados

Contraseña inicial: `Migracion2026!` — ver `mig_config`.
