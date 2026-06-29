-- Umbral y porcentaje de descuento mayoreo (joyas a precio lista).
-- Ejecutar en la base de datos de la joyería.

INSERT INTO configuracion_general (clave, valor, tipo_dato, descripcion)
VALUES
    ('mayoreo_umbral_mxn', '6000.00', 'DECIMAL', 'Subtotal mínimo de joyas a precio lista para aplicar descuento mayoreo'),
    ('mayoreo_descuento_pct', '50.00', 'DECIMAL', 'Porcentaje de descuento mayoreo en joyas (insumos excluidos en POS)')
ON DUPLICATE KEY UPDATE
    descripcion = VALUES(descripcion);
