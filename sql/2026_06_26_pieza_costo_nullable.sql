-- Permite costo NULL en piezas cuyo precio se define por grilla de variantes.
-- Idempotente: solo ejecuta el ALTER si la columna sigue siendo NOT NULL.
SET @db = DATABASE();
SET @sql = IF(
    (SELECT IS_NULLABLE FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'piezas' AND COLUMN_NAME = 'costo') = 'NO',
    'ALTER TABLE piezas MODIFY COLUMN costo DECIMAL(12,2) NULL DEFAULT NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
