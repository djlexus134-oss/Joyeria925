-- Variantes de dos ejes (talla + color) por unidad de stock.
-- Idempotente: ejecutar una sola vez en la base de datos de la aplicacion.

SET @db = DATABASE();

-- piezas_stock.variante_talla
SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'piezas_stock' AND COLUMN_NAME = 'variante_talla') = 0,
    'ALTER TABLE piezas_stock ADD COLUMN variante_talla VARCHAR(40) NULL DEFAULT NULL AFTER variante_valor',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- piezas_stock.variante_color
SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'piezas_stock' AND COLUMN_NAME = 'variante_color') = 0,
    'ALTER TABLE piezas_stock ADD COLUMN variante_color VARCHAR(40) NULL DEFAULT NULL AFTER variante_talla',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Migrar datos legacy variante_tipo / variante_valor
UPDATE piezas_stock
SET variante_talla = TRIM(variante_valor)
WHERE variante_tipo = 'talla'
  AND variante_valor IS NOT NULL
  AND TRIM(variante_valor) <> ''
  AND (variante_talla IS NULL OR TRIM(variante_talla) = '');

UPDATE piezas_stock
SET variante_color = TRIM(variante_valor)
WHERE variante_tipo = 'color'
  AND variante_valor IS NOT NULL
  AND TRIM(variante_valor) <> ''
  AND (variante_color IS NULL OR TRIM(variante_color) = '');
