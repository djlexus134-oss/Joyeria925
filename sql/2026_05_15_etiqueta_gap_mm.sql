-- Alineado con driver Argox: 60x10 mm, gap 3 mm, sensor espacios (no duplicar en RAW PPLA)
INSERT INTO configuracion_general (clave, valor, tipo, descripcion, fecha_actualizacion)
VALUES
    ('etiqueta_gap_mm', '3', 'STRING', 'Referencia gap driver mm (no se envia en RAW)', NOW()),
    ('etiqueta_ala_mm', '17', 'INT', 'Ancho pad izquierdo mm', NOW()),
    ('etiqueta_media_mm', '17', 'INT', 'Ancho pad derecho mm (barras)', NOW()),
    ('etiqueta_cola_mm', '26', 'INT', 'Largo cola mm', NOW()),
    ('etiqueta_alto_cola_mm', '10', 'INT', 'Alto cola mm', NOW())
ON DUPLICATE KEY UPDATE
    valor = VALUES(valor),
    descripcion = VALUES(descripcion),
    fecha_actualizacion = NOW();

UPDATE configuracion_general SET valor = '60' WHERE clave = 'etiqueta_ancho_mm';
UPDATE configuracion_general SET valor = '10' WHERE clave = 'etiqueta_alto_mm';
UPDATE configuracion_general SET valor = '3' WHERE clave = 'etiqueta_gap_mm';
UPDATE configuracion_general SET valor = '17' WHERE clave = 'etiqueta_ala_mm';
UPDATE configuracion_general SET valor = '17' WHERE clave = 'etiqueta_media_mm';
UPDATE configuracion_general SET valor = '26' WHERE clave = 'etiqueta_cola_mm';
UPDATE configuracion_general SET valor = '0' WHERE clave IN ('etiqueta_offset_x', 'etiqueta_offset_y');
