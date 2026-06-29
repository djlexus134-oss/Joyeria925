# Registra los agentes de impresion como servicios Windows (arranque automatico).
# Requiere NSSM: https://nssm.cc/download  o  winget install NSSM.NSSM
# Ejecutar PowerShell como Administrador.
#Requires -RunAsAdministrator
param(
    [string]$RepoRoot = "C:\Joyeria",
    [string]$NssmExe = "",
    [string]$RunAsUser = "",
    [string]$ServicePassword = "",
    [switch]$UseLocalSystem,
    [switch]$Remove
)

$ErrorActionPreference = "Stop"

function Find-Nssm {
    param([string]$Override)
    if ($Override -ne "" -and (Test-Path $Override)) {
        return (Resolve-Path $Override).Path
    }
    $cmd = Get-Command nssm -ErrorAction SilentlyContinue
    if ($cmd) {
        return $cmd.Source
    }
    $candidates = @(
        "${env:ProgramFiles}\nssm\nssm.exe",
        "${env:ProgramFiles(x86)}\nssm\nssm.exe",
        "C:\Tools\nssm\nssm.exe",
        "C:\nssm\nssm.exe"
    )
    foreach ($p in $candidates) {
        if (Test-Path $p) {
            return (Resolve-Path $p).Path
        }
    }
    return $null
}

function Find-NodeExe {
    $cmd = Get-Command node -ErrorAction SilentlyContinue
    if (-not $cmd) {
        throw "Node.js no esta en PATH. Instala Node 20 LTS y abre una ventana nueva."
    }
    return $cmd.Source
}

function Remove-AgentService {
    param(
        [string]$Nssm,
        [string]$Name
    )
    $existing = Get-Service -Name $Name -ErrorAction SilentlyContinue
    if (-not $existing) {
        Write-Host "  Servicio $Name no instalado." -ForegroundColor DarkGray
        return
    }
    & $Nssm stop $Name 2>$null | Out-Null
    Start-Sleep -Seconds 1
    & $Nssm remove $Name confirm 2>$null | Out-Null
    Write-Host "  Eliminado: $Name" -ForegroundColor Yellow
}

