-- Catalogo de tipos/valores de variante y FK en piezas_stock.
-- Idempotente: ejecutar una sola vez en la base de datos de la aplicacion.

SET @db = DATABASE();

-- ---------------------------------------------------------------------------
-- 1) Tablas de catalogo
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS variante_tipos (
    id_variante_tipo INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre VARCHAR(50) NOT NULL,
    slug VARCHAR(40) NOT NULL,
    es_talla TINYINT(1) NOT NULL DEFAULT 0,
    orden INT NOT NULL DEFAULT 0,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id_variante_tipo),
    UNIQUE KEY uk_variante_tipos_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS variante_valores (
    id_variante_valor INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_variante_tipo_FK INT UNSIGNED NOT NULL,
    valor VARCHAR(40) NOT NULL,
    orden INT NOT NULL DEFAULT 0,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id_variante_valor),
    UNIQUE KEY uk_variante_valores_tipo_valor (id_variante_tipo_FK, valor),
    KEY idx_variante_valores_tipo (id_variante_tipo_FK),
    CONSTRAINT fk_variante_valores_tipo
        FOREIGN KEY (id_variante_tipo_FK) REFERENCES variante_tipos (id_variante_tipo)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 2) Columnas FK en piezas_stock
-- ---------------------------------------------------------------------------
SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'piezas_stock' AND COLUMN_NAME = 'variante_valor1_id') = 0,
    'ALTER TABLE piezas_stock ADD COLUMN variante_valor1_id INT UNSIGNED NULL DEFAULT NULL AFTER variante_color',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'piezas_stock' AND COLUMN_NAME = 'variante_valor2_id') = 0,
    'ALTER TABLE piezas_stock ADD COLUMN variante_valor2_id INT UNSIGNED NULL DEFAULT NULL AFTER variante_valor1_id',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
     WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'piezas_stock' AND CONSTRAINT_NAME = 'fk_piezas_stock_variante_valor1') = 0,
    'ALTER TABLE piezas_stock ADD CONSTRAINT fk_piezas_stock_variante_valor1 FOREIGN KEY (variante_valor1_id) REFERENCES variante_valores (id_variante_valor) ON UPDATE CASCADE ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
     WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'piezas_stock' AND CONSTRAINT_NAME = 'fk_piezas_stock_variante_valor2') = 0,
    'ALTER TABLE piezas_stock ADD CONSTRAINT fk_piezas_stock_variante_valor2 FOREIGN KEY (variante_valor2_id) REFERENCES variante_valores (id_variante_valor) ON UPDATE CASCADE ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------------
-- 3) Seed tipos base
-- ---------------------------------------------------------------------------
INSERT INTO variante_tipos (nombre, slug, es_talla, orden, activo)
VALUES
    ('Talla', 'talla', 1, 1, 1),
    ('Color', 'color', 0, 2, 1)
ON DUPLICATE KEY UPDATE
    nombre = VALUES(nombre),
    es_talla = VALUES(es_talla),
    orden = VALUES(orden),
    activo = 1;

-- ---------------------------------------------------------------------------
-- 4) Seed valores desde stock existente
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO variante_valores (id_variante_tipo_FK, valor, orden, activo)
SELECT vt.id_variante_tipo, TRIM(ps.variante_talla), 0, 1
FROM piezas_stock ps
INNER JOIN variante_tipos vt ON vt.slug = 'talla'
WHERE ps.variante_talla IS NOT NULL
  AND TRIM(ps.variante_talla) <> '';

INSERT IGNORE INTO variante_valores (id_variante_tipo_FK, valor, orden, activo)
SELECT vt.id_variante_tipo, TRIM(ps.variante_color), 0, 1
FROM piezas_stock ps
INNER JOIN variante_tipos vt ON vt.slug = 'color'
WHERE ps.variante_color IS NOT NULL
  AND TRIM(ps.variante_color) <> '';

INSERT IGNORE INTO variante_valores (id_variante_tipo_FK, valor, orden, activo)
SELECT vt.id_variante_tipo, TRIM(ps.variante_valor), 0, 1
FROM piezas_stock ps
INNER JOIN variante_tipos vt ON vt.slug = 'talla'
WHERE ps.variante_tipo = 'talla'
  AND ps.variante_valor IS NOT NULL
  AND TRIM(ps.variante_valor) <> '';

INSERT IGNORE INTO variante_valores (id_variante_tipo_FK, valor, orden, activo)
SELECT vt.id_variante_tipo, TRIM(ps.variante_valor), 0, 1
FROM piezas_stock ps
INNER JOIN variante_tipos vt ON vt.slug = 'color'
WHERE ps.variante_tipo = 'color'
  AND ps.variante_valor IS NOT NULL
  AND TRIM(ps.variante_valor) <> '';

