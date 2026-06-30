<?php
require_once __DIR__ . "/../../sistema.class.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/list_search.php";
require_once __DIR__ . "/../../includes/pieza_dimension_helpers.php";

class Pieza extends Sistema
{
    /** Minimo de unidades en estado disponible para comprar en tienda online (carrito/checkout). */
    public const MIN_STOCK_DISPONIBLE_CATALOGO_ONLINE = 1;

    /** @var int[] IDs de stock creados en la ultima operacion masiva. */
    public array $ultimosStockIdsCreados = [];

    public static function esComprableOnlinePorStock(int $stockDisponible): bool
    {
        return $stockDisponible >= self::MIN_STOCK_DISPONIBLE_CATALOGO_ONLINE;
    }

    /**
     * Cuenta filas de piezas_stock activas y disponibles para una pieza de catalogo.
     */
    public function contarStockDisponible(int $idPieza): int
    {
        if ($idPieza <= 0) {
            return 0;
        }
        $stmt = $this->getDb()->prepare(
            "SELECT COUNT(*) FROM piezas_stock
             WHERE id_pieza_FK = :id
               AND activo = 1
               AND estado = 'disponible'"
        );
        $stmt->bindValue(':id', $idPieza, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    /**
     * Precio de venta minimo entre unidades disponibles (variantes con grilla).
     */
    public function precioMinimoStockDisponible(int $idPieza): ?float
    {
        if ($idPieza <= 0) {
            return null;
        }
        $stmt = $this->getDb()->prepare(
            "SELECT MIN(ps.precio_venta)
             FROM piezas_stock ps
             WHERE ps.id_pieza_FK = :id
               AND ps.activo = 1
               AND ps.estado = 'disponible'
               AND ps.precio_venta IS NOT NULL
               AND ps.precio_venta > 0"
        );
        $stmt->bindValue(':id', $idPieza, PDO::PARAM_INT);
        $stmt->execute();
        $min = $stmt->fetchColumn();
        if ($min === false || $min === null || !is_numeric($min)) {
            return null;
        }
        $valor = (float) $min;

        return $valor > 0.009 ? $valor : null;
    }

    private function selectPrecioMinStockDisponibleSql(): string
    {
        return "(SELECT MIN(ps.precio_venta)
                 FROM piezas_stock ps
                 WHERE ps.id_pieza_FK = p.id_pieza
                   AND ps.activo = 1
                   AND ps.estado = 'disponible'
                   AND ps.precio_venta IS NOT NULL
                   AND ps.precio_venta > 0) AS precio_min_stock_disponible";
    }

    public function tieneColumnasVarianteStock(): bool
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        try {
            $stmt = $this->getDb()->query("SHOW COLUMNS FROM piezas_stock LIKE 'variante_tipo'");
            $cache = (bool) $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            $cache = false;
        }

        return $cache;
    }

    public function tieneColumnasVarianteMatriz(): bool
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        try {
            $stmt = $this->getDb()->query("SHOW COLUMNS FROM piezas_stock LIKE 'variante_talla'");
            $cache = (bool) $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            $cache = false;
        }

