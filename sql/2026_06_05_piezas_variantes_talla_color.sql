-- Variantes por unidad de stock (talla en anillos / color) y flag de familia que usa talla.
-- Idempotente: ejecutar una sola vez en la base de datos de la aplicacion.

SET @db = DATABASE();

-- familias.usa_talla: marca las familias de anillos (habilita la opcion talla).
SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'familias' AND COLUMN_NAME = 'usa_talla') = 0,
    'ALTER TABLE familias ADD COLUMN usa_talla TINYINT(1) NOT NULL DEFAULT 0',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- piezas_stock.variante_tipo: ninguna / talla / color (por unidad).
SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'piezas_stock' AND COLUMN_NAME = 'variante_tipo') = 0,
    "ALTER TABLE piezas_stock ADD COLUMN variante_tipo ENUM('ninguna','talla','color') NOT NULL DEFAULT 'ninguna'",
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- piezas_stock.variante_valor: valor de la talla (ej. 7, "Ajustable") o color (ej. Rosa).
SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'piezas_stock' AND COLUMN_NAME = 'variante_valor') = 0,
    'ALTER TABLE piezas_stock ADD COLUMN variante_valor VARCHAR(40) NULL DEFAULT NULL AFTER variante_tipo',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
