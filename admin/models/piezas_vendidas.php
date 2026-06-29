<?php
require_once __DIR__ . '/../../sistema.class.php';
require_once __DIR__ . '/../includes/list_search.php';

class PiezasVendidas extends Sistema
{
    private const FACTOR_OBJETIVO = 0.5;

    /**
     * @return array<int, array{id_tienda: int, nom_tienda: string}>
     */
    public function listarTiendasActivas(): array
    {
        return $this->getDb()->query(
            'SELECT id_tienda, nom_tienda FROM tiendas WHERE activo = 1 ORDER BY nom_tienda ASC'
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Sugerencia de resurtido: demanda reciente + stock bajo o agotado.
     *
     * @return array<int, array<string, mixed>>
     */
    public function leer(
        ?string $busqueda = null,
        int $dias = 90,
        int $stockMax = 2,
        ?int $idTienda = null,
    ): array {
        $dias = max(1, min(365, $dias));
        $stockMax = max(0, min(99, $stockMax));
        $pat = joyeria_like_pattern($busqueda);

        $sql = "SELECT p.id_pieza,
                       p.desc_pieza,
                       sf.nom_sub_familia,
                       m.nom_metal,
                       IFNULL(pr.razon_social, '') AS razon_social,
                       t.nom_tienda,
                       COALESCE(stock_total.stock_actual, 0) AS stock_actual,
                       ventas_periodo.ventas_periodo,
                       ventas_periodo.ultima_venta
                FROM piezas p
                INNER JOIN sub_familia sf ON sf.id_sub_familia = p.id_sub_familia_FK
                INNER JOIN metales m ON m.id_metal = p.id_metal_FK
                LEFT JOIN proveedores pr ON pr.id_proveedor = p.id_proveedor_FK
                INNER JOIN tiendas t ON t.id_tienda = p.id_tienda_FK
                LEFT JOIN (
                    SELECT ps.id_pieza_FK AS id_pieza,
                           COUNT(*) AS stock_actual
                    FROM piezas_stock ps
                    WHERE ps.activo = 1
                      AND ps.estado = 'disponible'
                    GROUP BY ps.id_pieza_FK
                ) stock_total ON stock_total.id_pieza = p.id_pieza
                INNER JOIN (
                    SELECT ps.id_pieza_FK AS id_pieza,
                           COUNT(DISTINCT v.id_venta) AS ventas_periodo,
                           MAX(v.fecha_venta) AS ultima_venta
                    FROM venta_detalle vd
                    INNER JOIN ventas v ON v.id_venta = vd.id_venta_FK
                    INNER JOIN piezas_stock ps ON ps.id_pieza_stock = vd.id_pieza_stock_FK
                    WHERE v.estado = 'completada'
                      AND vd.tipo_linea = 'joya'
                      AND COALESCE(vd.anulada, 0) = 0
                      AND v.fecha_venta >= DATE_SUB(CURDATE(), INTERVAL :dias DAY)
                    GROUP BY ps.id_pieza_FK
                    HAVING COUNT(DISTINCT v.id_venta) >= 1
                ) ventas_periodo ON ventas_periodo.id_pieza = p.id_pieza
                WHERE p.activo = 1
                  AND (
                      COALESCE(stock_total.stock_actual, 0) = 0
                      OR COALESCE(stock_total.stock_actual, 0) <= :stock_max
                  )";

        if ($idTienda !== null && $idTienda > 0) {
            $sql .= ' AND p.id_tienda_FK = :id_tienda';
        }

        if ($pat !== null) {
            $sql .= " AND (
                CAST(p.id_pieza AS CHAR) LIKE :busq
                OR p.desc_pieza LIKE :busq2
                OR sf.nom_sub_familia LIKE :busq3
                OR m.nom_metal LIKE :busq4
                OR IFNULL(pr.razon_social, '') LIKE :busq5
                OR t.nom_tienda LIKE :busq6
                OR CAST(COALESCE(stock_total.stock_actual, 0) AS CHAR) LIKE :busq7
                OR CAST(ventas_periodo.ventas_periodo AS CHAR) LIKE :busq8
            )";
        }

        $sql .= ' ORDER BY p.id_pieza DESC';

        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(':dias', $dias, PDO::PARAM_INT);
        $stmt->bindValue(':stock_max', $stockMax, PDO::PARAM_INT);

        if ($idTienda !== null && $idTienda > 0) {
            $stmt->bindValue(':id_tienda', $idTienda, PDO::PARAM_INT);
        }

        if ($pat !== null) {
            $stmt->bindValue(':busq', $pat, PDO::PARAM_STR);
            $stmt->bindValue(':busq2', $pat, PDO::PARAM_STR);
            $stmt->bindValue(':busq3', $pat, PDO::PARAM_STR);
            $stmt->bindValue(':busq4', $pat, PDO::PARAM_STR);
            $stmt->bindValue(':busq5', $pat, PDO::PARAM_STR);
            $stmt->bindValue(':busq6', $pat, PDO::PARAM_STR);
            $stmt->bindValue(':busq7', $pat, PDO::PARAM_STR);
            $stmt->bindValue(':busq8', $pat, PDO::PARAM_STR);
        }

        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return self::calcularSugeridoYOrden($rows, $stockMax);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    public static function calcularSugeridoYOrden(array $rows, int $stockMax): array
    {
        foreach ($rows as &$row) {
            $stock = (int) ($row['stock_actual'] ?? 0);
            $ventas = (int) ($row['ventas_periodo'] ?? 0);
            $objetivo = max($stockMax, (int) ceil($ventas * self::FACTOR_OBJETIVO));
            $row['sugerido_comprar'] = max(0, $objetivo - $stock);
            $row['prioridad'] = $ventas / ($stock + 1);
        }
        unset($row);

        usort($rows, static function (array $a, array $b): int {
            $agotadoA = (int) ($a['stock_actual'] ?? 0) === 0 ? 0 : 1;
            $agotadoB = (int) ($b['stock_actual'] ?? 0) === 0 ? 0 : 1;
            if ($agotadoA !== $agotadoB) {
                return $agotadoA <=> $agotadoB;
            }

            $prioridadA = (float) ($a['prioridad'] ?? 0);
            $prioridadB = (float) ($b['prioridad'] ?? 0);
            if ($prioridadB !== $prioridadA) {
                return $prioridadB <=> $prioridadA;
            }

            $ventasA = (int) ($a['ventas_periodo'] ?? 0);
            $ventasB = (int) ($b['ventas_periodo'] ?? 0);
            if ($ventasB !== $ventasA) {
                return $ventasB <=> $ventasA;
            }

            return (int) ($b['id_pieza'] ?? 0) <=> (int) ($a['id_pieza'] ?? 0);
        });

        return $rows;
    }
}

