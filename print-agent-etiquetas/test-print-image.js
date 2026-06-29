/**
 * Test directo: genera un PNG con CODE128 + texto y lo manda a la impresora
 * via print_image.py (modo GDI). Sirve para verificar que el driver Argox
 * imprime correctamente sin pasar por el backend / cola.
 *
 * Uso:  node test-print-image.js
 */
const fs = require('fs');
const path = require('path');
const os = require('os');
const { execFile } = require('child_process');

const configPath = path.join(__dirname, 'config.json');
const config = JSON.parse(fs.readFileSync(configPath, 'utf8'));
const printerName = String(config.printerName || 'Argox OS-2140 PPLA');
const pythonPath = String(config.pythonPath || '').trim();

function resolvePythonExecutable() {
  return pythonPath || process.env.JOYERIA_PYTHON || 'py';
}

function runPython(scriptPath, args) {
  const exe = resolvePythonExecutable();
  const baseArgs = exe === 'py' ? ['-3', scriptPath] : [scriptPath];
  return new Promise((resolve, reject) => {
    execFile(exe, baseArgs.concat(args), { windowsHide: true }, (err, stdout, stderr) => {
      if (err) {
        reject(new Error([stderr, stdout, err.message].filter(Boolean).join(' ').trim()));
        return;
      }
      resolve(String(stdout || '').trim());
    });
  });
}

// PNG minimo (1 etiqueta blanca con texto): 480 x 80 px (60 x 10 mm @ 203 dpi).
// Para test rapido usamos un PNG sintetico de 480x80 con barras simples.
function generarPngTest() {
  // PNG header + IHDR + IDAT + IEND minimo. Mejor uso pngjs? Sin libs, manual.
  // Como Python tiene PIL, mejor delegamos a un mini-script Python.
  return null;
}

(async function main() {
  console.log('[test-print-image] Generando PNG de prueba via Python...');
  const tempPng = path.join(os.tmpdir(), `test-etiqueta-${Date.now()}.png`);
  const tempPy = path.join(os.tmpdir(), `gen-test-png-${Date.now()}.py`);

  // Layout objetivo (cinta troquelada PAD-PAD-COLA, cola NO se ve):
  //   60 x 10 mm @ 203 dpi -> 480 x 80 px
  //   PAD IZQ  (0-17 mm  = 0-136 px)   : BARCODE + "6597/002" debajo
  //   PAD MED  (17-34 mm = 136-272 px) : "$ 1,080" centrado
  //   COLA     (34-60 mm = 272-480 px) : VACIA a proposito
  fs.writeFileSync(
    tempPy,
    [
      'from PIL import Image, ImageDraw, ImageFont',
      'import sys, random',
      'W, H = 480, 80',
      'PAD_IZQ_FIN = 136',
      'PAD_MED_INI, PAD_MED_FIN = 136, 272',
      'img = Image.new("RGB", (W, H), "white")',
      'd = ImageDraw.Draw(img)',
      'def font(sz):',
      '    try:',
      '        return ImageFont.truetype("C:/Windows/Fonts/arialbd.ttf", sz)',
      '    except Exception:',
      '        return ImageFont.load_default()',
      'def medir(text, f):',
      '    try:',
      '        b = d.textbbox((0,0), text, font=f); return b[2]-b[0], b[3]-b[1]',
      '    except Exception:',
      '        return f.getsize(text)',
      '# --- PAD IZQ: barcode pseudo + codigo (con quiet zone izquierda) ---',
      'SHIFT = 16  # ~2 mm: corre todo a la derecha',
      'QUIET = 20 + SHIFT  # margen izq del barcode',
      'bx, by, bw, bh = QUIET, 0, PAD_IZQ_FIN - QUIET - 8 + SHIFT, 50',
      'random.seed(42)',
      'x = bx',
      'idx = 0',
      'while x < bx + bw:',
      '    wbar = random.choice([1, 1, 2, 2, 3])',
      '    if idx % 2 == 0:',
      '        d.rectangle([x, by, x + wbar - 1, by + bh], fill="black")',
      '    x += wbar',
      '    idx += 1',
      '# codigo auxiliar mas grande, centrado en el pad izq + shift',
      'fc = font(17)',
      'cod = "6597/002"',
      'cw, ch = medir(cod, fc)',
      'd.text((PAD_IZQ_FIN // 2 + SHIFT - cw // 2, 54), cod, fill="black", font=fc)',
      '# --- PAD MEDIO: precio centrado (mas grande, shift a la derecha) ---',
      'fp = font(38)',
      'precio = "$ 1,080"',
      'tw, th = medir(precio, fp)',
      'cx = (PAD_MED_INI + PAD_MED_FIN) // 2 + SHIFT',
      'd.text((cx - tw // 2, (H - th) // 2 - 4), precio, fill="black", font=fp)',
      '# --- COLA: vacia ---',
      'img.save(sys.argv[1], "PNG", optimize=True)',
      'print("OK", sys.argv[1])',
    ].join('\n')
  );

  try {
    const out = await runPython(tempPy, [tempPng]);
    console.log('[test-print-image] PNG generado:', out);
    console.log('[test-print-image] Tamano:', fs.statSync(tempPng).size, 'bytes');

    console.log('[test-print-image] Enviando a impresora "' + printerName + '"...');
    const printScript = path.join(__dirname, 'print_image.py');
    const result = await runPython(printScript, ['--printer', printerName, '--file', tempPng]);
    console.log('[test-print-image] Resultado:', result);
  } catch (err) {
    console.error('[test-print-image] ERROR:', err.message);
    process.exit(1);
  } finally {
    try { fs.unlinkSync(tempPng); } catch (e) {}
    try { fs.unlinkSync(tempPy); } catch (e) {}
  }
})();
