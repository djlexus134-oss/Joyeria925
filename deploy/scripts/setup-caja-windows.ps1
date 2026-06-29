# Configuracion inicial PC de caja (Windows 10/11) - impresoras + agentes.
# Version: 2026-05-17b (param debe ir al inicio del script)
# Ejecutar en PowerShell como Administrador.
#Requires -RunAsAdministrator
param(
    [Parameter(Mandatory = $true)]
    [string]$ServerUrl,

    [Parameter(Mandatory = $true)]
    [string]$CajaToken,

    [string]$EpsonPrinterName = "EPSON TM-T20 Receipt",
    [string]$ArgoxPrinterName = "Argox OS-2140 PPLA",
    [string]$RepoRoot = "C:\Joyeria"
)

$ErrorActionPreference = "Stop"

function Test-Command($name) {
    return [bool](Get-Command $name -ErrorAction SilentlyContinue)
}

function Write-JsonConfigNoBom {
    param(
        [string]$Path,
        [object]$Object
    )
    $json = $Object | ConvertTo-Json -Depth 5
    $utf8NoBom = New-Object System.Text.UTF8Encoding $false
    [System.IO.File]::WriteAllText($Path, $json, $utf8NoBom)
}

Write-Host '=== Joyeria - setup PC caja ===' -ForegroundColor Cyan

if (-not (Test-Command node)) {
    Write-Host "Instala Node.js 20 LTS: https://nodejs.org/" -ForegroundColor Yellow
    exit 1
}
if (-not (Test-Command py)) {
    Write-Host "Instala Python 3 desde python.org (marca 'Add to PATH')." -ForegroundColor Yellow
    exit 1
}

if (-not (Test-Path $RepoRoot)) {
    Write-Host "Clona el repo en $RepoRoot (solo carpetas print-agent* hacen falta en caja):" -ForegroundColor Yellow
    Write-Host ('  git clone https://github.com/TU_USUARIO/joyeria.git ' + $RepoRoot)
    exit 1
}

$ticketDir = Join-Path $RepoRoot "print-agent"
$labelDir = Join-Path $RepoRoot "print-agent-etiquetas"

foreach ($d in @($ticketDir, $labelDir)) {
    if (-not (Test-Path $d)) {
        throw "No existe $d"
    }
}

Write-Host "`nImpresoras instaladas:" -ForegroundColor Green
Get-Printer | Select-Object Name, DriverName, PortName | Format-Table -AutoSize

# --- print-agent (tickets) ---
Push-Location $ticketDir
if (-not (Test-Path "config.json")) {
    Copy-Item "config.example.json" "config.json"
}
$cfg = Get-Content "config.json" -Raw | ConvertFrom-Json
$cfg.serverUrl = $ServerUrl
$cfg.serverUrlUseLocalhost = $false
$cfg.cajaToken = $CajaToken
$cfg.printerName = $EpsonPrinterName
Write-JsonConfigNoBom -Path (Join-Path $ticketDir "config.json") -Object $cfg
npm install --omit=dev
Pop-Location

# --- print-agent-etiquetas ---
Push-Location $labelDir
if (-not (Test-Path "config.json")) {
    Copy-Item "config.example.json" "config.json"
}
$cfg2 = Get-Content "config.json" -Raw | ConvertFrom-Json
$cfg2.serverUrl = $ServerUrl
$cfg2.serverUrlUseLocalhost = $false
$cfg2.cajaToken = $CajaToken
$cfg2.printerName = $ArgoxPrinterName
$cfg2.destino = "etiqueta"
Write-JsonConfigNoBom -Path (Join-Path $labelDir "config.json") -Object $cfg2
npm install --omit=dev
npm run setup-python
Pop-Location

Write-Host ''
Write-Host '=== Pruebas ===' -ForegroundColor Cyan
Write-Host ('Tickets:  cd ' + $ticketDir + '; npm start')
Write-Host ('Etiquetas: cd ' + $labelDir + '; npm start')
Write-Host ''
Write-Host ('En el admin VPS (Sistema - Ticket POS) usa el mismo token: ' + $CajaToken)
Write-Host ('URL agentes: ' + $ServerUrl)
Write-Host ''
Write-Host 'Arranque automatico al encender Windows:'
Write-Host ('  .\deploy\scripts\install-caja-services.ps1 -RepoRoot ' + $RepoRoot)
