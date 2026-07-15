#Requires -RunAsAdministrator
<#
.SYNOPSIS
  Instala/reinstala el servicio Windows JoyeriaPrintAgent (NSSM + Node).

.USAGE
  Abre PowerShell como Administrador, ubicate en la carpeta del agente y ejecuta:

    cd C:\Joyeria925\print-agent
    powershell -ExecutionPolicy Bypass -File .\install-service.ps1

  Parametros opcionales:
    -PrinterName "EPSON TM-T20IV Receipt"
    -NodeExe "C:\Program Files\nodejs\node.exe"
    -NssmExe "C:\Tools\nssm\nssm.exe"
#>

param(
    [string]$PrinterName = 'EPSON TM-T20IV Receipt',
    [string]$NodeExe = 'C:\Program Files\nodejs\node.exe',
    [string]$NssmExe = 'C:\Tools\nssm\nssm.exe',
    [string]$ServiceName = 'JoyeriaPrintAgent'
)

$ErrorActionPreference = 'Stop'

# La carpeta del agente es la de este propio script (funciona la copies donde la copies).
$AgentDir   = Split-Path -Parent $MyInvocation.MyCommand.Path
$IndexJs    = Join-Path $AgentDir 'index.js'
$ConfigJson = Join-Path $AgentDir 'config.json'
$ConfigEx   = Join-Path $AgentDir 'config.example.json'
$LogOut     = Join-Path $AgentDir 'agent-out.log'
$LogErr     = Join-Path $AgentDir 'agent-err.log'

function Write-Step([string]$msg) {
    Write-Host ""
    Write-Host "==> $msg" -ForegroundColor Cyan
}

Write-Step "Comprobando rutas"
if (-not (Test-Path -LiteralPath $NodeExe)) { throw "No se encontro Node: $NodeExe" }
if (-not (Test-Path -LiteralPath $NssmExe)) { throw "No se encontro NSSM: $NssmExe" }
if (-not (Test-Path -LiteralPath $IndexJs)) { throw "No se encontro el agente: $IndexJs" }
Write-Host ("Node:    {0}" -f (& $NodeExe -v))
Write-Host ("NSSM:    {0}" -f $NssmExe)
Write-Host ("Agente:  {0}" -f $AgentDir)
Write-Host ("Impresora: {0}" -f $PrinterName)

Write-Step "Deteniendo servicio y procesos Node previos"
& $NssmExe stop $ServiceName 2>$null | Out-Null
Get-Process node -ErrorAction SilentlyContinue |
    Where-Object { try { $_.Path -and (Get-CimInstance Win32_Process -Filter "ProcessId=$($_.Id)").CommandLine -match 'print-agent' } catch { $false } } |
    ForEach-Object { Stop-Process -Id $_.Id -Force -ErrorAction SilentlyContinue }

Write-Step "npm install"
Push-Location $AgentDir
try { npm install } finally { Pop-Location }

Write-Step "Preparando config.json"
if (-not (Test-Path -LiteralPath $ConfigJson)) {
    if (-not (Test-Path -LiteralPath $ConfigEx)) { throw "Falta config.example.json" }
    Copy-Item -LiteralPath $ConfigEx -Destination $ConfigJson
    Write-Host "Se creo config.json. Edita serverUrl y cajaToken, guarda y cierra." -ForegroundColor Yellow
    Start-Process notepad.exe -ArgumentList $ConfigJson -Wait
}

# Fija printerName y reescribe SIN BOM (Node no parsea JSON con BOM/ï»¿).
try {
    $raw = [System.IO.File]::ReadAllText($ConfigJson)
    $raw = $raw.TrimStart([char]0xFEFF)
    $obj = $raw | ConvertFrom-Json
    if (-not ($obj.PSObject.Properties.Name -contains 'printerName')) {
        $obj | Add-Member -NotePropertyName printerName -NotePropertyValue $PrinterName -Force
    } else {
        $obj.printerName = $PrinterName
    }
    $json = $obj | ConvertTo-Json -Depth 10
    $utf8NoBom = New-Object System.Text.UTF8Encoding $false
    [System.IO.File]::WriteAllText($ConfigJson, $json + "`r`n", $utf8NoBom)
    Write-Host "config.json OK (UTF-8 sin BOM, printerName = $PrinterName)"
} catch {
    throw ("config.json invalido: {0}" -f $_.Exception.Message)
}

Write-Step "Limpiando cola de impresion de Windows"
Stop-Service Spooler -Force
Remove-Item "$env:SystemRoot\System32\spool\PRINTERS\*" -Force -ErrorAction SilentlyContinue
Start-Service Spooler
Start-Sleep -Seconds 2

Write-Step "Reinstalando servicio $ServiceName"
& $NssmExe remove $ServiceName confirm 2>$null | Out-Null
Start-Sleep -Seconds 1
& $NssmExe install $ServiceName $NodeExe $IndexJs
& $NssmExe set $ServiceName AppDirectory $AgentDir | Out-Null
& $NssmExe set $ServiceName AppStdout $LogOut | Out-Null
& $NssmExe set $ServiceName AppStderr $LogErr | Out-Null
& $NssmExe set $ServiceName AppRotateFiles 1 | Out-Null
& $NssmExe set $ServiceName Start SERVICE_AUTO_START | Out-Null
& $NssmExe set $ServiceName AppExit Default Restart | Out-Null
& $NssmExe set $ServiceName AppRestartDelay 5000 | Out-Null

Write-Step "Iniciando servicio"
& $NssmExe start $ServiceName
Start-Sleep -Seconds 3
$status = & $NssmExe status $ServiceName
Write-Host ("Estado: {0}" -f $status) -ForegroundColor $(if ($status -match 'RUNNING') { 'Green' } else { 'Red' })

Write-Step "Log del agente"
if (Test-Path -LiteralPath $LogOut) { Get-Content -LiteralPath $LogOut -Tail 25 } else { Write-Host "(sin agent-out.log todavia)" }
if (Test-Path -LiteralPath $LogErr) {
    $errTail = Get-Content -LiteralPath $LogErr -Tail 20 -ErrorAction SilentlyContinue
    if ($errTail) { Write-Host "--- stderr ---" -ForegroundColor Yellow; $errTail }
}

Write-Host ""
if ($status -match 'RUNNING') {
    Write-Host "Listo: el agente quedo como servicio y arranca solo con Windows." -ForegroundColor Green
} else {
    Write-Host "El servicio NO quedo en RUNNING. Revisa agent-err.log y config.json (serverUrl/cajaToken)." -ForegroundColor Red
}
