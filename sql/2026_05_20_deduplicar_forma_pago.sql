-- Unifica forma_pago duplicadas por LOWER(TRIM(forma_pago)) entre activas.
-- Conserva MIN(id_forma_pago). Reapunta pagos/gastos y actualiza config.

START TRANSACTION;

DROP TEMPORARY TABLE IF EXISTS tmp_forma_pago_merge;
CREATE TEMPORARY TABLE tmp_forma_pago_merge AS
SELECT
    d.id_forma_pago AS old_id_forma_pago,
    k.keep_id_forma_pago AS keep_id_forma_pago
FROM forma_pago d
INNER JOIN (
    SELECT
        LOWER(TRIM(forma_pago)) AS nom_norm,
        MIN(id_forma_pago) AS keep_id_forma_pago,
        COUNT(*) AS total
    FROM forma_pago
    WHERE activo = 1
    GROUP BY LOWER(TRIM(forma_pago))
    HAVING COUNT(*) > 1
) k ON LOWER(TRIM(d.forma_pago)) = k.nom_norm
WHERE d.activo = 1
  AND d.id_forma_pago <> k.keep_id_forma_pago;

UPDATE venta_pagos vp
INNER JOIN tmp_forma_pago_merge m ON m.old_id_forma_pago = vp.id_forma_pago_FK
SET vp.id_forma_pago_FK = m.keep_id_forma_pago;

UPDATE gastos g
INNER JOIN tmp_forma_pago_merge m ON m.old_id_forma_pago = g.id_forma_pago_FK
SET g.id_forma_pago_FK = m.keep_id_forma_pago;

UPDATE apartado_pagos ap
INNER JOIN tmp_forma_pago_merge m ON m.old_id_forma_pago = ap.id_forma_pago_FK
SET ap.id_forma_pago_FK = m.keep_id_forma_pago;

UPDATE devoluciones dv
INNER JOIN tmp_forma_pago_merge m ON m.old_id_forma_pago = dv.id_forma_pago_FK
SET dv.id_forma_pago_FK = m.keep_id_forma_pago;

-- Si algún duplicado era efectivo, marcar el canónico como efectivo.
SET @has_es_efectivo := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'forma_pago'
      AND column_name = 'es_efectivo'
);
SET @sql_efectivo := IF(
    @has_es_efectivo > 0,
    'UPDATE forma_pago fp_keep
     INNER JOIN tmp_forma_pago_merge m ON m.keep_id_forma_pago = fp_keep.id_forma_pago
     SET fp_keep.es_efectivo = 1
     WHERE EXISTS (
         SELECT 1 FROM forma_pago fp_old
         WHERE fp_old.id_forma_pago = m.old_id_forma_pago
           AND COALESCE(fp_old.es_efectivo, 0) = 1
     )',
    'SELECT 1'
);
PREPARE stmt_efectivo FROM @sql_efectivo;
EXECUTE stmt_efectivo;
DEALLOCATE PREPARE stmt_efectivo;

UPDATE forma_pago fp
INNER JOIN tmp_forma_pago_merge m ON m.old_id_forma_pago = fp.id_forma_pago
SET fp.activo = 0,
    fp.fecha_baja = NOW()
WHERE fp.activo = 1;

-- Actualizar config si apuntaba a un id duplicado.
UPDATE configuracion_general cg
INNER JOIN tmp_forma_pago_merge m ON CAST(cg.valor AS UNSIGNED) = m.old_id_forma_pago
SET cg.valor = CAST(m.keep_id_forma_pago AS CHAR),
    cg.fecha_actualizacion = NOW()
WHERE cg.clave = 'id_forma_pago_default';

COMMIT;
