const fs = require('fs');
const path = require('path');
const os = require('os');
const { execFile } = require('child_process');
const axios = require('axios');
const { resolveServerUrl, pickLanIPv4 } = require('./resolve-server-url');

const configPath = path.join(__dirname, 'config.json');
const printScriptPath = path.join(__dirname, 'print-raw.ps1');
const lockPath = path.join(__dirname, '.print-agent.lock');

if (!fs.existsSync(configPath)) {
  console.error('Falta config.json. Copia config.example.json y ajusta valores.');
  process.exit(1);
}

if (!fs.existsSync(printScriptPath)) {
  console.error('Falta print-raw.ps1 en la carpeta del agente.');
  process.exit(1);
}

function acquireSingletonLock() {
  try {
    if (fs.existsSync(lockPath)) {
      const prev = parseInt(String(fs.readFileSync(lockPath, 'utf8')).trim(), 10) || 0;
      if (prev > 0) {
        try {
          // Señal 0: solo comprueba si el PID sigue vivo (Windows/Node).
          process.kill(prev, 0);
          console.error(
            '[print-agent] Ya hay otra instancia corriendo (PID ' +
              prev +
              '). Cierra npm/NSSM duplicado: dos agentes pelean el USB y la Epson imprime "?".'
          );
          process.exit(1);
        } catch (e) {
          // PID muerto: lock viejo, se reemplaza.
        }
      }
    }
    fs.writeFileSync(lockPath, String(process.pid), 'utf8');
  } catch (e) {
    console.warn('[print-agent] No se pudo crear lock de instancia unica:', e.message || e);
  }
}

function releaseSingletonLock() {
  try {
    if (fs.existsSync(lockPath)) {
      const prev = parseInt(String(fs.readFileSync(lockPath, 'utf8')).trim(), 10) || 0;
      if (prev === process.pid) {
        fs.unlinkSync(lockPath);
      }
    }
  } catch (e) {
    // ignorar
  }
}

acquireSingletonLock();
process.on('exit', releaseSingletonLock);
process.on('SIGINT', () => {
  releaseSingletonLock();
  process.exit(0);
});
process.on('SIGTERM', () => {
  releaseSingletonLock();
  process.exit(0);
});

let configRaw = fs.readFileSync(configPath, 'utf8');
if (configRaw.charCodeAt(0) === 0xfeff) {
  configRaw = configRaw.slice(1);
}
const config = JSON.parse(configRaw.trim());
const serverUrl = resolveServerUrl(config);
const cajaToken = String(config.cajaToken || '');
const printerName = String(config.printerName || 'EPSON TM-T20 Receipt');
const pollIntervalMs = Math.max(500, parseInt(config.pollIntervalMs, 10) || 1500);

if (!serverUrl || !cajaToken) {
  console.error('serverUrl y cajaToken son obligatorios en config.json');
  process.exit(1);
}

function listPrinters() {
  return new Promise((resolve) => {
    execFile(
      'powershell.exe',
      ['-NoProfile', '-Command', 'Get-Printer | Select-Object -ExpandProperty Name'],
      { windowsHide: true },
      (err, stdout) => {
        if (err) {
          resolve([]);
          return;
        }
        resolve(
          String(stdout || '')
            .split(/\r?\n/)
            .map((line) => line.trim())
            .filter(Boolean)
        );
      }
    );
  });
}

function printRaw(buffer) {
  return new Promise((resolve, reject) => {
    const tempFile = path.join(os.tmpdir(), `joyeria-ticket-${Date.now()}.bin`);
    fs.writeFileSync(tempFile, buffer);

    execFile(
      'powershell.exe',
      [
        '-NoProfile',
        '-ExecutionPolicy',
        'Bypass',
        '-File',
        printScriptPath,
        '-PrinterName',
        printerName,
        '-FilePath',
        tempFile,
      ],
      { windowsHide: true },
      (err, stdout, stderr) => {
        try {
          fs.unlinkSync(tempFile);
        } catch (e) {
          // ignorar
        }
        if (err) {
          const detail = [stderr, stdout, err.message].filter(Boolean).join(' ').trim();
          reject(new Error(detail || 'Error al imprimir via PowerShell'));
          return;
        }
        resolve();
      }
    );
  });
}

async function fetchPending() {
  const url = serverUrl + '/api/impresion.php?accion=pendientes';
  const response = await axios.get(url, {
    headers: { 'X-Caja-Token': cajaToken },
    timeout: 15000,
    validateStatus: () => true,
  });
  if (response.status === 401) {
    throw new Error('Token de caja invalido. Revisa impresion_caja_token en el admin.');
  }
  if (response.status >= 400) {
    const msg = response.data && response.data.error ? response.data.error : 'Error HTTP ' + response.status;
    throw new Error(msg);
  }
  return response.data;
}

async function confirmJob(idCola, ok, mensaje) {
  const url = serverUrl + '/api/impresion.php?accion=confirmar';
  await axios.post(
    url,
    { id_cola_impresion: idCola, ok: ok, mensaje: mensaje || '' },
    {
      headers: {
        'X-Caja-Token': cajaToken,
        'Content-Type': 'application/json',
      },
      timeout: 15000,
    }
  );
}

async function processOne() {
  const payload = await fetchPending();
  if (!payload || !payload.success || !payload.data) {
    return;
  }
  const job = payload.data;
  if (!job || !job.id_cola_impresion) {
    return;
  }

  const idCola = job.id_cola_impresion;
  const b64 = job.escpos_base64;
  if (!b64) {
    await confirmJob(idCola, false, 'Payload ESC/POS vacio');
    return;
  }

  try {
    const buffer = Buffer.from(b64, 'base64');
    if (buffer.length === 0) {
      await confirmJob(idCola, false, 'Payload ESC/POS decodificado vacio');
      return;
    }
    // Tope practico: un ticket muy grande (>8 KB) suele indicar basura o datos corruptos.
    if (buffer.length > 8192) {
      await confirmJob(idCola, false, 'Payload ESC/POS demasiado grande (' + buffer.length + ' bytes)');
      return;
    }
    await printRaw(buffer);
    await confirmJob(idCola, true, '');
    console.log('[print-agent] Ticket impreso venta #' + (job.id_venta || '?') + ' (' + buffer.length + ' bytes)');
    // Da tiempo a la Epson a vaciar buffer/autocutter antes del siguiente ticket.
    await new Promise((r) => setTimeout(r, 800));
  } catch (err) {
    const msg = err && err.message ? err.message : String(err);
    console.error('[print-agent] Error:', msg);
    await confirmJob(idCola, false, msg);
  }
}

async function loop() {
  try {
    await processOne();
  } catch (err) {
    console.error('[print-agent] Poll error:', err.message || err);
  }
  setTimeout(loop, pollIntervalMs);
}

(async function main() {
  console.log('[print-agent] Iniciado (impresion RAW via PowerShell)');
  console.log('[print-agent] Servidor:', serverUrl, '(IPv4 local:', pickLanIPv4() + ')');
  console.log('[print-agent] Impresora:', printerName);

  const printers = await listPrinters();
  if (printers.length && !printers.includes(printerName)) {
    console.warn('[print-agent] ADVERTENCIA: impresora no encontrada. Disponibles:', printers.join(', '));
  }

  loop();
})();
