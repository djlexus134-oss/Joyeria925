<?php
declare(strict_types=1);

require_once __DIR__ . '/../../sistema.class.php';
require_once __DIR__ . '/pieza.php';
require_once __DIR__ . '/../includes/PromocionTiendaResolver.php';
require_once __DIR__ . '/../includes/DescuentoTiendaService.php';

class Carrito extends Sistema
{
    public const RESERVA_TTL_MINUTOS = 30;
    /**
     * TTL extendido para usar durante el checkout (creacion de preferencia MP).
     * Cubre pagos lentos con 3DS / OTP del banco / Checkout Pro en mobile que
     * tarda mas que el TTL normal de 30 min.
     */
    public const RESERVA_TTL_CHECKOUT_MINUTOS = 90;

    private function normalizarDecimal($valor, int $decimales = 2): string
    {
        $numero = (float) $valor;
        $epsilon = pow(10, -($decimales + 3));
        $ajustado = $numero + ($numero >= 0 ? $epsilon : -$epsilon);
        return number_format(round($ajustado, $decimales), $decimales, '.', '');
    }

    /**
     * Libera reservas vencidas (estado=reservada_online, reservada_hasta < NOW()).
     */
    public function liberarExpiradas(): int
    {
        $db = $this->getDb();

        // Antes de liberar, borrar los carrito_items que apunten a piezas con reserva caducada.
        $del = $db->prepare(
            "DELETE ci FROM carrito_items ci
             INNER JOIN piezas_stock ps ON ps.id_pieza_stock = ci.id_pieza_stock_FK
             WHERE ps.estado = 'reservada_online'
               AND ps.reservada_hasta IS NOT NULL
               AND ps.reservada_hasta < NOW()"
        );
        $del->execute();

        $upd = $db->prepare(
            "UPDATE piezas_stock
             SET estado = 'disponible', reservada_hasta = NULL, id_carrito_owner = NULL
             WHERE estado = 'reservada_online'
               AND reservada_hasta IS NOT NULL
               AND reservada_hasta < NOW()"
        );
        $upd->execute();

        return $upd->rowCount();
    }

