const fs = require('fs');
const path = require('path');
const os = require('os');
const { execFile } = require('child_process');

const configPath = path.join(__dirname, 'config.json');
const printScriptPath = path.join(__dirname, 'print_raw.py');

if (!fs.existsSync(configPath) || !fs.existsSync(printScriptPath)) {
  console.error('Faltan config.json o print_raw.py');
  process.exit(1);
}

const config = JSON.parse(fs.readFileSync(configPath, 'utf8'));
const printerName = String(config.printerName || 'Argox OS-2140 PPLA');
const testPayload = String(config.testPayload || 'ppla_win32').toLowerCase();
const pythonPath = String(config.pythonPath || '').trim();

function resolvePythonExecutable() {
  if (pythonPath) return pythonPath;
  if (process.env.JOYERIA_PYTHON) return process.env.JOYERIA_PYTHON;
  return 'py';
}

/** Mismo payload que test.py (formato 1211... legible en Argox PPLA). */
function buildTestSampleWin32() {
  return (
    '\x02L\r' +
    '121100000500100PRUEBA ARGOX PPLA OK\r' +
    '121100001500100ING. EN SISTEMAS\r' +
    'E\r'
  );
}

function buildTestSample(style) {
  if (style === 'ppla_win32' || style === 'win32') {
    return buildTestSampleWin32();
  }
  if (style === 'ppla_nope') {
    return '\x02L\r' + 'H10\r' + 'D22\r' + 'A50,50,0,3,1,1,N,0,"TEST NO PE"\r' + 'Q0001\r' + 'E\r';
  }
  if (style === 'ppla_classic') {
    return (
      '\x02L\r' +
      'H10\r' +
      'D22\r' +
      'A50,50,1,4,0,1,1,N,0,"CLASSIC"\r' +
      'Q0001\r' +
      'E\r'
    );
  }
  return (
    '\x02L\r' +
    'C0719\r' +
    'D22\r' +
    'PE\r' +
    'H10\r' +
    'A50,50,0,3,1,1,N,0,"TEST JOYERIA"\r' +
    'Q0001\r' +
    'E\r'
  );
}

const sample = buildTestSample(testPayload);
const tempFile = path.join(os.tmpdir(), `joyeria-etiqueta-test-${Date.now()}.bin`);
fs.writeFileSync(tempFile, Buffer.from(sample, 'latin1'));

const exe = resolvePythonExecutable();
const pyArgs = exe === 'py' ? ['-3', printScriptPath] : [printScriptPath];
pyArgs.push('--printer', printerName, '--file', tempFile);

console.log('[test-print] Enviando etiqueta de prueba a:', printerName, '| testPayload=', testPayload);
console.log('[test-print] Payload:\n' + sample.replace(/\r/g, '\\r\n'));

execFile(exe, pyArgs, { windowsHide: true }, (err, stdout, stderr) => {
  try {
    fs.unlinkSync(tempFile);
  } catch (e) {
    // ignorar
  }
  if (err) {
    console.error('[test-print] Error:', [stderr, stdout, err.message].filter(Boolean).join(' '));
    console.error('[test-print] Instala dependencias: py -3 -m pip install -r requirements.txt');
    process.exit(1);
  }
  console.log('[test-print] Enviado OK (' + String(stdout || '').trim() + '). Revisa la impresora.');
});
