# Importa dumps zipgema -> gema_staging en MariaDB Docker (mariadb, no mysql).
param(
    [switch]$UseDocker,
    [string]$ComposeDir = "D:\PrograWEB",
    [string]$DbService = "mariadb",
    [string]$User = "root",
    [string]$Password = "rootpassword",
    [string]$ZipgemaDir = "",
    [string]$Database = "gema_staging"
)

$ErrorActionPreference = "Stop"
$files = @("FAMILIAS.sql", "TIPOARTI.sql", "PROV.sql", "ARTIC.sql", "PIEZAS.sql", "CLIEN.sql")

$scriptRoot = $PSScriptRoot
if ([string]::IsNullOrWhiteSpace($ZipgemaDir)) {
    $ZipgemaDir = if ($UseDocker) { Join-Path $scriptRoot "zipgema_dump" } else { "d:\zipgema" }
}
if (-not [System.IO.Path]::IsPathRooted($ZipgemaDir)) {
    $ZipgemaDir = Join-Path $scriptRoot $ZipgemaDir.TrimStart('.', '\', '/')
}
$ZipgemaDir = (Resolve-Path -LiteralPath $ZipgemaDir).ProviderPath
$ComposeDir = (Resolve-Path -LiteralPath $ComposeDir).ProviderPath

function Invoke-MariaDbExec {
    param([string]$Sql)
    if ($UseDocker) {
        Push-Location $ComposeDir
        $prevEa = $ErrorActionPreference
        $ErrorActionPreference = "Continue"
        docker compose exec -T $DbService mariadb "-u$User" "-p$Password" "-e" $Sql
        $code = $LASTEXITCODE
        $ErrorActionPreference = $prevEa
        Pop-Location
        if ($code -ne 0) { throw "mariadb -e fallo (codigo $code): $Sql" }
    } else {
        & mariadb "-u$User" $(if ($Password) { "-p$Password" }) "-e" $Sql
        if ($LASTEXITCODE -ne 0) { throw "mariadb fallo." }
    }
}

function Get-GemaSqlPathForLinux {
    param([string]$SourcePath)
    # MyDAC (Windows): DROP / LOCK TABLES / INSERT en MAYUS; CREATE TABLE en minus.
    # MariaDB en Linux (Docker) distingue mayusculas en nombres de tabla.
    $content = [System.IO.File]::ReadAllText($SourcePath)
    $fileBase = [System.IO.Path]::GetFileNameWithoutExtension($SourcePath)

    $tableLower = $null
    if ($content -match 'CREATE\s+TABLE\s+`([^`]+)`') {
        $tableLower = $Matches[1]
    }
    if (-not $tableLower) {
        $tableLower = $fileBase.ToLowerInvariant()
    }

    $tableUpper = $fileBase.ToUpperInvariant()
    if ($tableUpper -ceq $tableLower) {
        return $SourcePath
    }

    # Sustituir cualquier identificador del nombre de archivo en MAYUS por el de CREATE (minus)
    $pattern = '\b' + [regex]::Escape($tableUpper) + '\b'
    $content = [regex]::Replace($content, $pattern, $tableLower)

    $tempPath = Join-Path ([System.IO.Path]::GetTempPath()) ("gema_norm_" + $tableLower + ".sql")
    $utf8NoBom = New-Object System.Text.UTF8Encoding $false
    [System.IO.File]::WriteAllText($tempPath, $content, $utf8NoBom)
    return $tempPath
}

function Import-SqlFile {
    param([string]$FileName)
    $hostPath = Join-Path $ZipgemaDir $FileName
    if (-not (Test-Path -LiteralPath $hostPath)) {
        throw "No existe: $hostPath"
    }
    $hostPath = (Resolve-Path -LiteralPath $hostPath).ProviderPath
    $importPath = Get-GemaSqlPathForLinux -SourcePath $hostPath
    $importPath = (Resolve-Path -LiteralPath $importPath).ProviderPath

    Write-Host "  -> $FileName ($([math]::Round((Get-Item -LiteralPath $hostPath).Length / 1MB, 2)) MB)"

    if ($UseDocker) {
        $tmp = "/tmp/gema_import_" + ($FileName -replace '[^a-zA-Z0-9]', '_')
        Push-Location $ComposeDir
        try {
            $prevEa = $ErrorActionPreference
            $ErrorActionPreference = "Continue"
            docker compose cp "${importPath}" "${DbService}:${tmp}"
            if ($LASTEXITCODE -ne 0) { throw "docker compose cp fallo para $FileName" }

            docker compose exec -T $DbService sh -c "mariadb -u$User -p$Password $Database < $tmp"
            if ($LASTEXITCODE -ne 0) { throw "Import fallo para $FileName" }

            docker compose exec -T $DbService sh -c "rm -f $tmp" | Out-Null
            $ErrorActionPreference = $prevEa
        } finally {
            Pop-Location
            if ($importPath -ne $hostPath -and (Test-Path -LiteralPath $importPath)) {
                Remove-Item -LiteralPath $importPath -Force -ErrorAction SilentlyContinue
            }
        }
    } else {
        Get-Content -Raw -Encoding Default $importPath | & mariadb "-u$User" $(if ($Password) { "-p$Password" }) $Database
        if ($LASTEXITCODE -ne 0) { throw "Import fallo para $FileName" }
    }
}

Write-Host "Recreando base $Database (staging desechable) ..."
Invoke-MariaDbExec -Sql "DROP DATABASE IF EXISTS ``$Database``; CREATE DATABASE ``$Database`` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

foreach ($f in $files) {
    Write-Host "Importando $f ..."
    Import-SqlFile -FileName $f
}

Write-Host "Listo."
if ($UseDocker) {
    Push-Location $ComposeDir
    docker compose exec -T $DbService mariadb "-u$User" "-p$Password" $Database "-e" "SELECT 'artic' t, COUNT(*) n FROM artic UNION ALL SELECT 'piezas', COUNT(*) FROM piezas UNION ALL SELECT 'clien', COUNT(*) FROM clien;"
    Pop-Location
}
