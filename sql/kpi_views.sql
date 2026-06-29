-- ============================================================================
-- KPI Views for Sales Analytics Dashboard
-- ============================================================================

-- 1. Vista: Ventas Diarias por Empleado
CREATE OR REPLACE VIEW vw_ventas_diarias_empleado AS
SELECT 
    e.id_empleado,
    CONCAT(u.nombre, ' ', u.primer_apellido) AS empleado_nombre,
    DATE(v.fecha_venta) AS fecha,
    COUNT(v.id_venta) AS num_ventas,
    SUM(v.total) AS total_ventas,
    SUM(COALESCE(vd.cantidad, 0)) AS cantidad_piezas,
    AVG(v.total) AS promedio_venta
FROM ventas v
JOIN empleados e ON v.id_empleado_FK = e.id_empleado
JOIN usuarios u ON e.id_usuario_FK = u.id_usuario
LEFT JOIN venta_detalle vd ON v.id_venta = vd.id_venta_FK
WHERE v.estado = 'completada'
GROUP BY e.id_empleado, DATE(v.fecha_venta)
ORDER BY DATE(v.fecha_venta) DESC, e.id_empleado;

-- 2. Vista: Total de Ventas por Empleado (General)
CREATE OR REPLACE VIEW vw_ventas_total_empleado AS
SELECT 
    e.id_empleado,
    CONCAT(u.nombre, ' ', u.primer_apellido) AS empleado_nombre,
    u.correo,
    COUNT(v.id_venta) AS num_ventas_totales,
    SUM(v.total) AS monto_total_ventas,
    SUM(COALESCE(vd.cantidad, 0)) AS total_piezas,
    AVG(v.total) AS promedio_venta,
    MIN(DATE(v.fecha_venta)) AS primera_venta,
    MAX(DATE(v.fecha_venta)) AS ultima_venta,
    DATEDIFF(MAX(v.fecha_venta), MIN(v.fecha_venta)) AS dias_activos
FROM ventas v
JOIN empleados e ON v.id_empleado_FK = e.id_empleado
JOIN usuarios u ON e.id_usuario_FK = u.id_usuario
LEFT JOIN venta_detalle vd ON v.id_venta = vd.id_venta_FK
WHERE v.estado = 'completada'
GROUP BY e.id_empleado
ORDER BY SUM(v.total) DESC;

-- 3. Vista: Ranking Semanal de Empleados
CREATE OR REPLACE VIEW vw_ventas_ranking_semanal AS
SELECT 
    e.id_empleado,
    CONCAT(u.nombre, ' ', u.primer_apellido) AS empleado_nombre,
    WEEK(v.fecha_venta, 1) AS semana,
    YEAR(v.fecha_venta) AS año,
    CONCAT(YEAR(v.fecha_venta), '-W', LPAD(WEEK(v.fecha_venta, 1), 2, '0')) AS semana_label,
    COUNT(v.id_venta) AS num_ventas,
    SUM(v.total) AS total_ventas,
    ROW_NUMBER() OVER (PARTITION BY WEEK(v.fecha_venta, 1), YEAR(v.fecha_venta) ORDER BY SUM(v.total) DESC) AS ranking
FROM ventas v
JOIN empleados e ON v.id_empleado_FK = e.id_empleado
JOIN usuarios u ON e.id_usuario_FK = u.id_usuario
WHERE v.estado = 'completada'
GROUP BY e.id_empleado, WEEK(v.fecha_venta, 1), YEAR(v.fecha_venta)
ORDER BY YEAR(v.fecha_venta) DESC, WEEK(v.fecha_venta, 1) DESC, ranking;

-- 4. Vista: Devoluciones por Empleado
CREATE OR REPLACE VIEW vw_devoluciones_empleado AS
SELECT 
    e.id_empleado,
    CONCAT(u.nombre, ' ', u.primer_apellido) AS empleado_nombre,
    DATE(d.fecha_devolucion) AS fecha,
    COUNT(d.id_devolucion) AS num_devoluciones,
    COUNT(DISTINCT d.id_venta_FK) AS ventas_con_devolucion,
    d.motivo AS motivo_devolucion
