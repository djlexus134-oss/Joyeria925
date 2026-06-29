-- Ajuste de margen y ancho para tickets TM-T20 (evita corte en borde izquierdo)
INSERT INTO configuracion_general (clave, valor, tipo, descripcion, fecha_actualizacion)
VALUES
    ('ticket_margen_izquierdo', '40', 'INT', 'Margen izquierdo ESC/POS en puntos (GS L). TM-T20: 40 ~ 5mm', NOW())
ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion), fecha_actualizacion = NOW();

UPDATE configuracion_general
SET valor = '38', fecha_actualizacion = NOW()
WHERE clave = 'ticket_ancho_columnas' AND valor = '42';