    /**
     * Agrega una pieza al carrito reservandola con CAS.
     *
     * @return array{ok:bool, error?:string, id_carrito_item?:int}
     */
    public function agregar(
        int $idCliente,
        int $idPieza,
        ?string $varianteValor = null,
        ?string $varianteColor = null,
        ?string $varianteTalla = null,
        ?int $varianteValor1Id = null,
        ?int $varianteValor2Id = null,
        ?string $varianteEje1 = null,
        ?string $varianteEje2 = null
    ): array {
        if ($idCliente <= 0) {
            return ['ok' => false, 'error' => 'Cliente invalido.'];
        }
        if ($idPieza <= 0) {
            return ['ok' => false, 'error' => 'Pieza invalida.'];
        }

        $this->liberarExpiradas();

        $piezaModel = new Pieza();
        $stockDisponible = $piezaModel->contarStockDisponible($idPieza);
        if (!Pieza::esComprableOnlinePorStock($stockDisponible)) {
            return [
                'ok' => false,
                'error' => 'Esta pieza no esta disponible para compra en linea en este momento.',
            ];
        }

        $resumenVar = $piezaModel->resumenVariantesDisponibles($idPieza);
        $modo = (string) ($resumenVar['modo'] ?? $resumenVar['variante_tipo'] ?? 'ninguna');
        $valorVariante = trim((string) ($varianteValor ?? ''));
        $colorSel = trim((string) ($varianteColor ?? ''));
        $tallaSel = trim((string) ($varianteTalla ?? ''));
        $eje1Sel = trim((string) ($varianteEje1 ?? ''));
        $eje2Sel = trim((string) ($varianteEje2 ?? ''));
        $valor1Id = ($varianteValor1Id !== null && $varianteValor1Id > 0) ? $varianteValor1Id : null;
        $valor2Id = ($varianteValor2Id !== null && $varianteValor2Id > 0) ? $varianteValor2Id : null;
        $usaCatalogo = $piezaModel->tieneColumnasVarianteCatalogo();

        if (!empty($resumenVar['tiene_variantes'])) {
            if ($modo === 'talla_color') {
                if ($colorSel === '' || $tallaSel === '') {
                    return ['ok' => false, 'error' => 'Selecciona color y talla antes de agregar al carrito.'];
                }
                $encontrada = false;
                $matriz = is_array($resumenVar['matriz'] ?? null) ? $resumenVar['matriz'] : [];
                if (isset($matriz[$colorSel][$tallaSel]) && (int) $matriz[$colorSel][$tallaSel] > 0) {
                    $encontrada = true;
                }
                if (!$encontrada) {
                    return ['ok' => false, 'error' => 'La combinacion seleccionada ya no esta disponible.'];
                }
            } elseif ($modo === 'dos_ejes') {
                if ($valor1Id !== null && $valor2Id !== null) {
                    $matrizIds = is_array($resumenVar['matriz_ids'] ?? null) ? $resumenVar['matriz_ids'] : [];
                    if (!isset($matrizIds[$valor1Id][$valor2Id]) || (int) $matrizIds[$valor1Id][$valor2Id] <= 0) {
                        return ['ok' => false, 'error' => 'La combinacion seleccionada ya no esta disponible.'];
                    }
                } elseif ($eje1Sel === '' || $eje2Sel === '') {
                    $ejes = is_array($resumenVar['ejes'] ?? null) ? $resumenVar['ejes'] : [];
                    $etiq1 = strtolower((string) ($ejes[0]['tipo'] ?? 'opcion'));
                    $etiq2 = strtolower((string) ($ejes[1]['tipo'] ?? 'opcion'));

                    return ['ok' => false, 'error' => 'Selecciona ' . $etiq1 . ' y ' . $etiq2 . ' antes de agregar al carrito.'];
                } else {
                    $matriz = is_array($resumenVar['matriz'] ?? null) ? $resumenVar['matriz'] : [];
                    if (!isset($matriz[$eje1Sel][$eje2Sel]) || (int) $matriz[$eje1Sel][$eje2Sel] <= 0) {
                        return ['ok' => false, 'error' => 'La combinacion seleccionada ya no esta disponible.'];
                    }
                }
            } else {
                if ($valorVariante === '' && $eje1Sel === '' && $valor1Id === null) {
                    $etiq = strtolower((string) ($resumenVar['variante_etiqueta'] ?? 'variante'));

                    return ['ok' => false, 'error' => 'Selecciona ' . $etiq . ' antes de agregar al carrito.'];
                }
                $encontrada = false;
                foreach ($resumenVar['variantes'] as $v) {
                    if (!is_array($v)) {
                        continue;
                    }
                    $matchValor = $valorVariante !== ''
                        && (string) ($v['valor'] ?? '') === $valorVariante;
                    $matchEje = $eje1Sel !== ''
                        && (string) ($v['valor1'] ?? '') === $eje1Sel;
                    $matchId = $valor1Id !== null
                        && (int) ($v['valor1_id'] ?? 0) === $valor1Id;
                    if (($matchValor || $matchEje || $matchId) && (int) ($v['cantidad'] ?? 0) > 0) {
                        $encontrada = true;
                        if ($eje1Sel === '' && isset($v['valor1'])) {
                            $eje1Sel = (string) $v['valor1'];
                        }
                        if ($valor1Id === null && isset($v['valor1_id']) && (int) $v['valor1_id'] > 0) {
                            $valor1Id = (int) $v['valor1_id'];
                        }
                        break;
                    }
                }
                if (!$encontrada) {
                    return ['ok' => false, 'error' => 'La opcion seleccionada ya no esta disponible.'];
                }
                if ($modo === 'talla') {
                    $tallaSel = $eje1Sel !== '' ? $eje1Sel : $valorVariante;
                } elseif ($modo === 'color') {
                    $colorSel = $eje1Sel !== '' ? $eje1Sel : $valorVariante;
                }
            }
        }

        $db = $this->getDb();
        $db->beginTransaction();

        try {
            $sqlStock = "SELECT ps.id_pieza_stock, ps.precio_venta
                 FROM piezas_stock ps
                 WHERE ps.id_pieza_FK = :id_pieza
                   AND ps.activo = 1
                   AND ps.estado = 'disponible'";
            if (!empty($resumenVar['tiene_variantes'])) {
                if ($usaCatalogo && $valor1Id !== null && $valor2Id !== null) {
                    $sqlStock .= ' AND ps.variante_valor1_id = :variante_valor1_id
                                   AND ps.variante_valor2_id = :variante_valor2_id';
                } elseif ($usaCatalogo && $valor1Id !== null && ($modo === 'un_eje' || $modo === 'talla' || $modo === 'color')) {
                    $sqlStock .= ' AND ps.variante_valor1_id = :variante_valor1_id
                                   AND (ps.variante_valor2_id IS NULL OR ps.variante_valor2_id = 0)';
                } elseif ($modo === 'talla_color') {
                    $sqlStock .= " AND TRIM(COALESCE(ps.variante_color, '')) = :variante_color
                                   AND TRIM(COALESCE(ps.variante_talla, '')) = :variante_talla";
                } elseif ($modo === 'dos_ejes') {
                    require_once __DIR__ . '/../../includes/variantes_stock_helpers.php';
                    $sqlStock .= joyeria_sql_join_variantes_stock('ps');
                    $sqlStock .= " AND TRIM(COALESCE(vv1.valor, '')) = :variante_eje1
                                   AND TRIM(COALESCE(vv2.valor, '')) = :variante_eje2";
                } elseif ($modo === 'talla') {
                    $sqlStock .= " AND (
                        (TRIM(COALESCE(ps.variante_talla, '')) = :variante_talla)
                        OR (ps.variante_tipo = 'talla' AND ps.variante_valor = :variante_talla_legacy)
                    )";
                } else {
                    $sqlStock .= " AND (
                        (TRIM(COALESCE(ps.variante_color, '')) = :variante_color)
                        OR (ps.variante_tipo = 'color' AND ps.variante_valor = :variante_color_legacy)
                        OR (TRIM(COALESCE(ps.variante_valor, '')) = :variante_color)
                    )";
                }
            }
            $sqlStock .= " ORDER BY ps.id_pieza_stock ASC LIMIT 1 FOR UPDATE";

