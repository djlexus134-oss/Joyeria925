#Requires -RunAsAdministrator
<#
.SYNOPSIS
  Reinstala el servicio Windows JoyeriaPrintAgent (NSSM + Node).

.USAGE
  Abre PowerShell como Administrador y ejecuta:

    Set-ExecutionPolicy -Scope Process Bypass -Force
    & "D:\PrograWEB\src\Joyeria925\print-agent\install-service.ps1"
#>

$ErrorActionPreference = 'Stop'

$NodeExe     = 'C:\Program Files\nodejs\node.exe'
$NssmExe     = 'C:\Tools\nssm\nssm.exe'
$AgentDir    = 'D:\PrograWEB\src\Joyeria925\print-agent'
$IndexJs     = Join-Path $AgentDir 'index.js'
$ConfigJson  = Join-Path $AgentDir 'config.json'
$ConfigEx    = Join-Path $AgentDir 'config.example.json'
$ServiceName = 'JoyeriaPrintAgent'
$LogOut      = Join-Path $AgentDir 'agent-out.log'
$LogErr      = Join-Path $AgentDir 'agent-err.log'

function Write-Step([string]$msg) {
    Write-Host ""
    Write-Host "==> $msg" -ForegroundColor Cyan
}

Write-Step "Comprobando rutas"
if (-not (Test-Path -LiteralPath $NodeExe)) {
    throw "No se encontro Node: $NodeExe"
}
if (-not (Test-Path -LiteralPath $NssmExe)) {
    throw "No se encontro NSSM: $NssmExe"
}
if (-not (Test-Path -LiteralPath $IndexJs)) {
    throw "No se encontro el agente: $IndexJs"
}

Write-Host ("Node:  {0}" -f (& $NodeExe -v))
Write-Host ("NSSM:  {0}" -f $NssmExe)
Write-Host ("Agent: {0}" -f $AgentDir)

Write-Step "Deteniendo procesos Node del print-agent (si hay)"
Get-CimInstance Win32_Process -ErrorAction SilentlyContinue |
    Where-Object { $_.CommandLine -and ($_.CommandLine -match 'print-agent') } |
    ForEach-Object {
        Write-Host ("Matando PID {0}" -f $_.ProcessId)
        Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue
    }

Write-Step "npm install"
Push-Location $AgentDir
try {
    npm install
} finally {
    Pop-Location
}

Write-Step "Revisando config.json"
if (-not (Test-Path -LiteralPath $ConfigJson)) {
    if (-not (Test-Path -LiteralPath $ConfigEx)) {
        throw "Falta config.example.json"
    }
    Copy-Item -LiteralPath $ConfigEx -Destination $ConfigJson
    Write-Host "Se creo config.json desde el ejemplo." -ForegroundColor Yellow
    Write-Host "Editalo ahora (token, serverUrl, printerName) y guarda." -ForegroundColor Yellow
    Start-Process notepad.exe -ArgumentList $ConfigJson -Wait
}

Write-Step "Quitando servicio anterior (si existe)"
& $NssmExe stop $ServiceName 2>$null | Out-Null
Start-Sleep -Seconds 1
& $NssmExe remove $ServiceName confirm 2>$null | Out-Null
Start-Sleep -Seconds 1

Write-Step "Instalando servicio $ServiceName"
& $NssmExe install $ServiceName $NodeExe $IndexJs
if ($LASTEXITCODE -ne 0) {
    throw "nssm install fallo con codigo $LASTEXITCODE"
}

& $NssmExe set $ServiceName AppDirectory $AgentDir | Out-Null
& $NssmExe set $ServiceName AppStdout $LogOut | Out-Null
& $NssmExe set $ServiceName AppStderr $LogErr | Out-Null
& $NssmExe set $ServiceName AppRotateFiles 1 | Out-Null
& $NssmExe set $ServiceName Start SERVICE_AUTO_START | Out-Null
& $NssmExe set $ServiceName AppRestartDelay 5000 | Out-Null

Write-Step "Limpiando cola de impresion Windows (tickets atascados)"
try {
    $cfg = Get-Content -LiteralPath $ConfigJson -Raw | ConvertFrom-Json
    $printerName = [string]$cfg.printerName
    if ([string]::IsNullOrWhiteSpace($printerName)) {
        $printerName = 'EPSON TM-T20 Receipt'
    }
    Write-Host ("Impresora en config: {0}" -f $printerName)
    Get-PrintJob -PrinterName $printerName -ErrorAction SilentlyContinue |
        ForEach-Object { Remove-PrintJob -InputObject $_ -ErrorAction SilentlyContinue }
} catch {
    Write-Host ("No se pudo limpiar cola: {0}" -f $_.Exception.Message) -ForegroundColor Yellow
}

Write-Step "Reiniciando Spooler"
Restart-Service Spooler -Force
Start-Sleep -Seconds 2

Write-Step "Iniciando servicio"
& $NssmExe start $ServiceName
Start-Sleep -Seconds 2

$status = & $NssmExe status $ServiceName
Write-Host ("Estado: {0}" -f $status) -ForegroundColor $(if ($status -match 'RUNNING') { 'Green' } else { 'Yellow' })

Write-Step "Ultimas lineas del log"
Start-Sleep -Seconds 1
if (Test-Path -LiteralPath $LogOut) {
    Get-Content -LiteralPath $LogOut -Tail 30
} else {
    Write-Host "(aun no hay agent-out.log)"
}
if (Test-Path -LiteralPath $LogErr) {
    Write-Host "--- stderr ---" -ForegroundColor Yellow
    Get-Content -LiteralPath $LogErr -Tail 20
}

Write-Host ""
Write-Host "Listo. Si el estado no es SERVICE_RUNNING, revisa config.json (token/serverUrl/printerName)." -ForegroundColor Green
Write-Host "Opcional: desactiva soporte bidireccional con:" -ForegroundColor DarkGray
Write-Host '  rundll32 printui.dll,PrintUIEntry /p /n "EPSON TM-T20 Receipt"' -ForegroundColor DarkGray
