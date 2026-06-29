-- Descuento de cliente con decimales (evita truncamiento y alinea con POS/config).
-- Ejecutar una sola vez en la base de datos de la aplicacion.

SET @schema := DATABASE();

SELECT DATA_TYPE, COLUMN_TYPE
INTO @col_type, @col_full
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA COLLATE utf8_general_ci = @schema COLLATE utf8_general_ci
  AND TABLE_NAME = 'clientes'
  AND COLUMN_NAME = 'descuento_porcentaje'
LIMIT 1;

SET @sql_alter := IF(
    @col_type IS NULL,
    'SELECT 1',
    IF(
        @col_full LIKE 'decimal(5,2)%',
        'SELECT 1',
        'ALTER TABLE clientes MODIFY descuento_porcentaje DECIMAL(5,2) NULL DEFAULT NULL'
    )
);

PREPARE stmt_cliente_descuento FROM @sql_alter;
EXECUTE stmt_cliente_descuento;
DEALLOCATE PREPARE stmt_cliente_descuento;

SELECT DATA_TYPE, COLUMN_TYPE
INTO @promo_type, @promo_full
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA COLLATE utf8_general_ci = @schema COLLATE utf8_general_ci
  AND TABLE_NAME = 'promociones'
  AND COLUMN_NAME = 'porcentaje_descuento'
LIMIT 1;

SET @sql_alter_promo := IF(
    @promo_type IS NULL,
    'SELECT 1',
    IF(
        @promo_full LIKE 'decimal(5,2)%',
        'SELECT 1',
        'ALTER TABLE promociones MODIFY porcentaje_descuento DECIMAL(5,2) NOT NULL'
    )
);

PREPARE stmt_promo_descuento FROM @sql_alter_promo;
EXECUTE stmt_promo_descuento;
DEALLOCATE PREPARE stmt_promo_descuento;
