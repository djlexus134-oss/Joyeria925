-- Normalizacion de categorias de insumos + aumento_pct
-- Ejecutar una sola vez sobre la BD de la app.

SET @schema := DATABASE();

-- ---------------------------------------------------------------------------
-- 1) Catalogo de categorias de insumos
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS insumo_categorias (
    id_categoria INT NOT NULL AUTO_INCREMENT,
    nombre VARCHAR(80) NOT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    fecha_alta DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_categoria),
    UNIQUE KEY uq_insumo_categorias_nombre (nombre),
    KEY idx_insumo_categorias_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 2) FK en insumos hacia categoria (manteniendo la columna legacy insumos.categoria)
-- ---------------------------------------------------------------------------
ALTER TABLE insumos
    ADD COLUMN IF NOT EXISTS id_categoria_FK INT NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS aumento_pct DECIMAL(7,2) NULL DEFAULT NULL,
    ADD KEY IF NOT EXISTS idx_insumos_id_categoria (id_categoria_FK);

-- Agregar constraint FK si no existe ya (MariaDB no soporta IF NOT EXISTS en FK)
SELECT CONSTRAINT_NAME
INTO @fk_cat
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA COLLATE utf8_general_ci = @schema COLLATE utf8_general_ci
  AND TABLE_NAME = 'insumos'
  AND COLUMN_NAME = 'id_categoria_FK'
  AND REFERENCED_TABLE_NAME IS NOT NULL
LIMIT 1;

SET @add_fk_sql := IF(
    @fk_cat IS NULL,
    'ALTER TABLE insumos
        ADD CONSTRAINT fk_insumos_categoria
            FOREIGN KEY (id_categoria_FK) REFERENCES insumo_categorias (id_categoria)
            ON UPDATE CASCADE ON DELETE RESTRICT',
    'SELECT 1'
);

PREPARE add_fk FROM @add_fk_sql;
EXECUTE add_fk;
DEALLOCATE PREPARE add_fk;

-- ---------------------------------------------------------------------------
-- 3) Migracion inicial: insumos.categoria -> insumo_categorias + insumos.id_categoria_FK
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO insumo_categorias (nombre)
SELECT DISTINCT TRIM(categoria) AS nombre
FROM insumos
WHERE categoria IS NOT NULL AND TRIM(categoria) <> '';

UPDATE insumos i
INNER JOIN insumo_categorias c ON c.nombre = TRIM(i.categoria)
SET i.id_categoria_FK = c.id_categoria
WHERE i.id_categoria_FK IS NULL
  AND i.categoria IS NOT NULL
  AND TRIM(i.categoria) <> '';

