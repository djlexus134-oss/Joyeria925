# Print Agent — Joyeria POS (Windows)

Agente local que consulta la cola de impresion del servidor y envia tickets ESC/POS a la impresora termica.

## 1. Instalar drivers Epson en la PC de caja

Antes del agente, instala en Windows (como administrador):

1. **TMUSB v8.00b** — `e:\epson\TMUSB_DeviceDriver_v8.00b\Setup.exe`
2. **TM-APD v4.56d** — `e:\epson\TM-APD_v4.56d\APD_456dE.exe`
3. Verifica en *Configuracion > Impresoras* que aparece **EPSON TM-T20 Receipt**
4. Imprime una pagina de prueba desde Windows

## 2. Migracion SQL en el servidor

Ejecuta en MySQL:

`sql/2026_05_14_ticket_impresion_pos.sql`

Luego en el admin abre **Sistema > Ticket POS** y define un **token seguro** para el agente (debe coincidir con `config.json`).

## 3. Configurar el agente

```powershell
cd D:\PrograWEB\src\Joyeria\print-agent
copy config.example.json config.json
npm install
```

Solo instala `axios`. La impresion RAW usa **PowerShell** (`print-raw.ps1`) y no requiere el paquete npm `printer` (abandonado y falla en Node moderno).

Edita `config.json`:

| Campo | Descripcion |
|-------|-------------|
| `serverUrl` | URL base del admin. `http://{localIp}:8080/Joyeria/admin` se adapta a la IP DHCP |
| `serverUrlUseLocalhost` | Si `true`, fuerza `127.0.0.1` (misma PC que Apache) |
| `serverUrl` = `"auto"` | Construye URL con `serverPort` y `serverPath` |
| `cajaToken` | Mismo valor que `impresion_caja_token` en configuracion de ticket |
| `printerName` | Nombre exacto en Windows, ej. `EPSON TM-T20 Receipt` |
| `pollIntervalMs` | Intervalo de consulta (1500 recomendado) |

## 4. Ejecutar

```powershell
npm start
```

## 5. Servicio Windows (opcional, NSSM)

```powershell
nssm install JoyeriaPrintAgent "C:\Program Files\nodejs\node.exe" "D:\PrograWEB\src\Joyeria\print-agent\index.js"
nssm set JoyeriaPrintAgent AppDirectory "D:\PrograWEB\src\Joyeria\print-agent"
nssm start JoyeriaPrintAgent
```

## Flujo con telefono

1. Cobras desde el movil en `punto_venta.php`
2. El servidor encola el ticket en `cola_impresion`
3. Este agente lo imprime en la PC conectada por USB
4. El POS muestra estado: pendiente / impreso / error

## Solucion de problemas

- **Token invalido**: sincroniza token en admin y `config.json`
- **Impresora no encontrada**: revisa nombre exacto con `Get-Printer` en PowerShell
- **Modulo printer falla**: ya no se usa; reinstala con `npm install` (solo axios)
- **Ticket en cola pero no imprime**: revisa firewall y que `serverUrl` sea alcanzable desde la PC
