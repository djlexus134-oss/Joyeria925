param(
    [Parameter(Mandatory = $true)]
    [string]$PrinterName,
    [ValidateSet('texto', 'corte', 'recupera')]
    [string]$Mode = 'texto'
)

# Prueba directa del canal RAW con ESC/POS minimo y seguro (sin pasar por el servidor).
# Aisla si el problema es el canal RAW o el contenido del ticket del servidor.
#   texto    : init + texto + avance (SIN corte)
#   corte    : init + texto + avance + corte parcial
#   recupera : recuperacion de error + init + texto + avance + corte

$ESC = [char]27
$GS  = [char]29
$DLE = [char]16
$init = "$ESC@"                       # ESC @  inicializa
$fontA = "$ESC" + 'M' + [char]0       # ESC M 0 Font A
$feed = "`n`n`n`n"
$cut  = "$GS" + 'V' + [char]1         # GS V 1 corte parcial
$recover = "$DLE" + [char]5 + [char]2 # DLE ENQ 2 recupera error + limpia buffer

$body = "PRUEBA RAW TM-T20IV`nJoyeria 925`n" + ('-' * 32) + "`nLinea de prueba`n"

switch ($Mode) {
    'texto'    { $data = $init + $fontA + $body + $feed }
    'corte'    { $data = $init + $fontA + $body + $feed + $cut }
    'recupera' { $data = $recover + $init + $fontA + $body + $feed + $cut }
}

$bytes = [System.Text.Encoding]::ASCII.GetBytes($data)
$tmp = Join-Path $env:TEMP ("joyeria-testraw-{0}.bin" -f (Get-Random))
[System.IO.File]::WriteAllBytes($tmp, $bytes)

Write-Host ("Modo: {0} | Bytes: {1} | Impresora: {2}" -f $Mode, $bytes.Length, $PrinterName)

$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$printRaw = Join-Path $scriptDir 'print-raw.ps1'

& powershell.exe -NoProfile -ExecutionPolicy Bypass -File $printRaw -PrinterName $PrinterName -FilePath $tmp
$code = $LASTEXITCODE

Remove-Item $tmp -Force -ErrorAction SilentlyContinue

if ($code -eq 0) {
    Write-Host "OK: el canal RAW funciono en modo '$Mode'." -ForegroundColor Green
} else {
    Write-Host "FALLO: el canal RAW devolvio codigo $code en modo '$Mode'." -ForegroundColor Red
}
