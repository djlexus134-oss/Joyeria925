<?php
require_once __DIR__ . '/../../sistema.class.php';

final class KPIDashboardService extends Sistema
{
    private ?bool $ventaDetalleHasCantidad = null;

    private function parseDate(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $dt = DateTime::createFromFormat('Y-m-d', $value);
        if (!$dt || $dt->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException('Fecha invalida: ' . $value . '. Usa formato YYYY-MM-DD.');
        }

        return $value;
    }

    /**
     * Normaliza un rango [from, to] (inclusive) con defaults.
     * - Default: últimos 30 días (incluyendo hoy).
     */
    public function normalizeRange(?string $from, ?string $to): array
    {
        $fromN = $this->parseDate($from);
        $toN = $this->parseDate($to);

        if ($toN === null) {
            $toN = (new DateTime('today'))->format('Y-m-d');
        }
        if ($fromN === null) {
            $fromN = (new DateTime($toN))->modify('-29 days')->format('Y-m-d');
        }

        if ($fromN > $toN) {
            throw new InvalidArgumentException('Rango invalido: from no puede ser mayor que to.');
        }

        return [$fromN, $toN];
    }

    private function ventaDetalleHasCantidadColumn(): bool
    {
        if ($this->ventaDetalleHasCantidad !== null) {
            return $this->ventaDetalleHasCantidad;
        }

        try {
            $db = $this->getDb();
            $sql = "SELECT COUNT(*) AS c
                    FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE()
                      AND TABLE_NAME = 'venta_detalle'
                      AND COLUMN_NAME = 'cantidad'";
            $count = (int) ($db->query($sql)->fetchColumn() ?: 0);
            $this->ventaDetalleHasCantidad = ($count > 0);
        } catch (Throwable $e) {
            // Fallback seguro: si no podemos inspeccionar, asumimos que NO existe.
            $this->ventaDetalleHasCantidad = false;
        }

        return $this->ventaDetalleHasCantidad;
    }

    private function buildVentasWhere(array $filters, array &$params): string
    {
        $clauses = ["v.estado = 'completada'"];

        if (isset($filters['from'], $filters['to'])) {
            $clauses[] = 'DATE(v.fecha_venta) BETWEEN :from AND :to';
            $params[':from'] = $filters['from'];
            $params[':to'] = $filters['to'];
        }

        if (!empty($filters['id_empleado'])) {
            $clauses[] = 'v.id_empleado_FK = :id_empleado';
            $params[':id_empleado'] = (int) $filters['id_empleado'];
        }

        return implode(' AND ', $clauses);
    }

