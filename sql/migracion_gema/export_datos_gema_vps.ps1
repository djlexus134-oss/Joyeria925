# Exporta solo tablas pobladas por migracion Gema (sin configuracion_general ni ventas).
# Uso: .\export_datos_gema_vps.ps1 [-ComposeDir D:\PrograWEB] [-Database joyeria]
param(
    [string]$ComposeDir = "D:\PrograWEB",
    [string]$Database = "joyeria",
    [string]$OutFile = "D:\PrograWEB\solo_datos_gema.sql"
)

$ErrorActionPreference = "Stop"
$ComposeDir = (Resolve-Path -LiteralPath $ComposeDir).ProviderPath

# Orden respetando FK al importar
$tables = @(
    'familias',
    'sub_familia',
    'metales',
    'proveedores',
    'mig_config',
    'mig_gema_familia',
    'mig_gema_metal',
    'mig_gema_proveedor',
    'piezas',
    'mig_gema_artic',
    'piezas_stock',
    'mig_gema_stock',
    'usuarios',
    'clientes',
    'usuario_rol',
    'mig_gema_cliente',
    'movimientos_inventario',
    'mig_log'
)

Write-Host "Exportando tablas Gema desde $Database ..." -ForegroundColor Cyan

# Solo clientes migrados de Gema (correo @migracion.local), no usuarios demo/prueba de Docker
$gemaCorreoFilter = "correo LIKE '%@migracion.local'"
$gemaClienteFilter = "id_cliente IN (SELECT id_cliente FROM mig_gema_cliente WHERE correo_usado LIKE '%@migracion.local')"
$gemaUsuarioFilter = $gemaCorreoFilter
$gemaUsuarioRolFilter = "id_usuario_FK IN (SELECT id_usuario FROM mig_gema_cliente WHERE correo_usado LIKE '%@migracion.local')"
$gemaMigClienteFilter = "correo_usado LIKE '%@migracion.local'"

$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$limpiarSqlPath = Join-Path $scriptDir "limpiar_todas_tablas_datos_gema.sql"
if (-not (Test-Path -LiteralPath $limpiarSqlPath)) {
    throw "No se encontro $limpiarSqlPath"
}
$limpiarSql = Get-Content -LiteralPath $limpiarSqlPath -Raw

$header = @"
-- solo_datos_gema.sql — generado por export_datos_gema_vps.ps1
-- Importar en VPS con: deploy/scripts/import-datos-gema.sh
-- Limpia TODOS los datos operativos/catalogo/clientes (conserva empleados y configuracion_general).
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
$limpiarSql
"@

$utf8 = New-Object System.Text.UTF8Encoding $false
[System.IO.File]::WriteAllText($OutFile, $header, $utf8)

function Export-Table {
    param([string]$Table, [string]$Where = "")
    if ($Where) {
        Write-Host "  -> $Table (filtrado)"
    } else {
        Write-Host "  -> $Table"
    }
    # No usar sh -c: las comillas del WHERE se rompen y mariadb-dump exporta toda la tabla
    $dumpArgs = @(
        'exec', '-T', 'mariadb',
        'mariadb-dump', '-uroot', '-prootpassword',
        '--no-create-info', '--skip-triggers', '--complete-insert',
        '--single-transaction', '--skip-lock-tables',
        $Database, $Table
    )
    if ($Where) {
        $dumpArgs += "--where=$Where"
    }
    $dump = & docker compose @dumpArgs 2>$null
    if (-not $dump) {
        Write-Host "    (omitida o vacia)" -ForegroundColor DarkYellow
        return
    }
    $dump = ($dump | ForEach-Object { $_ -replace 'utf8mb4_0900_ai_ci', 'utf8mb4_unicode_ci' }) -join "`n"
    $dump = $dump -replace 'DEFINER=`[^`]*`@`[^`]*`\s*', ''
    [System.IO.File]::AppendAllText($OutFile, "`n-- Table: $Table`n$dump`n", $utf8)
}

function Append-SqlBlock {
    param([string]$Sql)
    [System.IO.File]::AppendAllText($OutFile, "`n$Sql`n", $utf8)
}

Push-Location $ComposeDir
try {
    foreach ($t in $tables) {
        if ($t -eq 'usuarios') {
            Append-SqlBlock @"
-- Seguridad pre-usuarios Gema (todos los clientes / usuarios no empleado)
DELETE FROM cliente_credito_consumos;
DELETE FROM cliente_creditos;
DELETE FROM clientes;
DELETE ur FROM usuario_rol ur
LEFT JOIN empleados e ON e.id_usuario_FK = ur.id_usuario_FK
WHERE e.id_empleado IS NULL;
DELETE u FROM usuarios u
LEFT JOIN empleados e ON e.id_usuario_FK = u.id_usuario
WHERE e.id_empleado IS NULL;
"@
            Export-Table 'usuarios' $gemaUsuarioFilter
        } elseif ($t -eq 'clientes') {
            Append-SqlBlock @"
-- Seguridad pre-clientes (evita ERROR 1062 en id_cliente)
SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE cliente_credito_consumos;
TRUNCATE TABLE cliente_creditos;
TRUNCATE TABLE clientes;
SET FOREIGN_KEY_CHECKS = 1;
"@
            Export-Table 'clientes' $gemaClienteFilter
        } elseif ($t -eq 'usuario_rol') {
            Export-Table 'usuario_rol' $gemaUsuarioRolFilter
        } elseif ($t -eq 'mig_gema_cliente') {
            Export-Table 'mig_gema_cliente' $gemaMigClienteFilter
        } elseif ($t -eq 'movimientos_inventario') {
            Export-Table 'movimientos_inventario' "referencia = 'MIG-GEMA'"
        } else {
            Export-Table $t
        }
    }
    $footer = "`nSET FOREIGN_KEY_CHECKS = 1;`n"
    [System.IO.File]::AppendAllText($OutFile, $footer, $utf8)
}
finally {
    Pop-Location
}

$sizeMb = [math]::Round((Get-Item $OutFile).Length / 1MB, 2)
Write-Host "Listo: $OutFile ($sizeMb MB)" -ForegroundColor Green
$usuariosSection = $null
$content = [System.IO.File]::ReadAllText($OutFile)
if ($content -match '(?s)-- Table: usuarios\r?\n(.*?)(?=\r?\n-- Table: )') {
    $usuariosSection = $Matches[1]
}
if ($usuariosSection -match '1234fdwq@gmail\.com|@joyeria\.local') {
    Write-Host "ADVERTENCIA: bloque usuarios aun trae datos demo. Re-exporta (corrige filtro WHERE)." -ForegroundColor Red
} elseif ($usuariosSection -notmatch '@migracion\.local') {
    Write-Host "ADVERTENCIA: bloque usuarios sin correos @migracion.local." -ForegroundColor Red
} else {
    $nGema = ([regex]::Matches($usuariosSection, '@migracion\.local')).Count
    Write-Host "OK: export solo clientes Gema (~$nGema correos @migracion.local en usuarios)." -ForegroundColor Green
}
Write-Host "scp $OutFile root@TU_VPS:/root/"
