-- Idempotente: la columna puede existir si el dump/VPS ya la tenia.
SET @db = DATABASE();

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'piezas' AND COLUMN_NAME = 'precio_por_gramo') = 0,
    'ALTER TABLE piezas ADD COLUMN precio_por_gramo DECIMAL(10,4) NULL AFTER peso_gr',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
