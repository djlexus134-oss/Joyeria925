/**
 * Regenera etiqueta-prueba-ppla.bin (PPLA Argox, misma muestra que test-print ppla_classic).
 * Uso: node gen-etiqueta-prueba-ppla.js
 */
const fs = require('fs');
const path = require('path');

const out = path.join(__dirname, 'etiqueta-prueba-ppla.bin');
const sample =
  '\x02L\r' +
  'H10\r' +
  'D22\r' +
  'A50,50,1,4,0,1,1,N,0,"CLASSIC"\r' +
  'Q0001\r' +
  'E\r';

fs.writeFileSync(out, Buffer.from(sample, 'latin1'));
console.log('[gen] Creado:', out, '(' + Buffer.byteLength(sample, 'latin1') + ' bytes)');
