-- Forma de pago preseleccionada en captura (POS, gastos, apartados, devoluciones).
-- Ejecutar una sola vez. El valor inicial es la forma activa con menor id (ajustable en configuracion_general).

INSERT INTO configuracion_general (clave, valor, tipo, descripcion, fecha_actualizacion)
SELECT
    'id_forma_pago_default',
    CAST(COALESCE((SELECT MIN(fp.id_forma_pago) FROM forma_pago fp WHERE fp.activo = 1), 0) AS CHAR),
    'INT',
    'ID de forma_pago activa usada como valor inicial en formularios con forma de pago.',
    NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM configuracion_general cg WHERE cg.clave = 'id_forma_pago_default'
);
