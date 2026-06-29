# Respaldo de BD desde contenedor mariadb (MariaDB 11: mariadb-dump, no mysqldump).
param(
    [string]$ComposeDir = "D:\PrograWEB",
    [string]$DbService = "mariadb",
    [string]$User = "root",
    [string]$Password = "rootpassword",
    [string]$Database = "joyeria",
    [string]$OutFile = "backup_joyeria_antes_mig_gema.sql"
)

$ErrorActionPreference = "Stop"
# docker compose escribe progreso en stderr; no tratarlo como error fatal
$DockerEa = "Continue"
$OutFile = Join-Path $ComposeDir $OutFile
$tmpInContainer = "/tmp/backup_joyeria_mig.sql"

Push-Location $ComposeDir
try {
    $prevEa = $ErrorActionPreference
    $ErrorActionPreference = $DockerEa

    Write-Host "Generando dump ($Database) en $DbService ..."
    docker compose exec -T $DbService sh -c "mariadb-dump -u$User -p$Password --single-transaction --routines --triggers $Database > $tmpInContainer"
    if ($LASTEXITCODE -ne 0) {
        throw "mariadb-dump fallo (codigo $LASTEXITCODE). Verifique BD y credenciales."
    }

    Write-Host "Copiando a $OutFile ..."
    docker compose cp "${DbService}:${tmpInContainer}" $OutFile
    if ($LASTEXITCODE -ne 0) {
        throw "docker compose cp fallo."
    }

    docker compose exec -T $DbService sh -c "rm -f $tmpInContainer" | Out-Null
    $ErrorActionPreference = $prevEa

    if (-not (Test-Path $OutFile)) {
        throw "No se creo el archivo de respaldo."
    }
    $size = (Get-Item $OutFile).Length
    if ($size -lt 1000) {
        throw "Respaldo muy pequeno ($size bytes). BD '$Database' vacia o dump fallido."
    }
    Write-Host "Respaldo OK: $OutFile ($([math]::Round($size / 1MB, 2)) MB)"
} finally {
    Pop-Location
}
