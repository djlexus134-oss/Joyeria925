-- Reasigna referencias de tiendas inactivas/otras hacia la tienda activa "Joyeria Centro".
-- Fusiona insumo_existencia cuando ya existe fila (insumo, tienda destino).

START TRANSACTION;

SELECT id_tienda INTO @id_keep
FROM tiendas
WHERE activo = 1
  AND LOWER(TRIM(nom_tienda)) = 'joyeria centro'
LIMIT 1;

SET @sql_abort_tienda := IF(
    @id_keep IS NULL,
    'SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT = ''No existe tienda activa con nombre exacto Joyeria Centro''',
    'SELECT 1'
);
PREPARE stmt_abort_tienda FROM @sql_abort_tienda;
EXECUTE stmt_abort_tienda;
DEALLOCATE PREPARE stmt_abort_tienda;

DROP TEMPORARY TABLE IF EXISTS tmp_tienda_old;
CREATE TEMPORARY TABLE tmp_tienda_old AS
SELECT id_tienda
FROM tiendas
WHERE id_tienda <> @id_keep;

-- Piezas
UPDATE piezas p
INNER JOIN tmp_tienda_old t ON t.id_tienda = p.id_tienda_FK
SET p.id_tienda_FK = @id_keep;

-- Venta detalle (insumos)
UPDATE venta_detalle vd
INNER JOIN tmp_tienda_old t ON t.id_tienda = vd.id_tienda_FK
SET vd.id_tienda_FK = @id_keep
WHERE vd.id_tienda_FK IS NOT NULL;

-- Movimientos inventario (columna si existe)
SET @has_mov_inv := (
    SELECT COUNT(*)
    FROM information_schema.tables
    WHERE table_schema = DATABASE()
      AND table_name = 'movimientos_inventario'
);
SET @has_mov_col := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'movimientos_inventario'
      AND column_name = 'id_tienda_origen_FK'
);
SET @sql_mov := IF(
    @has_mov_inv > 0 AND @has_mov_col > 0,
    'UPDATE movimientos_inventario mi
     INNER JOIN tmp_tienda_old t ON t.id_tienda = mi.id_tienda_origen_FK
     SET mi.id_tienda_origen_FK = @id_keep',
    'SELECT 1'
);
PREPARE stmt_mov FROM @sql_mov;
EXECUTE stmt_mov;
DEALLOCATE PREPARE stmt_mov;

-- Cola impresion
SET @has_cola := (
    SELECT COUNT(*)
    FROM information_schema.tables
    WHERE table_schema = DATABASE()
      AND table_name = 'cola_impresion'
);
SET @sql_cola := IF(
    @has_cola > 0,
    'UPDATE cola_impresion ci
     INNER JOIN tmp_tienda_old t ON t.id_tienda = ci.id_tienda_FK
     SET ci.id_tienda_FK = @id_keep
     WHERE ci.id_tienda_FK IS NOT NULL',
    'SELECT 1'
);
PREPARE stmt_cola FROM @sql_cola;
EXECUTE stmt_cola;
DEALLOCATE PREPARE stmt_cola;

-- Fusionar insumo_existencia: sumar cantidades al destino y eliminar origen duplicado.
UPDATE insumo_existencia ie_dest
INNER JOIN insumo_existencia ie_src
    ON ie_src.id_insumo_FK = ie_dest.id_insumo_FK
   AND ie_dest.id_tienda_FK = @id_keep
INNER JOIN tmp_tienda_old t ON t.id_tienda = ie_src.id_tienda_FK
SET ie_dest.cantidad = ie_dest.cantidad + ie_src.cantidad,
    ie_dest.fecha_actualizado = NOW();

DELETE ie_src FROM insumo_existencia ie_src
INNER JOIN tmp_tienda_old t ON t.id_tienda = ie_src.id_tienda_FK
INNER JOIN insumo_existencia ie_dest
    ON ie_dest.id_insumo_FK = ie_src.id_insumo_FK
   AND ie_dest.id_tienda_FK = @id_keep;

UPDATE insumo_existencia ie
INNER JOIN tmp_tienda_old t ON t.id_tienda = ie.id_tienda_FK
SET ie.id_tienda_FK = @id_keep;

-- Configuracion
UPDATE configuracion_general
SET valor = CAST(@id_keep AS CHAR),
    fecha_actualizacion = NOW()
WHERE clave IN ('id_tienda_default', 'impresion_id_tienda_caja');

COMMIT;
