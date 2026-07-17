#Requires -RunAsAdministrator
<#
.SYNOPSIS
  Instala/reinstala el servicio Windows JoyeriaLabelAgent (NSSM + Node) para el
  agente de ETIQUETAS Argox/Zebra (PPLA/ZPL). Tambien deja listas las
  dependencias de Node (npm install) y de Python (pywin32/Pillow).

.USAGE
  Abre PowerShell como Administrador, ubicate en la carpeta del agente y ejecuta:

    cd C:\Joyeria925\print-agent-etiquetas
    powershell -ExecutionPolicy Bypass -File .\install-service.ps1

  Parametros opcionales:
    -PrinterName "ZDesigner ZD220-203dpi ZPL"   (si se omite, respeta el de config.json)
    -NodeExe "C:\Program Files\nodejs\node.exe"
    -NssmExe "C:\Tools\nssm\nssm.exe"
    -ServiceName "JoyeriaLabelAgent"

  NOTA: el agente de etiquetas depende de ..\print-agent\resolve-server-url.js,
  por lo que la carpeta print-agent (tickets) debe existir como carpeta hermana.
#>

param(
    [string]$PrinterName = '',
    [string]$NodeExe = 'C:\Program Files\nodejs\node.exe',
    [string]$NssmExe = 'C:\Tools\nssm\nssm.exe',
    [string]$ServiceName = 'JoyeriaLabelAgent'
)

$ErrorActionPreference = 'Stop'

# La carpeta del agente es la de este propio script (funciona la copies donde la copies).
$AgentDir   = Split-Path -Parent $MyInvocation.MyCommand.Path
$IndexJs    = Join-Path $AgentDir 'index.js'
$ConfigJson = Join-Path $AgentDir 'config.json'
$ConfigEx   = Join-Path $AgentDir 'config.example.json'
$Requirements = Join-Path $AgentDir 'requirements.txt'
$ResolveDep = Join-Path $AgentDir '..\print-agent\resolve-server-url.js'
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
if (-not (Test-Path -LiteralPath $ResolveDep)) {
    throw "Falta ..\print-agent\resolve-server-url.js. Copia tambien la carpeta print-agent (tickets) como carpeta hermana de print-agent-etiquetas."
}
Write-Host ("Node:      {0}" -f (& $NodeExe -v))
Write-Host ("NSSM:      {0}" -f $NssmExe)
Write-Host ("Agente:    {0}" -f $AgentDir)

Write-Step "Comprobando Python (para win32print / Pillow)"
$PyExe = $null
$PyArgs = @()
try {
    $null = & py -3 --version 2>$null
    if ($LASTEXITCODE -eq 0) { $PyExe = 'py'; $PyArgs = @('-3') }
} catch { }
if (-not $PyExe) {
    try {
        $null = & python --version 2>$null
        if ($LASTEXITCODE -eq 0) { $PyExe = 'python'; $PyArgs = @() }
    } catch { }
}
if (-not $PyExe) {
    throw "No se encontro Python (ni 'py -3' ni 'python'). Instala Python 3 y vuelve a ejecutar."
}
Write-Host ("Python:    {0} {1} -> {2}" -f $PyExe, ($PyArgs -join ' '), (& $PyExe @PyArgs --version))

Write-Step "Deteniendo servicio y procesos Node previos"
& $NssmExe stop $ServiceName 2>$null | Out-Null
Get-Process node -ErrorAction SilentlyContinue |
    Where-Object { try { $_.Path -and (Get-CimInstance Win32_Process -Filter "ProcessId=$($_.Id)").CommandLine -match 'print-agent-etiquetas' } catch { $false } } |
    ForEach-Object { Stop-Process -Id $_.Id -Force -ErrorAction SilentlyContinue }

Write-Step "npm install"
Push-Location $AgentDir
try { npm install } finally { Pop-Location }

Write-Step "Instalando dependencias de Python (pywin32, Pillow)"
if (-not (Test-Path -LiteralPath $Requirements)) { throw "Falta requirements.txt" }
& $PyExe @PyArgs -m pip install -r $Requirements
if ($LASTEXITCODE -ne 0) { throw "pip install fallo. Revisa la instalacion de Python/pip." }

Write-Step "Preparando config.json"
if (-not (Test-Path -LiteralPath $ConfigJson)) {
    if (-not (Test-Path -LiteralPath $ConfigEx)) { throw "Falta config.example.json" }
    Copy-Item -LiteralPath $ConfigEx -Destination $ConfigJson
    Write-Host "Se creo config.json. Edita serverUrl/serverPath, cajaToken y printerName, guarda y cierra." -ForegroundColor Yellow
    Start-Process notepad.exe -ArgumentList $ConfigJson -Wait
}

# Reescribe config.json SIN BOM (Node no parsea JSON con BOM) y opcionalmente fija printerName.
try {
    $raw = [System.IO.File]::ReadAllText($ConfigJson)
    $raw = $raw.TrimStart([char]0xFEFF)
    $obj = $raw | ConvertFrom-Json
    if ($PrinterName -ne '') {
        if (-not ($obj.PSObject.Properties.Name -contains 'printerName')) {
            $obj | Add-Member -NotePropertyName printerName -NotePropertyValue $PrinterName -Force
        } else {
            $obj.printerName = $PrinterName
        }
        Write-Host "printerName fijado a: $PrinterName"
    } else {
        Write-Host ("printerName (config.json): {0}" -f $obj.printerName)
    }
    $json = $obj | ConvertTo-Json -Depth 10
    $utf8NoBom = New-Object System.Text.UTF8Encoding $false
    [System.IO.File]::WriteAllText($ConfigJson, $json + "`r`n", $utf8NoBom)
    Write-Host "config.json OK (UTF-8 sin BOM)"
    if ([string]$obj.cajaToken -eq 'cambiar_token_seguro') {
        Write-Host "ADVERTENCIA: cajaToken sigue en el valor de ejemplo; el servidor devolvera 401 hasta que pongas el token real." -ForegroundColor Yellow
    }
} catch {
    throw ("config.json invalido: {0}" -f $_.Exception.Message)
}

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
    Write-Host "Listo: el agente de etiquetas quedo como servicio y arranca solo con Windows." -ForegroundColor Green
} else {
    Write-Host "El servicio NO quedo en RUNNING. Revisa agent-err.log y config.json (serverUrl/cajaToken/printerName)." -ForegroundColor Red
}