        return $cache;
    }

    public function tieneColumnasVarianteCatalogo(): bool
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        require_once __DIR__ . '/../../includes/variantes_stock_helpers.php';
        $cache = joyeria_tiene_columnas_variante_catalogo($this->getDb());

        return $cache;
    }

    /**
     * @return array<string, mixed>
     */
    private function resumenVariantesVacio(): array
    {
        return [
            'tiene_variantes' => false,
            'modo' => 'ninguna',
            'variante_tipo' => null,
            'variante_etiqueta' => '',
            'variantes' => [],
            'ejes' => [],
            'colores' => [],
            'tallas' => [],
            'matriz' => [],
            'matriz_ids' => [],
            'matriz_precios' => [],
        ];
    }

    /**
     * Resumen de unidades disponibles por talla o color (tienda en linea).
     *
     * @return array{
     *     tiene_variantes: bool,
     *     variante_tipo: ?string,
     *     variante_etiqueta: string,
     *     variantes: list<array{valor: string, cantidad: int, variante_tipo: string}>
     * }
     */
    public function resumenVariantesDisponibles(int $idPieza): array
    {
        $vacio = $this->resumenVariantesVacio();
        if ($idPieza <= 0 || !$this->tieneColumnasVarianteStock()) {
            return $vacio;
        }

        $mapa = $this->mapaResumenVariantesPorPiezas([$idPieza]);

        return $mapa[$idPieza] ?? $vacio;
    }

    /**
     * @param array<int, array<string, mixed>> $piezas
     * @return array<int, array<string, mixed>>
     */
    public function adjuntarResumenVariantesCatalogo(array $piezas): array
    {
        if ($piezas === []) {
            return $piezas;
        }

        $vacio = $this->resumenVariantesVacio();

        if (!$this->tieneColumnasVarianteStock()) {
            foreach ($piezas as &$row) {
                if (is_array($row)) {
                    $row['variantes_resumen'] = $vacio;
                }
            }
            unset($row);

            return $piezas;
        }

        $ids = [];
        foreach ($piezas as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = (int) ($row['id_pieza'] ?? 0);
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        $mapa = $this->mapaResumenVariantesPorPiezas(array_values($ids));

        foreach ($piezas as &$row) {
            if (!is_array($row)) {
                continue;
            }
            $id = (int) ($row['id_pieza'] ?? 0);
            $row['variantes_resumen'] = $mapa[$id] ?? $vacio;
        }
        unset($row);

        return $piezas;
    }

    /**
     * @param list<int> $idsPieza
     * @return array<int, array<string, mixed>>
     */
    public function mapaResumenVariantesPorPiezas(array $idsPieza): array
    {
        $vacio = $this->resumenVariantesVacio();
        $idsPieza = array_values(array_unique(array_filter(array_map('intval', $idsPieza), static fn (int $id): bool => $id > 0)));
        if ($idsPieza === [] || !$this->tieneColumnasVarianteStock()) {
            return [];
        }

        require_once __DIR__ . '/../../includes/variantes_stock_helpers.php';

        $usaCatalogo = $this->tieneColumnasVarianteCatalogo();
        $joinSql = $usaCatalogo ? joyeria_sql_join_variantes_stock('ps') : '';
        $selectCatalogo = $usaCatalogo ? ', ' . joyeria_sql_select_variantes_stock() : '';
        $colsIds = $usaCatalogo ? ', ps.variante_valor1_id, ps.variante_valor2_id' : '';
        $colsMatriz = $this->tieneColumnasVarianteMatriz()
            ? ', TRIM(COALESCE(ps.variante_talla, \'\')) AS variante_talla, TRIM(COALESCE(ps.variante_color, \'\')) AS variante_color'
            : '';

        $placeholders = implode(',', array_fill(0, count($idsPieza), '?'));
        $sql = "SELECT ps.id_pieza_FK AS id_pieza,
                       ps.variante_tipo,
                       TRIM(COALESCE(ps.variante_valor, '')) AS variante_valor{$colsMatriz}{$colsIds}{$selectCatalogo},
                       COUNT(*) AS cantidad,
                       MIN(CASE
                           WHEN ps.precio_venta IS NOT NULL AND CAST(ps.precio_venta AS DECIMAL(12,2)) > 0
                           THEN CAST(ps.precio_venta AS DECIMAL(12,2))
                           ELSE NULL
                       END) AS precio_venta_min
                FROM piezas_stock ps
                {$joinSql}
                WHERE ps.id_pieza_FK IN ({$placeholders})
                  AND ps.activo = 1
                  AND ps.estado = 'disponible'
                GROUP BY ps.id_pieza_FK, ps.variante_tipo, variante_valor";
        if ($usaCatalogo) {
            $sql .= ', ps.variante_valor1_id, ps.variante_valor2_id';
            $sql .= ', vt1.nombre, vv1.valor, vt1.es_talla, vt1.slug';
            $sql .= ', vt2.nombre, vv2.valor, vt2.es_talla, vt2.slug';
        }
        if ($this->tieneColumnasVarianteMatriz()) {
            $sql .= ', variante_talla, variante_color';
        }

        $stmt = $this->getDb()->prepare($sql);
        foreach ($idsPieza as $i => $id) {
            $stmt->bindValue($i + 1, $id, PDO::PARAM_INT);
        }
        $stmt->execute();
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $raw = [];
        foreach ($filas as $fila) {
            if (!is_array($fila)) {
                continue;
            }
            $idPieza = (int) ($fila['id_pieza'] ?? 0);
            $cant = (int) ($fila['cantidad'] ?? 0);
            if ($idPieza <= 0 || $cant <= 0) {
                continue;
            }

            $ejes = joyeria_extraer_ejes_stock($fila);
            if ($ejes === []) {
                continue;
            }

            $valor1Id = (int) ($fila['variante_valor1_id'] ?? 0);
            $valor2Id = (int) ($fila['variante_valor2_id'] ?? 0);

            if (!isset($raw[$idPieza])) {
                $raw[$idPieza] = [];
            }
            $precioVenta = isset($fila['precio_venta_min']) && $fila['precio_venta_min'] !== null && $fila['precio_venta_min'] !== ''
                ? (float) $fila['precio_venta_min']
                : null;
            if ($precioVenta !== null && $precioVenta <= 0) {
                $precioVenta = null;
            }

            $raw[$idPieza][] = [
                'ejes' => $ejes,
                'valor1_id' => $valor1Id > 0 ? $valor1Id : null,
                'valor2_id' => $valor2Id > 0 ? $valor2Id : null,
                'cantidad' => $cant,
                'precio_venta' => $precioVenta,
            ];
        }

        $out = [];
        foreach ($idsPieza as $idPieza) {
            $out[$idPieza] = $this->construirResumenVariantesDesdeFilas($raw[$idPieza] ?? [], $vacio);
        }

        return $out;
    }

    /**
     * @param list<array{ejes: list<array<string, mixed>>, valor1_id: ?int, valor2_id: ?int, cantidad: int}> $filas
     * @param array<string, mixed> $vacio
     * @return array<string, mixed>
     */
    private function construirResumenVariantesDesdeFilas(array $filas, array $vacio): array
    {
        if ($filas === []) {
            return $vacio;
        }

        $maxEjes = 0;
        foreach ($filas as $fila) {
            $maxEjes = max($maxEjes, count($fila['ejes'] ?? []));
        }
        if ($maxEjes >= 2) {
            return $this->construirResumenDosEjes($filas);
        }
        if ($maxEjes === 1) {
            return $this->construirResumenUnEje($filas);
        }

        return $vacio;
    }

    /**
     * @param list<array{ejes: list<array<string, mixed>>, valor1_id: ?int, valor2_id: ?int, cantidad: int}> $filas
     * @return array<string, mixed>
     */
    private function construirResumenDosEjes(array $filas): array
    {
        $meta1 = null;
        $meta2 = null;
        foreach ($filas as $fila) {
            $ejes = $fila['ejes'] ?? [];
            if (count($ejes) >= 2) {
                $meta1 = $ejes[0];
                $meta2 = $ejes[1];
                break;
            }
        }
        if ($meta1 === null || $meta2 === null) {
            return $this->resumenVariantesVacio();
        }

        $matriz = [];
        $matrizIds = [];
        $matrizPrecios = [];
        $variantes = [];

        foreach ($filas as $fila) {
            $ejes = $fila['ejes'] ?? [];
            if (count($ejes) < 2) {
                continue;
            }
            $v1 = trim((string) ($ejes[0]['valor'] ?? ''));
            $v2 = trim((string) ($ejes[1]['valor'] ?? ''));
            $cant = (int) ($fila['cantidad'] ?? 0);
            $precioVenta = isset($fila['precio_venta']) && $fila['precio_venta'] !== null
                ? (float) $fila['precio_venta']
                : null;
            if ($v1 === '' || $v2 === '' || $cant <= 0) {
                continue;
            }

            if (!isset($matriz[$v1])) {
                $matriz[$v1] = [];
            }
            $matriz[$v1][$v2] = ($matriz[$v1][$v2] ?? 0) + $cant;
            if ($precioVenta !== null && $precioVenta > 0) {
                if (!isset($matrizPrecios[$v1])) {
                    $matrizPrecios[$v1] = [];
                }
                $prev = $matrizPrecios[$v1][$v2] ?? null;
                $matrizPrecios[$v1][$v2] = $prev === null
                    ? $precioVenta
                    : min((float) $prev, $precioVenta);
            }

            $id1 = $fila['valor1_id'] ?? null;
            $id2 = $fila['valor2_id'] ?? null;
            if ($id1 !== null && $id2 !== null) {
                if (!isset($matrizIds[$id1])) {
                    $matrizIds[$id1] = [];
                }
                $matrizIds[$id1][$id2] = ($matrizIds[$id1][$id2] ?? 0) + $cant;
            }

            $prefT2 = !empty($ejes[1]['es_talla']) ? 'T' : '';
            $colorVal = $v1;
            $tallaVal = $v2;
            if (!empty($ejes[0]['es_talla']) && empty($ejes[1]['es_talla'])) {
                $colorVal = $v2;
                $tallaVal = $v1;
            } elseif (trim((string) ($ejes[0]['slug'] ?? '')) === 'color') {
                $colorVal = $v1;
                $tallaVal = $v2;
            } elseif (trim((string) ($ejes[1]['slug'] ?? '')) === 'color') {
                $colorVal = $v2;
                $tallaVal = $v1;
            }
            $variantes[] = [
                'valor' => $v1 . ' · ' . $prefT2 . $v2,
                'valor1' => $v1,
                'valor2' => $v2,
                'color' => $colorVal,
                'talla' => $tallaVal,
                'valor1_id' => $id1,
                'valor2_id' => $id2,
                'cantidad' => $cant,
                'precio' => $precioVenta,
                'variante_tipo' => 'dos_ejes',
            ];
        }

        if ($variantes === []) {
            return $this->resumenVariantesVacio();
        }

        $valores1 = joyeria_ordenar_valores_natural(array_keys($matriz));
        if (!empty($meta2['es_talla'])) {
            $valores2 = joyeria_ordenar_valores_talla($this->valoresUnicosMatriz($matriz));
        } else {
            $valores2 = joyeria_ordenar_valores_natural($this->valoresUnicosMatriz($matriz));
        }

        $matrizOrdenada = [];
        $matrizPreciosOrdenada = [];
        foreach ($valores1 as $v1) {
            if (!isset($matriz[$v1]) || !is_array($matriz[$v1])) {
                continue;
            }
            $matrizOrdenada[$v1] = [];
            $matrizPreciosOrdenada[$v1] = [];
            foreach ($valores2 as $v2) {
                if (!isset($matriz[$v1][$v2])) {
                    continue;
                }
                $matrizOrdenada[$v1][$v2] = (int) $matriz[$v1][$v2];
                if (isset($matrizPrecios[$v1][$v2])) {
                    $matrizPreciosOrdenada[$v1][$v2] = (float) $matrizPrecios[$v1][$v2];
                }
            }
        }

        $etiqueta = trim((string) ($meta1['tipo'] ?? 'Opción')) . ' y ' . trim((string) ($meta2['tipo'] ?? 'Opción'));
        $modo = 'dos_ejes';
        $colores = [];
        $tallas = [];
        $ejesUi = [
            [
                'tipo' => (string) ($meta1['tipo'] ?? 'Opción'),
                'slug' => (string) ($meta1['slug'] ?? ''),
                'es_talla' => !empty($meta1['es_talla']),
                'valores' => $valores1,
            ],
            [
                'tipo' => (string) ($meta2['tipo'] ?? 'Opción'),
                'slug' => (string) ($meta2['slug'] ?? ''),
                'es_talla' => !empty($meta2['es_talla']),
                'valores' => $valores2,
            ],
        ];

        if ($this->esParLegacyColorTalla($meta1, $meta2)) {
            $modo = 'talla_color';
            $etiqueta = 'Color y talla';
            if (!empty($meta1['es_talla'])) {
                $matrizOrdenada = $this->invertirMatriz($matrizOrdenada);
                $colores = joyeria_ordenar_valores_natural(array_keys($matrizOrdenada));
                $tallas = joyeria_ordenar_valores_talla($this->valoresUnicosMatriz($matrizOrdenada));
                $ejesUi = [
                    [
                        'tipo' => (string) ($meta2['tipo'] ?? 'Color'),
                        'slug' => (string) ($meta2['slug'] ?? 'color'),
                        'es_talla' => false,
                        'valores' => $colores,
                    ],
                    [
                        'tipo' => (string) ($meta1['tipo'] ?? 'Talla'),
                        'slug' => (string) ($meta1['slug'] ?? 'talla'),
                        'es_talla' => true,
                        'valores' => $tallas,
                    ],
                ];
            } else {
                $colores = $valores1;
                $tallas = $valores2;
                $ejesUi[0]['valores'] = $colores;
                $ejesUi[1]['valores'] = $tallas;
            }
        }

        usort($variantes, static function (array $a, array $b): int {
            $c1 = strnatcasecmp((string) ($a['valor1'] ?? ''), (string) ($b['valor1'] ?? ''));
            if ($c1 !== 0) {
                return $c1;
            }

            return strnatcasecmp((string) ($a['valor2'] ?? ''), (string) ($b['valor2'] ?? ''));
        });

        return [
            'tiene_variantes' => true,
            'modo' => $modo,
            'variante_tipo' => $modo,
            'variante_etiqueta' => $etiqueta,
            'ejes' => $ejesUi,
            'variantes' => $variantes,
            'colores' => $colores,
            'tallas' => $tallas,
            'matriz' => $matrizOrdenada,
            'matriz_ids' => $matrizIds,
            'matriz_precios' => $matrizPreciosOrdenada,
        ];
    }

    /**
     * @param list<array{ejes: list<array<string, mixed>>, valor1_id: ?int, valor2_id: ?int, cantidad: int, precio_venta: ?float}> $filas
     * @return array<string, mixed>
     */
    private function construirResumenUnEje(array $filas): array
    {
        $meta = null;
        foreach ($filas as $fila) {
            $ejes = $fila['ejes'] ?? [];
            if ($ejes !== []) {
                $meta = $ejes[0];
                break;
            }
        }
        if ($meta === null) {
            return $this->resumenVariantesVacio();
        }

        $variantes = [];
        $valores = [];
        foreach ($filas as $fila) {
            $ejes = $fila['ejes'] ?? [];
            if ($ejes === []) {
                continue;
            }
            $valor = trim((string) ($ejes[0]['valor'] ?? ''));
            $cant = (int) ($fila['cantidad'] ?? 0);
            $precioVenta = isset($fila['precio_venta']) && $fila['precio_venta'] !== null
                ? (float) $fila['precio_venta']
                : null;
            if ($valor === '' || $cant <= 0) {
                continue;
            }
            $valores[] = $valor;
            $pref = !empty($ejes[0]['es_talla']) ? 'T' : '';
            $variantes[] = [
                'valor' => $pref . $valor,
                'valor1' => $valor,
                'valor1_id' => $fila['valor1_id'] ?? null,
                'cantidad' => $cant,
                'precio' => $precioVenta !== null && $precioVenta > 0 ? $precioVenta : null,
                'variante_tipo' => 'un_eje',
            ];
        }

        if ($variantes === []) {
            return $this->resumenVariantesVacio();
        }

        $slug = trim((string) ($meta['slug'] ?? ''));
        $esTalla = !empty($meta['es_talla']);
        $modo = 'un_eje';
        if ($esTalla) {
            $modo = 'talla';
        } elseif ($slug === 'color') {
            $modo = 'color';
        }

        $valoresOrden = $esTalla
            ? joyeria_ordenar_valores_talla($valores)
            : joyeria_ordenar_valores_natural($valores);

        $variantes = $this->ordenarFilasVariantesSimples($variantes, $esTalla ? 'talla' : 'color');
        $etiqueta = trim((string) ($meta['tipo'] ?? 'Opción'));

        return [
            'tiene_variantes' => true,
            'modo' => $modo,
            'variante_tipo' => $modo,
            'variante_etiqueta' => $etiqueta,
            'ejes' => [[
                'tipo' => $etiqueta,
                'slug' => $slug,
                'es_talla' => $esTalla,
                'valores' => $valoresOrden,
            ]],
            'variantes' => $variantes,
            'colores' => $modo === 'color' ? $valoresOrden : [],
            'tallas' => $modo === 'talla' ? $valoresOrden : [],
            'matriz' => [],
            'matriz_ids' => [],
        ];
    }

    /**
     * @param array<string, array<string, int>> $matriz
     * @return list<string>
     */
    private function valoresUnicosMatriz(array $matriz): array
    {
        $out = [];
        foreach ($matriz as $filas) {
            if (!is_array($filas)) {
                continue;
            }
            foreach (array_keys($filas) as $valor) {
                $out[] = (string) $valor;
            }
        }

        return $out;
    }

    /**
     * @param array<string, array<string, int>> $matriz
     * @return array<string, array<string, int>>
     */
    private function invertirMatriz(array $matriz): array
    {
        $out = [];
        foreach ($matriz as $k1 => $filas) {
            if (!is_array($filas)) {
                continue;
            }
            foreach ($filas as $k2 => $cant) {
                if (!isset($out[$k2])) {
                    $out[$k2] = [];
                }
                $out[$k2][$k1] = (int) $cant;
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $meta1
     * @param array<string, mixed> $meta2
     */
    private function esParLegacyColorTalla(array $meta1, array $meta2): bool
    {
        $slug1 = trim((string) ($meta1['slug'] ?? ''));
        $slug2 = trim((string) ($meta2['slug'] ?? ''));

        return ($slug1 === 'color' && !empty($meta2['es_talla']))
            || ($slug2 === 'color' && !empty($meta1['es_talla']));
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    private function ordenarFilasVariantesSimples(array $items, string $modo): array
    {
        usort($items, function (array $a, array $b) use ($modo): int {
            $va = (string) ($a['valor'] ?? '');
            $vb = (string) ($b['valor'] ?? '');
            if ($modo === 'talla') {
                $na = is_numeric($va) ? (float) $va : null;
                $nb = is_numeric($vb) ? (float) $vb : null;
                if ($na !== null && $nb !== null) {
                    return $na <=> $nb;
                }
            }

            return strnatcasecmp($va, $vb);
        });

        return $items;
    }

    private function condicionPiezaConFotoCatalogo(): string
    {
        return "EXISTS (
                    SELECT 1 FROM imagenes_piezas ip
                    WHERE ip.id_pieza_FK = p.id_pieza
                      AND ip.es_principal = 1
                      AND TRIM(ip.url_imagen) <> ''
                )";
    }

    private function normalizarDecimal($valor, int $decimales = 2): string
    {
        $numero = (float) $valor;
        $epsilon = pow(10, -($decimales + 3));
        $ajustado = $numero + ($numero >= 0 ? $epsilon : -$epsilon);
        return number_format(round($ajustado, $decimales), $decimales, '.', '');
    }

    public function leer(?string $busqueda = null, string $campo = 'global')
    {
        $pat = joyeria_like_pattern($busqueda);
        $sql = "SELECT p.id_pieza,
                       p.desc_pieza,
                       p.costo,
                       p.peso_gr,
                       p.precio_por_gramo,
                       p.aumento_pct,
                       p.largo,
                       p.ancho,
                       sf.nom_sub_familia,
                       m.nom_metal,
                       pr.razon_social,
                       t.nom_tienda,
                       img.url_imagen
                FROM piezas p
                INNER JOIN sub_familia sf ON sf.id_sub_familia = p.id_sub_familia_FK
                INNER JOIN metales m ON m.id_metal = p.id_metal_FK
                LEFT JOIN proveedores pr ON pr.id_proveedor = p.id_proveedor_FK
                INNER JOIN tiendas t ON t.id_tienda = p.id_tienda_FK
                LEFT JOIN imagenes_piezas img ON img.id_pieza_FK = p.id_pieza AND img.es_principal = 1
                WHERE p.activo = 1";
        if ($pat !== null) {
            switch ($campo) {
                case 'id':
                    $sql .= " AND CAST(p.id_pieza AS CHAR) LIKE :busq";
                    break;
                case 'descripcion':
                    $sql .= " AND p.desc_pieza LIKE :busq";
                    break;
                case 'subfamilia':
                    $sql .= " AND sf.nom_sub_familia LIKE :busq";
                    break;
                case 'metal':
                    $sql .= " AND m.nom_metal LIKE :busq";
                    break;
                case 'proveedor':
                    $sql .= " AND IFNULL(pr.razon_social, '') LIKE :busq";
                    break;
                case 'tienda':
                    $sql .= " AND t.nom_tienda LIKE :busq";
                    break;
                case 'global':
                default:
                    $sql .= " AND (
                        CAST(p.id_pieza AS CHAR) LIKE :busq OR p.desc_pieza LIKE :busq2 OR sf.nom_sub_familia LIKE :busq3
                        OR m.nom_metal LIKE :busq4 OR IFNULL(pr.razon_social, '') LIKE :busq5 OR t.nom_tienda LIKE :busq6
                    )";
                    break;
            }
        }
        $sql .= " ORDER BY p.id_pieza DESC";

        $stmt = $this->getDb()->prepare($sql);
        if ($pat !== null) {
            $stmt->bindValue(':busq', $pat, PDO::PARAM_STR);
            if ($campo === 'global') {
                $stmt->bindValue(':busq2', $pat, PDO::PARAM_STR);
                $stmt->bindValue(':busq3', $pat, PDO::PARAM_STR);
                $stmt->bindValue(':busq4', $pat, PDO::PARAM_STR);
                $stmt->bindValue(':busq5', $pat, PDO::PARAM_STR);
                $stmt->bindValue(':busq6', $pat, PDO::PARAM_STR);
            }
        }
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Piezas activas para el catálogo público (solo con foto principal).
     *
     * @return array<int, array<string, mixed>>
     */
    public function listarCatalogoPublico(): array
    {
        $sql = "SELECT p.id_pieza,
                       p.desc_pieza,
                       p.costo,
                       p.peso_gr,
                       p.precio_por_gramo,
                       p.aumento_pct,
                       p.largo,
                       p.ancho,
                       f.id_familia,
                       f.nom_familia,
                       sf.id_sub_familia,
                       sf.nom_sub_familia,
                       m.id_metal,
                       m.nom_metal,
                       pr.razon_social,
                       t.nom_tienda,
                       img.url_imagen,
                       " . $this->selectPrecioMinStockDisponibleSql() . "
                FROM piezas p
                INNER JOIN sub_familia sf ON sf.id_sub_familia = p.id_sub_familia_FK
                INNER JOIN familias f ON f.id_familia = sf.id_familia_FK AND f.activo = 1
                INNER JOIN metales m ON m.id_metal = p.id_metal_FK
                LEFT JOIN proveedores pr ON pr.id_proveedor = p.id_proveedor_FK
                INNER JOIN tiendas t ON t.id_tienda = p.id_tienda_FK
                LEFT JOIN imagenes_piezas img ON img.id_pieza_FK = p.id_pieza AND img.es_principal = 1
                WHERE p.activo = 1
                  AND " . $this->condicionPiezaConFotoCatalogo() . "
                ORDER BY f.nom_familia ASC, p.id_pieza DESC";

        $stmt = $this->getDb()->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Catálogo público filtrable por familia y subfamilia.
     *
     * Solo devuelve piezas con foto principal.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listarCatalogoPublicoFiltrado(?int $idFamilia = null, ?int $idSubFamilia = null): array
    {
        $sql = "SELECT p.id_pieza,
                       p.desc_pieza,
                       p.costo,
                       p.peso_gr,
                       p.precio_por_gramo,
                       p.aumento_pct,
                       p.largo,
                       p.ancho,
                       f.id_familia,
                       f.nom_familia,
                       sf.id_sub_familia,
                       sf.nom_sub_familia,
                       m.id_metal,
                       m.nom_metal,
                       pr.razon_social,
                       t.nom_tienda,
                       img.url_imagen,
                       " . $this->selectPrecioMinStockDisponibleSql() . "
                FROM piezas p
                INNER JOIN sub_familia sf ON sf.id_sub_familia = p.id_sub_familia_FK AND sf.activo = 1
                INNER JOIN familias f ON f.id_familia = sf.id_familia_FK AND f.activo = 1
                INNER JOIN metales m ON m.id_metal = p.id_metal_FK
                LEFT JOIN proveedores pr ON pr.id_proveedor = p.id_proveedor_FK
                INNER JOIN tiendas t ON t.id_tienda = p.id_tienda_FK
                LEFT JOIN imagenes_piezas img ON img.id_pieza_FK = p.id_pieza AND img.es_principal = 1
                WHERE p.activo = 1
                  AND " . $this->condicionPiezaConFotoCatalogo();

        if ($idFamilia !== null && $idFamilia > 0) {
            $sql .= " AND f.id_familia = :id_familia";
        }
        if ($idSubFamilia !== null && $idSubFamilia > 0) {
            $sql .= " AND sf.id_sub_familia = :id_sub_familia";
        }
        $sql .= " ORDER BY f.nom_familia ASC, sf.nom_sub_familia ASC, p.id_pieza DESC";

        $stmt = $this->getDb()->prepare($sql);
        if ($idFamilia !== null && $idFamilia > 0) {
            $stmt->bindValue(':id_familia', $idFamilia, PDO::PARAM_INT);
        }
        if ($idSubFamilia !== null && $idSubFamilia > 0) {
            $stmt->bindValue(':id_sub_familia', $idSubFamilia, PDO::PARAM_INT);
        }
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function leerUno($idPieza)
    {
        $sql = "SELECT p.*,
                       img.url_imagen
                FROM piezas p
                LEFT JOIN imagenes_piezas img ON img.id_pieza_FK = p.id_pieza AND img.es_principal = 1
                WHERE p.id_pieza = :id_pieza";

        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(':id_pieza', (int) $idPieza, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function leerImagenes($idPieza)
    {
        $sql = "SELECT id_imagen, id_pieza_FK, url_imagen, es_principal, fecha_alta
                FROM imagenes_piezas
                WHERE id_pieza_FK = :id_pieza
                ORDER BY es_principal DESC, id_imagen DESC";

        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(':id_pieza', (int) $idPieza, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerCatalogos()
    {
        return [
            'familias' => $this->getDb()->query("SELECT id_familia, nom_familia, usa_talla FROM familias WHERE activo = 1 ORDER BY nom_familia ASC")->fetchAll(PDO::FETCH_ASSOC),
            'subfamilias' => $this->getDb()->query("SELECT id_sub_familia, nom_sub_familia, id_familia_FK FROM sub_familia WHERE activo = 1 ORDER BY nom_sub_familia ASC")->fetchAll(PDO::FETCH_ASSOC),
            'metales' => $this->getDb()->query("SELECT id_metal, nom_metal FROM metales WHERE activo = 1 ORDER BY nom_metal ASC")->fetchAll(PDO::FETCH_ASSOC),
            'proveedores' => $this->getDb()->query("SELECT id_proveedor, razon_social FROM proveedores WHERE COALESCE(activo,1) = 1 ORDER BY razon_social ASC")->fetchAll(PDO::FETCH_ASSOC),
            'tiendas' => $this->getDb()->query("SELECT id_tienda, nom_tienda FROM tiendas WHERE COALESCE(activo,1) = 1 ORDER BY nom_tienda ASC")->fetchAll(PDO::FETCH_ASSOC),
        ];
    }

    public function crear($data, $archivoImagen = null, $opcionesStock = null)
    {
        $descripcion = $this->validarTexto($data, 'desc_pieza', 100, 'La descripcion de la pieza');
        $idSubfamilia = $this->validarEntero($data, 'id_sub_familia_FK', 'La subfamilia');
        $idMetal = $this->validarEntero($data, 'id_metal_FK', 'El metal');
        $idProveedor = $this->validarEnteroOpcional($data, 'id_proveedor_FK');
        $idTienda = $this->validarEntero($data, 'id_tienda_FK', 'La tienda');

        $observaciones = $this->validarTextoOpcional($data, 'observaciones', 65535);
        $largo = joyeria_normalizar_dimension_pieza('Alto', $this->validarTextoOpcional($data, 'largo', 50));
        $ancho = joyeria_normalizar_dimension_pieza('Ancho', $this->validarTextoOpcional($data, 'ancho', 50));
        $aumentoPct = $this->validarPorcentajeOpcional($data, 'aumento_pct');

        $calculo = $this->resolverCostoSegunMetodo($data);
        $peso = $calculo['peso'];
        $precioPorGramo = $calculo['precio_por_gramo'];
        $costo = $calculo['costo'];
        $aumentoPct = $this->calcularAumentoPctEfectivo($costo, $aumentoPct);

        $db = $this->getDb();
        $db->beginTransaction();

        try {
            auth_mysql_set_audit_vars($db);

            $stmt = $db->prepare(
                "INSERT INTO piezas
                (desc_pieza, id_sub_familia_FK, id_metal_FK, id_proveedor_FK, id_tienda_FK,
                 peso_gr, costo, precio_por_gramo, aumento_pct, largo, ancho, observaciones, activo)
                VALUES
                (:desc_pieza, :id_sub_familia_FK, :id_metal_FK, :id_proveedor_FK, :id_tienda_FK,
                 :peso_gr, :costo, :precio_por_gramo, :aumento_pct, :largo, :ancho, :observaciones, 1)"
            );

            $stmt->bindValue(':desc_pieza', $descripcion, PDO::PARAM_STR);
            $stmt->bindValue(':id_sub_familia_FK', $idSubfamilia, PDO::PARAM_INT);
            $stmt->bindValue(':id_metal_FK', $idMetal, PDO::PARAM_INT);
            $stmt->bindValue(':id_proveedor_FK', $idProveedor, $idProveedor === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(':id_tienda_FK', $idTienda, PDO::PARAM_INT);
            $stmt->bindValue(':peso_gr', $peso, $peso === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':costo', $costo, $costo === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':precio_por_gramo', $precioPorGramo, $precioPorGramo === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':aumento_pct', $aumentoPct, $aumentoPct === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':largo', $largo, $largo === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':ancho', $ancho, $ancho === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':observaciones', $observaciones, $observaciones === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->execute();

            $idPieza = (int) $db->lastInsertId();

            if ($archivoImagen !== null && isset($archivoImagen['tmp_name']) && is_uploaded_file($archivoImagen['tmp_name'])) {
                $rutaRelativa = $this->moverImagenSubida($archivoImagen, $this->obtenerDirectorioImagenesPiezas(), 'pieza', $idPieza);

                $stmtImagen = $db->prepare(
                    "INSERT INTO imagenes_piezas (id_pieza_FK, url_imagen, es_principal)
                     VALUES (:id_pieza_FK, :url_imagen, 1)"
                );
                $stmtImagen->bindValue(':id_pieza_FK', $idPieza, PDO::PARAM_INT);
                $stmtImagen->bindValue(':url_imagen', $this->construirUrlImagenPieza($rutaRelativa), PDO::PARAM_STR);
                $stmtImagen->execute();
            }

            if (is_array($opcionesStock) && (int) ($opcionesStock['cantidad'] ?? 0) > 0) {
                $precioVenta = $this->calcularPrecioVenta($costo, $aumentoPct);
                $opcionesStock['precio_venta'] = $precioVenta;
                $opcionesStock['aumento_pct'] = $aumentoPct;
                if ($costo === null) {
                    $opcionesStock['costo_desde_grilla'] = true;
                }
                $this->ultimosStockIdsCreados = $this->crearStockMasivo($idPieza, $opcionesStock);
            } else {
                $this->ultimosStockIdsCreados = [];
            }

            $db->commit();
            return $idPieza;
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public function actualizar($idPieza, $data, $archivoImagen = null)
    {
        $pieza = $this->leerUno($idPieza);
        if (!$pieza || (int) ($pieza['activo'] ?? 0) !== 1) {
            throw new RuntimeException('Pieza no encontrada o inactiva.');
        }

        $descripcion = $this->validarTexto($data, 'desc_pieza', 100, 'La descripcion de la pieza');
        $idSubfamilia = $this->validarEntero($data, 'id_sub_familia_FK', 'La subfamilia');
        $idMetal = $this->validarEntero($data, 'id_metal_FK', 'El metal');
        $idProveedor = $this->validarEnteroOpcional($data, 'id_proveedor_FK');
        $idTienda = $this->validarEntero($data, 'id_tienda_FK', 'La tienda');

        $observaciones = $this->validarTextoOpcional($data, 'observaciones', 65535);
        $largo = joyeria_normalizar_dimension_pieza('Alto', $this->validarTextoOpcional($data, 'largo', 50));
        $ancho = joyeria_normalizar_dimension_pieza('Ancho', $this->validarTextoOpcional($data, 'ancho', 50));
        $aumentoPct = $this->validarPorcentajeOpcional($data, 'aumento_pct');

        $calculo = $this->resolverCostoSegunMetodo($data);
        $peso = $calculo['peso'];
        $precioPorGramo = $calculo['precio_por_gramo'];
        $costo = $calculo['costo'];
        $aumentoPct = $this->calcularAumentoPctEfectivo($costo, $aumentoPct);

        $db = $this->getDb();
        $db->beginTransaction();

        try {
            auth_mysql_set_audit_vars($db);

            $stmt = $db->prepare(
                "UPDATE piezas
                 SET desc_pieza = :desc_pieza,
                     id_sub_familia_FK = :id_sub_familia_FK,
                     id_metal_FK = :id_metal_FK,
                     id_proveedor_FK = :id_proveedor_FK,
                     id_tienda_FK = :id_tienda_FK,
                     peso_gr = :peso_gr,
                     costo = :costo,
                     precio_por_gramo = :precio_por_gramo,
                     aumento_pct = :aumento_pct,
                     largo = :largo,
                     ancho = :ancho,
                     observaciones = :observaciones
                 WHERE id_pieza = :id_pieza AND activo = 1"
            );

            $stmt->bindValue(':desc_pieza', $descripcion, PDO::PARAM_STR);
            $stmt->bindValue(':id_sub_familia_FK', $idSubfamilia, PDO::PARAM_INT);
            $stmt->bindValue(':id_metal_FK', $idMetal, PDO::PARAM_INT);
            $stmt->bindValue(':id_proveedor_FK', $idProveedor, $idProveedor === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(':id_tienda_FK', $idTienda, PDO::PARAM_INT);
            $stmt->bindValue(':peso_gr', $peso, $peso === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':costo', $costo, PDO::PARAM_STR);
            $stmt->bindValue(':precio_por_gramo', $precioPorGramo, $precioPorGramo === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':aumento_pct', $aumentoPct, $aumentoPct === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':largo', $largo, $largo === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':ancho', $ancho, $ancho === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':observaciones', $observaciones, $observaciones === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':id_pieza', (int) $idPieza, PDO::PARAM_INT);
            $stmt->execute();

            $costoPrevio = $pieza['costo'] !== null ? (string) $pieza['costo'] : null;
            $aumentoPrevio = $pieza['aumento_pct'] !== null ? (string) $pieza['aumento_pct'] : null;
            $cambioCosto = $costoPrevio === null || abs((float) $costoPrevio - (float) $costo) > 0.0001;
            $cambioAumento = ($aumentoPrevio === null && $aumentoPct !== null)
                || ($aumentoPrevio !== null && $aumentoPct === null)
                || ($aumentoPrevio !== null && $aumentoPct !== null && abs((float) $aumentoPrevio - (float) $aumentoPct) > 0.0001);

            $stocksRecalculados = 0;
            if ($cambioCosto || $cambioAumento) {
                $precioVenta = $this->calcularPrecioVenta($costo, $aumentoPct);
                $stmtRecalc = $db->prepare(
                    "UPDATE piezas_stock
                     SET precio_venta = :precio_venta
                     WHERE id_pieza_FK = :id_pieza
                       AND activo = 1
                       AND estado IN ('disponible','apartada')"
                );
                $stmtRecalc->bindValue(':precio_venta', $precioVenta, PDO::PARAM_STR);
                $stmtRecalc->bindValue(':id_pieza', (int) $idPieza, PDO::PARAM_INT);
                $stmtRecalc->execute();
                $stocksRecalculados = $stmtRecalc->rowCount();
            }

            $huboCambioImagen = false;
            if ($archivoImagen !== null && isset($archivoImagen['tmp_name']) && is_uploaded_file($archivoImagen['tmp_name'])) {
                $rutaRelativa = $this->moverImagenSubida($archivoImagen, $this->obtenerDirectorioImagenesPiezas(), 'pieza', (int) $idPieza);

                $stmtReset = $db->prepare("UPDATE imagenes_piezas SET es_principal = 0 WHERE id_pieza_FK = :id_pieza");
                $stmtReset->bindValue(':id_pieza', (int) $idPieza, PDO::PARAM_INT);
                $stmtReset->execute();

                $stmtImagen = $db->prepare(
                    "INSERT INTO imagenes_piezas (id_pieza_FK, url_imagen, es_principal)
                     VALUES (:id_pieza_FK, :url_imagen, 1)"
                );
                $stmtImagen->bindValue(':id_pieza_FK', (int) $idPieza, PDO::PARAM_INT);
                $stmtImagen->bindValue(':url_imagen', $this->construirUrlImagenPieza($rutaRelativa), PDO::PARAM_STR);
                $stmtImagen->execute();
                $huboCambioImagen = true;
            }

            $db->commit();
            return $stmt->rowCount() > 0 || $huboCambioImagen || $stocksRecalculados > 0;
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Reemplaza la imagen principal de la pieza sin tocar el resto de la fila.
     * Usado por la accion 'subir_foto' (permiso PIEZA_FOTO) para permitir a la
     * empleada cambiar la foto sin tener PIEZA_ACTUALIZAR.
     */
    public function reemplazarImagenPrincipal($idPieza, $archivoImagen)
    {
        $pieza = $this->leerUno($idPieza);
        if (!$pieza || (int) ($pieza['activo'] ?? 0) !== 1) {
            throw new RuntimeException('Pieza no encontrada o inactiva.');
        }
        if (!is_array($archivoImagen) || !isset($archivoImagen['tmp_name']) || !is_uploaded_file($archivoImagen['tmp_name'])) {
            return false;
        }

        $db = $this->getDb();
        $db->beginTransaction();
        try {
            auth_mysql_set_audit_vars($db);

            $rutaRelativa = $this->moverImagenSubida(
                $archivoImagen,
                $this->obtenerDirectorioImagenesPiezas(),
                'pieza',
                (int) $idPieza
            );

            $stmtReset = $db->prepare("UPDATE imagenes_piezas SET es_principal = 0 WHERE id_pieza_FK = :id_pieza");
            $stmtReset->bindValue(':id_pieza', (int) $idPieza, PDO::PARAM_INT);
            $stmtReset->execute();

            $stmtImagen = $db->prepare(
                "INSERT INTO imagenes_piezas (id_pieza_FK, url_imagen, es_principal)
                 VALUES (:id_pieza_FK, :url_imagen, 1)"
            );
            $stmtImagen->bindValue(':id_pieza_FK', (int) $idPieza, PDO::PARAM_INT);
            $stmtImagen->bindValue(':url_imagen', $this->construirUrlImagenPieza($rutaRelativa), PDO::PARAM_STR);
            $stmtImagen->execute();

            $db->commit();
            return true;
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public function borrar($idPieza, $idUsuarioBaja)
    {
        $idUsuarioBaja = isset($_SESSION['id_usuario']) ? (int) $_SESSION['id_usuario'] : null;
        $stmt = $this->getDb()->prepare(
            "UPDATE piezas
             SET activo = 0,
                 fecha_baja = NOW(),
                 id_usuario_baja = :id_usuario_baja
             WHERE id_pieza = :id_pieza AND activo = 1"
        );
        $stmt->bindValue(':id_usuario_baja', $idUsuarioBaja !== null ? (int) $idUsuarioBaja : null, $idUsuarioBaja !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':id_pieza', (int) $idPieza, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount();
    }

    public function agregarImagenes($idPieza, $imagenes, $idImagenPrincipal = null)
    {
        $pieza = $this->leerUno($idPieza);
        if (!$pieza || (int) ($pieza['activo'] ?? 0) !== 1) {
            throw new RuntimeException('Pieza no encontrada o inactiva.');
        }

        $archivosNormalizados = $this->normalizarArchivosSubidos($imagenes);
        if (empty($archivosNormalizados)) {
            return 0;
        }

        $db = $this->getDb();
        $db->beginTransaction();

        try {
            $tienePrincipal = $this->piezaTieneImagenPrincipal($idPieza);
            $insertadas = 0;

            foreach ($archivosNormalizados as $index => $archivo) {
                if (!isset($archivo['tmp_name']) || !is_uploaded_file($archivo['tmp_name'])) {
                    continue;
                }

                $rutaRelativa = $this->moverImagenSubida($archivo, $this->obtenerDirectorioImagenesPiezas(), 'pieza', (int) $idPieza);

                $esPrincipal = 0;
                if ($idImagenPrincipal !== null && $idImagenPrincipal === $index) {
                    $esPrincipal = 1;
                } elseif (!$tienePrincipal && $insertadas === 0) {
                    $esPrincipal = 1;
                    $tienePrincipal = true;
                }

                $stmtImagen = $db->prepare(
                    "INSERT INTO imagenes_piezas (id_pieza_FK, url_imagen, es_principal)
                     VALUES (:id_pieza_FK, :url_imagen, :es_principal)"
                );
                $stmtImagen->bindValue(':id_pieza_FK', (int) $idPieza, PDO::PARAM_INT);
                $stmtImagen->bindValue(':url_imagen', $this->construirUrlImagenPieza($rutaRelativa), PDO::PARAM_STR);
                $stmtImagen->bindValue(':es_principal', $esPrincipal, PDO::PARAM_INT);
                $stmtImagen->execute();

                $insertadas++;
            }

            $db->commit();
            return $insertadas;
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public function establecerImagenPrincipal($idPieza, $idImagen)
    {
        $db = $this->getDb();
        $db->beginTransaction();

        try {
            $stmtValidar = $db->prepare(
                "SELECT id_imagen
                 FROM imagenes_piezas
                 WHERE id_imagen = :id_imagen AND id_pieza_FK = :id_pieza"
            );
            $stmtValidar->bindValue(':id_imagen', (int) $idImagen, PDO::PARAM_INT);
            $stmtValidar->bindValue(':id_pieza', (int) $idPieza, PDO::PARAM_INT);
            $stmtValidar->execute();
            if (!$stmtValidar->fetch(PDO::FETCH_ASSOC)) {
                throw new RuntimeException('La imagen no pertenece a la pieza seleccionada.');
            }

            $stmtReset = $db->prepare("UPDATE imagenes_piezas SET es_principal = 0 WHERE id_pieza_FK = :id_pieza");
            $stmtReset->bindValue(':id_pieza', (int) $idPieza, PDO::PARAM_INT);
            $stmtReset->execute();

            $stmtPrincipal = $db->prepare("UPDATE imagenes_piezas SET es_principal = 1 WHERE id_imagen = :id_imagen");
            $stmtPrincipal->bindValue(':id_imagen', (int) $idImagen, PDO::PARAM_INT);
            $stmtPrincipal->execute();

            $db->commit();
            return true;
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public function eliminarImagen($idPieza, $idImagen)
    {
        $db = $this->getDb();
        $db->beginTransaction();

        try {
            $stmtImagen = $db->prepare(
                "SELECT id_imagen, url_imagen, es_principal
                 FROM imagenes_piezas
                 WHERE id_imagen = :id_imagen AND id_pieza_FK = :id_pieza"
            );
            $stmtImagen->bindValue(':id_imagen', (int) $idImagen, PDO::PARAM_INT);
            $stmtImagen->bindValue(':id_pieza', (int) $idPieza, PDO::PARAM_INT);
            $stmtImagen->execute();
            $imagen = $stmtImagen->fetch(PDO::FETCH_ASSOC);

            if (!$imagen) {
                throw new RuntimeException('La imagen no existe o no corresponde a la pieza.');
            }

            $stmtDelete = $db->prepare("DELETE FROM imagenes_piezas WHERE id_imagen = :id_imagen");
            $stmtDelete->bindValue(':id_imagen', (int) $idImagen, PDO::PARAM_INT);
            $stmtDelete->execute();

            if ((int) $imagen['es_principal'] === 1) {
                $stmtNuevaPrincipal = $db->prepare(
                    "SELECT id_imagen
                     FROM imagenes_piezas
                     WHERE id_pieza_FK = :id_pieza
                     ORDER BY id_imagen DESC
                     LIMIT 1"
                );
                $stmtNuevaPrincipal->bindValue(':id_pieza', (int) $idPieza, PDO::PARAM_INT);
                $stmtNuevaPrincipal->execute();
                $nuevaPrincipal = $stmtNuevaPrincipal->fetch(PDO::FETCH_ASSOC);

                if ($nuevaPrincipal) {
                    $stmtSetPrincipal = $db->prepare("UPDATE imagenes_piezas SET es_principal = 1 WHERE id_imagen = :id_imagen");
                    $stmtSetPrincipal->bindValue(':id_imagen', (int) $nuevaPrincipal['id_imagen'], PDO::PARAM_INT);
                    $stmtSetPrincipal->execute();
                }
            }

            $rutaRelativa = (string) ($imagen['url_imagen'] ?? '');
            $rutaAbsoluta = $this->resolverRutaAbsolutaImagen($rutaRelativa);

            $db->commit();

            if (is_string($rutaAbsoluta) && is_file($rutaAbsoluta)) {
                @unlink($rutaAbsoluta);
            }

            return true;
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    private function validarTexto($data, $campo, $max, $label)
    {
        if (!isset($data[$campo])) {
            throw new InvalidArgumentException($label . ' es requerida.');
        }

        $valor = trim(strip_tags((string) $data[$campo]));
        if ($valor === '') {
            throw new InvalidArgumentException($label . ' no puede estar vacia.');
        }

        if (mb_strlen($valor) > $max) {
            $valor = mb_substr($valor, 0, $max);
        }

        return $valor;
    }

    /**
     * Directorio físico donde se guardan las imágenes de piezas en admin.
     */
    private function obtenerDirectorioImagenesPiezas(): string
    {
        return __DIR__ . '/../imagenes/piezas';
    }

    /**
     * Ruta relativa pública persistida en DB (sin prefijo admin).
     */
    private function construirUrlImagenPieza(string $nombreArchivo): string
    {
        $archivo = ltrim(str_replace('\\', '/', $nombreArchivo), '/');
        return 'imagenes/piezas/' . $archivo;
    }

    /**
     * Resuelve la ruta absoluta de una imagen desde la ruta DB.
     * Soporta tanto formato actual (imagenes/...) como legado (admin/imagenes/...).
     */
    private function resolverRutaAbsolutaImagen(?string $rutaRelativa): ?string
    {
        $ruta = trim((string) $rutaRelativa);
        if ($ruta === '') {
            return null;
        }

        $normalizada = ltrim(str_replace('\\', '/', $ruta), '/');
        if (strpos($normalizada, 'admin/') === 0) {
            $normalizada = substr($normalizada, 6);
        }

        $baseAdmin = realpath(__DIR__ . '/..');
        if (!is_string($baseAdmin) || $baseAdmin === '') {
            return null;
        }

        return $baseAdmin . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalizada);
    }

    private function validarTextoOpcional($data, $campo, $max)
    {
        if (!isset($data[$campo])) {
            return null;
        }

        $valor = trim(strip_tags((string) $data[$campo]));
        if ($valor === '') {
            return null;
        }

        if (mb_strlen($valor) > $max) {
            $valor = mb_substr($valor, 0, $max);
        }

        return $valor;
    }

    private function validarEntero($data, $campo, $label)
    {
        if (!isset($data[$campo]) || trim((string) $data[$campo]) === '') {
            throw new InvalidArgumentException($label . ' es obligatorio.');
        }

        if (!is_numeric($data[$campo])) {
            throw new InvalidArgumentException($label . ' debe ser numerico.');
        }

        return (int) $data[$campo];
    }

    private function validarEnteroOpcional($data, $campo)
    {
        if (!isset($data[$campo]) || trim((string) $data[$campo]) === '') {
            return null;
        }

        if (!is_numeric($data[$campo])) {
            throw new InvalidArgumentException('El campo ' . $campo . ' debe ser numerico.');
        }

        return (int) $data[$campo];
    }

    private function validarDecimal($data, $campo, $label)
    {
        if (!isset($data[$campo]) || trim((string) $data[$campo]) === '') {
            throw new InvalidArgumentException($label . ' es obligatorio.');
        }

        if (!is_numeric($data[$campo])) {
            throw new InvalidArgumentException($label . ' debe ser numerico.');
        }

        return $this->normalizarDecimal($data[$campo], 2);
    }

    private function validarDecimalOpcional($data, $campo)
    {
        if (!isset($data[$campo]) || trim((string) $data[$campo]) === '') {
            return null;
        }

        if (!is_numeric($data[$campo])) {
            throw new InvalidArgumentException('El campo ' . $campo . ' debe ser numerico.');
        }

        return $this->normalizarDecimal($data[$campo], 2);
    }

    private function normalizarArchivosSubidos($imagenes)
    {
        if (!is_array($imagenes) || !isset($imagenes['name'])) {
            return [];
        }

        if (!is_array($imagenes['name'])) {
            if ((int) ($imagenes['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                return [];
            }
            return [$imagenes];
        }

        $normalizados = [];
        $total = count($imagenes['name']);
        for ($i = 0; $i < $total; $i++) {
            $error = (int) ($imagenes['error'][$i] ?? UPLOAD_ERR_NO_FILE);
            if ($error !== UPLOAD_ERR_OK) {
                continue;
            }

            $normalizados[] = [
                'name' => $imagenes['name'][$i] ?? '',
                'type' => $imagenes['type'][$i] ?? '',
                'tmp_name' => $imagenes['tmp_name'][$i] ?? '',
                'error' => $error,
                'size' => $imagenes['size'][$i] ?? 0,
            ];
        }

        return $normalizados;
    }

    private function piezaTieneImagenPrincipal($idPieza)
    {
        $stmt = $this->getDb()->prepare(
            "SELECT id_imagen
             FROM imagenes_piezas
             WHERE id_pieza_FK = :id_pieza AND es_principal = 1
             LIMIT 1"
        );
        $stmt->bindValue(':id_pieza', (int) $idPieza, PDO::PARAM_INT);
        $stmt->execute();
        return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function validarPorcentajeOpcional($data, $campo)
    {
        if (!isset($data[$campo]) || trim((string) $data[$campo]) === '') {
            return null;
        }

        if (!is_numeric($data[$campo])) {
            throw new InvalidArgumentException('El aumento debe ser numerico.');
        }

        $valor = (float) $data[$campo];
        if ($valor < 0) {
            throw new InvalidArgumentException('El aumento no puede ser negativo.');
        }

        return $this->normalizarDecimal($valor, 2);
    }

    private function resolverCostoSegunMetodo($data)
    {
        // Cuando los precios vienen de la grilla de variantes, el costo es opcional.
        if (!empty($data['costo_desde_grilla'])) {
            return ['peso' => null, 'precio_por_gramo' => null, 'costo' => null];
        }

        $metodo = isset($data['metodo_costo']) ? trim((string) $data['metodo_costo']) : 'directo';
        if (!in_array($metodo, ['por_gramo', 'directo'], true)) {
            throw new InvalidArgumentException('Metodo de costo invalido.');
        }

        if ($metodo === 'por_gramo') {
            $peso = $this->validarDecimal($data, 'peso_gr', 'El peso (gr)');
            $precioPorGramo = $this->validarDecimalPrecio($data, 'precio_por_gramo', 'El precio por gramo');

            if ((float) $peso <= 0) {
                throw new InvalidArgumentException('El peso debe ser mayor a 0 cuando se calcula por gramo.');
            }
            if ((float) $precioPorGramo <= 0) {
                throw new InvalidArgumentException('El precio por gramo debe ser mayor a 0.');
            }

            $costo = $this->normalizarDecimal(((float) $peso * (float) $precioPorGramo), 2);

            return [
                'peso' => $peso,
                'precio_por_gramo' => $precioPorGramo,
                'costo' => $costo,
            ];
        }

        $costo = $this->validarDecimal($data, 'costo', 'El costo');
        if ((float) $costo <= 0) {
            throw new InvalidArgumentException('El costo debe ser mayor a 0.');
        }
        $peso = $this->validarDecimalOpcional($data, 'peso_gr');

        return [
            'peso' => $peso,
            'precio_por_gramo' => null,
            'costo' => $costo,
        ];
    }

    private function validarDecimalPrecio($data, $campo, $label)
    {
        if (!isset($data[$campo]) || trim((string) $data[$campo]) === '') {
            throw new InvalidArgumentException($label . ' es obligatorio.');
        }
        if (!is_numeric($data[$campo])) {
            throw new InvalidArgumentException($label . ' debe ser numerico.');
        }
        return $this->normalizarDecimal($data[$campo], 4);
    }

    private function calcularPrecioVenta($costo, $aumentoPct)
    {
        if ($costo === null) {
            return null;
        }
        $valor = $this->calcularPrecioVentaAjustado($costo, $aumentoPct);
        return $this->normalizarDecimal($valor, 2);
    }

    private function calcularPrecioVentaAjustado($costo, $aumentoPct): float
    {
        if ($costo === null) {
            return 0.0;
        }
        $factor = 1 + ((float) ($aumentoPct ?? 0)) / 100;
        $valor = round((float) $costo * $factor, 2);

        // Ajusta hacia arriba al siguiente multiplo de 5.00 (ej: 453.94 -> 455.00).
        if ($valor > 0) {
            $valor = ceil($valor / 5) * 5;
        }

        if ($valor <= 0) {
            $valor = 0.01;
        }
        return (float) $valor;
    }

    private function calcularAumentoPctEfectivo($costo, $aumentoPct)
    {
        if ($aumentoPct === null) {
            return null;
        }
        if ($costo === null) {
            return $this->normalizarDecimal($aumentoPct, 2);
        }

        $costoNum = (float) $costo;
        if ($costoNum <= 0) {
            return $this->normalizarDecimal($aumentoPct, 2);
        }

        $precioVentaAjustado = $this->calcularPrecioVentaAjustado($costoNum, $aumentoPct);
        $aumentoEfectivo = (($precioVentaAjustado / $costoNum) - 1) * 100;
        return $this->normalizarDecimal($aumentoEfectivo, 2);
    }

    private function generarCodigoAuxiliar($idPieza)
    {
        $idPieza = (int) $idPieza;
        $prefijo = $idPieza . '/';
        $stmt = $this->getDb()->prepare(
            "SELECT MAX(CAST(SUBSTRING_INDEX(codigo_auxiliar, '/', -1) AS UNSIGNED)) AS max_corr
             FROM piezas_stock
             WHERE codigo_auxiliar LIKE :prefijo"
        );
        $stmt->bindValue(':prefijo', $prefijo . '%', PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $siguiente = ((int) ($row['max_corr'] ?? 0)) + 1;

        return $prefijo . $siguiente;
    }

    private function generarCodigoBarrasEAN13()
    {
        $stmtCheck = $this->getDb()->prepare(
            "SELECT 1 FROM piezas_stock WHERE codigo_barras = :codigo LIMIT 1"
        );

        for ($intento = 0; $intento < 5; $intento++) {
            $base = '';
            for ($i = 0; $i < 12; $i++) {
                $base .= (string) random_int(0, 9);
            }
            $codigo = $base . $this->calcularDigitoEAN13($base);

            $stmtCheck->bindValue(':codigo', $codigo, PDO::PARAM_STR);
            $stmtCheck->execute();
            if (!$stmtCheck->fetch(PDO::FETCH_ASSOC)) {
                return $codigo;
            }
        }

        throw new RuntimeException('No se pudo generar un codigo de barras unico tras varios intentos.');
    }

    private function calcularDigitoEAN13($base12)
    {
        $suma = 0;
        for ($i = 0; $i < 12; $i++) {
            $digito = (int) $base12[$i];
            $suma += ($i % 2 === 0) ? $digito : $digito * 3;
        }
        $resto = $suma % 10;
        return (string) (($resto === 0) ? 0 : 10 - $resto);
    }

    public function crearStockMasivo($idPieza, array $opciones): array
    {
        $cantidad = (int) ($opciones['cantidad'] ?? 0);
        if ($cantidad <= 0) {
            return [];
        }
        if ($cantidad > 500) {
            throw new InvalidArgumentException('La cantidad de stock a generar no puede exceder 500 por operacion.');
        }

        $precioVenta = isset($opciones['precio_venta']) && $opciones['precio_venta'] !== null
            ? (string) $opciones['precio_venta']
            : null;
        $aumentoPctGrilla = isset($opciones['aumento_pct']) && $opciones['aumento_pct'] !== null && $opciones['aumento_pct'] !== ''
            ? (float) $opciones['aumento_pct']
            : null;
        $costoDesdeGrilla = !empty($opciones['costo_desde_grilla']);

        if (!$costoDesdeGrilla) {
            if ($precioVenta === null || !is_numeric($precioVenta) || (float) $precioVenta <= 0) {
                throw new InvalidArgumentException('Precio de venta invalido al generar stock.');
            }
        }

        $tipoCodigo = isset($opciones['tipo_codigo']) ? (string) $opciones['tipo_codigo'] : 'CODE128';
        if (!in_array($tipoCodigo, ['EAN13', 'CODE128', 'QR'], true)) {
            $tipoCodigo = 'CODE128';
        }

        $varianteModo = isset($opciones['variante_modo']) ? (string) $opciones['variante_modo'] : 'ninguna';
        if (!in_array($varianteModo, ['ninguna', 'ejes'], true)) {
            $varianteModo = 'ninguna';
        }

        require_once __DIR__ . '/../../includes/variantes_stock_helpers.php';
        $usaMatriz = $this->tieneColumnasVarianteMatriz();
        $usaCatalogo = joyeria_tiene_columnas_variante_catalogo($this->getDb());

        /** @var list<array{talla: ?string, color: ?string, valor1_id: ?int, valor2_id: ?int}> $unidades */
        $unidades = [];
        if ($varianteModo === 'ejes' && $usaCatalogo) {
            $matriz = isset($opciones['matriz']) && is_array($opciones['matriz']) ? $opciones['matriz'] : [];
            $totalVariantes = 0;
            foreach ($matriz as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $valor1Id = isset($item['valor1_id']) ? (int) $item['valor1_id'] : 0;
                $valor2Id = isset($item['valor2_id']) && $item['valor2_id'] !== null ? (int) $item['valor2_id'] : null;
                $cant = isset($item['cantidad']) ? (int) $item['cantidad'] : 0;
                if ($valor1Id <= 0 || $cant < 1) {
                    continue;
                }
                $resolved = joyeria_resolver_variantes_desde_catalogo($this->getDb(), $valor1Id, $valor2Id);
                $metodoCelda = isset($item['metodo_celda']) ? (string) $item['metodo_celda'] : 'directo';
                if ($metodoCelda === 'por_gramo') {
                    $pesoItem = isset($item['peso_gr']) ? (float) $item['peso_gr'] : 0;
                    $ppgItem = isset($item['precio_por_gramo']) ? (float) $item['precio_por_gramo'] : 0;
                    if ($pesoItem > 0 && $ppgItem > 0) {
                        $costoCelda = $pesoItem * $ppgItem;
                        $precioCalc = $this->calcularPrecioVentaAjustado($costoCelda, $aumentoPctGrilla);
                        $precioCelda = $this->normalizarDecimal($precioCalc, 2);
                    } else {
                        $precioCelda = null;
                    }
                } else {
                    // directo: precio_es_final=true → PV ya calculado (catalogo uniforme o modo grilla);
                    // precio_es_final=false → el valor es costo por fila y aplica aumento_pct de la grilla.
                    if (isset($item['precio']) && (float) $item['precio'] > 0) {
                        $rawPrecio = (float) $item['precio'];
                        if (!empty($item['precio_es_final'])) {
                            $precioCelda = (string) round($rawPrecio, 2);
                        } else {
                            $precioCalc = $this->calcularPrecioVentaAjustado($rawPrecio, $aumentoPctGrilla);
                            $precioCelda = $this->normalizarDecimal($precioCalc, 2);
                        }
                    } else {
                        $precioCelda = null;
                    }
                }
                for ($k = 0; $k < $cant; $k++) {
                    $unidades[] = [
                        'talla' => $resolved['variante_talla'],
                        'color' => $resolved['variante_color'],
                        'valor1_id' => $resolved['variante_valor1_id'],
                        'valor2_id' => $resolved['variante_valor2_id'],
                        'variante_tipo' => $resolved['variante_tipo'],
                        'variante_valor' => $resolved['variante_valor'],
                        'precio_venta' => $precioCelda,
                    ];
                }
                $totalVariantes += $cant;
            }
            if ($unidades === []) {
                throw new InvalidArgumentException('Debes indicar al menos una combinacion valida en la grilla.');
            }
            if ($totalVariantes > 500) {
                throw new InvalidArgumentException('La cantidad de stock a generar no puede exceder 500 por operacion.');
            }
        } else {
            for ($i = 0; $i < $cantidad; $i++) {
                $unidades[] = [
                    'talla' => null,
                    'color' => null,
                    'valor1_id' => null,
                    'valor2_id' => null,
                    'variante_tipo' => 'ninguna',
                    'variante_valor' => null,
                ];
            }
        }

        require_once __DIR__ . '/piezas_stock.php';
        $stockModel = new PiezasStock();

        auth_mysql_set_audit_vars($this->getDb());

        $colsExtra = '';
        $valsExtra = '';
        if ($usaCatalogo) {
            $colsExtra .= ', variante_valor1_id, variante_valor2_id';
            $valsExtra .= ', :variante_valor1_id, :variante_valor2_id';
        }
        if ($usaMatriz) {
            $colsExtra .= ', variante_talla, variante_color';
            $valsExtra .= ', :variante_talla, :variante_color';
        }
        $stmt = $this->getDb()->prepare(
            "INSERT INTO piezas_stock
             (id_pieza_FK, codigo_auxiliar, precio_venta, codigo_barras, estado, tipo_codigo, variante_tipo, variante_valor{$colsExtra}, activo)
             VALUES
             (:id_pieza_FK, :codigo_auxiliar, :precio_venta, :codigo_barras, 'disponible', :tipo_codigo, :variante_tipo, :variante_valor{$valsExtra}, 1)"
        );

        $idsCreados = [];
        foreach ($unidades as $unidad) {
            $codigoAux = $this->generarCodigoAuxiliar($idPieza);
            $codigoBarras = $stockModel->generarCodigoBarrasStock($tipoCodigo);

            if ($varianteModo === 'ejes' && $usaCatalogo) {
                $varianteTipoRow = (string) ($unidad['variante_tipo'] ?? 'ninguna');
                $varianteValorRow = $unidad['variante_valor'] ?? null;
                $varianteTallaRow = $unidad['talla'] ?? null;
                $varianteColorRow = $unidad['color'] ?? null;
            } else {
                [$varianteTipoRow, $varianteValorRow, $varianteTallaRow, $varianteColorRow] = joyeria_normalizar_variantes_stock(
                    $unidad['talla'] ?? null,
                    $unidad['color'] ?? null
                );
            }

            $pvUnidad = isset($unidad['precio_venta']) && $unidad['precio_venta'] !== null
                ? (string) $unidad['precio_venta']
                : $precioVenta;
            if ($pvUnidad === null || !is_numeric($pvUnidad) || (float) $pvUnidad <= 0) {
                throw new InvalidArgumentException('El precio de venta no es valido para una o mas celdas de la grilla. Verifica que cada fila tenga precio o peso.');
            }

            $stmt->bindValue(':id_pieza_FK', (int) $idPieza, PDO::PARAM_INT);
            $stmt->bindValue(':codigo_auxiliar', $codigoAux, PDO::PARAM_STR);
            $stmt->bindValue(':precio_venta', $pvUnidad, PDO::PARAM_STR);
            $stmt->bindValue(':codigo_barras', $codigoBarras, PDO::PARAM_STR);
            $stmt->bindValue(':tipo_codigo', $tipoCodigo, PDO::PARAM_STR);
            $stmt->bindValue(':variante_tipo', $varianteTipoRow, PDO::PARAM_STR);
            if ($varianteValorRow === null) {
                $stmt->bindValue(':variante_valor', null, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue(':variante_valor', $varianteValorRow, PDO::PARAM_STR);
            }
            if ($usaCatalogo) {
                $v1 = $unidad['valor1_id'] ?? null;
                $v2 = $unidad['valor2_id'] ?? null;
                if ($v1 === null) {
                    $stmt->bindValue(':variante_valor1_id', null, PDO::PARAM_NULL);
                } else {
                    $stmt->bindValue(':variante_valor1_id', (int) $v1, PDO::PARAM_INT);
                }
                if ($v2 === null) {
                    $stmt->bindValue(':variante_valor2_id', null, PDO::PARAM_NULL);
                } else {
                    $stmt->bindValue(':variante_valor2_id', (int) $v2, PDO::PARAM_INT);
                }
            }
            if ($usaMatriz) {
                if ($varianteTallaRow === null) {
                    $stmt->bindValue(':variante_talla', null, PDO::PARAM_NULL);
                } else {
                    $stmt->bindValue(':variante_talla', $varianteTallaRow, PDO::PARAM_STR);
                }
                if ($varianteColorRow === null) {
                    $stmt->bindValue(':variante_color', null, PDO::PARAM_NULL);
                } else {
                    $stmt->bindValue(':variante_color', $varianteColorRow, PDO::PARAM_STR);
                }
            }
            $stmt->execute();
            $idsCreados[] = (int) $this->getDb()->lastInsertId();
        }

        return $idsCreados;
    }
}