FROM devoluciones d
JOIN empleados e ON d.id_empleado_FK = e.id_empleado
JOIN usuarios u ON e.id_usuario_FK = u.id_usuario
GROUP BY e.id_empleado, DATE(d.fecha_devolucion), d.motivo
ORDER BY DATE(d.fecha_devolucion) DESC;

-- 5. Vista: Tasa de Devolución por Empleado
CREATE OR REPLACE VIEW vw_tasa_devolucion_empleado AS
SELECT 
    e.id_empleado,
    CONCAT(u.nombre, ' ', u.primer_apellido) AS empleado_nombre,
    COUNT(DISTINCT v.id_venta) AS total_ventas,
    COUNT(DISTINCT d.id_venta_FK) AS ventas_devueltas,
    ROUND(COUNT(DISTINCT d.id_venta_FK) * 100.0 / COUNT(DISTINCT v.id_venta), 2) AS tasa_devolucion_pct
FROM empleados e
JOIN usuarios u ON e.id_usuario_FK = u.id_usuario
LEFT JOIN ventas v ON e.id_empleado = v.id_empleado_FK AND v.estado = 'completada'
LEFT JOIN devoluciones d ON v.id_venta = d.id_venta_FK
GROUP BY e.id_empleado
ORDER BY ROUND(COUNT(DISTINCT d.id_venta_FK) * 100.0 / COUNT(DISTINCT v.id_venta), 2) DESC;

-- 6. Vista: Hoy vs Ayer (Comparativa Diaria)
CREATE OR REPLACE VIEW vw_ventas_comparativa_diaria AS
SELECT 
    DATE(v.fecha_venta) AS fecha,
    IF(DATE(v.fecha_venta) = CURDATE(), 'Hoy', 'Ayer') AS periodo,
    COUNT(v.id_venta) AS num_ventas,
    SUM(v.total) AS total_ventas,
    COUNT(DISTINCT v.id_empleado_FK) AS empleados_activos
FROM ventas v
WHERE v.estado = 'completada'
    AND DATE(v.fecha_venta) IN (CURDATE(), DATE_SUB(CURDATE(), INTERVAL 1 DAY))
GROUP BY DATE(v.fecha_venta)
ORDER BY DATE(v.fecha_venta) DESC;

-- 7. Vista: Trend de Ventas (Últimos 30 días)
CREATE OR REPLACE VIEW vw_ventas_trend_30dias AS
SELECT 
    DATE(v.fecha_venta) AS fecha,
    COUNT(v.id_venta) AS num_ventas,
    SUM(v.total) AS total_ventas,
    SUM(COALESCE(vd.cantidad, 0)) AS cantidad_piezas,
    COUNT(DISTINCT v.id_empleado_FK) AS empleados_activos,
    AVG(v.total) AS promedio_ticket
FROM ventas v
LEFT JOIN venta_detalle vd ON v.id_venta = vd.id_venta_FK
WHERE v.estado = 'completada'
    AND DATE(v.fecha_venta) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
GROUP BY DATE(v.fecha_venta)
ORDER BY DATE(v.fecha_venta) DESC;

-- 8. Vista: Apartados Activos por Empleado
CREATE OR REPLACE VIEW vw_apartados_empleado AS
SELECT 
    e.id_empleado,
    CONCAT(u.nombre, ' ', u.primer_apellido) AS empleado_nombre,
    COUNT(a.id_apartado) AS total_apartados,
    SUM(a.total_apartado) AS monto_apartado,
    SUM(CASE WHEN a.estado = 'activo' THEN 1 ELSE 0 END) AS apartados_activos,
    SUM(CASE WHEN a.estado = 'liquidado' THEN 1 ELSE 0 END) AS apartados_liquidados,
    SUM(CASE WHEN a.estado = 'vencido' THEN 1 ELSE 0 END) AS apartados_vencidos
FROM apartados a
JOIN empleados e ON a.id_empleado_FK = e.id_empleado
JOIN usuarios u ON e.id_usuario_FK = u.id_usuario
GROUP BY e.id_empleado
ORDER BY SUM(CASE WHEN a.estado = 'activo' THEN 1 ELSE 0 END) DESC;

