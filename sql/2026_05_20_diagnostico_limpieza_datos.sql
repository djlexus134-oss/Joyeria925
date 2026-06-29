-- Diagnostico previo a limpieza de catalogos, tienda y empleados (solo lectura).
-- Ejecutar y revisar resultados antes de los scripts de migracion.
--
-- Orden sugerido de migracion (tras respaldo mysqldump):
--   1) 2026_05_20_consolidar_tienda_joyeria_centro.sql
--   2) 2026_05_deduplicar_familias_subfamilias.sql
--   3) 2026_05_20_deduplicar_forma_pago.sql
--   4) 2026_05_20_deduplicar_impuestos.sql
--   5) 2026_05_20_limpiar_empleados.sql
--   6) 2026_05_20_verificacion_post_limpieza.sql

-- ---------------------------------------------------------------------------
-- 1) Familias duplicadas (activas, mismo nombre normalizado)
-- ---------------------------------------------------------------------------
SELECT 'familias_duplicadas' AS seccion;
SELECT
    LOWER(TRIM(nom_familia)) AS nom_norm,
    COUNT(*) AS total,
    GROUP_CONCAT(id_familia ORDER BY id_familia) AS ids,
    MIN(id_familia) AS id_canonico_sugerido
FROM familias
WHERE activo = 1
GROUP BY LOWER(TRIM(nom_familia))
HAVING COUNT(*) > 1;

-- ---------------------------------------------------------------------------
-- 2) Subfamilias duplicadas (misma familia + nombre)
-- ---------------------------------------------------------------------------
SELECT 'sub_familia_duplicadas' AS seccion;
SELECT
    id_familia_FK,
    LOWER(TRIM(nom_sub_familia)) AS nom_norm,
    COUNT(*) AS total,
    GROUP_CONCAT(id_sub_familia ORDER BY id_sub_familia) AS ids,
    MIN(id_sub_familia) AS id_canonico_sugerido
FROM sub_familia
WHERE activo = 1
GROUP BY id_familia_FK, LOWER(TRIM(nom_sub_familia))
HAVING COUNT(*) > 1;

-- ---------------------------------------------------------------------------
-- 3) Formas de pago duplicadas
-- ---------------------------------------------------------------------------
SELECT 'forma_pago_duplicadas' AS seccion;
SELECT
    LOWER(TRIM(forma_pago)) AS nom_norm,
    COUNT(*) AS total,
    GROUP_CONCAT(id_forma_pago ORDER BY id_forma_pago) AS ids,
    MIN(id_forma_pago) AS id_canonico_sugerido,
    MAX(COALESCE(es_efectivo, 0)) AS alguno_es_efectivo
FROM forma_pago
WHERE activo = 1
GROUP BY LOWER(TRIM(forma_pago))
HAVING COUNT(*) > 1;

-- ---------------------------------------------------------------------------
-- 4) Impuestos duplicados (mismo tipo; canónico = mayor porcentaje)
-- ---------------------------------------------------------------------------
SELECT 'impuestos_duplicados' AS seccion;
SELECT
    nom_norm,
    COUNT(*) AS total,
    GROUP_CONCAT(CONCAT(id_impuesto, ':', COALESCE(porcentaje, 'NULL')) ORDER BY id_impuesto) AS ids_pct,
    SUBSTRING_INDEX(
        GROUP_CONCAT(
            id_impuesto
            ORDER BY COALESCE(porcentaje, 0) DESC, id_impuesto ASC
        ),
        ',',
        1
    ) AS id_canonico_sugerido
FROM (
    SELECT
        id_impuesto,
        porcentaje,
        LOWER(TRIM(tipo_impuesto)) AS nom_norm
    FROM impuestos
) AS i
GROUP BY nom_norm
HAVING COUNT(*) > 1;

-- ---------------------------------------------------------------------------
-- 5) Tiendas y referencias a tiendas inactivas
-- ---------------------------------------------------------------------------
SELECT 'tiendas_estado' AS seccion;
SELECT id_tienda, nom_tienda, activo, fecha_baja FROM tiendas ORDER BY activo DESC, id_tienda;

