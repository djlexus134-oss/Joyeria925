# Exporta PROCEDIMIENTOS sp_* (sin sp_mig_*) desde Docker para MariaDB en VPS.
# Uso: .\export-routines-docker.ps1
#      scp D:\PrograWEB\joyeria_routines.sql root@VPS:/root/
# VPS: bash deploy/scripts/run-sql-critical.sh /etc/joyeria/env

param(
    [string]$ComposeDir = "D:\PrograWEB",
    [string]$Database = "joyeria",
    [string]$OutFile = "D:\PrograWEB\joyeria_routines.sql"
)

$ErrorActionPreference = "Stop"

function Get-ProcedureDdl {
    param([string]$ProcName)
    $raw = docker compose exec -T mariadb mariadb -uroot -prootpassword $Database -e "SHOW CREATE PROCEDURE ``$ProcName``\G" 2>$null
    if (-not $raw) { return $null }

    $capture = $false
    $lines = New-Object System.Collections.Generic.List[string]

    foreach ($line in ($raw -split "`r?`n")) {
        if ($line -match '^\s*Create Procedure:\s*(.*)$') {
            $capture = $true
            if ($Matches[1].Trim() -ne '') { $lines.Add($Matches[1]) }
            continue
        }
        if (-not $capture) { continue }
        if ($line -match '^\s*character_set_client:') { break }
        if ($line -match '^\*{10,}') { continue }
        $lines.Add($line)
    }

    if ($lines.Count -eq 0) { return $null }
    $body = ($lines -join "`n").Trim()
    $body = $body -replace 'DEFINER=`[^`]*`@`[^`]*`\s*', ''
    $body = $body -replace 'utf8mb4_0900_ai_ci', 'utf8mb4_unicode_ci'
    $body = $body -replace 'utf8mb4_uca1400_ai_ci', 'utf8mb4_unicode_ci'
    return $body
}

Push-Location $ComposeDir
try {
    $namesRaw = docker compose exec -T mariadb mariadb -uroot -prootpassword -N -B $Database -e @"
SELECT ROUTINE_NAME
FROM information_schema.ROUTINES
WHERE ROUTINE_SCHEMA = '$Database'
  AND ROUTINE_TYPE = 'PROCEDURE'
  AND ROUTINE_NAME LIKE 'sp\_%'
  AND ROUTINE_NAME NOT LIKE 'sp_mig\_%'
ORDER BY ROUTINE_NAME;
"@

    if (-not $namesRaw) { throw "No hay procedimientos sp_* en $Database" }

    $names = ($namesRaw -split "`r?`n" | ForEach-Object { $_.Trim() } | Where-Object { $_ -ne '' })
    Write-Host "Exportando $($names.Count) procedimientos (sin sp_mig_*)..." -ForegroundColor Cyan

    $out = New-Object System.Collections.Generic.List[string]
    $out.Add("SET NAMES utf8mb4;")

    foreach ($proc in $names) {
        $ddl = Get-ProcedureDdl $proc
        if (-not $ddl) {
            Write-Warning "Omitido: $proc"
            continue
        }
        $out.Add("")
        $out.Add("DROP PROCEDURE IF EXISTS ``$proc``;")
        $out.Add("DELIMITER ;;")
        $out.Add("$ddl;;")
        $out.Add("DELIMITER ;")
    }

    $utf8 = New-Object System.Text.UTF8Encoding $false
    [System.IO.File]::WriteAllText($OutFile, ($out -join "`n"), $utf8)
    $kb = [math]::Round((Get-Item $OutFile).Length / 1KB, 1)
    Write-Host "Listo: $OutFile ($kb KB)" -ForegroundColor Green
    if (Select-String -Path $OutFile -Pattern 'mig_fn_ci' -Quiet) {
        Write-Host "ADVERTENCIA: el archivo aun contiene mig_fn_*; borra y vuelve a exportar." -ForegroundColor Red
    }
}
finally {
    Pop-Location
}
