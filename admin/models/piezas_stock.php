<?php
require_once __DIR__ . "/../../sistema.class.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/list_search.php";

class PiezasStock extends Sistema
{
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
    public function obtenerPrecioVentaHermano($idPieza): ?string
    {
        $stmt = $this->getDb()->prepare(
            "SELECT precio_venta
             FROM piezas_stock
             WHERE id_pieza_FK = :id_pieza
               AND activo = 1
             ORDER BY id_pieza_stock DESC
             LIMIT 1"
        );
        $stmt->bindValue(':id_pieza', (int) $idPieza, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || !isset($row['precio_venta']) || !is_numeric($row['precio_venta'])) {
            return null;
        }
        return number_format((float) $row['precio_venta'], 2, '.', '');
    }

    public function tieneColumnasVarianteCatalogo(): bool
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        try {
            require_once __DIR__ . '/../../includes/variantes_stock_helpers.php';
            $cache = joyeria_tiene_columnas_variante_catalogo($this->getDb());
        } catch (Throwable $e) {
            $cache = false;
        }

        return $cache;
    }

    public function leer($idPieza = null, ?string $busqueda = null)
    {
        $pat = joyeria_like_pattern($busqueda);
        require_once __DIR__ . '/../../includes/variantes_stock_helpers.php';
        $usaCatalogo = $this->tieneColumnasVarianteCatalogo();
        $colsMatriz = $this->tieneColumnasVarianteMatriz()
            ? "ps.variante_talla,\n                       ps.variante_color,"
            : '';
        $colsCatalogo = $usaCatalogo
            ? "ps.variante_valor1_id,\n                       ps.variante_valor2_id,"
            : '';
        $joinCatalogo = $usaCatalogo ? joyeria_sql_join_variantes_stock('ps') : '';
        $selectCatalogo = $usaCatalogo ? joyeria_sql_select_variantes_stock() . ',' : '';
        $sql = "SELECT ps.id_pieza_stock,
                       ps.codigo_auxiliar,
                       ps.codigo_barras,
                       ps.precio_venta,
                       ps.estado,
                       ps.tipo_codigo,
                       ps.variante_tipo,
                       ps.variante_valor,
                       {$colsMatriz}
                       {$colsCatalogo}
                       {$selectCatalogo}
                       ps.fecha_alta,
                       p.desc_pieza,
                       sf.nom_sub_familia,
                       m.nom_metal,
                       pr.razon_social
                FROM piezas_stock ps
                INNER JOIN piezas p ON p.id_pieza = ps.id_pieza_FK
                INNER JOIN sub_familia sf ON sf.id_sub_familia = p.id_sub_familia_FK
                INNER JOIN metales m ON m.id_metal = p.id_metal_FK
                LEFT JOIN proveedores pr ON pr.id_proveedor = p.id_proveedor_FK
                {$joinCatalogo}
                WHERE ps.activo = 1";

        if ($idPieza !== null && (int) $idPieza > 0) {
            $sql .= " AND ps.id_pieza_FK = :id_pieza";
        }

        if ($pat !== null) {
            $sql .= " AND (
                ps.codigo_auxiliar LIKE :busq OR ps.codigo_barras LIKE :busq2 OR p.desc_pieza LIKE :busq3
                OR ps.estado LIKE :busq4 OR sf.nom_sub_familia LIKE :busq5 OR m.nom_metal LIKE :busq6
                OR IFNULL(pr.razon_social, '') LIKE :busq7 OR CAST(ps.id_pieza_stock AS CHAR) LIKE :busq8
            )";
        }

        $sql .= " ORDER BY ps.id_pieza_stock DESC";

        $stmt = $this->getDb()->prepare($sql);
        if ($idPieza !== null && (int) $idPieza > 0) {
            $stmt->bindValue(':id_pieza', (int) $idPieza, PDO::PARAM_INT);
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
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function leerUno($idPiezaStock)
    {
        require_once __DIR__ . '/../../includes/variantes_stock_helpers.php';
        $usaCatalogo = $this->tieneColumnasVarianteCatalogo();
        $joinCatalogo = $usaCatalogo ? joyeria_sql_join_variantes_stock('ps') : '';
        $selectCatalogo = $usaCatalogo ? ', ' . joyeria_sql_select_variantes_stock() : '';
        $sql = "SELECT ps.*{$selectCatalogo}
                FROM piezas_stock ps
                {$joinCatalogo}
                WHERE ps.id_pieza_stock = :id_pieza_stock";

        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(':id_pieza_stock', (int) $idPiezaStock, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function resolverIdsRango(int $idPieza, int $desde, int $hasta, bool $soloDisponibles = true): array
    {
        if ($idPieza <= 0 || $desde <= 0 || $hasta <= 0) {
            return [];
        }
        if ($desde > $hasta) {
            [$desde, $hasta] = [$hasta, $desde];
        }

        $sql = 'SELECT ps.id_pieza_stock
                FROM piezas_stock ps
                WHERE ps.id_pieza_FK = :id_pieza
                  AND ps.activo = 1
                  AND CAST(SUBSTRING_INDEX(ps.codigo_auxiliar, \'/\', -1) AS UNSIGNED) BETWEEN :desde AND :hasta';
        if ($soloDisponibles) {
            $sql .= " AND ps.estado = 'disponible'";
        }
        $sql .= ' ORDER BY CAST(SUBSTRING_INDEX(ps.codigo_auxiliar, \'/\', -1) AS UNSIGNED) ASC';

        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(':id_pieza', $idPieza, PDO::PARAM_INT);
        $stmt->bindValue(':desde', $desde, PDO::PARAM_INT);
        $stmt->bindValue(':hasta', $hasta, PDO::PARAM_INT);
        $stmt->execute();

        $ids = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $ids[] = (int) $row['id_pieza_stock'];
        }

        return $ids;
    }

    public function generarCodigoBarrasStock(string $tipoCodigo): string
    {
        return $this->generarCodigoBarras($tipoCodigo);
    }

    public function obtenerCatalogos()
    {
        return [
            'piezas' => $this->getDb()->query("SELECT p.id_pieza, p.desc_pieza, sf.nom_sub_familia, m.nom_metal, f.usa_talla
                                              FROM piezas p
                                              INNER JOIN sub_familia sf ON sf.id_sub_familia = p.id_sub_familia_FK
                                              INNER JOIN familias f ON f.id_familia = sf.id_familia_FK
                                              INNER JOIN metales m ON m.id_metal = p.id_metal_FK
                                              WHERE p.activo = 1
                                              ORDER BY p.desc_pieza ASC")->fetchAll(PDO::FETCH_ASSOC),
            'estados' => ['disponible', 'vendida', 'apartada', 'defectuosa', 'reparacion', 'reservada_online', 'reservada_pos'],
            'tiposCodigo' => ['EAN13', 'CODE128', 'QR'],
        ];
    }

    public function crear($data)
    {
        $idPieza = $this->validarEnteroPositivo($data, 'id_pieza_FK', 'La pieza');
        $precioVenta = $this->validarDecimal($data, 'precio_venta', 'El precio de venta');
        $tipoCodigo = $this->validarEnumOpcional($data, 'tipo_codigo', ['EAN13', 'CODE128', 'QR'], 'El tipo de código', 'CODE128');
        $estado = $this->validarEnumOpcional($data, 'estado', ['disponible', 'vendida', 'apartada', 'defectuosa', 'reparacion', 'reservada_online', 'reservada_pos'], 'El estado', 'disponible');
        $codigoBarrasRaw = isset($data['codigo_barras']) ? trim((string) $data['codigo_barras']) : '';
        $codigoBarras = $codigoBarrasRaw !== '' ? $this->validarTexto($data, 'codigo_barras', 50, 'El código de barras') : $this->generarCodigoBarras($tipoCodigo);
        $codigoAuxiliar = $this->generarCodigoAuxiliar($idPieza);
        [$varianteTipo, $varianteValor, $varianteTalla, $varianteColor, $varianteValor1Id, $varianteValor2Id] = $this->resolverVariante($data);
        $usaMatriz = $this->tieneColumnasVarianteMatriz();
        $usaCatalogo = $this->tieneColumnasVarianteCatalogo();

        // Validar código de barras único
        $sqlCheck = "SELECT id_pieza_stock FROM piezas_stock WHERE codigo_barras = :codigo_barras";
        $stmtCheck = $this->getDb()->prepare($sqlCheck);
        $stmtCheck->bindValue(':codigo_barras', $codigoBarras, PDO::PARAM_STR);
        $stmtCheck->execute();
        if ($stmtCheck->fetch()) {
            throw new Exception("El código de barras ya existe");
        }

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
            (:id_pieza_FK, :codigo_auxiliar, :precio_venta, :codigo_barras, :estado, :tipo_codigo, :variante_tipo, :variante_valor{$valsExtra}, 1)"
        );

        $stmt->bindValue(':id_pieza_FK', $idPieza, PDO::PARAM_INT);
        $stmt->bindValue(':codigo_auxiliar', $codigoAuxiliar, PDO::PARAM_STR);
        $stmt->bindValue(':precio_venta', $precioVenta, PDO::PARAM_STR);
        $stmt->bindValue(':codigo_barras', $codigoBarras, PDO::PARAM_STR);
        $stmt->bindValue(':estado', $estado, PDO::PARAM_STR);
        $stmt->bindValue(':tipo_codigo', $tipoCodigo, PDO::PARAM_STR);
        $stmt->bindValue(':variante_tipo', $varianteTipo, PDO::PARAM_STR);
        if ($varianteValor === null) {
            $stmt->bindValue(':variante_valor', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':variante_valor', $varianteValor, PDO::PARAM_STR);
        }
        if ($usaCatalogo) {
            if ($varianteValor1Id === null) {
                $stmt->bindValue(':variante_valor1_id', null, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue(':variante_valor1_id', (int) $varianteValor1Id, PDO::PARAM_INT);
            }
            if ($varianteValor2Id === null) {
                $stmt->bindValue(':variante_valor2_id', null, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue(':variante_valor2_id', (int) $varianteValor2Id, PDO::PARAM_INT);
            }
        }
        if ($usaMatriz) {
            if ($varianteTalla === null) {
                $stmt->bindValue(':variante_talla', null, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue(':variante_talla', $varianteTalla, PDO::PARAM_STR);
            }
            if ($varianteColor === null) {
                $stmt->bindValue(':variante_color', null, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue(':variante_color', $varianteColor, PDO::PARAM_STR);
            }
        }
        $stmt->execute();

        return (int) $this->getDb()->lastInsertId();
    }

    public function actualizar($idPiezaStock, $data)
    {
        $stockActual = $this->leerUno((int) $idPiezaStock);
        if (!$stockActual) {
            throw new InvalidArgumentException('El stock de pieza no existe.');
        }

        $idPieza = $this->validarEnteroPositivo($data, 'id_pieza_FK', 'La pieza');
        $precioVenta = $this->validarDecimal($data, 'precio_venta', 'El precio de venta');
        $estado = $this->validarEnum($data, 'estado', ['disponible', 'vendida', 'apartada', 'defectuosa', 'reparacion', 'reservada_online', 'reservada_pos'], 'El estado');
        $tipoCodigoActual = isset($stockActual['tipo_codigo']) ? (string) $stockActual['tipo_codigo'] : 'CODE128';
        $tipoCodigo = $this->validarEnumOpcional($data, 'tipo_codigo', ['EAN13', 'CODE128', 'QR'], 'El tipo de código', $tipoCodigoActual);

        $codigoBarrasRaw = isset($data['codigo_barras']) ? trim((string) $data['codigo_barras']) : '';
        if ($codigoBarrasRaw !== '') {
            $codigoBarras = $this->validarTexto($data, 'codigo_barras', 50, 'El código de barras');
        } else {
            $codigoBarrasActual = isset($stockActual['codigo_barras']) ? trim((string) $stockActual['codigo_barras']) : '';
            $codigoBarras = $codigoBarrasActual !== '' ? $codigoBarrasActual : $this->generarCodigoBarras($tipoCodigo);
        }

        // Validar código de barras único (excepto el actual)
        $sqlCheck = "SELECT id_pieza_stock FROM piezas_stock WHERE codigo_barras = :codigo_barras AND id_pieza_stock != :id_pieza_stock";
        $stmtCheck = $this->getDb()->prepare($sqlCheck);
        $stmtCheck->bindValue(':codigo_barras', $codigoBarras, PDO::PARAM_STR);
        $stmtCheck->bindValue(':id_pieza_stock', (int) $idPiezaStock, PDO::PARAM_INT);
        $stmtCheck->execute();
        if ($stmtCheck->fetch()) {
            throw new Exception("El código de barras ya existe");
        }

        [$varianteTipo, $varianteValor, $varianteTalla, $varianteColor, $varianteValor1Id, $varianteValor2Id] = $this->resolverVariante($data);
        $usaMatriz = $this->tieneColumnasVarianteMatriz();
        $usaCatalogo = $this->tieneColumnasVarianteCatalogo();

        auth_mysql_set_audit_vars($this->getDb());

        $setExtra = '';
        if ($usaCatalogo) {
            $setExtra .= ', variante_valor1_id = :variante_valor1_id, variante_valor2_id = :variante_valor2_id';
        }
        if ($usaMatriz) {
            $setExtra .= ', variante_talla = :variante_talla, variante_color = :variante_color';
        }
        $stmt = $this->getDb()->prepare(
            "UPDATE piezas_stock
            SET id_pieza_FK = :id_pieza_FK,
                precio_venta = :precio_venta,
                codigo_barras = :codigo_barras,
                estado = :estado,
                tipo_codigo = :tipo_codigo,
                variante_tipo = :variante_tipo,
                variante_valor = :variante_valor{$setExtra}
            WHERE id_pieza_stock = :id_pieza_stock"
        );

        $stmt->bindValue(':id_pieza_FK', $idPieza, PDO::PARAM_INT);
        $stmt->bindValue(':precio_venta', $precioVenta, PDO::PARAM_STR);
        $stmt->bindValue(':codigo_barras', $codigoBarras, PDO::PARAM_STR);
        $stmt->bindValue(':estado', $estado, PDO::PARAM_STR);
        $stmt->bindValue(':tipo_codigo', $tipoCodigo, PDO::PARAM_STR);
        $stmt->bindValue(':variante_tipo', $varianteTipo, PDO::PARAM_STR);
        if ($varianteValor === null) {
            $stmt->bindValue(':variante_valor', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':variante_valor', $varianteValor, PDO::PARAM_STR);
        }
        if ($usaCatalogo) {
            if ($varianteValor1Id === null) {
                $stmt->bindValue(':variante_valor1_id', null, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue(':variante_valor1_id', (int) $varianteValor1Id, PDO::PARAM_INT);
            }
            if ($varianteValor2Id === null) {
                $stmt->bindValue(':variante_valor2_id', null, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue(':variante_valor2_id', (int) $varianteValor2Id, PDO::PARAM_INT);
            }
        }
        if ($usaMatriz) {
            if ($varianteTalla === null) {
                $stmt->bindValue(':variante_talla', null, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue(':variante_talla', $varianteTalla, PDO::PARAM_STR);
            }
            if ($varianteColor === null) {
                $stmt->bindValue(':variante_color', null, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue(':variante_color', $varianteColor, PDO::PARAM_STR);
            }
        }
        $stmt->bindValue(':id_pieza_stock', (int) $idPiezaStock, PDO::PARAM_INT);
        $stmt->execute();
    }

    /**
     * @return array{0: string, 1: ?string, 2: ?string, 3: ?string, 4: ?int, 5: ?int}
     */
    private function resolverVariante($data): array
    {
        require_once __DIR__ . '/../../includes/variantes_stock_helpers.php';

        $valor1Id = isset($data['variante_valor1_id']) && (int) $data['variante_valor1_id'] > 0
            ? (int) $data['variante_valor1_id'] : null;
        $valor2Id = isset($data['variante_valor2_id']) && (int) $data['variante_valor2_id'] > 0
            ? (int) $data['variante_valor2_id'] : null;

        if ($this->tieneColumnasVarianteCatalogo() && ($valor1Id !== null || $valor2Id !== null)) {
            $resolved = joyeria_resolver_variantes_desde_catalogo($this->getDb(), $valor1Id, $valor2Id);

            return [
                $resolved['variante_tipo'],
                $resolved['variante_valor'],
                $resolved['variante_talla'],
                $resolved['variante_color'],
                $resolved['variante_valor1_id'],
                $resolved['variante_valor2_id'],
            ];
        }

        $tipo = isset($data['variante_tipo']) ? trim((string) $data['variante_tipo']) : 'ninguna';
        if (!in_array($tipo, ['ninguna', 'talla', 'color', 'talla_color'], true)) {
            $tipo = 'ninguna';
        }

        $tallaDirecta = isset($data['variante_talla']) ? trim(strip_tags((string) $data['variante_talla'])) : '';
        $colorDirecto = isset($data['variante_color']) ? trim(strip_tags((string) $data['variante_color'])) : '';

        if ($tipo === 'talla_color' || ($tallaDirecta !== '' && $colorDirecto !== '')) {
            if ($tallaDirecta === '' || $colorDirecto === '') {
                throw new InvalidArgumentException('Indica color y talla para la variante combinada.');
            }

            [$varianteTipo, $varianteValor, $varianteTalla, $varianteColor] = joyeria_normalizar_variantes_stock($tallaDirecta, $colorDirecto);

            return [$varianteTipo, $varianteValor, $varianteTalla, $varianteColor, null, null];
        }

        if ($tipo === 'ninguna') {
            if ($tallaDirecta !== '' || $colorDirecto !== '') {
                [$varianteTipo, $varianteValor, $varianteTalla, $varianteColor] = joyeria_normalizar_variantes_stock(
                    $tallaDirecta !== '' ? $tallaDirecta : null,
                    $colorDirecto !== '' ? $colorDirecto : null
                );

                return [$varianteTipo, $varianteValor, $varianteTalla, $varianteColor, null, null];
            }

            return ['ninguna', null, null, null, null, null];
        }

        $valor = isset($data['variante_valor']) ? trim(strip_tags((string) $data['variante_valor'])) : '';
        if ($tipo === 'talla' && $valor === '' && $tallaDirecta !== '') {
            $valor = $tallaDirecta;
        }
        if ($tipo === 'color' && $valor === '' && $colorDirecto !== '') {
            $valor = $colorDirecto;
        }
        if ($valor === '') {
            throw new InvalidArgumentException('Indica el valor de la ' . ($tipo === 'talla' ? 'talla' : 'color') . '.');
        }
        if (mb_strlen($valor) > 40) {
            $valor = mb_substr($valor, 0, 40);
        }

        if ($tipo === 'talla') {
            [$varianteTipo, $varianteValor, $varianteTalla, $varianteColor] = joyeria_normalizar_variantes_stock($valor, null);

            return [$varianteTipo, $varianteValor, $varianteTalla, $varianteColor, null, null];
        }

        [$varianteTipo, $varianteValor, $varianteTalla, $varianteColor] = joyeria_normalizar_variantes_stock(null, $valor);

        return [$varianteTipo, $varianteValor, $varianteTalla, $varianteColor, null, null];
    }

    public function eliminar($idPiezaStock, $idUsuario)
    {
        $idUsuario = isset($_SESSION['id_usuario']) ? (int) $_SESSION['id_usuario'] : null;
        auth_mysql_set_audit_vars($this->getDb());

        $stmt = $this->getDb()->prepare(
            "UPDATE piezas_stock
            SET activo = 0, fecha_baja = NOW(), id_usuario_baja = :id_usuario_baja
            WHERE id_pieza_stock = :id_pieza_stock"
        );

        $stmt->bindValue(':id_usuario_baja', $idUsuario, $idUsuario === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':id_pieza_stock', (int) $idPiezaStock, PDO::PARAM_INT);
        $stmt->execute();
    }

    private function validarTexto($data, $campo, $max, $label)
    {
        if (!isset($data[$campo])) {
            throw new InvalidArgumentException($label . ' es obligatorio.');
        }

        $valor = trim(strip_tags((string) $data[$campo]));
        if ($valor === '') {
            throw new InvalidArgumentException($label . ' no puede estar vacio.');
        }

        if (mb_strlen($valor) > $max) {
            $valor = mb_substr($valor, 0, $max);
        }

        return $valor;
    }

    private function validarEnteroPositivo($data, $campo, $label)
    {
        if (!isset($data[$campo])) {
            throw new InvalidArgumentException($label . ' es obligatorio.');
        }

        $valor = (int) $data[$campo];
        if ($valor <= 0) {
            throw new InvalidArgumentException($label . ' no es valido.');
        }

        return $valor;
    }

    private function validarDecimal($data, $campo, $label)
    {
        if (!isset($data[$campo])) {
            throw new InvalidArgumentException($label . ' es obligatorio.');
        }

        $valor = trim((string) $data[$campo]);
        if ($valor === '' || !is_numeric($valor)) {
            throw new InvalidArgumentException($label . ' debe ser un número valido.');
        }

        if ((float) $valor <= 0) {
            throw new InvalidArgumentException($label . ' debe ser mayor a 0.');
        }

        return $valor;
    }

    private function validarEnum($data, $campo, $valoresValidos, $label)
    {
        if (!isset($data[$campo])) {
            throw new InvalidArgumentException($label . ' es obligatorio.');
        }

        $valor = trim((string) $data[$campo]);
        if (!in_array($valor, $valoresValidos, true)) {
            throw new InvalidArgumentException($label . ' no es valido.');
        }

        return $valor;
    }

    private function validarEnumOpcional($data, $campo, $valoresValidos, $label, $default)
    {
        if (!isset($data[$campo]) || trim((string) $data[$campo]) === '') {
            return $default;
        }
        return $this->validarEnum($data, $campo, $valoresValidos, $label);
    }

    private function existeCodigoBarras(string $codigo): bool
    {
        $stmt = $this->getDb()->prepare("SELECT 1 FROM piezas_stock WHERE codigo_barras = :codigo LIMIT 1");
        $stmt->bindValue(':codigo', $codigo, PDO::PARAM_STR);
        $stmt->execute();
        return (bool) $stmt->fetchColumn();
    }

    /** Misma convención que Pieza::crearStockMasivo: {id}/{incremental}. */
    private function generarCodigoAuxiliar(int $idPieza): string
    {
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

    private function generarCodigoBarras(string $tipoCodigo): string
    {
        if ($tipoCodigo === 'EAN13') {
            return $this->generarCodigoEAN13Unico();
        }

        for ($i = 0; $i < 10; $i++) {
            $base = strtoupper($tipoCodigo) . '-' . date('YmdHis') . '-' . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            if (!$this->existeCodigoBarras($base)) {
                return $base;
            }
        }

        throw new RuntimeException('No se pudo generar un código de barras único.');
    }

    private function generarCodigoEAN13Unico(): string
    {
        for ($intento = 0; $intento < 10; $intento++) {
            $base = '';
            for ($i = 0; $i < 12; $i++) {
                $base .= (string) random_int(0, 9);
            }
            $codigo = $base . $this->calcularDigitoEAN13($base);
            if (!$this->existeCodigoBarras($codigo)) {
                return $codigo;
            }
        }
        throw new RuntimeException('No se pudo generar un EAN13 único.');
    }

    private function calcularDigitoEAN13(string $base12): string
    {
        $suma = 0;
        for ($i = 0; $i < 12; $i++) {
            $digito = (int) $base12[$i];
            $suma += ($i % 2 === 0) ? $digito : $digito * 3;
        }
        $resto = $suma % 10;
        return (string) (($resto === 0) ? 0 : 10 - $resto);
    }
}

