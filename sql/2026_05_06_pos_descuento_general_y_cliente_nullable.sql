-- Configuracion para POS: descuento general mostrador y cliente opcional en ventas.
-- Ejecutar una sola vez en la base de datos de la aplicacion.

SET @schema := DATABASE();

-- 1) Parametro global para descuento general cuando no hay cliente
INSERT INTO configuracion_general (clave, valor, tipo, descripcion, fecha_actualizacion)
SELECT 'descuento_general_mostrador', '0.00', 'DECIMAL',
       'Descuento general aplicado en punto de venta cuando no existe descuento de cliente.',
       NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM configuracion_general WHERE clave = 'descuento_general_mostrador'
);

-- 2) Ventas: permitir cliente opcional para ticket de mostrador
SELECT IS_NULLABLE
INTO @ventas_cliente_nullable
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA COLLATE utf8_general_ci = @schema COLLATE utf8_general_ci
  AND TABLE_NAME = 'ventas'
  AND COLUMN_NAME = 'id_cliente_FK'
LIMIT 1;

SET @sql_alter_ventas_cliente := IF(
    @ventas_cliente_nullable = 'YES',
    'SELECT 1',
    'ALTER TABLE ventas MODIFY id_cliente_FK INT NULL'
);

PREPARE stmt_alter_ventas_cliente FROM @sql_alter_ventas_cliente;
EXECUTE stmt_alter_ventas_cliente;
DEALLOCATE PREPARE stmt_alter_ventas_cliente;

-- 3) Compatibilidad venta_detalle para POS (instancias antiguas sin columnas nuevas)
SELECT COUNT(*)
INTO @vd_has_cantidad
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA COLLATE utf8_general_ci = @schema COLLATE utf8_general_ci
  AND TABLE_NAME = 'venta_detalle'
  AND COLUMN_NAME = 'cantidad';

SET @sql_add_vd_cantidad := IF(
    @vd_has_cantidad > 0,
    'SELECT 1',
    'ALTER TABLE venta_detalle ADD COLUMN cantidad DECIMAL(12,3) NOT NULL DEFAULT 1.000'
);
PREPARE stmt_add_vd_cantidad FROM @sql_add_vd_cantidad;
EXECUTE stmt_add_vd_cantidad;
DEALLOCATE PREPARE stmt_add_vd_cantidad;

SELECT COUNT(*)
INTO @vd_has_precio_unitario
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA COLLATE utf8_general_ci = @schema COLLATE utf8_general_ci
  AND TABLE_NAME = 'venta_detalle'
  AND COLUMN_NAME = 'precio_unitario';

SET @sql_add_vd_precio_unitario := IF(
    @vd_has_precio_unitario > 0,
    'SELECT 1',
    'ALTER TABLE venta_detalle ADD COLUMN precio_unitario DECIMAL(12,2) NOT NULL DEFAULT 0.01'
);
PREPARE stmt_add_vd_precio_unitario FROM @sql_add_vd_precio_unitario;
EXECUTE stmt_add_vd_precio_unitario;
DEALLOCATE PREPARE stmt_add_vd_precio_unitario;

SELECT COUNT(*)
INTO @vd_has_subtotal
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA COLLATE utf8_general_ci = @schema COLLATE utf8_general_ci
  AND TABLE_NAME = 'venta_detalle'
  AND COLUMN_NAME = 'subtotal';

SET @sql_add_vd_subtotal := IF(
    @vd_has_subtotal > 0,
    'SELECT 1',
    'ALTER TABLE venta_detalle ADD COLUMN subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.01'
);
PREPARE stmt_add_vd_subtotal FROM @sql_add_vd_subtotal;
EXECUTE stmt_add_vd_subtotal;
DEALLOCATE PREPARE stmt_add_vd_subtotal;

SELECT COUNT(*)
INTO @vd_has_tipo_linea
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA COLLATE utf8_general_ci = @schema COLLATE utf8_general_ci
  AND TABLE_NAME = 'venta_detalle'
  AND COLUMN_NAME = 'tipo_linea';

SET @sql_add_vd_tipo_linea := IF(
    @vd_has_tipo_linea > 0,
    'SELECT 1',
    'ALTER TABLE venta_detalle ADD COLUMN tipo_linea ENUM(''joya'', ''insumo'') NOT NULL DEFAULT ''joya'''
);
PREPARE stmt_add_vd_tipo_linea FROM @sql_add_vd_tipo_linea;
EXECUTE stmt_add_vd_tipo_linea;
DEALLOCATE PREPARE stmt_add_vd_tipo_linea;

SELECT COUNT(*)
INTO @vd_has_id_insumo
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA COLLATE utf8_general_ci = @schema COLLATE utf8_general_ci
  AND TABLE_NAME = 'venta_detalle'
  AND COLUMN_NAME = 'id_insumo_FK';

SET @sql_add_vd_id_insumo := IF(
    @vd_has_id_insumo > 0,
    'SELECT 1',
    'ALTER TABLE venta_detalle ADD COLUMN id_insumo_FK INT NULL DEFAULT NULL'
);
PREPARE stmt_add_vd_id_insumo FROM @sql_add_vd_id_insumo;
EXECUTE stmt_add_vd_id_insumo;
DEALLOCATE PREPARE stmt_add_vd_id_insumo;

SELECT COUNT(*)
INTO @vd_has_id_tienda
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA COLLATE utf8_general_ci = @schema COLLATE utf8_general_ci
  AND TABLE_NAME = 'venta_detalle'
  AND COLUMN_NAME = 'id_tienda_FK';

SET @sql_add_vd_id_tienda := IF(
    @vd_has_id_tienda > 0,
    'SELECT 1',
    'ALTER TABLE venta_detalle ADD COLUMN id_tienda_FK INT NULL DEFAULT NULL'
);
PREPARE stmt_add_vd_id_tienda FROM @sql_add_vd_id_tienda;
EXECUTE stmt_add_vd_id_tienda;
DEALLOCATE PREPARE stmt_add_vd_id_tienda;

-- En lineas de insumo, id_pieza_stock_FK debe permitir NULL.
SELECT IS_NULLABLE
INTO @vd_id_pieza_nullable
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA COLLATE utf8_general_ci = @schema COLLATE utf8_general_ci
  AND TABLE_NAME = 'venta_detalle'
  AND COLUMN_NAME = 'id_pieza_stock_FK'
LIMIT 1;

SET @sql_vd_id_pieza_nullable := IF(
    @vd_id_pieza_nullable = 'YES',
    'SELECT 1',
    'ALTER TABLE venta_detalle MODIFY id_pieza_stock_FK INT NULL'
);
PREPARE stmt_vd_id_pieza_nullable FROM @sql_vd_id_pieza_nullable;
EXECUTE stmt_vd_id_pieza_nullable;
DEALLOCATE PREPARE stmt_vd_id_pieza_nullable;
