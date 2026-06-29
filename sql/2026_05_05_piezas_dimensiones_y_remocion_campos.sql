-- Idempotente: en VPS ya migrado puede no existir precio_mano_obra / quilates.
SET @db = DATABASE();

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'piezas' AND COLUMN_NAME = 'precio_mano_obra') > 0,
    'ALTER TABLE piezas DROP COLUMN precio_mano_obra',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'piezas' AND COLUMN_NAME = 'quilates') > 0,
    'ALTER TABLE piezas DROP COLUMN quilates',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'piezas' AND COLUMN_NAME = 'largo') = 0,
    'ALTER TABLE piezas ADD COLUMN largo VARCHAR(50) NULL AFTER precio_por_gramo',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'piezas' AND COLUMN_NAME = 'ancho') = 0,
    'ALTER TABLE piezas ADD COLUMN ancho VARCHAR(50) NULL AFTER largo',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
