# Ejecuta scripts 01-07 / 99 contra MariaDB Docker (cliente: mariadb).
param(
    [string]$ComposeDir = "D:\PrograWEB",
    [string]$DbService = "mariadb",
    [string]$User = "root",
    [string]$Password = "rootpassword",
    [string]$TargetDatabase = "joyeria",
    [ValidateSet("migrate", "validate", "rollback")]
    [string]$Action = "migrate"
)

$ErrorActionPreference = "Stop"
$ComposeDir = (Resolve-Path -LiteralPath $ComposeDir).ProviderPath
$scriptDir = Join-Path $ComposeDir "src\Joyeria\sql\migracion_gema"
$scriptDir = (Resolve-Path -LiteralPath $scriptDir).ProviderPath

$sequences = @{
    migrate  = @("01_mig_tablas_mapeo.sql", "02_mig_catalogos.sql", "03_mig_clientes.sql", "04_mig_piezas.sql", "05_mig_piezas_stock.sql", "06_mig_movimientos_entrada.sql", "07_validacion.sql")
    validate = @("07_validacion.sql")
    rollback = @("99_rollback.sql")
}

Push-Location $ComposeDir
try {
    foreach ($file in $sequences[$Action]) {
        $hostPath = (Resolve-Path -LiteralPath (Join-Path $scriptDir $file)).ProviderPath

        $tmp = "/tmp/mig_" + ($file -replace '[^a-zA-Z0-9]', '_')
        Write-Host ">>> $file -> $TargetDatabase"

        $prevEa = $ErrorActionPreference
        $ErrorActionPreference = "Continue"
        docker compose cp "${hostPath}" "${DbService}:${tmp}"
        if ($LASTEXITCODE -ne 0) { throw "cp fallo: $file" }

        docker compose exec -T $DbService sh -c "mariadb -u$User -p$Password $TargetDatabase < $tmp"
        if ($LASTEXITCODE -ne 0) { throw "mariadb fallo en $file" }

        docker compose exec -T $DbService sh -c "rm -f $tmp" | Out-Null
        $ErrorActionPreference = $prevEa
    }
    Write-Host "Accion '$Action' completada en $TargetDatabase."
} finally {
    Pop-Location
}
