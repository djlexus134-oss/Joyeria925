const fs = require('fs');
const path = require('path');
const os = require('os');
const { execFile } = require('child_process');
const axios = require('axios');
const { resolveServerUrl, pickLanIPv4 } = require('../print-agent/resolve-server-url');

const configPath = path.join(__dirname, 'config.json');
const printRawScriptPath = path.join(__dirname, 'print_raw.py');
const printImageScriptPath = path.join(__dirname, 'print_image.py');

if (!fs.existsSync(configPath)) {
  console.error('Falta config.json. Copia config.example.json y ajusta valores.');
  process.exit(1);
}

if (!fs.existsSync(printRawScriptPath)) {
  console.error('Falta print_raw.py en la carpeta del agente.');
  process.exit(1);
}

if (!fs.existsSync(printImageScriptPath)) {
  console.error('Falta print_image.py en la carpeta del agente.');
  process.exit(1);
}

let configRaw = fs.readFileSync(configPath, 'utf8');
if (configRaw.charCodeAt(0) === 0xfeff) {
  configRaw = configRaw.slice(1);
}
const config = JSON.parse(configRaw.trim());
const serverUrl = resolveServerUrl(config);
const cajaToken = String(config.cajaToken || '');
const printerName = String(config.printerName || 'Argox OS-2140 PPLA');
const pollIntervalMs = Math.max(500, parseInt(config.pollIntervalMs, 10) || 1500);
const destino = String(config.destino || 'etiqueta');
const pythonPath = String(config.pythonPath || '').trim();

if (!serverUrl || !cajaToken) {
  console.error('serverUrl y cajaToken son obligatorios en config.json');
  process.exit(1);
}
if (cajaToken === 'cambiar_token_seguro') {
  console.warn(
    '[label-agent] ADVERTENCIA: cajaToken sigue siendo el valor de ejemplo; el servidor rechazara el poll (401) hasta que pongas el token real de impresion.'
  );
}

function resolvePythonExecutable() {
  if (pythonPath) {
    return pythonPath;
  }
  if (process.env.JOYERIA_PYTHON) {
    return process.env.JOYERIA_PYTHON;
  }
  return 'py';
}

function runPython(scriptPath, args, opts = {}) {
  const exe = resolvePythonExecutable();
  const baseArgs = exe === 'py' ? ['-3', scriptPath] : [scriptPath];
  const timeoutMs = opts.timeoutMs || 30000;
  return new Promise((resolve, reject) => {
    const child = execFile(
      exe,
      baseArgs.concat(args),
      { windowsHide: true, timeout: timeoutMs },
      (err, stdout, stderr) => {
        // Mostramos stderr siempre que tenga contenido para diagnostico.
        const errOut = String(stderr || '').trim();
        if (errOut) {
          errOut.split(/\r?\n/).forEach((line) => console.log('  [py]', line));
        }
        if (err) {
          if (err.killed && err.signal) {
            reject(new Error('Script Python excedio el timeout de ' + timeoutMs + 'ms (signal ' + err.signal + ')'));
          } else {
            const detail = [errOut, stdout, err.message].filter(Boolean).join(' ').trim();
            reject(new Error(detail || 'Error al ejecutar script Python'));
          }
          return;
        }
        resolve(String(stdout || '').trim());
      }
    );
    child.on('error', (e) => reject(e));
  });
}

function listPrinters() {
  return runPython(printRawScriptPath, ['--list-printers'])
    .then((stdout) =>
      stdout
        .split(/\r?\n/)
        .map((line) => line.trim())
        .filter(Boolean)
    )
    .catch(() => []);
}

/**
 * Separa el buffer PPLA en una lista de etiquetas autosuficientes.
 * - Captura la cabecera previa al primer \x02L (ej. \x02e\r) para no perderla.
 * - Devuelve cada etiqueta como: <cabecera><\x02L ... E\r>.
 *
 * Asi cada job RAW se envia de forma independiente a la impresora y
 * aprovechamos la calibracion nativa de gaps entre etiquetas (como Gemarun).
 */
