<?php
require_once __DIR__ . '/../../sistema.class.php';
require_once __DIR__ . '/../includes/list_search.php';

class Insumos extends Sistema
{
    private function normalizarDecimal($valor, int $decimales = 2): string
    {
        $numero = (float) $valor;
        $epsilon = pow(10, -($decimales + 3));
        $ajustado = $numero + ($numero >= 0 ? $epsilon : -$epsilon);
        return number_format(round($ajustado, $decimales), $decimales, '.', '');
    }

    /** Cantidad física para inventario insumos (frascos/unidades pueden ser fraccionarias). */
    private function normalizarCantidadExistencia($valor): string
    {
        return $this->normalizarDecimal($valor, 3);
    }

    public function obtenerCatalogos(): array
    {
        return [
            'tiendas' => $this->getDb()->query(
                "SELECT id_tienda, nom_tienda FROM tiendas WHERE COALESCE(activo, 1) = 1 ORDER BY nom_tienda ASC"
            )->fetchAll(PDO::FETCH_ASSOC),
            'categorias' => $this->getDb()->query(
                "SELECT id_categoria, nombre
                 FROM insumo_categorias
                 WHERE activo = 1
                 ORDER BY nombre ASC"
            )->fetchAll(PDO::FETCH_ASSOC),
        ];
    }

    /**
     * Listado administrativo de insumos activos con suma global de stock.
     */
    public function leer(?string $busqueda = null): array
    {
        $pat = joyeria_like_pattern($busqueda);
        $sql = "SELECT i.id_insumo,
                       i.nombre,
                       c.nombre AS categoria,
                       i.id_categoria_FK,
                       i.sku_codigo,
                       i.costo_referencia,
                       i.aumento_pct,
                       i.precio_venta_sugerido,
                       i.observaciones,
                       i.fecha_alta,
                       COALESCE(SUM(e.cantidad), 0) AS stock_total
                FROM insumos i
                LEFT JOIN insumo_categorias c ON i.id_categoria_FK = c.id_categoria
                LEFT JOIN insumo_existencia e ON e.id_insumo_FK = i.id_insumo
                WHERE i.activo = 1";
        if ($pat !== null) {
            $sql .= " AND (
                i.nombre LIKE :busq OR i.sku_codigo LIKE :busq2 OR c.nombre LIKE :busq3
                OR i.observaciones LIKE :busq4 OR CAST(i.id_insumo AS CHAR) LIKE :busq5
            )";
        }
        $sql .= " GROUP BY i.id_insumo, i.nombre, c.nombre, i.id_categoria_FK, i.sku_codigo,
                         i.costo_referencia, i.aumento_pct, i.precio_venta_sugerido, i.observaciones, i.fecha_alta
                ORDER BY i.nombre ASC";