-- ---------------------------------------------------------------------------
-- 5) Backfill FK en piezas_stock
-- ---------------------------------------------------------------------------
UPDATE piezas_stock ps
INNER JOIN variante_tipos vt ON vt.slug = 'talla'
INNER JOIN variante_valores vv ON vv.id_variante_tipo_FK = vt.id_variante_tipo
    AND vv.valor = TRIM(ps.variante_talla)
SET ps.variante_valor1_id = vv.id_variante_valor
WHERE ps.variante_talla IS NOT NULL
  AND TRIM(ps.variante_talla) <> ''
  AND ps.variante_valor1_id IS NULL;

UPDATE piezas_stock ps
INNER JOIN variante_tipos vt ON vt.slug = 'color'
INNER JOIN variante_valores vv ON vv.id_variante_tipo_FK = vt.id_variante_tipo
    AND vv.valor = TRIM(ps.variante_color)
SET ps.variante_valor2_id = vv.id_variante_valor
WHERE ps.variante_color IS NOT NULL
  AND TRIM(ps.variante_color) <> ''
  AND ps.variante_valor2_id IS NULL;

UPDATE piezas_stock ps
INNER JOIN variante_tipos vt ON vt.slug = 'talla'
INNER JOIN variante_valores vv ON vv.id_variante_tipo_FK = vt.id_variante_tipo
    AND vv.valor = TRIM(ps.variante_valor)
SET ps.variante_valor1_id = vv.id_variante_valor
WHERE ps.variante_tipo = 'talla'
  AND ps.variante_valor IS NOT NULL
  AND TRIM(ps.variante_valor) <> ''
  AND ps.variante_valor1_id IS NULL;

UPDATE piezas_stock ps
INNER JOIN variante_tipos vt ON vt.slug = 'color'
INNER JOIN variante_valores vv ON vv.id_variante_tipo_FK = vt.id_variante_tipo
    AND vv.valor = TRIM(ps.variante_valor)
SET ps.variante_valor1_id = vv.id_variante_valor
WHERE ps.variante_tipo = 'color'
  AND ps.variante_valor IS NOT NULL
  AND TRIM(ps.variante_valor) <> ''
  AND ps.variante_valor1_id IS NULL
  AND ps.variante_valor2_id IS NULL;

-- Solo color en columna matriz (sin talla): mover a valor1 si valor2 quedo vacio
UPDATE piezas_stock ps
INNER JOIN variante_tipos vt ON vt.slug = 'color'
INNER JOIN variante_valores vv ON vv.id_variante_tipo_FK = vt.id_variante_tipo
    AND vv.valor = TRIM(ps.variante_color)
SET ps.variante_valor1_id = vv.id_variante_valor
WHERE ps.variante_talla IS NULL OR TRIM(IFNULL(ps.variante_talla, '')) = ''
  AND ps.variante_color IS NOT NULL
  AND TRIM(ps.variante_color) <> ''
  AND ps.variante_valor1_id IS NULL;

-- ---------------------------------------------------------------------------
-- 6) Permisos RBAC
-- ---------------------------------------------------------------------------
INSERT INTO permisos (nombre_permiso, descripcion, activo)
VALUES
    ('VARIANTE_LEER', 'Consultar catalogo de tipos y valores de variante', 1),
    ('VARIANTE_CREAR', 'Crear tipos y valores de variante', 1),
    ('VARIANTE_ACTUALIZAR', 'Editar tipos y valores de variante', 1),
    ('VARIANTE_BORRAR', 'Dar de baja tipos y valores de variante', 1)
ON DUPLICATE KEY UPDATE
    descripcion = VALUES(descripcion),
    activo = VALUES(activo);

INSERT IGNORE INTO rol_permiso (id_rol_FK, id_permiso_FK)
SELECT r.id_rol, p.id_permiso
FROM roles r
CROSS JOIN permisos p
WHERE UPPER(TRIM(r.nombre_rol)) IN ('ADMINISTRADOR', 'EMPLEADO')
  AND p.nombre_permiso IN ('VARIANTE_LEER', 'VARIANTE_CREAR', 'VARIANTE_ACTUALIZAR');

INSERT IGNORE INTO rol_permiso (id_rol_FK, id_permiso_FK)
SELECT r.id_rol, p.id_permiso
FROM roles r
CROSS JOIN permisos p
WHERE UPPER(TRIM(r.nombre_rol)) = 'ADMINISTRADOR'
  AND p.nombre_permiso = 'VARIANTE_BORRAR';