function splitPplaJobs(buffer) {
  const raw = buffer.toString('latin1');
  const firstLabelIdx = raw.indexOf('\x02L');
  if (firstLabelIdx < 0) {
    return [buffer];
  }
  const cabecera = raw.slice(0, firstLabelIdx);
  const cuerpo = raw.slice(firstLabelIdx);
  const partes = cuerpo.split(/(?=\x02L)/).filter((s) => s.length > 0);
  if (partes.length === 0) {
    return [buffer];
  }
  return partes.map((part) => Buffer.from(cabecera + part, 'latin1'));
}

function printRaw(buffer) {
  return new Promise((resolve, reject) => {
    const tempFile = path.join(os.tmpdir(), `joyeria-etiqueta-${Date.now()}.bin`);
    fs.writeFileSync(tempFile, buffer);

    runPython(printRawScriptPath, ['--printer', printerName, '--file', tempFile])
      .then((method) => {
        if (method) {
          console.log('[label-agent] Via envio (RAW):', method);
        }
        resolve();
      })
      .catch(reject)
      .finally(() => {
        try {
          fs.unlinkSync(tempFile);
        } catch (e) {}
      });
  });
}

function printPng(pngBuffer) {
  return new Promise((resolve, reject) => {
    const tempFile = path.join(os.tmpdir(), `joyeria-etiqueta-${Date.now()}-${Math.random().toString(36).slice(2, 8)}.png`);
    fs.writeFileSync(tempFile, pngBuffer);

    runPython(printImageScriptPath, ['--printer', printerName, '--file', tempFile])
      .then((method) => {
        if (method) {
          console.log('[label-agent] Via envio (GDI):', method);
        }
        resolve();
      })
      .catch(reject)
      .finally(() => {
        try {
          fs.unlinkSync(tempFile);
        } catch (e) {}
      });
  });
}

/**
 * Detecta el formato del payload:
 * - JSON (empieza con '{') con tipo "imagen": lote de PNGs base64 (estilo Gemarun).
 * - Cualquier otra cosa: bytes RAW PPLA/ZPL.
 */
function detectarPayload(buffer) {
  if (!buffer || buffer.length === 0) {
    return { tipo: 'vacio' };
  }
  const c = buffer[0];
  if (c === 0x7b /* { */) {
    try {
      const obj = JSON.parse(buffer.toString('utf8'));
      if (obj && obj.tipo === 'imagen' && Array.isArray(obj.etiquetas)) {
        return { tipo: 'imagen', etiquetas: obj.etiquetas, meta: obj };
      }
    } catch (e) {
      // Si falla el parseo, tratar como RAW.
    }
  }
  return { tipo: 'raw', buffer };
}

function sleep(ms) {
  return new Promise((r) => setTimeout(r, ms));
}

async function fetchPending() {
  const url = serverUrl + '/api/impresion.php?accion=pendientes&destino=' + encodeURIComponent(destino);
  const response = await axios.get(url, {
    headers: { 'X-Caja-Token': cajaToken },
    timeout: 15000,
    validateStatus: () => true,
  });
  if (response.status === 401) {
    throw new Error(
      'Token invalido (destino=etiqueta). En el admin: deja VACIO etiqueta_impresion_token ' +
        'o pon el mismo valor que impresion_caja_token y en config.json cajaToken.'
    );
  }
  if (response.status >= 400) {
    const msg = response.data && response.data.error ? response.data.error : 'Error HTTP ' + response.status;
    throw new Error(msg);
  }
  return response.data;
}