        $stmt = $this->getDb()->prepare($sql);
        if ($pat !== null) {
            $stmt->bindValue(':busq', $pat, PDO::PARAM_STR);
            $stmt->bindValue(':busq2', $pat, PDO::PARAM_STR);
            $stmt->bindValue(':busq3', $pat, PDO::PARAM_STR);
            $stmt->bindValue(':busq4', $pat, PDO::PARAM_STR);
            $stmt->bindValue(':busq5', $pat, PDO::PARAM_STR);
        }
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function leerUno(int $idInsumo): ?array
    {
        $stmt = $this->getDb()->prepare(
            'SELECT i.*,
                    c.nombre AS categoria_nombre
             FROM insumos i
             LEFT JOIN insumo_categorias c ON i.id_categoria_FK = c.id_categoria
             WHERE i.id_insumo = :id
             LIMIT 1'
        );
        $stmt->bindValue(':id', $idInsumo, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /** Mapa id_tienda => cantidad (cadena decimal). */
    public function obtenerExistenciasPorTienda(int $idInsumo): array
    {
        $stmt = $this->getDb()->prepare(
            'SELECT id_tienda_FK, cantidad FROM insumo_existencia WHERE id_insumo_FK = :id'
        );
        $stmt->bindValue(':id', $idInsumo, PDO::PARAM_INT);
        $stmt->execute();
        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $map[(int) $row['id_tienda_FK']] = (string) $row['cantidad'];
        }

        return $map;
    }

    /**
     * Para POS/catálogo: insumos con existencia opcionalmente filtrada por tienda.
     */
    public function leerParaVentaPorTienda(?int $idTienda, bool $soloConStock = false): array
    {
        if ($idTienda !== null && $idTienda > 0) {
            $sql = 'SELECT i.id_insumo,
                           i.nombre,
                           c.nombre AS categoria,
                           i.sku_codigo,
                           i.precio_venta_sugerido,
                           COALESCE(e.cantidad, 0) AS cantidad_disponible
                    FROM insumos i
                    LEFT JOIN insumo_categorias c ON i.id_categoria_FK = c.id_categoria
                    LEFT JOIN insumo_existencia e ON e.id_insumo_FK = i.id_insumo AND e.id_tienda_FK = :id_tienda
                    WHERE i.activo = 1
                    ORDER BY i.nombre ASC';
            $stmt = $this->getDb()->prepare($sql);
            $stmt->bindValue(':id_tienda', $idTienda, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if ($soloConStock) {
                return array_values(array_filter($rows, static function ($r) {
                    return (float) ($r['cantidad_disponible'] ?? 0) > 0;
                }));
            }

            return $rows;
        }

        return $this->getDb()->query(
            'SELECT i.id_insumo,
                    i.nombre,
                    c.nombre AS categoria,
                    i.sku_codigo,
                    i.precio_venta_sugerido
             FROM insumos i
             LEFT JOIN insumo_categorias c ON i.id_categoria_FK = c.id_categoria
             WHERE i.activo = 1
             ORDER BY i.nombre ASC'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function crear(array $data): int
    {
        $nombre = $this->validarTexto($data, 'nombre', 150, 'El nombre');
        $idCategoria = $this->validarCategoriaOpcional($data);
        $costoRef = $this->validarDecimalOpcional($data, 'costo_referencia', 'El costo de referencia');
        $aum = $this->validarDecimalOpcionalRango($data, 'aumento_pct', 'El aumento', 0, 10000);
        $pvp = $this->validarDecimalOpcional($data, 'precio_venta_sugerido', 'El precio de venta sugerido');
        $obs = $this->validarTextoOpcional($data, 'observaciones', 500);
        $promo = $this->validarPromoCantidad($data);

        $existenciasPost = isset($data['existencia']) && is_array($data['existencia']) ? $data['existencia'] : [];

        $db = $this->getDb();
        $db->beginTransaction();
        try {
            $stmt = $db->prepare(
                'INSERT INTO insumos
                (nombre, id_categoria_FK, sku_codigo, costo_referencia, aumento_pct, precio_venta_sugerido, observaciones,
                 promo_paga_unidades, promo_lleva_unidades, activo)
                VALUES (:nombre, :id_cat, NULL, :costo, :aum, :pvp, :obs, :promo_paga, :promo_lleva, 1)'
            );
            $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
            $stmt->bindValue(':id_cat', $idCategoria, $idCategoria === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(':costo', $costoRef, $costoRef === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':aum', $aum, $aum === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':pvp', $pvp, $pvp === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':obs', $obs, $obs === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':promo_paga', $promo['paga'], $promo['paga'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(':promo_lleva', $promo['lleva'], $promo['lleva'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->execute();

            $id = (int) $db->lastInsertId();

            $skuAuto = $this->generarSkuAuto($id);
            $stmtSku = $db->prepare('UPDATE insumos SET sku_codigo = :sku WHERE id_insumo = :id');
            $stmtSku->bindValue(':sku', $skuAuto, PDO::PARAM_STR);
            $stmtSku->bindValue(':id', $id, PDO::PARAM_INT);
            $stmtSku->execute();

            $this->aplicarExistenciasFormulario($db, $id, $existenciasPost);
            $db->commit();

            return $id;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public function actualizar(int $idInsumo, array $data): int
    {
        $nombre = $this->validarTexto($data, 'nombre', 150, 'El nombre');
        $idCategoria = $this->validarCategoriaOpcional($data);
        $costoRef = $this->validarDecimalOpcional($data, 'costo_referencia', 'El costo de referencia');
        $aum = $this->validarDecimalOpcionalRango($data, 'aumento_pct', 'El aumento', 0, 10000);
        $pvp = $this->validarDecimalOpcional($data, 'precio_venta_sugerido', 'El precio de venta sugerido');
        $obs = $this->validarTextoOpcional($data, 'observaciones', 500);
        $promo = $this->validarPromoCantidad($data);
        $existenciasPost = isset($data['existencia']) && is_array($data['existencia']) ? $data['existencia'] : [];

        $db = $this->getDb();
        $db->beginTransaction();
        try {
            $this->verificarActivoPorId($db, $idInsumo);

            $skuActual = $this->obtenerSkuActivo($db, $idInsumo);
            if ($skuActual === null) {
                $skuActual = $this->generarSkuAuto($idInsumo);
            }

            $stmt = $db->prepare(
                'UPDATE insumos SET nombre = :nombre, id_categoria_FK = :id_cat, sku_codigo = :sku,
                 costo_referencia = :costo, aumento_pct = :aum, precio_venta_sugerido = :pvp, observaciones = :obs,
                 promo_paga_unidades = :promo_paga, promo_lleva_unidades = :promo_lleva
                 WHERE id_insumo = :id AND activo = 1'
            );
            $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
            $stmt->bindValue(':id_cat', $idCategoria, $idCategoria === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(':sku', $skuActual, PDO::PARAM_STR);
            $stmt->bindValue(':costo', $costoRef, $costoRef === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':aum', $aum, $aum === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':pvp', $pvp, $pvp === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':obs', $obs, $obs === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':promo_paga', $promo['paga'], $promo['paga'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(':promo_lleva', $promo['lleva'], $promo['lleva'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(':id', $idInsumo, PDO::PARAM_INT);
            $stmt->execute();

            $filasEliminadasExistencia = 0;
            if (isset($data['existencias_sincronizar'])) {
                $filasEliminadasExistencia = $this->eliminarExistenciasTiendasNoEnFormulario(
                    $db,
                    $idInsumo,
                    $existenciasPost
                );
            }

            $cambiosExistencias = $this->aplicarExistenciasFormulario($db, $idInsumo, $existenciasPost);
            $db->commit();

            return $stmt->rowCount() + $cambiosExistencias + $filasEliminadasExistencia;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public function borrar(int $idInsumo): int
    {
        $stmt = $this->getDb()->prepare('UPDATE insumos SET activo = 0 WHERE id_insumo = :id AND activo = 1');
        $stmt->bindValue(':id', $idInsumo, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount();
    }

    /**
     * Ajuste relativo desde ventas (consume stock). Debe ejecutarse dentro de transacción.
     */
    public function decrementarExistenciaTienda(PDO $db, int $idInsumo, int $idTienda, string $cantidadStr): void
    {
        $cant = (float) $cantidadStr;
        if ($cant <= 0) {
            throw new InvalidArgumentException('La cantidad de insumo a descontar no es válida.');
        }

        $stmt = $db->prepare(
            'UPDATE insumo_existencia
             SET cantidad = cantidad - :q
             WHERE id_insumo_FK = :ins AND id_tienda_FK = :tnd AND cantidad >= :q2'
        );
        $stmt->bindValue(':q', $this->normalizarCantidadExistencia($cant), PDO::PARAM_STR);
        $stmt->bindValue(':q2', $this->normalizarCantidadExistencia($cant), PDO::PARAM_STR);
        $stmt->bindValue(':ins', $idInsumo, PDO::PARAM_INT);
        $stmt->bindValue(':tnd', $idTienda, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() !== 1) {
            throw new InvalidArgumentException('Stock insuficiente del insumo en la tienda indicada.');
        }
    }

    /**
     * En edición, las filas quitadas en el formulario dejan de enviarse en POST; aquí se borran
     * los registros de insumo_existencia que ya no aplican (cambio persistente en BD).
     */
    private function eliminarExistenciasTiendasNoEnFormulario(PDO $db, int $idInsumo, array $existenciasPorTienda): int
    {
        $idsEnFormulario = [];
        foreach (array_keys($existenciasPorTienda) as $idRaw) {
            $id = (int) $idRaw;
            if ($id > 0) {
                $idsEnFormulario[$id] = true;
            }
        }

        if ($idsEnFormulario === []) {
            $stmt = $db->prepare('DELETE FROM insumo_existencia WHERE id_insumo_FK = :ins');
            $stmt->bindValue(':ins', $idInsumo, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->rowCount();
        }

        $ids = array_keys($idsEnFormulario);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = 'DELETE FROM insumo_existencia WHERE id_insumo_FK = ? AND id_tienda_FK NOT IN (' . $placeholders . ')';
        $stmt = $db->prepare($sql);
        $stmt->bindValue(1, $idInsumo, PDO::PARAM_INT);
        $i = 2;
        foreach ($ids as $idTienda) {
            $stmt->bindValue($i, $idTienda, PDO::PARAM_INT);
            $i++;
        }
        $stmt->execute();

        return $stmt->rowCount();
    }

    private function aplicarExistenciasFormulario(PDO $db, int $idInsumo, array $existenciasPorTienda): int
    {
        $cambios = 0;
        if ($existenciasPorTienda === []) {
            return 0;
        }

        $tiendasVistas = [];
        foreach ($existenciasPorTienda as $idTiendaRaw => $qtyRaw) {
            $idT = (int) $idTiendaRaw;
            if ($idT <= 0) {
                continue;
            }
            if (isset($tiendasVistas[$idT])) {
                throw new InvalidArgumentException('La misma tienda no puede repetirse en existencias.');
            }
            $tiendasVistas[$idT] = true;
        }

        foreach ($existenciasPorTienda as $idTiendaRaw => $qtyRaw) {
            $idTienda = (int) $idTiendaRaw;
            if ($idTienda <= 0) {
                continue;
            }

            $trim = trim((string) $qtyRaw);
            if ($trim === '') {
                continue;
            }

            if (!is_numeric($trim)) {
                throw new InvalidArgumentException('Cantidad invalida para la tienda ' . $idTienda . '.');
            }

            $qty = $this->normalizarCantidadExistencia((float) $trim);
            if ((float) $qty < 0) {
                throw new InvalidArgumentException('La cantidad en tienda no puede ser negativa.');
            }

            $this->verificarTiendaActiva($db, $idTienda);

            $stmtUp = $db->prepare(
                'UPDATE insumo_existencia SET cantidad = :cant
                 WHERE id_insumo_FK = :ins AND id_tienda_FK = :tnd'
            );
            $stmtUp->bindValue(':cant', $qty, PDO::PARAM_STR);
            $stmtUp->bindValue(':ins', $idInsumo, PDO::PARAM_INT);
            $stmtUp->bindValue(':tnd', $idTienda, PDO::PARAM_INT);
            $stmtUp->execute();
            $upAfectadas = $stmtUp->rowCount();
            if ($upAfectadas > 0) {
                $cambios += $upAfectadas;
            }

            if ($upAfectadas === 0) {
                // rowCount() puede ser 0 cuando el registro existe pero la cantidad es la misma.
                // Validamos existencia real antes de intentar insertar para evitar duplicados.
                $stmtExiste = $db->prepare(
                    'SELECT 1
                     FROM insumo_existencia
                     WHERE id_insumo_FK = :ins AND id_tienda_FK = :tnd
                     LIMIT 1'
                );
                $stmtExiste->bindValue(':ins', $idInsumo, PDO::PARAM_INT);
                $stmtExiste->bindValue(':tnd', $idTienda, PDO::PARAM_INT);
                $stmtExiste->execute();

                if (!$stmtExiste->fetchColumn()) {
                    $stmtIn = $db->prepare(
                        'INSERT INTO insumo_existencia (id_insumo_FK, id_tienda_FK, cantidad) VALUES (:ins, :tnd, :cant)'
                    );
                    $stmtIn->bindValue(':ins', $idInsumo, PDO::PARAM_INT);
                    $stmtIn->bindValue(':tnd', $idTienda, PDO::PARAM_INT);
                    $stmtIn->bindValue(':cant', $qty, PDO::PARAM_STR);
                    $stmtIn->execute();
                    $cambios += $stmtIn->rowCount();
                }
            }
        }

        return $cambios;
    }

    private function verificarTiendaActiva(PDO $db, int $idTienda): void
    {
        $s = $db->prepare(
            'SELECT 1 FROM tiendas WHERE id_tienda = :id AND COALESCE(activo, 1) = 1 LIMIT 1'
        );
        $s->bindValue(':id', $idTienda, PDO::PARAM_INT);
        $s->execute();
        if (!$s->fetchColumn()) {
            throw new InvalidArgumentException('La tienda no existe o está inactiva.');
        }
    }

    private function verificarActivoPorId(PDO $db, int $id): void
    {
        $s = $db->prepare('SELECT 1 FROM insumos WHERE id_insumo = :id AND activo = 1 LIMIT 1');
        $s->bindValue(':id', $id, PDO::PARAM_INT);
        $s->execute();
        if (!$s->fetchColumn()) {
            throw new InvalidArgumentException('El insumo no existe o esta inactivo.');
        }
    }

    private function validarTexto(array $data, string $campo, int $max, string $label): string
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

    private function validarTextoOpcional(array $data, string $campo, int $max): ?string
    {
        if (!isset($data[$campo]) || trim((string) $data[$campo]) === '') {
            return null;
        }
        $valor = trim(strip_tags((string) $data[$campo]));
        if (mb_strlen($valor) > $max) {
            $valor = mb_substr($valor, 0, $max);
        }

        return $valor === '' ? null : $valor;
    }

    private function validarCategoriaOpcional(array $data): ?int
    {
        if (!isset($data['id_categoria_FK']) || trim((string) $data['id_categoria_FK']) === '' || (int) $data['id_categoria_FK'] <= 0) {
            return null;
        }
        $id = (int) $data['id_categoria_FK'];

        $stmt = $this->getDb()->prepare(
            'SELECT 1 FROM insumo_categorias WHERE id_categoria = :id AND activo = 1 LIMIT 1'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        if (!$stmt->fetchColumn()) {
            throw new InvalidArgumentException('La categoria seleccionada no existe o esta inactiva.');
        }

        return $id;
    }

    private function validarDecimalOpcional(array $data, string $campo, string $labelNegado): ?string
    {
        if (!isset($data[$campo]) || trim((string) $data[$campo]) === '') {
            return null;
        }
        $v = trim((string) $data[$campo]);
        if (!is_numeric($v)) {
            throw new InvalidArgumentException($labelNegado . ' debe ser numerico.');
        }
        $f = (float) $v;
        if ($f < 0) {
            throw new InvalidArgumentException($labelNegado . ' no puede ser negativo.');
        }

        return $this->normalizarDecimal($f, 2);
    }

    private function validarDecimalOpcionalRango(array $data, string $campo, string $labelNegado, float $min, float $max): ?string
    {
        if (!isset($data[$campo]) || trim((string) $data[$campo]) === '') {
            return null;
        }
        $v = trim((string) $data[$campo]);
        if (!is_numeric($v)) {
            throw new InvalidArgumentException($labelNegado . ' debe ser numerico.');
        }
        $f = (float) $v;
        if ($f < $min || $f > $max) {
            throw new InvalidArgumentException($labelNegado . ' debe estar entre ' . $min . ' y ' . $max . '.');
        }

        return $this->normalizarDecimal($f, 2);
    }

    private function generarSkuAuto(int $idInsumo): string
    {
        return $idInsumo . '/1';
    }

    private function obtenerSkuActivo(PDO $db, int $idInsumo): ?string
    {
        $stmt = $db->prepare(
            'SELECT sku_codigo FROM insumos WHERE id_insumo = :id AND activo = 1 LIMIT 1'
        );
        $stmt->bindValue(':id', $idInsumo, PDO::PARAM_INT);
        $stmt->execute();
        $sku = $stmt->fetchColumn();
        if ($sku === false || trim((string) $sku) === '') {
            return null;
        }

        return trim((string) $sku);
    }

    public function obtenerExistenciaNumericaTienda(PDO $db, int $idInsumo, int $idTienda): float
    {
        $stmt = $db->prepare(
            'SELECT cantidad FROM insumo_existencia WHERE id_insumo_FK = :i AND id_tienda_FK = :t LIMIT 1'
        );
        $stmt->bindValue(':i', $idInsumo, PDO::PARAM_INT);
        $stmt->bindValue(':t', $idTienda, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return 0.0;
        }

        return (float) $row['cantidad'];
    }

    /** GET /api: buscar categorias por texto (para autocompletar). */
    public function buscarCategorias(?string $q, int $limit = 20): array
    {
        $q = $q !== null ? trim($q) : '';
        $limit = max(1, min(50, (int) $limit));

        if ($q === '') {
            $stmt = $this->getDb()->prepare(
                'SELECT id_categoria, nombre
                 FROM insumo_categorias
                 WHERE activo = 1
                 ORDER BY nombre ASC
                 LIMIT ' . $limit
            );
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $stmt = $this->getDb()->prepare(
            'SELECT id_categoria, nombre
             FROM insumo_categorias
             WHERE activo = 1 AND nombre LIKE :q
             ORDER BY nombre ASC
             LIMIT ' . $limit
        );
        $stmt->bindValue(':q', '%' . $q . '%', PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** POST /api: crear categoria si no existe; retorna id. */
    public function crearCategoria(string $nombre): int
    {
        $nombre = trim(strip_tags($nombre));
        if ($nombre === '') {
            throw new InvalidArgumentException('El nombre de la categoria es obligatorio.');
        }
        if (mb_strlen($nombre) > 80) {
            $nombre = mb_substr($nombre, 0, 80);
        }

        $db = $this->getDb();
        $stmt = $db->prepare(
            'SELECT id_categoria FROM insumo_categorias WHERE nombre = :n LIMIT 1'
        );
        $stmt->bindValue(':n', $nombre, PDO::PARAM_STR);
        $stmt->execute();
        $id = $stmt->fetchColumn();
        if ($id) {
            return (int) $id;
        }

        $ins = $db->prepare('INSERT INTO insumo_categorias (nombre, activo) VALUES (:n, 1)');
        $ins->bindValue(':n', $nombre, PDO::PARAM_STR);
        $ins->execute();

        return (int) $db->lastInsertId();
    }

    /**
     * @return array{paga: ?int, lleva: ?int}
     */
    private function validarPromoCantidad(array $data): array
    {
        $paga = isset($data['promo_paga_unidades']) && trim((string) $data['promo_paga_unidades']) !== ''
            ? (int) $data['promo_paga_unidades']
            : null;
        $lleva = isset($data['promo_lleva_unidades']) && trim((string) $data['promo_lleva_unidades']) !== ''
            ? (int) $data['promo_lleva_unidades']
            : null;

        if ($paga === null && $lleva === null) {
            return ['paga' => null, 'lleva' => null];
        }
        if ($paga === null || $lleva === null || $paga <= 0 || $lleva <= 0) {
            throw new InvalidArgumentException('La promocion por cantidad requiere unidades a pagar y unidades gratis/lleva.');
        }

        return ['paga' => $paga, 'lleva' => $lleva];
    }
}
