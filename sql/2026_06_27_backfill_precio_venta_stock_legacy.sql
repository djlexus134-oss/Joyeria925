-- =============================================================================
-- Backfill: rellenar precio_venta en piezas_stock que tienen NULL o 0
-- Stock dado de alta antes de la grilla de variantes (sin precio por unidad).
-- Calcula: costo_pieza * (1 + aumento_pct/100), redondeo a múltiplo de $5.
-- Solo actualiza unidades que tengan costo > 0 en la pieza maestra.
-- =============================================================================

START TRANSACTION;

UPDATE piezas_stock ps
INNER JOIN piezas p ON p.id_pieza = ps.id_pieza_FK
SET ps.precio_venta = CEILING(
        (CAST(p.costo AS DECIMAL(12,4)) * (1 + COALESCE(CAST(p.aumento_pct AS DECIMAL(10,4)), 0) / 100)) / 5
    ) * 5
WHERE ps.activo = 1
  AND (ps.precio_venta IS NULL OR CAST(ps.precio_venta AS DECIMAL(12,2)) <= 0)
  AND CAST(p.costo AS DECIMAL(12,4)) > 0.009;

-- Informe de cuántas filas se actualizaron (revisa antes de hacer COMMIT)
SELECT ROW_COUNT() AS filas_actualizadas;

COMMIT;

-- =============================================================================
-- Verificación post-backfill: listar stocks que aún quedaron sin precio
-- (costo de pieza = 0 o NULL; requieren precio manual)
-- =============================================================================
SELECT
    ps.id_pieza_stock,
    ps.codigo_auxiliar,
    ps.codigo_barras,
    ps.estado,
    p.id_pieza,
    p.desc_pieza,
    p.costo,
    p.aumento_pct,
    ps.precio_venta
FROM piezas_stock ps
INNER JOIN piezas p ON p.id_pieza = ps.id_pieza_FK
WHERE ps.activo = 1
  AND (ps.precio_venta IS NULL OR CAST(ps.precio_venta AS DECIMAL(12,2)) <= 0)
ORDER BY p.desc_pieza, ps.codigo_auxiliar;
