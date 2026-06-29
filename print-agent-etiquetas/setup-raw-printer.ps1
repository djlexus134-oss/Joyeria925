# Cola RAW (Generic / Text Only) para PPLA directo; el driver Seagull PPLA no acepta WritePrinter RAW.
$rawName = 'Argox RAW'
$pplaName = 'Argox OS-2140 PPLA'
$ppla = Get-Printer -Name $pplaName -ErrorAction SilentlyContinue
if (-not $ppla) {
    Write-Host "ADVERTENCIA: no se encontro $pplaName"
    exit 1
}
$port = $ppla.PortName
$existing = Get-Printer -Name $rawName -ErrorAction SilentlyContinue
if (-not $existing) {
    try {
        Add-Printer -Name $rawName -DriverName 'Generic / Text Only' -PortName $port -ErrorAction Stop
        Write-Host "Creada cola RAW: $rawName en puerto $port"
    } catch {
        Write-Host "No se pudo crear $rawName (ejecutar como admin): $($_.Exception.Message)"
        exit 1
    }
} else {
    Write-Host "Ya existe $rawName (puerto $($existing.PortName))"
}
Write-Host "Usar en config.json printerName: $rawName"
