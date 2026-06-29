-- Configuraciones para gestión de piezas
-- Insertar configuración de tienda por defecto
INSERT INTO configuracion_general (clave, valor, tipo, descripcion, fecha_actualizacion)
VALUES ('id_tienda_default', '1', 'INT', 'ID de la tienda por defecto para nuevas piezas', NOW())
ON DUPLICATE KEY UPDATE valor = VALUES(valor), fecha_actualizacion = NOW();

-- Insertar configuración de porcentaje de margen por defecto
INSERT INTO configuracion_general (clave, valor, tipo, descripcion, fecha_actualizacion)
VALUES ('markup_pct_default', '10', 'DECIMAL', 'Porcentaje de margen aplicado por defecto al generar stock inicial de piezas', NOW())
ON DUPLICATE KEY UPDATE valor = VALUES(valor), fecha_actualizacion = NOW();
