# Flujo completo migracion Gema -> Joyeria en Docker (MariaDB 11).
# Uso: .\migracion_gema_docker.ps1 -Paso staging
#      .\migracion_gema_docker.ps1 -Paso migrate
#      .\migracion_gema_docker.ps1 -Paso todo
param(
    [ValidateSet("backup", "staging", "migrate", "validate", "todo")]
    [string]$Paso = "todo",
    [string]$ComposeDir = "D:\PrograWEB",
    [string]$TargetDatabase = "joyeria"
)

$ErrorActionPreference = "Stop"
$here = $PSScriptRoot

function Step-Backup {
    Write-Host "`n=== 1/4 Respaldo $TargetDatabase ===" -ForegroundColor Cyan
    & (Join-Path $here "backup_joyeria.ps1") -ComposeDir $ComposeDir -Database $TargetDatabase
}

function Step-Staging {
    Write-Host "`n=== 2/4 Import gema_staging ===" -ForegroundColor Cyan
    $dump = Join-Path $here "zipgema_dump"
    if (-not (Test-Path -LiteralPath (Join-Path $dump "PIEZAS.sql"))) {
        Write-Host "Copiando SQL desde d:\zipgema ..."
        New-Item -ItemType Directory -Force -Path $dump | Out-Null
        Copy-Item "d:\zipgema\FAMILIAS.sql", "d:\zipgema\TIPOARTI.sql", "d:\zipgema\PROV.sql",
            "d:\zipgema\ARTIC.sql", "d:\zipgema\PIEZAS.sql", "d:\zipgema\CLIEN.sql" -Destination $dump -Force
    }
    $dump = (Resolve-Path -LiteralPath $dump).ProviderPath
    & (Join-Path $here "import_gema_staging.ps1") -UseDocker -ComposeDir $ComposeDir -ZipgemaDir $dump
}

function Step-Migrate {
    Write-Host "`n=== 3/4 Migracion 01-07 -> $TargetDatabase ===" -ForegroundColor Cyan
    & (Join-Path $here "run_migracion_gema.ps1") -ComposeDir $ComposeDir -TargetDatabase $TargetDatabase -Action migrate
}

function Step-Validate {
    Write-Host "`n=== 4/4 Validacion ===" -ForegroundColor Cyan
    & (Join-Path $here "run_migracion_gema.ps1") -ComposeDir $ComposeDir -TargetDatabase $TargetDatabase -Action validate
}

switch ($Paso) {
    "backup"   { Step-Backup }
    "staging"  { Step-Staging }
    "migrate"  { Step-Migrate }
    "validate" { Step-Validate }
    "todo"     { Step-Backup; Step-Staging; Step-Migrate; Step-Validate }
}

Write-Host "`nListo ($Paso)." -ForegroundColor Green