-- 9. Vista: Métodos de Pago (Distribución)
CREATE OR REPLACE VIEW vw_formas_pago_distribucion AS
SELECT 
    fp.id_forma_pago,
    fp.forma_pago,
    COUNT(vp.id_venta_FK) AS num_transacciones,
    SUM(vp.monto) AS monto_total,
    ROUND(SUM(vp.monto) * 100.0 / (SELECT SUM(monto) FROM venta_pagos), 2) AS porcentaje
FROM forma_pago fp
LEFT JOIN venta_pagos vp ON fp.id_forma_pago = vp.id_forma_pago_FK
GROUP BY fp.id_forma_pago
ORDER BY SUM(vp.monto) DESC;

-- 10. Vista: Productos más vendidos (joyas + insumos)
CREATE OR REPLACE VIEW vw_productos_top_ventas AS
SELECT tipo_linea, nombre_pieza, num_lineas AS num_ventas, cantidad_vendida, monto_total, precio_promedio
FROM (
    SELECT 
        'joya' AS tipo_linea,
        p.desc_pieza AS nombre_pieza,
        COUNT(vd.id_venta_detalle) AS num_lineas,
        SUM(COALESCE(vd.cantidad, 1)) AS cantidad_vendida,
        SUM(vd.subtotal) AS monto_total,
        AVG(vd.precio_unitario) AS precio_promedio
    FROM venta_detalle vd
    INNER JOIN piezas_stock ps ON vd.id_pieza_stock_FK = ps.id_pieza_stock
    INNER JOIN piezas p ON ps.id_pieza_FK = p.id_pieza
    WHERE vd.tipo_linea = 'joya'
    GROUP BY p.id_pieza, p.desc_pieza
    
    UNION ALL
    
    SELECT 
        'insumo' AS tipo_linea,
        i.nombre AS nombre_pieza,
        COUNT(vd.id_venta_detalle) AS num_lineas,
        SUM(COALESCE(vd.cantidad, 1)) AS cantidad_vendida,
        SUM(vd.subtotal) AS monto_total,
        AVG(vd.precio_unitario) AS precio_promedio
    FROM venta_detalle vd
    INNER JOIN insumos i ON vd.id_insumo_FK = i.id_insumo
    WHERE vd.tipo_linea = 'insumo'
    GROUP BY i.id_insumo, i.nombre
) AS rk
ORDER BY monto_total DESC
LIMIT 50;

-- 11. Tabla de Estadísticas Generales (Resumen Ejecutivo)
CREATE OR REPLACE VIEW vw_resumen_ejecutivo_ventas AS
SELECT 
    'Total Ventas Hoy' AS metrica,
    COUNT(DISTINCT CASE WHEN DATE(v.fecha_venta) = CURDATE() THEN v.id_venta END) AS valor_num,
    CAST(SUM(CASE WHEN DATE(v.fecha_venta) = CURDATE() THEN v.total ELSE 0 END) AS DECIMAL(12,2)) AS valor_monto
FROM ventas v
WHERE v.estado = 'completada'
UNION ALL
SELECT 
    'Total Ventas Este Mes',
    COUNT(DISTINCT CASE WHEN MONTH(v.fecha_venta) = MONTH(CURDATE()) AND YEAR(v.fecha_venta) = YEAR(CURDATE()) THEN v.id_venta END),
    CAST(SUM(CASE WHEN MONTH(v.fecha_venta) = MONTH(CURDATE()) AND YEAR(v.fecha_venta) = YEAR(CURDATE()) THEN v.total ELSE 0 END) AS DECIMAL(12,2))
FROM ventas v
WHERE v.estado = 'completada'
UNION ALL
SELECT 
    'Empleados Activos Hoy',
    COUNT(DISTINCT CASE WHEN DATE(v.fecha_venta) = CURDATE() THEN v.id_empleado_FK END),
    0
FROM ventas v
WHERE v.estado = 'completada'
UNION ALL
SELECT 
    'Promedio Venta Hoy',
    CAST(AVG(CASE WHEN DATE(v.fecha_venta) = CURDATE() THEN v.total ELSE NULL END) AS DECIMAL(12,2)),
    0
FROM ventas v
WHERE v.estado = 'completada';
