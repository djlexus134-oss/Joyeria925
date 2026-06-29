-- Corrige venta_detalle cuyo subtotal (precio lista) no cuadra con ventas.total
-- tras descuentos de cliente o canjes parciales previos.
-- Idempotente: solo ajusta ventas con desfase > $0.02 donde suma lineas > total cobrado.

SET @db = DATABASE();

-- ---------------------------------------------------------------------------
-- 1) Asegurar columna descuento_aplicado (instancias sin migracion previa)
-- ---------------------------------------------------------------------------
SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'venta_detalle' AND COLUMN_NAME = 'descuento_aplicado') = 0,
    'ALTER TABLE venta_detalle ADD COLUMN descuento_aplicado DECIMAL(12,2) NULL DEFAULT NULL AFTER subtotal',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------------
-- 2) Backfill proporcional: subtotal alineado al total cobrado en cabecera
-- ---------------------------------------------------------------------------
UPDATE venta_detalle vd
INNER JOIN (
    SELECT
        vd2.id_venta_FK AS id_venta,
        v.total AS total_venta,
        SUM(
            CASE
                WHEN vd2.subtotal IS NOT NULL AND vd2.subtotal > 0.01 THEN vd2.subtotal
                ELSE COALESCE(vd2.precio_unitario, 0) * COALESCE(vd2.cantidad, 1)
            END
        ) AS sum_lineas
    FROM venta_detalle vd2
    INNER JOIN ventas v ON v.id_venta = vd2.id_venta_FK
    WHERE COALESCE(vd2.anulada, 0) = 0
      AND v.estado IN ('completada', 'devuelta')
    GROUP BY vd2.id_venta_FK, v.total
    HAVING sum_lineas > v.total + 0.02
) desfase ON desfase.id_venta = vd.id_venta_FK
SET
    vd.subtotal = ROUND(
        (CASE
            WHEN vd.subtotal IS NOT NULL AND vd.subtotal > 0.01 THEN vd.subtotal
            ELSE COALESCE(vd.precio_unitario, 0) * COALESCE(vd.cantidad, 1)
        END) * desfase.total_venta / desfase.sum_lineas,
        2
    ),
    vd.descuento_aplicado = GREATEST(
        0,
        ROUND(
            COALESCE(vd.precio_unitario, 0) * COALESCE(vd.cantidad, 1)
            - (CASE
                WHEN vd.subtotal IS NOT NULL AND vd.subtotal > 0.01 THEN vd.subtotal
                ELSE COALESCE(vd.precio_unitario, 0) * COALESCE(vd.cantidad, 1)
            END) * desfase.total_venta / desfase.sum_lineas,
            2
        )
    )
WHERE COALESCE(vd.anulada, 0) = 0;

-- ---------------------------------------------------------------------------
-- 3) precio_final unitario coherente con subtotal (si existe la columna)
-- ---------------------------------------------------------------------------
SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'venta_detalle' AND COLUMN_NAME = 'precio_final') > 0,
    'UPDATE venta_detalle SET precio_final = ROUND(subtotal / GREATEST(COALESCE(cantidad, 1), 1), 2)
     WHERE COALESCE(anulada, 0) = 0 AND subtotal IS NOT NULL AND subtotal > 0.01
       AND (precio_final IS NULL OR ABS(precio_final * GREATEST(COALESCE(cantidad, 1), 1) - subtotal) > 0.02)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
