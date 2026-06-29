# Limpia colas atascadas (Retained/Deleting) que bloquean USB compartido entre Argox RAW y Seagull.
# Ejecutar PowerShell como Administrador.
#Requires -RunAsAdministrator
$ErrorActionPreference = 'Stop'
Write-Host 'Deteniendo Spooler...'
Stop-Service -Name Spooler -Force
Start-Sleep -Seconds 2
$spool = Join-Path $env:SystemRoot 'System32\spool\PRINTERS'
Get-ChildItem -LiteralPath $spool -Filter '*.SHD' -ErrorAction SilentlyContinue | Remove-Item -Force -ErrorAction SilentlyContinue
Get-ChildItem -LiteralPath $spool -Filter '*.SPL' -ErrorAction SilentlyContinue | Remove-Item -Force -ErrorAction SilentlyContinue
Write-Host 'Iniciando Spooler...'
Start-Service -Name Spooler
Write-Host 'Listo. Vuelve a ejecutar npm run test-print.'
