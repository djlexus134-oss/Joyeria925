-- Unifica impuestos duplicados por LOWER(TRIM(tipo_impuesto)).
-- Conserva el de mayor porcentaje; en empate MIN(id_impuesto).

START TRANSACTION;

DROP TEMPORARY TABLE IF EXISTS tmp_impuesto_merge;
CREATE TEMPORARY TABLE tmp_impuesto_merge AS
SELECT
    d.id_impuesto AS old_id_impuesto,
    k.keep_id_impuesto AS keep_id_impuesto
FROM impuestos d
INNER JOIN (
    SELECT
        LOWER(TRIM(tipo_impuesto)) AS nom_norm,
        SUBSTRING_INDEX(
            GROUP_CONCAT(
                id_impuesto
                ORDER BY COALESCE(porcentaje, 0) DESC, id_impuesto ASC
            ),
            ',',
            1
        ) AS keep_id_impuesto
    FROM impuestos
    GROUP BY LOWER(TRIM(tipo_impuesto))
    HAVING COUNT(*) > 1
) k ON LOWER(TRIM(d.tipo_impuesto)) = k.nom_norm
WHERE d.id_impuesto <> CAST(k.keep_id_impuesto AS UNSIGNED);

UPDATE ventas v
INNER JOIN tmp_impuesto_merge m ON m.old_id_impuesto = v.id_impuesto_FK
SET v.id_impuesto_FK = m.keep_id_impuesto;

SET @has_imp_hist := (
    SELECT COUNT(*)
    FROM information_schema.tables
    WHERE table_schema = DATABASE()
      AND table_name = 'impuestos_historico'
);
SET @sql_imp_hist := IF(
    @has_imp_hist > 0,
    'UPDATE impuestos_historico ih
     INNER JOIN tmp_impuesto_merge m ON m.old_id_impuesto = ih.id_impuesto_FK
     SET ih.id_impuesto_FK = m.keep_id_impuesto',
    'SELECT 1'
);
PREPARE stmt_imp_hist FROM @sql_imp_hist;
EXECUTE stmt_imp_hist;
DEALLOCATE PREPARE stmt_imp_hist;

UPDATE configuracion_general cg
INNER JOIN tmp_impuesto_merge m ON CAST(cg.valor AS UNSIGNED) = m.old_id_impuesto
SET cg.valor = CAST(m.keep_id_impuesto AS CHAR),
    cg.fecha_actualizacion = NOW()
WHERE cg.clave = 'id_impuesto_default';

DELETE i FROM impuestos i
INNER JOIN tmp_impuesto_merge m ON m.old_id_impuesto = i.id_impuesto;

COMMIT;