function Read-ServicePasswordPlain {
    param([string]$AccountLabel, [string]$Provided)
    if ($Provided -ne "") {
        return $Provided
    }
    Write-Host "NSSM requiere usuario Y contrasena de Windows para imprimir en USB." -ForegroundColor Cyan
    $sec = Read-Host "Contrasena de $AccountLabel" -AsSecureString
    $ptr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($sec)
    try {
        return [Runtime.InteropServices.Marshal]::PtrToStringAuto($ptr)
    } finally {
        [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($ptr)
    }
}

function Install-AgentService {
    param(
        [string]$Nssm,
        [string]$NodeExe,
        [string]$Name,
        [string]$AppDir,
        [string]$EntryJs,
        [string]$RunAs,
        [string]$RunAsPassword,
        [bool]$AsLocalSystem
    )

    if (-not (Test-Path $EntryJs)) {
        throw "No existe $EntryJs"
    }

    Remove-AgentService -Nssm $Nssm -Name $Name

    $logDir = Join-Path $AppDir "logs"
    New-Item -ItemType Directory -Force -Path $logDir | Out-Null
    $stdout = Join-Path $logDir "service.out.log"
    $stderr = Join-Path $logDir "service.err.log"

    & $Nssm install $Name $NodeExe $EntryJs
    & $Nssm set $Name AppDirectory $AppDir
    & $Nssm set $Name DisplayName "Joyeria - $Name"
    & $Nssm set $Name Description "Agente impresion Joyeria ($Name)"
    & $Nssm set $Name Start SERVICE_AUTO_START
    & $Nssm set $Name AppStdout $stdout
    & $Nssm set $Name AppStderr $stderr
    & $Nssm set $Name AppStdoutCreationDisposition 4
    & $Nssm set $Name AppStderrCreationDisposition 4
    & $Nssm set $Name AppRotateFiles 1
    & $Nssm set $Name AppRotateBytes 1048576

    if ($AsLocalSystem) {
        Write-Host "  Cuenta: LocalSystem (si no imprime USB, reinstala sin -UseLocalSystem)" -ForegroundColor DarkYellow
        & $Nssm reset $Name ObjectName confirm 2>$null | Out-Null
    } elseif ($RunAs -ne "" -and $RunAsPassword -ne "") {
        Write-Host "  Cuenta de servicio: $RunAs" -ForegroundColor Cyan
        & $Nssm set $Name ObjectName $RunAs $RunAsPassword
    }

    & $Nssm start $Name
    Start-Sleep -Seconds 2
    $svc = Get-Service -Name $Name -ErrorAction SilentlyContinue
    if ($svc -and $svc.Status -eq "Running") {
        Write-Host "  OK en ejecucion: $Name" -ForegroundColor Green
    } else {
        Write-Host "  Instalado pero no Running. Revisa $stderr" -ForegroundColor Yellow
    }
}

$nssm = Find-Nssm -Override $NssmExe
if (-not $nssm) {
    Write-Host "NSSM no encontrado." -ForegroundColor Red
    Write-Host "  1) Descarga https://nssm.cc/download y extrae nssm.exe a C:\Tools\nssm\" -ForegroundColor Yellow
    Write-Host "  2) O: winget install NSSM.NSSM" -ForegroundColor Yellow
    Write-Host "  3) Vuelve a ejecutar este script con -NssmExe 'C:\ruta\nssm.exe'" -ForegroundColor Yellow
    exit 1
}

if ($Remove) {
    Write-Host "Eliminando servicios..." -ForegroundColor Cyan
    Remove-AgentService -Nssm $nssm -Name "JoyeriaPrintTicket"
    Remove-AgentService -Nssm $nssm -Name "JoyeriaPrintEtiquetas"
    exit 0
}

$node = Find-NodeExe
$ticketDir = Join-Path $RepoRoot "print-agent"
$labelDir = Join-Path $RepoRoot "print-agent-etiquetas"

foreach ($d in @($ticketDir, $labelDir)) {
    if (-not (Test-Path (Join-Path $d "config.json"))) {
        throw "Falta config.json en $d. Ejecuta setup-caja-windows.ps1 o copia config.example.json primero."
    }
}

Write-Host "Instalando dependencias npm (axios, etc.)..." -ForegroundColor Cyan
foreach ($d in @($ticketDir, $labelDir)) {
    Push-Location $d
    npm install --omit=dev
    if (-not (Test-Path (Join-Path $d "node_modules\axios"))) {
        Pop-Location
        throw "Falta node_modules\axios en $d. Revisa salida de npm install."
    }
    Pop-Location
    Write-Host "  OK: $d" -ForegroundColor Green
}

if (Test-Path (Join-Path $labelDir "package.json")) {
    Push-Location $labelDir
    npm run setup-python 2>$null
    Pop-Location
}

$runAs = $RunAsUser
$runAsPassword = $ServicePassword
$asLocalSystem = $UseLocalSystem.IsPresent

if (-not $asLocalSystem) {
    if ($runAs -eq "") {
        $runAs = "$env:USERDOMAIN\$env:USERNAME"
    }
    $runAsPassword = Read-ServicePasswordPlain -AccountLabel $runAs -Provided $runAsPassword
}

Write-Host "=== Instalando servicios (NSSM: $nssm) ===" -ForegroundColor Cyan
Write-Host "Node: $node"

Install-AgentService -Nssm $nssm -NodeExe $node -Name "JoyeriaPrintTicket" `
    -AppDir $ticketDir -EntryJs (Join-Path $ticketDir "index.js") `
    -RunAs $runAs -RunAsPassword $runAsPassword -AsLocalSystem $asLocalSystem

Install-AgentService -Nssm $nssm -NodeExe $node -Name "JoyeriaPrintEtiquetas" `
    -AppDir $labelDir -EntryJs (Join-Path $labelDir "index.js") `
    -RunAs $runAs -RunAsPassword $runAsPassword -AsLocalSystem $asLocalSystem

Write-Host ""
Write-Host "Listo. Al reiniciar Windows los agentes arrancan solos." -ForegroundColor Green
Write-Host "Logs: print-agent\logs\ y print-agent-etiquetas\logs\" -ForegroundColor Green
Write-Host "Etiquetas: en config.json pon pythonPath con ruta completa a python.exe (servicio sin PATH de py)." -ForegroundColor Yellow
Write-Host "Comandos utiles:" -ForegroundColor Cyan
Write-Host "  Get-Service JoyeriaPrint*"
Write-Host "  nssm restart JoyeriaPrintTicket"
Write-Host "  .\install-caja-services.ps1 -Remove   # desinstalar"