async function confirmJob(idCola, ok, mensaje) {
  const url = serverUrl + '/api/impresion.php?accion=confirmar&destino=' + encodeURIComponent(destino);
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

// Conjunto de jobs ya procesados en esta sesion para garantizar idempotencia
// ante un payload duplicado del server (cinturon + tirantes).
const jobsVistos = new Set();
let procesando = false;

async function processOne() {
  if (procesando) {
    return;
  }
  procesando = true;
  try {
    const resp = await fetchPending();
    if (!resp || !resp.success || !resp.data) {
      return;
    }

    const job = resp.data;
    if (!job || !job.id_cola_impresion) {
      return;
    }

    const idCola = job.id_cola_impresion;

    if (jobsVistos.has(idCola)) {
      // El server nos volvio a entregar un job que ya procesamos en esta
      // sesion. No lo imprimimos, solo lo confirmamos para que cambie de
      // estado y deje de aparecer.
      console.warn('[label-agent] Cola #' + idCola + ' ya procesada, ignorando duplicado.');
      try {
        await confirmJob(idCola, true, 'duplicado-ignorado');
      } catch (e) {}
      return;
    }

    const b64 = job.zpl_base64;
    if (!b64) {
      await confirmJob(idCola, false, 'Payload PPLA vacio');
      return;
    }

    try {
      const buffer = Buffer.from(b64, 'base64');
      if (buffer.length === 0) {
        await confirmJob(idCola, false, 'Payload vacio (0 bytes)');
        return;
      }

      const detectado = detectarPayload(buffer);
      console.log(
        '[label-agent] Cola #' +
          idCola +
          ' tipo=' +
          detectado.tipo +
          ' bytes=' +
          buffer.length +
          (detectado.tipo === 'imagen' ? ' etiquetas=' + detectado.etiquetas.length : '')
      );

      // Marcamos el job como visto ANTES de empezar a imprimir para que un
      // poll concurrente no lo procese otra vez (en combinacion con el lock
      // 'imprimiendo' del backend).
      jobsVistos.add(idCola);

      if (detectado.tipo === 'imagen') {
        for (let i = 0; i < detectado.etiquetas.length; i += 1) {
          const png = Buffer.from(detectado.etiquetas[i], 'base64');
          console.log(
            '[label-agent]   etiqueta ' + (i + 1) + '/' + detectado.etiquetas.length + ' (' + png.length + ' bytes PNG)'
          );
          await printPng(png);
        }
        await confirmJob(idCola, true, '');
        console.log(
          '[label-agent] Imagenes impresas (cola #' +
            idCola +
            ', ' +
            detectado.etiquetas.length +
            ' etiqueta(s) GDI)'
        );
        return;
      }

      const jobs = splitPplaJobs(buffer);
      await printRaw(buffer);
      await confirmJob(idCola, true, '');
      const qty = job.cantidad_etiquetas || '?';
      console.log(
        '[label-agent] Etiquetas impresas RAW (cola #' +
          idCola +
          ', cantidad ' +
          qty +
          ', ' +
          buffer.length +
          ' bytes, ' +
          jobs.length +
          ' etiqueta(s) en 1 job)'
      );
    } catch (err) {
      const msg = err && err.message ? err.message : String(err);
      console.error('[label-agent] Error:', msg);
      try {
        await confirmJob(idCola, false, msg);
      } catch (e) {}
      // Si fallo la impresion, lo sacamos de la lista para permitir reintento.
      jobsVistos.delete(idCola);
    }
  } finally {
    procesando = false;
  }
}

async function loop() {
  try {
    await processOne();
  } catch (err) {
    console.error('[label-agent] Poll error:', err.message || err);
  }
  setTimeout(loop, pollIntervalMs);
}

(async function main() {
  console.log('[label-agent] Iniciado (impresion RAW via Python/win32print)');
  console.log('[label-agent] Servidor:', serverUrl, '(IPv4 local:', pickLanIPv4() + ')');
  console.log('[label-agent] Destino:', destino);
  console.log('[label-agent] Impresora:', printerName);
  console.log('[label-agent] Python:', resolvePythonExecutable());

  const printers = await listPrinters();
  if (printers.length && !printers.includes(printerName)) {
    console.warn('[label-agent] ADVERTENCIA: impresora no encontrada. Disponibles:', printers.join(', '));
  }

  loop();
})();
