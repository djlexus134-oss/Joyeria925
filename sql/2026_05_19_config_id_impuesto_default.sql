-- Impuesto preseleccionado en captura (POS, apartados, ventas, historico de impuestos).
-- Ejecutar una sola vez. El valor inicial es el impuesto con menor id (ajustable en configuracion_general).

INSERT INTO configuracion_general (clave, valor, tipo, descripcion, fecha_actualizacion)
SELECT
    'id_impuesto_default',
    CAST(COALESCE((SELECT MIN(i.id_impuesto) FROM impuestos i), 0) AS CHAR),
    'INT',
    'ID de impuesto usado como valor inicial en formularios con selector de impuesto.',
    NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM configuracion_general cg WHERE cg.clave = 'id_impuesto_default'
);