            $stmt = $db->prepare($sqlStock);
            $stmt->bindValue(':id_pieza', $idPieza, PDO::PARAM_INT);
            if (!empty($resumenVar['tiene_variantes'])) {
                if ($usaCatalogo && $valor1Id !== null && $valor2Id !== null) {
                    $stmt->bindValue(':variante_valor1_id', $valor1Id, PDO::PARAM_INT);
                    $stmt->bindValue(':variante_valor2_id', $valor2Id, PDO::PARAM_INT);
                } elseif ($usaCatalogo && $valor1Id !== null && ($modo === 'un_eje' || $modo === 'talla' || $modo === 'color')) {
                    $stmt->bindValue(':variante_valor1_id', $valor1Id, PDO::PARAM_INT);
                } elseif ($modo === 'talla_color') {
                    $stmt->bindValue(':variante_color', $colorSel, PDO::PARAM_STR);
                    $stmt->bindValue(':variante_talla', $tallaSel, PDO::PARAM_STR);
                } elseif ($modo === 'dos_ejes') {
                    $stmt->bindValue(':variante_eje1', $eje1Sel, PDO::PARAM_STR);
                    $stmt->bindValue(':variante_eje2', $eje2Sel, PDO::PARAM_STR);
                } elseif ($modo === 'talla') {
                    $stmt->bindValue(':variante_talla', $tallaSel, PDO::PARAM_STR);
                    $stmt->bindValue(':variante_talla_legacy', $tallaSel, PDO::PARAM_STR);
                } else {
                    $buscar = $colorSel !== '' ? $colorSel : ($eje1Sel !== '' ? $eje1Sel : $valorVariante);
                    $stmt->bindValue(':variante_color', $buscar, PDO::PARAM_STR);
                    $stmt->bindValue(':variante_color_legacy', $buscar, PDO::PARAM_STR);
                }
            }
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                $db->rollBack();

