# Print Agent Etiquetas — Joyeria (Windows / Argox PPLA)

Agente local que consulta la cola de impresion del servidor y envia etiquetas PPLA a la Argox en modo **RAW** con Python (`win32print`), el mismo metodo que `test.py` en la raiz del proyecto.

## 1. Driver Argox

Instala el driver con variante **PPLA** (ej. `Argox OS-2140 PPLA`). En admin configura **Lenguaje: PPLA**.

En **Material** del driver deja:
- Sensor: espacios entre etiquetas
- Altura del espacio: **3.0 mm** (como tu rollo)
- En **Preparar pagina**: tamano **60 x 10 mm**

El programa envia el PPLA con cabecera de sistema (sensor de espacios `\x02e`,
altura maxima `\x02M...`) y por cada etiqueta `STX L ... Q0001 E`. Esto replica
lo que hace el driver cuando imprime una pagina de prueba: la impresora detecta
el gap entre etiquetas y avanza una etiqueta por cada `E`.

## 2. Migraciones SQL

Ejecuta en orden:

1. `sql/2026_05_14_ticket_impresion_pos.sql`
2. `sql/2026_05_14_cola_impresion_etiquetas.sql`

En el admin abre **Sistema > Ticket POS** y configura token + nombre impresora Argox en la seccion Etiquetas.

## 3. Configurar el agente

```powershell
cd D:\PrograWEB\src\Joyeria\print-agent-etiquetas
copy config.example.json config.json
npm install
npm run setup-python
```

| Campo | Descripcion |
|-------|-------------|
| `serverUrl` | URL base del admin. Usa `{localIp}` para la IPv4 DHCP actual, o `"auto"` (ver abajo) |
| `serverUrlUseLocalhost` | Si `true`, usa `127.0.0.1` (Apache en la misma PC que el agente) |
| `serverPort` / `serverPath` | Solo con `serverUrl`: `"auto"` |
| `cajaToken` | Preferible: `etiqueta_impresion_token` (puede ser distinto al de tickets). Si ese campo esta vacio en el admin, el servidor acepta `impresion_caja_token` como respaldo |
| `printerName` | Nombre exacto Argox en Windows (como en `test.py`) |
| `pythonPath` | Opcional: ruta a `python.exe`. Vacio = `py -3` |
| `destino` | Dejar `etiqueta` |

Lista impresoras:

```powershell
npm run list-printers
```

Prueba local (mismo payload que `test.py`):

```powershell
npm run test-print
```

### URL del servidor y DHCP

Si la IP de la PC cambia por DHCP, no hace falta editar la IP a mano:

```json
"serverUrl": "http://{localIp}:8080/Joyeria/admin"
```

O modo compacto:

```json
"serverUrl": "auto",
"serverPort": 8080,
"serverPath": "/Joyeria/admin"
```

Si Apache y el agente estan en la **misma PC**, es mas estable:

```json
"serverUrl": "http://127.0.0.1:8080/Joyeria/admin"
```

o `"serverUrlUseLocalhost": true` con cualquier host en la URL.

Al iniciar, el agente muestra la URL resuelta y la IPv4 detectada.

## 4. Ejecutar

```powershell
npm start
```

## Flujo

1. Desde admin: stock de piezas → **Encolar etiquetas**
2. El servidor inserta en `cola_impresion` (tipo `etiqueta_stock` / `etiqueta_lote`)
3. Este agente imprime en la PC con Argox USB
4. La UI muestra estado pendiente / impreso / error

## Servicio Windows (NSSM)

### Opcion recomendada: instalador automatico

Abre PowerShell **como Administrador**, entra a la carpeta del agente y ejecuta:

```powershell
cd C:\Joyeria925\print-agent-etiquetas
powershell -ExecutionPolicy Bypass -File .\install-service.ps1
```

El script hace todo: valida Node/NSSM/Python, corre `npm install`, instala las
dependencias de Python (`pywin32`, `Pillow`), normaliza `config.json` (UTF-8 sin
BOM) y (re)instala el servicio `JoyeriaLabelAgent` con arranque automatico,
logs (`agent-out.log` / `agent-err.log`) y reinicio ante fallos.

Parametros opcionales:

```powershell
# Fijar la impresora (si se omite, respeta el printerName de config.json)
.\install-service.ps1 -PrinterName "ZDesigner ZD220-203dpi ZPL"

# Rutas alternativas de Node / NSSM
.\install-service.ps1 -NodeExe "C:\Program Files\nodejs\node.exe" -NssmExe "C:\Tools\nssm\nssm.exe"
```

> **Importante:** el agente de etiquetas hace `require('../print-agent/resolve-server-url')`.
> En el equipo destino la carpeta `print-agent` (tickets) debe existir como
> carpeta **hermana** de `print-agent-etiquetas`, aunque no uses el agente de tickets.

### Opcion manual

```powershell
nssm install JoyeriaLabelAgent "C:\Program Files\nodejs\node.exe" "C:\Joyeria925\print-agent-etiquetas\index.js"
nssm set JoyeriaLabelAgent AppDirectory "C:\Joyeria925\print-agent-etiquetas"
nssm start JoyeriaLabelAgent
```

### El servicio quedo deshabilitado / no arranca

Si durante una solucion de problemas se deshabilito el servicio:

```powershell
# Ver estado y tipo de arranque
nssm status JoyeriaLabelAgent
Get-Service JoyeriaLabelAgent | Format-List Name,Status,StartType

# Volver a habilitar arranque automatico y arrancar
nssm set JoyeriaLabelAgent Start SERVICE_AUTO_START
nssm start JoyeriaLabelAgent
```

O simplemente vuelve a ejecutar `install-service.ps1`, que lo reinstala desde cero.

## Problemas frecuentes

- **No module named win32print**: `npm run setup-python`
- **Impresora no encontrada**: `npm run list-printers` y copia el nombre exacto a `printerName`
- **401 en poll**: pon el token real en `cajaToken` (mismo que en configuracion del admin)
- **Etiquetas corridas / codigo de barras vertical en varias filas**: el driver Argox no tiene **60 x 10 mm** por pagina. En los logs debe verse `page` cercano a **480x80** (203 dpi), no `807x1218`. En Windows: Propiedades de impresora Argox → Material → gap **3 mm** → Preparar pagina **60 x 10 mm**. Actualiza `print_image.py` del repo (ya no estira el PNG a toda la pagina del driver).

### Calibrar driver Argox (Windows)

1. Panel de control → Impresoras → **Argox OS-2140 PPLA** → Preferencias de impresion.
2. **Material**: sensor por espacio (gap), altura del espacio **3.0 mm**.
3. **Tamano / Preparar pagina**: **60 mm x 10 mm** (igual que `etiqueta_ancho_mm` / `etiqueta_alto_mm` en admin).
4. Imprime pagina de prueba del driver; debe salir **una** etiqueta por hoja de prueba.
5. Reinicia el agente (`npm start`) y revisa el log: `page=480x80` aprox. (puede variar unos dots).

Rollo fisico: etiquetas joyeria **mariposa** 60 mm de ancho total (dos pads + cola), no rollo de etiquetas grandes distinto tamano.
