<?php

require_once __DIR__ . '/../../sistema.class.php';

/**
 * Capital invertido en inventario disponible (costo × unidades por pieza de catálogo).
 */
class CapitalInventario extends Sistema
{
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
     * @return array<int, array{id_familia: int, nom_familia: string}>
     */
    public function listarFamiliasActivas(): array
    {
        return $this->getDb()->query(
            'SELECT id_familia, nom_familia FROM familias WHERE activo = 1 ORDER BY nom_familia ASC'
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listarDetalle(int $idTienda = 0, int $idFamilia = 0): array
    {
        $sql = "SELECT f.id_familia,
                       f.nom_familia,
                       sf.nom_sub_familia,
                       p.id_pieza,
                       p.desc_pieza,
                       m.nom_metal,
                       t.nom_tienda,
                       p.costo AS costo_unitario,
                       COUNT(ps.id_pieza_stock) AS unidades,
                       (COUNT(ps.id_pieza_stock) * p.costo) AS costo_total
                FROM piezas_stock ps
                INNER JOIN piezas p ON p.id_pieza = ps.id_pieza_FK AND p.activo = 1
                INNER JOIN sub_familia sf ON sf.id_sub_familia = p.id_sub_familia_FK AND sf.activo = 1
                INNER JOIN familias f ON f.id_familia = sf.id_familia_FK AND f.activo = 1
                INNER JOIN metales m ON m.id_metal = p.id_metal_FK
                INNER JOIN tiendas t ON t.id_tienda = p.id_tienda_FK
                WHERE ps.activo = 1
                  AND ps.estado = 'disponible'";

        if ($idTienda > 0) {
            $sql .= ' AND p.id_tienda_FK = :id_tienda';
        }
        if ($idFamilia > 0) {
            $sql .= ' AND f.id_familia = :id_familia';
        }

        $sql .= ' GROUP BY f.id_familia, f.nom_familia, sf.nom_sub_familia, p.id_pieza, p.desc_pieza,
                         m.nom_metal, t.nom_tienda, p.costo
                  HAVING unidades > 0
                  ORDER BY f.nom_familia ASC, p.desc_pieza ASC';

        $stmt = $this->getDb()->prepare($sql);
        if ($idTienda > 0) {
            $stmt->bindValue(':id_tienda', $idTienda, PDO::PARAM_INT);
        }
        if ($idFamilia > 0) {
            $stmt->bindValue(':id_familia', $idFamilia, PDO::PARAM_INT);
        }
        $stmt->execute();

        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($filas as &$fila) {
            $fila['unidades'] = (int) ($fila['unidades'] ?? 0);
            $fila['costo_unitario'] = (float) ($fila['costo_unitario'] ?? 0);
            $fila['costo_total'] = (float) ($fila['costo_total'] ?? 0);
        }
        unset($fila);

        return $filas;
    }

    /**
     * @param array<int, array<string, mixed>> $filas
     * @return array{
     *   total_costo: float,
     *   total_unidades: int,
     *   num_familias: int,
     *   num_piezas: int,
     *   subtotales_familia: array<int, array{id_familia: int, nom_familia: string, unidades: int, costo_total: float}>
     * }
     */
    public function obtenerResumen(array $filas): array
    {
        $totalCosto = 0.0;
        $totalUnidades = 0;
        $subtotales = [];

        foreach ($filas as $fila) {
            $idFamilia = (int) ($fila['id_familia'] ?? 0);
            $unidades = (int) ($fila['unidades'] ?? 0);
            $costoTotal = (float) ($fila['costo_total'] ?? 0);

            $totalUnidades += $unidades;
            $totalCosto += $costoTotal;

            if (!isset($subtotales[$idFamilia])) {
                $subtotales[$idFamilia] = [
                    'id_familia' => $idFamilia,
                    'nom_familia' => (string) ($fila['nom_familia'] ?? ''),
                    'unidades' => 0,
                    'costo_total' => 0.0,
                ];
            }
            $subtotales[$idFamilia]['unidades'] += $unidades;
            $subtotales[$idFamilia]['costo_total'] += $costoTotal;
        }

        return [
            'total_costo' => $totalCosto,
            'total_unidades' => $totalUnidades,
            'num_familias' => count($subtotales),
            'num_piezas' => count($filas),
            'subtotales_familia' => array_values($subtotales),
        ];
    }

    public function resolverNombreTienda(array $tiendasActivas, int $idTienda): string
    {
        if ($idTienda <= 0) {
            return 'Todas';
        }
        foreach ($tiendasActivas as $tienda) {
            if ((int) ($tienda['id_tienda'] ?? 0) === $idTienda) {
                return (string) ($tienda['nom_tienda'] ?? '');
            }
        }
        return 'Tienda #' . $idTienda;
    }

    public function resolverNombreFamilia(array $familiasActivas, int $idFamilia): string
    {
        if ($idFamilia <= 0) {
            return 'Todas';
        }
        foreach ($familiasActivas as $familia) {
            if ((int) ($familia['id_familia'] ?? 0) === $idFamilia) {
                return (string) ($familia['nom_familia'] ?? '');
            }
        }
        return 'Familia #' . $idFamilia;
    }
}