                return ['ok' => false, 'error' => 'No hay piezas disponibles en stock para la opcion seleccionada.'];
            }

            $idStock = (int) $row['id_pieza_stock'];
            $precioLista = $row['precio_venta'];

            if ($precioLista === null || trim((string) $precioLista) === '' || (float) $precioLista <= 0) {
                $precioLista = $this->resolverPrecioPiezaCatalogo($db, $idPieza);
            } else {
                $precioLista = (float) $precioLista;
            }

            $precioListaSnapshot = $precioLista;
            $precioVenta = $precioLista;
            $idPromocionFk = null;

            // CAS: marcar reservada_online
            $upd = $db->prepare(
                "UPDATE piezas_stock
                 SET estado = 'reservada_online',
                     reservada_hasta = DATE_ADD(NOW(), INTERVAL :ttl MINUTE),
                     id_carrito_owner = :id_cliente
                 WHERE id_pieza_stock = :id
                   AND estado = 'disponible'
                   AND activo = 1"
            );
            $upd->bindValue(':ttl', self::RESERVA_TTL_MINUTOS, PDO::PARAM_INT);
            $upd->bindValue(':id_cliente', $idCliente, PDO::PARAM_INT);
            $upd->bindValue(':id', $idStock, PDO::PARAM_INT);
            $upd->execute();
            if ($upd->rowCount() !== 1) {
                $db->rollBack();
                return ['ok' => false, 'error' => 'La pieza acaba de ser apartada por otro cliente.'];
            }

            $colsPromo = $this->columnasCarritoPromo($db);
            $insSql = 'INSERT INTO carrito_items (id_cliente_FK, id_pieza_stock_FK, precio_unitario_snapshot';
            $insVals = 'VALUES (:id_cliente, :id_stock, :precio';
            if ($colsPromo['precio_lista_snapshot']) {
                $insSql .= ', precio_lista_snapshot';
                $insVals .= ', :precio_lista';
            }
            if ($colsPromo['id_promocion_FK']) {
                $insSql .= ', id_promocion_FK';
                $insVals .= ', :id_promo';
            }
            $insSql .= ', fecha_alta) ' . $insVals . ', NOW())';
            $ins = $db->prepare($insSql);
            $ins->bindValue(':id_cliente', $idCliente, PDO::PARAM_INT);
            $ins->bindValue(':id_stock', $idStock, PDO::PARAM_INT);
            $ins->bindValue(':precio', $this->normalizarDecimal($precioVenta), PDO::PARAM_STR);
            if ($colsPromo['precio_lista_snapshot']) {
                $ins->bindValue(':precio_lista', $this->normalizarDecimal($precioListaSnapshot), PDO::PARAM_STR);
            }
            if ($colsPromo['id_promocion_FK']) {
                $ins->bindValue(
                    ':id_promo',
                    $idPromocionFk !== null ? (int) $idPromocionFk : null,
                    $idPromocionFk !== null ? PDO::PARAM_INT : PDO::PARAM_NULL
                );
            }
            $ins->execute();
            $idCi = (int) $db->lastInsertId();

            $db->commit();
            $this->sincronizarPreciosDescuento($idCliente);

            return ['ok' => true, 'id_carrito_item' => $idCi];
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('Carrito::agregar ' . $e->getMessage());
            return ['ok' => false, 'error' => 'No se pudo agregar al carrito.'];
        }
    }

    public function eliminar(int $idCliente, int $idCarritoItem): array
    {
        if ($idCliente <= 0 || $idCarritoItem <= 0) {
            return ['ok' => false, 'error' => 'Datos invalidos.'];
        }
        $db = $this->getDb();
        $db->beginTransaction();
        try {
            $stmt = $db->prepare(
                "SELECT id_pieza_stock_FK FROM carrito_items
                 WHERE id_carrito_item = :id AND id_cliente_FK = :cli
                 LIMIT 1"
            );
            $stmt->bindValue(':id', $idCarritoItem, PDO::PARAM_INT);
            $stmt->bindValue(':cli', $idCliente, PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                $db->rollBack();
                return ['ok' => false, 'error' => 'Item no encontrado.'];
            }
            $idStock = (int) $row['id_pieza_stock_FK'];

            $del = $db->prepare("DELETE FROM carrito_items WHERE id_carrito_item = :id");
            $del->bindValue(':id', $idCarritoItem, PDO::PARAM_INT);
            $del->execute();

            // Liberar la pieza si seguia reservada por este cliente.
            $upd = $db->prepare(
                "UPDATE piezas_stock
                 SET estado = 'disponible', reservada_hasta = NULL, id_carrito_owner = NULL
                 WHERE id_pieza_stock = :id
                   AND estado = 'reservada_online'
                   AND id_carrito_owner = :cli"
            );
            $upd->bindValue(':id', $idStock, PDO::PARAM_INT);
            $upd->bindValue(':cli', $idCliente, PDO::PARAM_INT);
            $upd->execute();

            $db->commit();
            $this->sincronizarPreciosDescuento($idCliente);

            return ['ok' => true];
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('Carrito::eliminar ' . $e->getMessage());
            return ['ok' => false, 'error' => 'No se pudo eliminar.'];
        }
    }

    public function listar(int $idCliente): array
    {
        if ($idCliente <= 0) {
            return [];
        }
        $this->liberarExpiradas();
        $this->sincronizarPreciosDescuento($idCliente);

        return $this->consultarItemsCarrito($idCliente);
    }

    /**
     * Recalcula snapshots del carrito con cliente, promoción y mayoreo del ticket.
     */
    public function sincronizarPreciosDescuento(int $idCliente): void
    {
        if ($idCliente <= 0) {
            return;
        }

        $items = $this->consultarItemsCarrito($idCliente);
        if ($items === []) {
            return;
        }

        $svc = new DescuentoTiendaService();
        $reglas = new ReglasDescuentoService();
        $metalesMap = $reglas->cargarMetalesActivos();
        $subtotalLista = $svc->calcularSubtotalJoyasListaCarrito($items);
        $conteoPorMetal = [];
        $subtotalPlata = 0.0;
        foreach ($items as $itPre) {
            if (!is_array($itPre)) {
                continue;
            }
            $idMetalPre = (int) ($itPre['id_metal_FK'] ?? 0);
            if ($idMetalPre > 0) {
                $conteoPorMetal[$idMetalPre] = ($conteoPorMetal[$idMetalPre] ?? 0) + 1;
            }
            $listaPre = isset($itPre['precio_lista_snapshot']) && $itPre['precio_lista_snapshot'] !== null && $itPre['precio_lista_snapshot'] !== ''
                ? (float) $itPre['precio_lista_snapshot']
                : (float) ($itPre['precio_unitario_snapshot'] ?? 0);
            $metalPre = $metalesMap[$idMetalPre] ?? null;
            if ($metalPre !== null && !empty($metalPre['aplica_mayoreo']) && (int) $metalPre['aplica_mayoreo'] === 1 && $listaPre > 0) {
                $subtotalPlata += $listaPre;
            }
        }
        $subtotalPlata = round($subtotalPlata, 2);
        $db = $this->getDb();
        $colsPromo = $this->columnasCarritoPromo($db);

        $setParts = ['precio_unitario_snapshot = :precio'];
        if ($colsPromo['precio_lista_snapshot']) {
            $setParts[] = 'precio_lista_snapshot = :precio_lista';
        }
        if ($colsPromo['id_promocion_FK']) {
            $setParts[] = 'id_promocion_FK = :id_promo';
        }
        $upd = $db->prepare(
            'UPDATE carrito_items SET ' . implode(', ', $setParts) . ' WHERE id_carrito_item = :id AND id_cliente_FK = :cli'
        );

        foreach ($items as $it) {
            if (!is_array($it)) {
                continue;
            }
            $precioLista = isset($it['precio_lista_snapshot']) && $it['precio_lista_snapshot'] !== null && $it['precio_lista_snapshot'] !== ''
                ? (float) $it['precio_lista_snapshot']
                : (float) ($it['precio_unitario_snapshot'] ?? 0);
            if ($precioLista <= 0) {
                continue;
            }

            $piezaCtx = [
                'id_pieza' => (int) ($it['id_pieza'] ?? 0),
                'id_sub_familia' => (int) ($it['id_sub_familia_FK'] ?? 0),
                'id_familia' => (int) ($it['id_familia_FK'] ?? 0),
                'id_metal_FK' => (int) ($it['id_metal_FK'] ?? 0),
            ];
            $idMetalItem = (int) ($it['id_metal_FK'] ?? 0);
            $conteoMetalItem = $idMetalItem > 0 ? (int) ($conteoPorMetal[$idMetalItem] ?? 1) : 1;
            $precios = $svc->calcularPreciosPieza(
                $piezaCtx,
                $precioLista,
                $idCliente,
                $subtotalLista,
                $conteoMetalItem,
                $subtotalPlata
            );
            $idPromo = null;
            if (($precios['descuento_origen'] ?? '') === 'promocion' && is_array($precios['promocion'] ?? null)) {
                $idPromo = (int) ($precios['promocion']['id_promocion'] ?? 0) ?: null;
            }

            $upd->bindValue(':precio', $this->normalizarDecimal($precios['precio_final']), PDO::PARAM_STR);
            if ($colsPromo['precio_lista_snapshot']) {
                $upd->bindValue(':precio_lista', $this->normalizarDecimal($precios['precio_lista']), PDO::PARAM_STR);
            }
            if ($colsPromo['id_promocion_FK']) {
                $upd->bindValue(
                    ':id_promo',
                    $idPromo,
                    $idPromo !== null ? PDO::PARAM_INT : PDO::PARAM_NULL
                );
            }
            $upd->bindValue(':id', (int) ($it['id_carrito_item'] ?? 0), PDO::PARAM_INT);
            $upd->bindValue(':cli', $idCliente, PDO::PARAM_INT);
            $upd->execute();
        }
    }

    private function consultarItemsCarrito(int $idCliente): array
    {
        $db = $this->getDb();
        $colsPromo = $this->columnasCarritoPromo($db);
        $extraCols = '';
        if ($colsPromo['precio_lista_snapshot']) {
            $extraCols .= ', ci.precio_lista_snapshot';
        }
        if ($colsPromo['id_promocion_FK']) {
            $extraCols .= ', ci.id_promocion_FK, pr.nombre AS promocion_nombre, pr.porcentaje_descuento AS promocion_porcentaje';
        }

        $colsVariante = '';
        $piezaCols = new Pieza();
        $joinCatalogo = '';
        if ($piezaCols->tieneColumnasVarianteStock()) {
            require_once __DIR__ . '/../../includes/variantes_stock_helpers.php';
            if ($piezaCols->tieneColumnasVarianteCatalogo()) {
                $joinCatalogo = joyeria_sql_join_variantes_stock('ps');
                $colsVariante = ', ' . joyeria_sql_select_variantes_stock();
            }
            $colsVariante .= ', ps.variante_tipo, ps.variante_valor';
            if ($piezaCols->tieneColumnasVarianteMatriz()) {
                $colsVariante .= ', ps.variante_talla, ps.variante_color';
            }
        }

        $sql = "SELECT ci.id_carrito_item,
                       ci.id_pieza_stock_FK,
                       ci.precio_unitario_snapshot{$extraCols},
                       ci.fecha_alta,
                       ps.codigo_auxiliar,
                       ps.estado AS estado_stock,
                       ps.reservada_hasta{$colsVariante},
                       p.id_pieza,
                       p.id_metal_FK,
                       p.id_sub_familia_FK,
                       sf.id_familia_FK,
                       p.desc_pieza,
                       sf.nom_sub_familia,
                       m.nom_metal,
                       t.id_tienda,
                       t.nom_tienda,
                       img.url_imagen
                FROM carrito_items ci
                INNER JOIN piezas_stock ps ON ps.id_pieza_stock = ci.id_pieza_stock_FK
                {$joinCatalogo}
                INNER JOIN piezas p ON p.id_pieza = ps.id_pieza_FK
                INNER JOIN sub_familia sf ON sf.id_sub_familia = p.id_sub_familia_FK
                INNER JOIN metales m ON m.id_metal = p.id_metal_FK
                INNER JOIN tiendas t ON t.id_tienda = p.id_tienda_FK
                LEFT JOIN imagenes_piezas img ON img.id_pieza_FK = p.id_pieza AND img.es_principal = 1";
        if ($colsPromo['id_promocion_FK']) {
            $sql .= "
                LEFT JOIN promociones pr ON pr.id_promocion = ci.id_promocion_FK";
        }
        $sql .= "
                WHERE ci.id_cliente_FK = :cli
                ORDER BY ci.fecha_alta ASC";
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':cli', $idCliente, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function contar(int $idCliente): int
    {
        if ($idCliente <= 0) return 0;
        $stmt = $this->getDb()->prepare("SELECT COUNT(*) FROM carrito_items WHERE id_cliente_FK = :cli");
        $stmt->bindValue(':cli', $idCliente, PDO::PARAM_INT);
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    /**
     * Devuelve totales y grupos por tienda.
     * @param array $items resultado de listar()
     */
    public function calcularResumen(array $items): array
    {
        $tiendas = [];
        $total = 0.0;
        $subtotalLista = 0.0;
        $totalDescuentos = 0.0;
        foreach ($items as $it) {
            $idTnd = (int) ($it['id_tienda'] ?? 0);
            $nomTnd = (string) ($it['nom_tienda'] ?? 'Sucursal');
            $precioFinal = (float) $it['precio_unitario_snapshot'];
            $precioLista = isset($it['precio_lista_snapshot']) && $it['precio_lista_snapshot'] !== null && $it['precio_lista_snapshot'] !== ''
                ? (float) $it['precio_lista_snapshot']
                : $precioFinal;
            $descuentoLinea = max(0.0, $precioLista - $precioFinal);

            if (!isset($tiendas[$idTnd])) {
                $tiendas[$idTnd] = [
                    'id_tienda' => $idTnd,
                    'nom_tienda' => $nomTnd,
                    'items' => [],
                    'subtotal' => 0.0,
                    'subtotal_lista' => 0.0,
                    'descuentos' => 0.0,
                ];
            }
            $tiendas[$idTnd]['items'][] = $it;
            $tiendas[$idTnd]['subtotal'] += $precioFinal;
            $tiendas[$idTnd]['subtotal_lista'] += $precioLista;
            $tiendas[$idTnd]['descuentos'] += $descuentoLinea;
            $total += $precioFinal;
            $subtotalLista += $precioLista;
            $totalDescuentos += $descuentoLinea;
        }
        return [
            'total' => $total,
            'subtotal_lista' => $subtotalLista,
            'total_descuentos' => $totalDescuentos,
            'tiendas' => array_values($tiendas),
            'multi_tienda' => count($tiendas) > 1,
            'items_count' => count($items),
        ];
    }

    public function vaciar(int $idCliente): void
    {
        if ($idCliente <= 0) return;
        $db = $this->getDb();
        $db->beginTransaction();
        try {
            // Liberar piezas
            $upd = $db->prepare(
                "UPDATE piezas_stock ps
                 INNER JOIN carrito_items ci ON ci.id_pieza_stock_FK = ps.id_pieza_stock
                 SET ps.estado = 'disponible',
                     ps.reservada_hasta = NULL,
                     ps.id_carrito_owner = NULL
                 WHERE ci.id_cliente_FK = :cli
                   AND ps.estado = 'reservada_online'"
            );
            $upd->bindValue(':cli', $idCliente, PDO::PARAM_INT);
            $upd->execute();

            $del = $db->prepare("DELETE FROM carrito_items WHERE id_cliente_FK = :cli");
            $del->bindValue(':cli', $idCliente, PDO::PARAM_INT);
            $del->execute();
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log('Carrito::vaciar ' . $e->getMessage());
        }
    }

    public function refrescarReservas(int $idCliente, int $ttlMinutos = self::RESERVA_TTL_MINUTOS): void
    {
        if ($idCliente <= 0) return;
        $stmt = $this->getDb()->prepare(
            "UPDATE piezas_stock ps
             INNER JOIN carrito_items ci ON ci.id_pieza_stock_FK = ps.id_pieza_stock
             SET ps.reservada_hasta = DATE_ADD(NOW(), INTERVAL :ttl MINUTE)
             WHERE ci.id_cliente_FK = :cli
               AND ps.estado = 'reservada_online'"
        );
        $stmt->bindValue(':ttl', $ttlMinutos, PDO::PARAM_INT);
        $stmt->bindValue(':cli', $idCliente, PDO::PARAM_INT);
        $stmt->execute();
    }

    /**
     * Verifica que todas las piezas del carrito de este cliente sigan reservadas
     * para EL mismo (estado='reservada_online' AND id_carrito_owner=idCliente).
     * Si alguna fue vendida en sucursal o la reserva expiro y la robo otro
     * cliente, devuelve la lista de descripciones perdidas.
     *
     * @return array{ok:bool, perdidas?:array<int, array{id_pieza_stock:int, desc:string, motivo:string}>}
     */
    public function validarReservasIntactas(int $idCliente): array
    {
        if ($idCliente <= 0) {
            return ['ok' => false, 'perdidas' => []];
        }

        $stmt = $this->getDb()->prepare(
            "SELECT ci.id_pieza_stock_FK AS id_ps,
                    ps.estado AS estado_actual,
                    ps.id_carrito_owner AS owner_actual,
                    COALESCE(p.desc_pieza, CONCAT('Pieza #', ci.id_pieza_stock_FK)) AS desc_pieza
             FROM carrito_items ci
             LEFT JOIN piezas_stock ps ON ps.id_pieza_stock = ci.id_pieza_stock_FK
             LEFT JOIN piezas p ON p.id_pieza = ps.id_pieza_FK
             WHERE ci.id_cliente_FK = :cli"
        );
        $stmt->bindValue(':cli', $idCliente, PDO::PARAM_INT);
        $stmt->execute();
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $perdidas = [];
        foreach ($filas as $r) {
            $estado = (string) ($r['estado_actual'] ?? '');
            $owner = (int) ($r['owner_actual'] ?? 0);
            $motivo = null;
            if ($estado === '' || $estado === 'vendida' || $estado === 'apartada' || $estado === 'defectuosa' || $estado === 'reparacion') {
                $motivo = 'Ya no esta disponible (' . ($estado !== '' ? $estado : 'inexistente') . ').';
            } elseif ($estado === 'reservada_online' && $owner !== $idCliente) {
                $motivo = 'La reserva paso a otro cliente.';
            } elseif ($estado === 'disponible') {
                // La reserva expiro pero nadie la ha tomado todavia. Es recuperable,
                // pero conservadoramente la consideramos perdida para evitar pagos
                // por piezas que pueden ser vendidas en sucursal en este instante.
                $motivo = 'La reserva expiro y debe volver a agregarse al carrito.';
            }

            if ($motivo !== null) {
                $perdidas[] = [
                    'id_pieza_stock' => (int) $r['id_ps'],
                    'desc' => (string) $r['desc_pieza'],
                    'motivo' => $motivo,
                ];
            }
        }

        return $perdidas === [] ? ['ok' => true] : ['ok' => false, 'perdidas' => $perdidas];
    }

    /**
     * @return array{precio_lista: float, precio_final: float, id_promocion: ?int}
     */
    private function resolverPrecioConPromocion(PDO $db, int $idPieza, float $precioLista): array
    {
        $stmt = $db->prepare(
            "SELECT p.id_pieza, p.id_sub_familia_FK, sf.id_familia_FK
             FROM piezas p
             INNER JOIN sub_familia sf ON sf.id_sub_familia = p.id_sub_familia_FK
             WHERE p.id_pieza = :id
             LIMIT 1"
        );
        $stmt->bindValue(':id', $idPieza, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return [
                'precio_lista' => $precioLista,
                'precio_final' => $precioLista,
                'id_promocion' => null,
            ];
        }

        $resolver = new PromocionTiendaResolver();
        $promo = $resolver->resolverParaPieza(
            (int) ($row['id_pieza'] ?? 0),
            (int) ($row['id_sub_familia_FK'] ?? 0),
            (int) ($row['id_familia_FK'] ?? 0)
        );
        if ($promo === null) {
            return [
                'precio_lista' => $precioLista,
                'precio_final' => $precioLista,
                'id_promocion' => null,
            ];
        }

        $precios = $resolver->calcularPrecios($precioLista, (float) ($promo['porcentaje_descuento'] ?? 0));

        return [
            'precio_lista' => $precios['precio_lista'],
            'precio_final' => $precios['precio_final'],
            'id_promocion' => (int) ($promo['id_promocion'] ?? 0) ?: null,
        ];
    }

    /**
     * @return array{precio_lista_snapshot: bool, id_promocion_FK: bool}
     */
    private function columnasCarritoPromo(PDO $db): array
    {
        static $cache = null;
        if (is_array($cache)) {
            return $cache;
        }

        $cache = ['precio_lista_snapshot' => false, 'id_promocion_FK' => false];
        try {
            $stmt = $db->query('SHOW COLUMNS FROM carrito_items');
            $cols = $stmt !== false ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
            if (is_array($cols)) {
                $cache['precio_lista_snapshot'] = in_array('precio_lista_snapshot', $cols, true);
                $cache['id_promocion_FK'] = in_array('id_promocion_FK', $cols, true);
            }
        } catch (Throwable $e) {
            error_log('Carrito::columnasCarritoPromo ' . $e->getMessage());
        }

        return $cache;
    }

    private function resolverPrecioPiezaCatalogo(PDO $db, int $idPieza): float
    {
        $stmt = $db->prepare("SELECT costo, aumento_pct FROM piezas WHERE id_pieza = :id LIMIT 1");
        $stmt->bindValue(':id', $idPieza, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return 0.0;
        $costo = (float) ($row['costo'] ?? 0);
        $aumento = ($row['aumento_pct'] !== null && $row['aumento_pct'] !== '')
            ? (float) $row['aumento_pct']
            : 0.0;
        $pv = round($costo * (1 + $aumento / 100), 2);
        if ($pv > 0) {
            $pv = ceil($pv / 5) * 5;
        }
        return (float) $pv;
    }
}
