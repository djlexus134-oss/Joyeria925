-- Verificacion posterior a la limpieza de datos (solo lectura).
-- Ejecutar tras aplicar los scripts de migracion en orden.

SELECT 'duplicados_familias' AS check_name, COUNT(*) AS problemas
FROM (
    SELECT LOWER(TRIM(nom_familia)) AS n
    FROM familias WHERE activo = 1
    GROUP BY LOWER(TRIM(nom_familia)) HAVING COUNT(*) > 1
) x
UNION ALL
SELECT 'duplicados_forma_pago', COUNT(*)
FROM (
    SELECT LOWER(TRIM(forma_pago)) AS n
    FROM forma_pago WHERE activo = 1
    GROUP BY LOWER(TRIM(forma_pago)) HAVING COUNT(*) > 1
) x
UNION ALL
SELECT 'duplicados_impuestos', COUNT(*)
FROM (
    SELECT LOWER(TRIM(tipo_impuesto)) AS n
    FROM impuestos
    GROUP BY LOWER(TRIM(tipo_impuesto)) HAVING COUNT(*) > 1
) x
UNION ALL
SELECT 'piezas_tienda_inactiva', COUNT(*)
FROM piezas p
INNER JOIN tiendas t ON t.id_tienda = p.id_tienda_FK
WHERE t.activo = 0
UNION ALL
SELECT 'empleados_activos_extra', GREATEST(COUNT(*) - 3, 0)
FROM empleados e
WHERE e.activo = 1;

SELECT 'empleados_activos_lista' AS seccion;
SELECT
    e.id_empleado,
    TRIM(CONCAT_WS(' ', u.nombre, u.primer_apellido, u.segundo_apellido)) AS nombre,
    u.correo,
    (SELECT COUNT(*) FROM usuario_rol ur WHERE ur.id_usuario_FK = u.id_usuario) AS roles_asignados
FROM empleados e
INNER JOIN usuarios u ON u.id_usuario = e.id_usuario_FK
WHERE e.activo = 1;

SELECT 'config_claves_tienda_pago_impuesto' AS seccion;
SELECT cg.clave, cg.valor,
    t.nom_tienda,
    fp.forma_pago,
    i.tipo_impuesto,
    i.porcentaje
FROM configuracion_general cg
LEFT JOIN tiendas t ON cg.clave IN ('id_tienda_default', 'impresion_id_tienda_caja')
    AND t.id_tienda = CAST(cg.valor AS UNSIGNED)
LEFT JOIN forma_pago fp ON cg.clave = 'id_forma_pago_default'
    AND fp.id_forma_pago = CAST(cg.valor AS UNSIGNED)
LEFT JOIN impuestos i ON cg.clave = 'id_impuesto_default'
    AND i.id_impuesto = CAST(cg.valor AS UNSIGNED)
WHERE cg.clave IN (
    'id_tienda_default',
    'impresion_id_tienda_caja',
    'id_forma_pago_default',
    'id_impuesto_default'
);
