-- Descuento global para insumos en punto de venta (independiente del descuento del cliente).
-- Ejecutar una sola vez en la base de datos de la aplicacion.

INSERT INTO configuracion_general (clave, valor, tipo, descripcion, fecha_actualizacion)
SELECT 'descuento_insumos_mostrador', '0.00', 'DECIMAL',
       'Descuento aplicado a lineas insumo en POS; no usa el descuento del cliente.',
       NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM configuracion_general WHERE clave = 'descuento_insumos_mostrador'
);