SELECT 'tienda_joyeria_centro_activa' AS seccion;
SELECT id_tienda, nom_tienda
FROM tiendas
WHERE activo = 1 AND LOWER(TRIM(nom_tienda)) = 'joyeria centro';

SELECT 'refs_tiendas_inactivas' AS seccion;
SELECT 'piezas' AS tabla, COUNT(*) AS filas
FROM piezas p
INNER JOIN tiendas t ON t.id_tienda = p.id_tienda_FK
WHERE t.activo = 0
UNION ALL
SELECT 'insumo_existencia', COUNT(*)
FROM insumo_existencia ie
INNER JOIN tiendas t ON t.id_tienda = ie.id_tienda_FK
WHERE t.activo = 0
UNION ALL
SELECT 'venta_detalle', COUNT(*)
FROM venta_detalle vd
INNER JOIN tiendas t ON t.id_tienda = vd.id_tienda_FK
WHERE vd.id_tienda_FK IS NOT NULL AND t.activo = 0;

SELECT 'config_tienda' AS seccion;
SELECT clave, valor
FROM configuracion_general
WHERE clave IN ('id_tienda_default', 'impresion_id_tienda_caja');

-- ---------------------------------------------------------------------------
-- 6) Empleados: conservar vs eliminar
-- ---------------------------------------------------------------------------
SELECT 'empleados_activos' AS seccion;
SELECT
    e.id_empleado,
    u.id_usuario,
    TRIM(CONCAT_WS(' ', u.nombre, u.primer_apellido, u.segundo_apellido)) AS nombre_completo,
    u.correo,
    e.activo AS empleado_activo,
    u.activo AS usuario_activo
FROM empleados e
INNER JOIN usuarios u ON u.id_usuario = e.id_usuario_FK
WHERE e.activo = 1
ORDER BY nombre_completo;

SELECT 'empleados_a_limpiar' AS seccion;
SELECT
    e.id_empleado,
    u.id_usuario,
    TRIM(CONCAT_WS(' ', u.nombre, u.primer_apellido, u.segundo_apellido)) AS nombre_completo,
    (
        SELECT COUNT(*) FROM ventas v WHERE v.id_empleado_FK = e.id_empleado
    ) AS refs_ventas,
    (
        SELECT COUNT(*) FROM apartados a WHERE a.id_empleado_FK = e.id_empleado
    ) AS refs_apartados,
    (
        SELECT COUNT(*) FROM gastos g WHERE g.id_empleado_FK = e.id_empleado
    ) AS refs_gastos,
    (
        SELECT COUNT(*) FROM devoluciones d WHERE d.id_empleado_FK = e.id_empleado
    ) AS refs_devoluciones,
    (
        SELECT COUNT(*) FROM contratos_empleados ce WHERE ce.id_empleado_FK = e.id_empleado
    ) AS refs_contratos
FROM empleados e
INNER JOIN usuarios u ON u.id_usuario = e.id_usuario_FK
WHERE e.activo = 1
  AND LOWER(
        REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
            TRIM(CONCAT_WS(' ', u.nombre, u.primer_apellido, COALESCE(u.segundo_apellido, ''))),
        'á','a'),'é','e'),'í','i'),'ó','o'),'ú','u')
      ) NOT IN (
        'gael ricardo mendoza ballardo',
        'beatriz martha hernandez alvarado',
        'daniela melesio diaz'
      )
ORDER BY nombre_completo;

-- ---------------------------------------------------------------------------
-- 7) Config defaults que apuntan a ids inactivos o duplicados
-- ---------------------------------------------------------------------------
SELECT 'config_forma_pago_impuesto' AS seccion;
SELECT cg.clave, cg.valor, fp.forma_pago, fp.activo AS fp_activo, i.tipo_impuesto
FROM configuracion_general cg
LEFT JOIN forma_pago fp ON cg.clave = 'id_forma_pago_default' AND fp.id_forma_pago = CAST(cg.valor AS UNSIGNED)
LEFT JOIN impuestos i ON cg.clave = 'id_impuesto_default' AND i.id_impuesto = CAST(cg.valor AS UNSIGNED)
WHERE cg.clave IN ('id_forma_pago_default', 'id_impuesto_default');
