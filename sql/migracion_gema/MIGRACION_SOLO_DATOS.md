# Migración solo datos Gema (sin dump completo de Joyería)

Usa este flujo cuando **no quieres subir todo el dump** de desarrollo al VPS, sino solo:

- Catálogo y stock del sistema anterior (Gema)
- Clientes migrados
- **Sin** mezclar demo, ventas de prueba ni `configuracion_general` del entorno local

La migración corre **en Docker (PC)**. Al VPS solo va un archivo `solo_datos_gema.sql`.

---

## Requisitos

- Docker con `mariadb` y base `joyeria` con **esquema actual** (tablas de la app ya creadas).
- Archivos Gema en `sql/migracion_gema/zipgema_dump/` (o `d:\zipgema\`).
- Usuario admin de Joyería y **configuración** ya definidos en el VPS (no se pisan con este export).

---

## Paso 1 — Respaldo (Docker)

```powershell
cd D:\PrograWEB\src\Joyeria\sql\migracion_gema
.\backup_joyeria.ps1
```

---

## Paso 2 — Limpiar datos viejos (opcional, en Docker)

Si ya corriste una migración Gema antes:

```powershell
.\run_migracion_gema.ps1 -Action rollback -TargetDatabase joyeria
```

Si además tienes ventas/apartados/demo en Docker y quieres exportar **solo Gema**:

```powershell
Get-Content ".\00_limpiar_datos_previos_gema.sql" -Raw |
  docker compose exec -T mariadb mariadb -uroot -prootpassword joyeria
```

(Ejecuta desde `D:\PrograWEB` con `docker compose`.)

El script **omite tablas que no existan** en tu BD (p. ej. módulos no migrados).

**Borra TODOS** los datos de ventas, apartados, piezas, familias, metales, proveedores, clientes y usuarios que **no** sean empleados. **No borra** `configuracion_general`, `empleados`, roles, permisos, `forma_pago`, tiendas, etc.

---

## Paso 3 — Staging + migración 01–07

```powershell
cd D:\PrograWEB\src\Joyeria\sql\migracion_gema
.\migracion_gema_docker.ps1 -Paso staging
.\migracion_gema_docker.ps1 -Paso migrate
.\migracion_gema_docker.ps1 -Paso validate
```

Revisa que `07_validacion.sql` no muestre fallos graves (duplicados, piezas huérfanas = 0).

---

## Paso 4 — Exportar solo tablas de datos Gema

```powershell
.\export_datos_gema_vps.ps1
```

Genera: `D:\PrograWEB\solo_datos_gema.sql`

---

## Paso 5 — Subir al VPS

```powershell
scp D:\PrograWEB\solo_datos_gema.sql root@IP_VPS:/root/
```

---

## Paso 6 — Importar en VPS (sin tocar configuración)

El VPS debe tener ya el **esquema** y tu `config.php`. Si acabas de importar un dump completo fallido, puedes limpiar solo tablas de datos antes:

```bash
cd /var/www/joyeria925
git pull
bash deploy/scripts/import-datos-gema.sh /etc/joyeria925/env /root/solo_datos_gema.sql
```

Eso **no modifica** `configuracion_general`.

El encabezado del `.sql` ejecuta `limpiar_todas_tablas_datos_gema.sql`: vacía **todas** las tablas operativas y de catálogo antes de insertar Gema. Si el archivo es viejo, vuelve a ejecutar el paso 4 (`export_datos_gema_vps.ps1`) y sube el archivo nuevo.

Luego restaura tokens si hace falta:

```bash
mariadb -u joyeria_app -p joyeria < /root/restore_config.sql
```

(Ver `deploy/sql/restore_config_produccion.example.sql`.)

---

## Paso 7 — Comprobar

```bash
source /etc/joyeria925/env
export MYSQL_PWD="$DB_PASSWORD"
mariadb -u"$DB_USER" "$DB_NAME" -e "
SELECT COUNT(*) AS stock FROM piezas_stock;
SELECT COUNT(*) AS clientes_mig FROM mig_gema_cliente;
SELECT COUNT(*) AS config FROM configuracion_general;
"
```

---

## Si la migración falla en Docker

| Síntoma | Qué hacer |
|---------|-----------|
| `ARTPIE sin pieza catalogo` | Falta `ARTIC.sql` en staging o `04` no corrió |
| `Duplicate` correo/teléfono | Normal en clientes; script usa `.dup` |
| Conteos origen ≠ migrado | Revisa `mig_log` |
| Quieres empezar de cero | `rollback` + `00_limpiar` + `todo` |
| `Duplicate entry '1' for key 'PRIMARY'` en `familias` | El `.sql` viejo no vaciaba catálogo. **Vuelve a exportar** e importa de nuevo |
| `Duplicate entry '1' for key 'PRIMARY'` en `clientes` o `usuarios` | Casi siempre el `.sql` incluye **usuarios demo de Docker** (`1234fdwq@gmail.com`, `joyeria.local`) además de Gema. **Re-exporta** con `export_datos_gema_vps.ps1` actualizado (solo `@migracion.local`). El INSERT no debe empezar con `id_usuario`=1 de prueba |
| Error en `usuarios` (correo/teléfono duplicado) | Import a medias o mezcla demo+Gema. Limpia y vuelve a importar el `.sql` completo |

---

## Códigos de pieza migradas

Cada unidad Gema conserva los identificadores del sistema anterior:

| Campo Joyería | Valor legacy |
|---------------|----------------|
| `codigo_auxiliar` | `ARTPIE/CODPIE` (ej. `28488/97`) |
| `codigo_barras` | EAN-13 interno `20` + ARTPIE (6 dígitos) + CODPIE (4 dígitos) + dígito verificador |

Las piezas **nuevas** creadas en Joyería siguen usando `id_pieza/consecutivo` y EAN aleatorio (ver `admin/models/pieza.php`).

---

## Qué NO incluye `solo_datos_gema.sql`

- `configuracion_general` (tokens, tickets, etiquetas → VPS manual)
- `ventas`, `apartados`, `cola_impresion` (los creas en producción)
- `empleados` / usuarios admin (déjalos en el VPS)
- `gema_staging` (solo local)
