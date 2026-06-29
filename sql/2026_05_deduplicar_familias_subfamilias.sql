-- Limpieza de duplicados por nombre en familias/sub_familia (MySQL 8+).
-- Criterio de duplicado: TRIM + LOWER(nombre), solo registros activos.
-- Mantiene integridad de referencias en piezas y promociones.

START TRANSACTION;

-- 1) Consolidar familias duplicadas (activas) hacia el menor id_familia.
DROP TEMPORARY TABLE IF EXISTS tmp_familia_merge;
CREATE TEMPORARY TABLE tmp_familia_merge AS
SELECT
    d.id_familia AS old_id_familia,
    k.keep_id_familia AS keep_id_familia
FROM familias d
INNER JOIN (
    SELECT
        LOWER(TRIM(nom_familia)) AS nom_norm,
        MIN(id_familia) AS keep_id_familia,
        COUNT(*) AS total
    FROM familias
    WHERE activo = 1
    GROUP BY LOWER(TRIM(nom_familia))
    HAVING COUNT(*) > 1
) k
    ON LOWER(TRIM(d.nom_familia)) = k.nom_norm
WHERE d.activo = 1
  AND d.id_familia <> k.keep_id_familia;

-- Reapuntar subfamilias a la familia canónica.
UPDATE sub_familia sf
INNER JOIN tmp_familia_merge m ON m.old_id_familia = sf.id_familia_FK
SET sf.id_familia_FK = m.keep_id_familia
WHERE sf.activo = 1;

-- Reapuntar promociones por familia (si aplica en tu tabla).
UPDATE promociones p
INNER JOIN tmp_familia_merge m ON m.old_id_familia = p.id_familia_FK
SET p.id_familia_FK = m.keep_id_familia;

-- Reapuntar catalogo_compra por familia si la tabla existe.
SET @has_catalogo_compra := (
    SELECT COUNT(*)
    FROM information_schema.tables
    WHERE table_schema = DATABASE()
      AND table_name = 'catalogo_compra'
);
SET @sql_cat_fam := IF(
    @has_catalogo_compra > 0,
    'UPDATE catalogo_compra cc
     INNER JOIN tmp_familia_merge m ON m.old_id_familia = cc.id_familia_FK
     SET cc.id_familia_FK = m.keep_id_familia',
    'SELECT 1'
);
PREPARE stmt_cat_fam FROM @sql_cat_fam;
EXECUTE stmt_cat_fam;
DEALLOCATE PREPARE stmt_cat_fam;

-- Marcar familias duplicadas como inactivas.
UPDATE familias f
INNER JOIN tmp_familia_merge m ON m.old_id_familia = f.id_familia
SET f.activo = 0,
    f.fecha_baja = NOW()
WHERE f.activo = 1;

-- 2) Consolidar subfamilias duplicadas por (familia canónica + nombre normalizado).
DROP TEMPORARY TABLE IF EXISTS tmp_subfamilia_merge;
CREATE TEMPORARY TABLE tmp_subfamilia_merge AS
SELECT
    d.id_sub_familia AS old_id_sub_familia,
    k.keep_id_sub_familia AS keep_id_sub_familia
FROM sub_familia d
INNER JOIN (
    SELECT
        id_familia_FK,
        LOWER(TRIM(nom_sub_familia)) AS nom_norm,
        MIN(id_sub_familia) AS keep_id_sub_familia,
        COUNT(*) AS total
    FROM sub_familia
    WHERE activo = 1
    GROUP BY id_familia_FK, LOWER(TRIM(nom_sub_familia))
    HAVING COUNT(*) > 1
) k
    ON d.id_familia_FK = k.id_familia_FK
   AND LOWER(TRIM(d.nom_sub_familia)) = k.nom_norm
WHERE d.activo = 1
  AND d.id_sub_familia <> k.keep_id_sub_familia;

-- Reapuntar piezas a la subfamilia canónica (activas e inactivas).
UPDATE piezas p
INNER JOIN tmp_subfamilia_merge m ON m.old_id_sub_familia = p.id_sub_familia_FK
SET p.id_sub_familia_FK = m.keep_id_sub_familia;

-- Reapuntar catalogo_compra por subfamilia si la tabla existe.
SET @sql_cat_sub := IF(
    @has_catalogo_compra > 0,
    'UPDATE catalogo_compra cc
     INNER JOIN tmp_subfamilia_merge m ON m.old_id_sub_familia = cc.id_sub_familia_FK
     SET cc.id_sub_familia_FK = m.keep_id_sub_familia',
    'SELECT 1'
);
PREPARE stmt_cat_sub FROM @sql_cat_sub;
EXECUTE stmt_cat_sub;
DEALLOCATE PREPARE stmt_cat_sub;

-- Reapuntar promociones por subfamilia (si aplica en tu tabla).
UPDATE promociones p
INNER JOIN tmp_subfamilia_merge m ON m.old_id_sub_familia = p.id_subfamilia_FK
SET p.id_subfamilia_FK = m.keep_id_sub_familia;

-- Marcar subfamilias duplicadas como inactivas.
UPDATE sub_familia sf
INNER JOIN tmp_subfamilia_merge m ON m.old_id_sub_familia = sf.id_sub_familia
SET sf.activo = 0,
    sf.fecha_baja = NOW()
WHERE sf.activo = 1;

COMMIT;

-- Verificación rápida posterior (opcional):
-- SELECT LOWER(TRIM(nom_familia)) AS nom_norm, COUNT(*) FROM familias WHERE activo = 1 GROUP BY LOWER(TRIM(nom_familia)) HAVING COUNT(*) > 1;
-- SELECT id_familia_FK, LOWER(TRIM(nom_sub_familia)) AS nom_norm, COUNT(*) FROM sub_familia WHERE activo = 1 GROUP BY id_familia_FK, LOWER(TRIM(nom_sub_familia)) HAVING COUNT(*) > 1;
