-- Validacion post-migracion Gema -> Joyeria
-- Ejecutar despues de 06_mig_movimientos_entrada.sql

SET NAMES utf8mb4;

SELECT '=== CONTEOS ORIGEN (gema_staging) vs DESTINO (mig_*) ===' AS seccion;

SELECT 'piezas_fisicas' AS metrica,
       (SELECT COUNT(*) FROM gema_staging.piezas) AS origen,
       (SELECT COUNT(*) FROM mig_gema_stock) AS migrado,
       (SELECT COUNT(*) FROM gema_staging.piezas) - (SELECT COUNT(*) FROM mig_gema_stock) AS diferencia;

SELECT 'articulos_con_stock' AS metrica,
       (SELECT COUNT(DISTINCT artpie) FROM gema_staging.piezas) AS origen,
       (SELECT COUNT(*) FROM mig_gema_artic) AS migrado,
       (SELECT COUNT(DISTINCT artpie) FROM gema_staging.piezas) - (SELECT COUNT(*) FROM mig_gema_artic) AS diferencia;

SELECT 'clientes' AS metrica,
       (SELECT COUNT(*) FROM gema_staging.clien) AS origen,
       (SELECT COUNT(*) FROM mig_gema_cliente) AS migrado,
       (SELECT COUNT(*) FROM gema_staging.clien) - (SELECT COUNT(*) FROM mig_gema_cliente) AS diferencia;

SELECT '=== STOCK POR ESTADO (destino) ===' AS seccion;
SELECT estado_destino, COUNT(*) AS cantidad
FROM mig_gema_stock
GROUP BY estado_destino
ORDER BY cantidad DESC;

SELECT '=== CODIGOS LEGACY (ARTPIE/CODPIE) ===' AS seccion;
SELECT 'aux_no_legacy' AS prueba, COUNT(*) AS fallos
FROM mig_gema_stock ms
WHERE mig_fn_cs(ms.codigo_auxiliar) <> mig_fn_cs(mig_fn_legacy_auxiliar_gema(ms.artpie, ms.codpie))
  AND mig_fn_cs(ms.codigo_auxiliar) <> mig_fn_cs(CONCAT(mig_fn_legacy_auxiliar_gema(ms.artpie, ms.codpie), 'M'));

SELECT 'barra_no_legacy_ean' AS prueba, COUNT(*) AS fallos
FROM mig_gema_stock ms
WHERE ms.codigo_barras NOT REGEXP '^[0-9]{13}$'
  AND mig_fn_cs(ms.codigo_barras) NOT LIKE mig_fn_cs(CONCAT('M', ms.artpie, 'X', ms.codpie, '%'));

SELECT '=== UNICIDAD codigo_barras / codigo_auxiliar ===' AS seccion;
SELECT 'duplicados_codigo_barras' AS prueba, COUNT(*) AS fallos
FROM (
    SELECT codigo_barras FROM piezas_stock GROUP BY codigo_barras HAVING COUNT(*) > 1
) d;

SELECT 'duplicados_codigo_auxiliar' AS prueba, COUNT(*) AS fallos
FROM (
    SELECT codigo_auxiliar FROM piezas_stock GROUP BY codigo_auxiliar HAVING COUNT(*) > 1
) d;

SELECT '=== UNICIDAD usuarios (correo / telefono) migrados ===' AS seccion;
SELECT 'correo_dup' AS prueba, COUNT(*) AS fallos
FROM (
    SELECT u.correo FROM usuarios u
    INNER JOIN mig_gema_cliente mc ON mc.id_usuario = u.id_usuario
    GROUP BY u.correo HAVING COUNT(*) > 1
) x;

SELECT '=== ARTPIE sin catalogo (debe ser 0) ===' AS seccion;
SELECT COUNT(*) AS piezas_huerfanas
FROM gema_staging.piezas p
LEFT JOIN mig_gema_artic ma ON ma.codart = p.artpie
WHERE ma.codart IS NULL;

SELECT '=== STOCK sin pieza padre (debe ser 0) ===' AS seccion;
SELECT COUNT(*) AS stock_sin_pieza
FROM mig_gema_stock ms
LEFT JOIN piezas p ON p.id_pieza = ms.id_pieza
WHERE p.id_pieza IS NULL;

SELECT '=== MUESTRA 10 unidades migradas ===' AS seccion;
SELECT ms.artpie, ms.codpie, ms.codigo_auxiliar, ms.codigo_barras, ms.estado_destino,
       p.desc_pieza, ps.precio_venta
FROM mig_gema_stock ms
INNER JOIN piezas_stock ps ON ps.id_pieza_stock = ms.id_pieza_stock
INNER JOIN piezas p ON p.id_pieza = ms.id_pieza
ORDER BY ms.artpie, ms.codpie
LIMIT 10;

SELECT '=== MUESTRA 10 clientes migrados ===' AS seccion;
SELECT mc.numcli, mc.correo_usado, mc.telefono_usado, mc.activo_destino,
       u.nombre, u.primer_apellido, c.descuento_porcentaje
FROM mig_gema_cliente mc
INNER JOIN clientes c ON c.id_cliente = mc.id_cliente
INNER JOIN usuarios u ON u.id_usuario = mc.id_usuario
ORDER BY mc.numcli
LIMIT 10;

SELECT '=== ULTIMOS AVISOS mig_log ===' AS seccion;
SELECT etapa, nivel, clave, mensaje, fecha_log
FROM mig_log
ORDER BY id_log DESC
LIMIT 30;