    public function getSummaryCards(array $filters): array
    {
        $db = $this->getDb();
        $params = [];
        $where = $this->buildVentasWhere($filters, $params);

        $sql = "SELECT
                    COUNT(DISTINCT v.id_venta) AS ventas,
                    COALESCE(SUM(v.total), 0) AS monto,
                    COUNT(DISTINCT v.id_empleado_FK) AS empleados_activos,
                    COALESCE(AVG(v.total), 0) AS ticket_promedio
                FROM ventas v
                WHERE $where";

        $stmt = $db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'ventas' => (int) ($row['ventas'] ?? 0),
            'monto' => (float) ($row['monto'] ?? 0),
            'empleados_activos' => (int) ($row['empleados_activos'] ?? 0),
            'ticket_promedio' => (float) ($row['ticket_promedio'] ?? 0),
        ];
    }

    public function getTrendDiario(array $filters): array
    {
        $db = $this->getDb();
        $params = [];
        $where = $this->buildVentasWhere($filters, $params);

        $sql = "SELECT
                    DATE(v.fecha_venta) AS fecha,
                    COUNT(v.id_venta) AS ventas,
                    COALESCE(SUM(v.total), 0) AS monto
                FROM ventas v
                WHERE $where
                GROUP BY DATE(v.fecha_venta)
                ORDER BY DATE(v.fecha_venta) ASC";

        $stmt = $db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getFormasPago(array $filters): array
    {
        $db = $this->getDb();
        $params = [];
        $where = $this->buildVentasWhere($filters, $params);

        $sql = "SELECT
                    fp.id_forma_pago,
                    fp.forma_pago,
                    COUNT(vp.id_venta_pago) AS num_transacciones,
                    COALESCE(SUM(vp.monto), 0) AS monto_total
                FROM forma_pago fp
                INNER JOIN venta_pagos vp ON fp.id_forma_pago = vp.id_forma_pago_FK
                INNER JOIN ventas v ON vp.id_venta_FK = v.id_venta
                WHERE $where
                GROUP BY fp.id_forma_pago, fp.forma_pago
                HAVING COALESCE(SUM(vp.monto), 0) > 0
                ORDER BY COALESCE(SUM(vp.monto), 0) DESC";

        $stmt = $db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $total = 0.0;
        foreach ($rows as $r) {
            $total += (float) ($r['monto_total'] ?? 0);
        }

        $out = [];
        foreach ($rows as $r) {
            $m = (float) ($r['monto_total'] ?? 0);
            $out[] = [
                'id_forma_pago' => (int) ($r['id_forma_pago'] ?? 0),
                'forma_pago' => (string) ($r['forma_pago'] ?? ''),
                'num_transacciones' => (int) ($r['num_transacciones'] ?? 0),
                'monto_total' => $m,
                'porcentaje' => $total > 0 ? round(($m / $total) * 100.0, 2) : 0.0,
            ];
        }

        return $out;
    }

    public function getTopProductos(array $filters, int $limit = 10): array
    {
        $db = $this->getDb();
        $params = [];
        $where = $this->buildVentasWhere($filters, $params);
        $params[':limit'] = $limit;

        $qtyExpr = $this->ventaDetalleHasCantidadColumn()
            ? "COALESCE(vd.cantidad, 1)"
            : "1";

        $extra = '';
        if (!empty($filters['id_tienda'])) {
            // Ventas no tiene tienda; aplicamos filtro solo a líneas de insumo.
            $extra = " AND (
                vd.tipo_linea <> 'insumo'
                OR (vd.tipo_linea = 'insumo' AND vd.id_tienda_FK = :id_tienda)
            )";
            $params[':id_tienda'] = (int) $filters['id_tienda'];
        }

        $sql = "SELECT
                    vd.tipo_linea,
                    CASE vd.tipo_linea
                        WHEN 'joya' THEN p.desc_pieza
                        ELSE i.nombre
                    END AS nombre_item,
                    COUNT(vd.id_venta_detalle) AS num_lineas,
                    COALESCE(SUM($qtyExpr), 0) AS cantidad_vendida,
                    COALESCE(SUM(vd.subtotal), 0) AS monto_total,
                    COALESCE(AVG(vd.precio_unitario), 0) AS precio_promedio
                FROM venta_detalle vd
                INNER JOIN ventas v ON vd.id_venta_FK = v.id_venta
                LEFT JOIN piezas_stock ps ON vd.id_pieza_stock_FK = ps.id_pieza_stock
                LEFT JOIN piezas p ON ps.id_pieza_FK = p.id_pieza
                LEFT JOIN insumos i ON vd.id_insumo_FK = i.id_insumo
                WHERE $where $extra
                GROUP BY vd.tipo_linea, nombre_item
                ORDER BY COALESCE(SUM(vd.subtotal), 0) DESC
                LIMIT :limit";

        $stmt = $db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'tipo_linea' => (string) ($r['tipo_linea'] ?? 'joya'),
                'nombre' => (string) ($r['nombre_item'] ?? ''),
                'num_lineas' => (int) ($r['num_lineas'] ?? 0),
                'cantidad_vendida' => (float) ($r['cantidad_vendida'] ?? 0),
                'monto_total' => (float) ($r['monto_total'] ?? 0),
                'precio_promedio' => (float) ($r['precio_promedio'] ?? 0),
            ];
        }

        return $out;
    }

    public function getRankingEmpleados(array $filters, int $limit = 10): array
    {
        $db = $this->getDb();
        $params = [];
        $where = $this->buildVentasWhere($filters, $params);
        $params[':limit'] = $limit;

        $sql = "SELECT
                    e.id_empleado,
                    CONCAT(u.nombre, ' ', u.primer_apellido) AS empleado,
                    COUNT(v.id_venta) AS num_ventas,
                    COALESCE(SUM(v.total), 0) AS total_ventas,
                    COALESCE(AVG(v.total), 0) AS promedio_venta
                FROM ventas v
                INNER JOIN empleados e ON v.id_empleado_FK = e.id_empleado
                INNER JOIN usuarios u ON e.id_usuario_FK = u.id_usuario
                WHERE $where
                GROUP BY e.id_empleado, empleado
                ORDER BY COALESCE(SUM(v.total), 0) DESC
                LIMIT :limit";

        $stmt = $db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTasaDevolucionPorEmpleado(array $filters, int $limit = 10): array
    {
        $db = $this->getDb();
        $params = [];
        $where = $this->buildVentasWhere($filters, $params);
        $params[':limit'] = $limit;

        $sql = "SELECT
                    e.id_empleado,
                    CONCAT(u.nombre, ' ', u.primer_apellido) AS empleado,
                    COUNT(DISTINCT v.id_venta) AS total_ventas,
                    COUNT(DISTINCT d.id_venta_FK) AS ventas_devueltas,
                    ROUND(COUNT(DISTINCT d.id_venta_FK) * 100.0 / COUNT(DISTINCT v.id_venta), 2) AS tasa_devolucion_pct
                FROM empleados e
                INNER JOIN usuarios u ON e.id_usuario_FK = u.id_usuario
                INNER JOIN ventas v ON e.id_empleado = v.id_empleado_FK
                LEFT JOIN devoluciones d ON v.id_venta = d.id_venta_FK
                WHERE $where
                GROUP BY e.id_empleado, empleado
                HAVING COUNT(DISTINCT v.id_venta) > 0
                ORDER BY tasa_devolucion_pct DESC
                LIMIT :limit";

        $stmt = $db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getFilterOptions(): array
    {
        $db = $this->getDb();

        $empleados = $db->query(
            "SELECT e.id_empleado,
                    CONCAT(u.nombre, ' ', u.primer_apellido) AS nombre
             FROM empleados e
             INNER JOIN usuarios u ON e.id_usuario_FK = u.id_usuario
             WHERE COALESCE(e.activo, 1) = 1
             ORDER BY u.primer_apellido ASC, u.nombre ASC"
        )->fetchAll(PDO::FETCH_ASSOC);

        $tiendas = [];
        try {
            $tiendas = $db->query(
                "SELECT id_tienda, nom_tienda AS nombre
                 FROM tiendas
                 WHERE COALESCE(activo, 1) = 1
                 ORDER BY nom_tienda ASC"
            )->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            // Tiendas puede no existir o no tener ese esquema; el frontend lo oculta si llega vacío.
            $tiendas = [];
        }

        return [
            'empleados' => $empleados,
            'tiendas' => $tiendas,
        ];
    }

    /**
     * @return array{ingresos_lineas: float, costo_vendido: float}
     */
    private function getMargenVentas(array $filters): array
    {
        $db = $this->getDb();
        $params = [];
        $where = $this->buildVentasWhere($filters, $params);

        $qtyExpr = $this->ventaDetalleHasCantidadColumn()
            ? 'COALESCE(vd.cantidad, 1)'
            : '1';

        $extra = '';
        if (!empty($filters['id_tienda'])) {
            $extra = " AND (
                vd.tipo_linea <> 'insumo'
                OR (vd.tipo_linea = 'insumo' AND vd.id_tienda_FK = :id_tienda)
            )";
            $params[':id_tienda'] = (int) $filters['id_tienda'];
        }

        $sql = "SELECT
                    COALESCE(SUM(vd.subtotal), 0) AS ingresos_lineas,
                    COALESCE(SUM(
                        CASE vd.tipo_linea
                            WHEN 'joya' THEN COALESCE(p.costo, 0) * $qtyExpr
                            ELSE COALESCE(i.costo_referencia, 0) * $qtyExpr
                        END
                    ), 0) AS costo_vendido
                FROM venta_detalle vd
                INNER JOIN ventas v ON vd.id_venta_FK = v.id_venta
                LEFT JOIN piezas_stock ps ON vd.id_pieza_stock_FK = ps.id_pieza_stock
                LEFT JOIN piezas p ON ps.id_pieza_FK = p.id_pieza
                LEFT JOIN insumos i ON vd.id_insumo_FK = i.id_insumo
                WHERE $where $extra";

        $stmt = $db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'ingresos_lineas' => (float) ($row['ingresos_lineas'] ?? 0),
            'costo_vendido' => (float) ($row['costo_vendido'] ?? 0),
        ];
    }

    private function getGastosPeriodo(string $from, string $to): float
    {
        $db = $this->getDb();
        $sql = 'SELECT COALESCE(SUM(g.monto), 0) AS total
                FROM gastos g
                WHERE DATE(g.fecha_gasto) BETWEEN :from AND :to';
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':from', $from, PDO::PARAM_STR);
        $stmt->bindValue(':to', $to, PDO::PARAM_STR);
        $stmt->execute();

        return (float) ($stmt->fetchColumn() ?: 0);
    }

    private function getDevolucionesPeriodo(array $filters): float
    {
        $db = $this->getDb();
        $params = [
            ':from' => $filters['from'],
            ':to' => $filters['to'],
        ];

        $clauses = ['DATE(d.fecha_devolucion) BETWEEN :from AND :to'];

        if (!empty($filters['id_empleado'])) {
            $clauses[] = "v.estado = 'completada'";
            $clauses[] = 'v.id_empleado_FK = :id_empleado';
            $params[':id_empleado'] = (int) $filters['id_empleado'];
        }

        $where = implode(' AND ', $clauses);
        $joinVentas = !empty($filters['id_empleado'])
            ? 'INNER JOIN ventas v ON d.id_venta_FK = v.id_venta'
            : '';

        $sql = "SELECT COALESCE(SUM(d.monto_reembolso), 0) AS total
                FROM devoluciones d
                $joinVentas
                WHERE $where";

        $stmt = $db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();

        return (float) ($stmt->fetchColumn() ?: 0);
    }

    /**
     * Utilidad operativa: margen de ventas (ingresos − COGS) − gastos − devoluciones.
     *
     * @return array{
     *   ingresos_lineas: float,
     *   costo_vendido: float,
     *   margen_bruto: float,
     *   gastos: float,
     *   devoluciones: float,
     *   ganancia_neta: float,
     *   margen_bruto_pct: float
     * }
     */
    public function getGananciaNeta(array $filters): array
    {
        if (!isset($filters['from'], $filters['to'])) {
            throw new InvalidArgumentException('Se requiere rango from/to para ganancia neta.');
        }

        $margen = $this->getMargenVentas($filters);
        $ingresos = $margen['ingresos_lineas'];
        $costo = $margen['costo_vendido'];
        $margenBruto = $ingresos - $costo;
        $gastos = $this->getGastosPeriodo((string) $filters['from'], (string) $filters['to']);
        $devoluciones = $this->getDevolucionesPeriodo($filters);
        $gananciaNeta = $margenBruto - $gastos - $devoluciones;

        return [
            'ingresos_lineas' => round($ingresos, 2),
            'costo_vendido' => round($costo, 2),
            'margen_bruto' => round($margenBruto, 2),
            'gastos' => round($gastos, 2),
            'devoluciones' => round($devoluciones, 2),
            'ganancia_neta' => round($gananciaNeta, 2),
            'margen_bruto_pct' => $ingresos > 0
                ? round(($margenBruto / $ingresos) * 100.0, 2)
                : 0.0,
        ];
    }
}

