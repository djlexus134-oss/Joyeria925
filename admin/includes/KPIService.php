<?php
/**
 * KPIService.php
 * 
 * Servicio centralizado para obtener KPIs de ventas
 * Utiliza las vistas SQL creadas en kpi_views.sql
 */

require_once __DIR__ . '/../../sistema.class.php';

class KPIService extends Sistema
{
    /**
     * Obtiene las estadísticas generales de hoy
     */
    public function getResumenHoy(): array
    {
        try {
            $sql = "SELECT 
                        COUNT(DISTINCT v.id_venta) AS total_ventas,
                        SUM(v.total) AS monto_total,
                        COUNT(DISTINCT v.id_empleado_FK) AS empleados_activos,
                        AVG(v.total) AS promedio_ticket,
                        MAX(v.total) AS mayor_venta
                    FROM ventas v
                    WHERE v.estado = 'completada' 
                    AND DATE(v.fecha_venta) = CURDATE()";
            
            $result = $this->getDb()->query($sql)->fetch(PDO::FETCH_ASSOC);
            return [
                'ventas' => (int)($result['total_ventas'] ?? 0),
                'monto' => (float)($result['monto_total'] ?? 0),
                'empleados' => (int)($result['empleados_activos'] ?? 0),
                'promedio_ticket' => (float)($result['promedio_ticket'] ?? 0),
                'mayor_venta' => (float)($result['mayor_venta'] ?? 0)
            ];
        } catch (Exception $e) {
            error_log("Error en KPIService::getResumenHoy - " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene el ranking de empleados de hoy
     */
    public function getRankingEmpleadosHoy(int $limit = 5): array
    {
        try {
            $sql = "SELECT 
                        e.id_empleado,
                        CONCAT(u.nombre, ' ', u.primer_apellido) AS empleado,
                        COUNT(v.id_venta) AS num_ventas,
                        SUM(v.total) AS total_ventas,
                        AVG(v.total) AS promedio_venta
                    FROM ventas v
                    JOIN empleados e ON v.id_empleado_FK = e.id_empleado
                    JOIN usuarios u ON e.id_usuario_FK = u.id_usuario
                    WHERE v.estado = 'completada' 
                    AND DATE(v.fecha_venta) = CURDATE()
                    GROUP BY e.id_empleado
                    ORDER BY SUM(v.total) DESC
                    LIMIT :limit";
            
            $stmt = $this->getDb()->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            $resultados = [];
            $ranking = 1;
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $row['ranking'] = $ranking++;
                $resultados[] = $row;
            }
            return $resultados;
        } catch (Exception $e) {
            error_log("Error en KPIService::getRankingEmpleadosHoy - " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene datos históricos de 7 días
     */
    public function getTrendUltimos7Dias(): array
    {
        try {
            $sql = "SELECT 
                        DATE(v.fecha_venta) AS fecha,
                        COUNT(v.id_venta) AS num_ventas,
                        SUM(v.total) AS total_ventas,
                        AVG(v.total) AS promedio_ticket
                    FROM ventas v
                    WHERE v.estado = 'completada'
                    AND DATE(v.fecha_venta) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                    GROUP BY DATE(v.fecha_venta)
                    ORDER BY DATE(v.fecha_venta) DESC";
            
            return $this->getDb()->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error en KPIService::getTrendUltimos7Dias - " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene datos del mes actual
     */
    public function getResumenMesActual(): array
    {
        try {
            $sql = "SELECT 
                        COUNT(DISTINCT v.id_venta) AS total_ventas,
                        SUM(v.total) AS monto_total,
                        COUNT(DISTINCT v.id_empleado_FK) AS empleados_activos,
                        COUNT(DISTINCT DATE(v.fecha_venta)) AS dias_activos,
                        AVG(v.total) AS promedio_ticket
                    FROM ventas v
                    WHERE v.estado = 'completada'
                    AND MONTH(v.fecha_venta) = MONTH(CURDATE())
                    AND YEAR(v.fecha_venta) = YEAR(CURDATE())";
            
            $result = $this->getDb()->query($sql)->fetch(PDO::FETCH_ASSOC);
            return [
                'ventas' => (int)($result['total_ventas'] ?? 0),
                'monto' => (float)($result['monto_total'] ?? 0),
                'empleados_activos' => (int)($result['empleados_activos'] ?? 0),
                'dias_activos' => (int)($result['dias_activos'] ?? 0),
                'promedio_ticket' => (float)($result['promedio_ticket'] ?? 0)
            ];
        } catch (Exception $e) {
            error_log("Error en KPIService::getResumenMesActual - " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene total de ventas por empleado (general)
     */
    public function getVentasTotalEmpleado(int $limit = 10): array
    {
        try {
            $sql = "SELECT 
                        e.id_empleado,
                        CONCAT(u.nombre, ' ', u.primer_apellido) AS empleado,
                        COUNT(v.id_venta) AS num_ventas_totales,
                        SUM(v.total) AS monto_total,
                        AVG(v.total) AS promedio_venta,
                        MAX(DATE(v.fecha_venta)) AS ultima_venta
                    FROM ventas v
                    JOIN empleados e ON v.id_empleado_FK = e.id_empleado
                    JOIN usuarios u ON e.id_usuario_FK = u.id_usuario
                    WHERE v.estado = 'completada'
                    GROUP BY e.id_empleado
                    ORDER BY SUM(v.total) DESC
                    LIMIT :limit";
            
            $stmt = $this->getDb()->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error en KPIService::getVentasTotalEmpleado - " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene tasa de devolución por empleado
     */
    public function getTasaDevolucion(): array
    {
        try {
            $sql = "SELECT 
                        e.id_empleado,
                        CONCAT(u.nombre, ' ', u.primer_apellido) AS empleado,
                        COUNT(DISTINCT v.id_venta) AS total_ventas,
                        COUNT(DISTINCT d.id_venta_FK) AS ventas_devueltas,
                        ROUND(COUNT(DISTINCT d.id_venta_FK) * 100.0 / COUNT(DISTINCT v.id_venta), 2) AS tasa_devolucion_pct
                    FROM empleados e
                    JOIN usuarios u ON e.id_usuario_FK = u.id_usuario
                    LEFT JOIN ventas v ON e.id_empleado = v.id_empleado_FK AND v.estado = 'completada'
                    LEFT JOIN devoluciones d ON v.id_venta = d.id_venta_FK
                    GROUP BY e.id_empleado
                    HAVING COUNT(DISTINCT v.id_venta) > 0
                    ORDER BY ROUND(COUNT(DISTINCT d.id_venta_FK) * 100.0 / COUNT(DISTINCT v.id_venta), 2) DESC";
            
            return $this->getDb()->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error en KPIService::getTasaDevolucion - " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene distribución de formas de pago hoy
     */
    public function getFormasPagoHoy(): array
    {
        try {
            $sql = "SELECT 
                        fp.forma_pago,
                        COUNT(vp.id_venta_FK) AS num_transacciones,
                        SUM(vp.monto) AS monto_total,
                        ROUND(SUM(vp.monto) * 100.0 / 
                            (SELECT SUM(monto) FROM venta_pagos vp2 
                             JOIN ventas v2 ON vp2.id_venta_FK = v2.id_venta 
                             WHERE DATE(v2.fecha_venta) = CURDATE()), 2) AS porcentaje
                    FROM forma_pago fp
                    LEFT JOIN venta_pagos vp ON fp.id_forma_pago = vp.id_forma_pago_FK
                    LEFT JOIN ventas v ON vp.id_venta_FK = v.id_venta
                    WHERE DATE(v.fecha_venta) = CURDATE() OR vp.monto IS NULL
                    GROUP BY fp.id_forma_pago
                    ORDER BY SUM(vp.monto) DESC";
            
            return $this->getDb()->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error en KPIService::getFormasPagoHoy - " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene productos más vendidos
     */
    public function getProductosTopVentas(int $limit = 10): array
    {
        try {
            $sql = "SELECT tipo_linea, nombre_pieza, num_ventas, cantidad_vendida, monto_total, precio_promedio
                    FROM (
                        SELECT
                            'joya' AS tipo_linea,
                            p.desc_pieza AS nombre_pieza,
                            COUNT(vd.id_venta_detalle) AS num_ventas,
                            SUM(COALESCE(vd.cantidad, 1)) AS cantidad_vendida,
                            SUM(vd.subtotal) AS monto_total,
                            ROUND(AVG(vd.precio_unitario), 2) AS precio_promedio
                        FROM venta_detalle vd
                        INNER JOIN piezas_stock ps ON vd.id_pieza_stock_FK = ps.id_pieza_stock
                        INNER JOIN piezas p ON ps.id_pieza_FK = p.id_pieza
                        WHERE vd.tipo_linea = 'joya'
                        GROUP BY p.id_pieza, p.desc_pieza

                        UNION ALL

                        SELECT
                            'insumo' AS tipo_linea,
                            i.nombre AS nombre_pieza,
                            COUNT(vd.id_venta_detalle) AS num_ventas,
                            SUM(COALESCE(vd.cantidad, 1)) AS cantidad_vendida,
                            SUM(vd.subtotal) AS monto_total,
                            ROUND(AVG(vd.precio_unitario), 2) AS precio_promedio
                        FROM venta_detalle vd
                        INNER JOIN insumos i ON vd.id_insumo_FK = i.id_insumo
                        WHERE vd.tipo_linea = 'insumo'
                        GROUP BY i.id_insumo, i.nombre
                    ) AS ranking
                    ORDER BY monto_total DESC
                    LIMIT :limit";
            
            $stmt = $this->getDb()->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error en KPIService::getProductosTopVentas - " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene apartados activos
     */
    public function getApartadosActivos(): array
    {
        try {
            $sql = "SELECT 
                        e.id_empleado,
                        CONCAT(u.nombre, ' ', u.primer_apellido) AS empleado,
                        COUNT(CASE WHEN a.estado = 'activo' THEN 1 END) AS apartados_activos,
                        SUM(CASE WHEN a.estado = 'activo' THEN a.total_apartado ELSE 0 END) AS monto_activo,
                        COUNT(CASE WHEN a.estado = 'liquidado' THEN 1 END) AS apartados_liquidados,
                        COUNT(CASE WHEN a.estado = 'vencido' THEN 1 END) AS apartados_vencidos
                    FROM empleados e
                    JOIN usuarios u ON e.id_usuario_FK = u.id_usuario
                    LEFT JOIN apartados a ON e.id_empleado = a.id_empleado_FK
                    GROUP BY e.id_empleado
                    HAVING COUNT(CASE WHEN a.estado = 'activo' THEN 1 END) > 0
                    ORDER BY COUNT(CASE WHEN a.estado = 'activo' THEN 1 END) DESC";
            
            return $this->getDb()->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error en KPIService::getApartadosActivos - " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene comparativa Hoy vs Ayer
     */
    public function getComparativaHoyVsAyer(): array
    {
        try {
            $sql = "SELECT 
                        IF(DATE(v.fecha_venta) = CURDATE(), 'Hoy', 'Ayer') AS periodo,
                        COUNT(v.id_venta) AS num_ventas,
                        SUM(v.total) AS total_ventas,
                        AVG(v.total) AS promedio_ticket
                    FROM ventas v
                    WHERE v.estado = 'completada'
                    AND DATE(v.fecha_venta) IN (CURDATE(), DATE_SUB(CURDATE(), INTERVAL 1 DAY))
                    GROUP BY DATE(v.fecha_venta)
                    ORDER BY DATE(v.fecha_venta) DESC";
            
            $resultados = $this->getDb()->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($resultados) === 2) {
                $hoy = $resultados[0];
                $ayer = $resultados[1];
                
                return [
                    'hoy' => $hoy,
                    'ayer' => $ayer,
                    'variacion_pct' => $ayer['num_ventas'] > 0 
                        ? (($hoy['num_ventas'] - $ayer['num_ventas']) / $ayer['num_ventas'] * 100)
                        : 0,
                    'variacion_monto_pct' => $ayer['total_ventas'] > 0
                        ? (($hoy['total_ventas'] - $ayer['total_ventas']) / $ayer['total_ventas'] * 100)
                        : 0
                ];
            }
            return [];
        } catch (Exception $e) {
            error_log("Error en KPIService::getComparativaHoyVsAyer - " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene trend de 30 días para gráfica de línea
     */
    public function getTrendGrafica30Dias(): array
    {
        try {
            $sql = "SELECT 
                        DATE_FORMAT(DATE(v.fecha_venta), '%d/%m') AS fecha_label,
                        DATE(v.fecha_venta) AS fecha,
                        COUNT(v.id_venta) AS ventas,
                        SUM(v.total) AS monto
                    FROM ventas v
                    WHERE v.estado = 'completada'
                    AND DATE(v.fecha_venta) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                    GROUP BY DATE(v.fecha_venta)
                    ORDER BY DATE(v.fecha_venta) ASC";
            
            return $this->getDb()->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error en KPIService::getTrendGrafica30Dias - " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene ranking de empleados para gráfica de barras
     */
    public function getRankingEmpleadosGrafica(int $limit = 10): array
    {
        try {
            $sql = "SELECT 
                        e.id_empleado,
                        CONCAT(u.nombre, ' ', u.primer_apellido) AS empleado,
                        COUNT(v.id_venta) AS num_ventas,
                        SUM(v.total) AS total_ventas
                    FROM ventas v
                    JOIN empleados e ON v.id_empleado_FK = e.id_empleado
                    JOIN usuarios u ON e.id_usuario_FK = u.id_usuario
                    WHERE v.estado = 'completada'
                    AND DATE(v.fecha_venta) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                    GROUP BY e.id_empleado
                    ORDER BY SUM(v.total) DESC
                    LIMIT :limit";
            
            $stmt = $this->getDb()->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error en KPIService::getRankingEmpleadosGrafica - " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene formas de pago para gráfica de pastel
     */
    public function getFormasPagoGrafica(): array
    {
        try {
            $sql = "SELECT 
                        fp.forma_pago,
                        COUNT(vp.id_venta_pago) AS num_transacciones,
                        SUM(vp.monto) AS monto_total
                    FROM forma_pago fp
                    LEFT JOIN venta_pagos vp ON fp.id_forma_pago = vp.id_forma_pago_FK
                    LEFT JOIN ventas v ON vp.id_venta_FK = v.id_venta
                    WHERE v.estado = 'completada' 
                    AND DATE(v.fecha_venta) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                    GROUP BY fp.id_forma_pago
                    HAVING SUM(vp.monto) > 0
                    ORDER BY SUM(vp.monto) DESC";
            
            return $this->getDb()->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error en KPIService::getFormasPagoGrafica - " . $e->getMessage());
            return [];
        }
    }
}
