-- Alcance global: promoción aplicable a todo el catálogo (todas las familias).
-- Ejecutar en la base de datos joyeria.

SET @db := DATABASE();

SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'promociones' AND COLUMN_NAME = 'aplica_todas_familias') = 0,
    'ALTER TABLE promociones ADD COLUMN aplica_todas_familias TINYINT(1) NOT NULL DEFAULT 0 AFTER id_familia_FK',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
