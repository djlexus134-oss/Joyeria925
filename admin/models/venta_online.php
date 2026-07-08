<?php
declare(strict_types=1);

require_once __DIR__ . '/../../sistema.class.php';
require_once __DIR__ . '/../includes/list_search.php';
require_once __DIR__ . '/carrito.php';
require_once __DIR__ . '/apartado_gestion.php';

class VentaOnline extends Sistema
{
    public const ESTADO_PAGO_PENDIENTE = 'pendiente';
    public const ESTADO_PAGO_PAGADO = 'pagado';
    public const ESTADO_PAGO_RECHAZADO = 'rechazado';
    public const ESTADO_PAGO_REEMBOLSADO = 'reembolsado';

    public const ESTADO_ENTREGA_PENDIENTE = 'pendiente';
    public const ESTADO_ENTREGA_LISTA = 'lista_recoger';
    public const ESTADO_ENTREGA_ENTREGADA = 'entregada';
    public const ESTADO_ENTREGA_CANCELADA = 'cancelada';

    private function normalizarDecimal($valor, int $decimales = 2): string
    {
        $numero = (float) $valor;
        $epsilon = pow(10, -($decimales + 3));
        $ajustado = $numero + ($numero >= 0 ? $epsilon : -$epsilon);
        return number_format(round($ajustado, $decimales), $decimales, '.', '');
    }

    /**
     * Crea una venta pendiente a partir del carrito del cliente.
     *
     * @return array{ok:bool, error?:string, id_venta?:int, total?:float, credito_aplicado?:float, monto_a_pagar?:float}
     */
    public function crearPedidoPendiente(int $idCliente, bool $aceptacionEntregaTienda, float $creditoSolicitado = 0.0): array
    {
        if ($idCliente <= 0) {
            return ['ok' => false, 'error' => 'Cliente invalido.'];
        }
        if (!$aceptacionEntregaTienda) {
            return ['ok' => false, 'error' => 'Debes aceptar que la entrega es en tienda.'];
        }

        $carrito = new Carrito();
        $items = $carrito->listar($idCliente);
        if ($items === []) {
            return ['ok' => false, 'error' => 'Tu carrito esta vacio.'];
        }

        $resumen = $carrito->calcularResumen($items);
        $total = (float) $resumen['total'];
        if ($total <= 0) {
            return ['ok' => false, 'error' => 'Total invalido.'];
        }

        $creditoAplicado = 0.0;
        if ($creditoSolicitado > 0) {
            $saldoCredito = (float) (new ApartadoGestion())->totalCreditoDisponibleCliente($idCliente);
            $creditoAplicado = min($creditoSolicitado, $saldoCredito, $total);
            $creditoAplicado = (float) $this->normalizarDecimal($creditoAplicado);
        }
        $montoAPagar = max(0.0, (float) $this->normalizarDecimal($total - $creditoAplicado));

        // Si hay varias tiendas, registramos id_tienda_FK de la primera (mayoritaria).
        // En el detalle por linea queda el origen real.
        $idTiendaPrincipal = (int) $resumen['tiendas'][0]['id_tienda'];

        $db = $this->getDb();
        if (!$this->esquemaVentaOnlineListo($db)) {
            return [
                'ok' => false,
                'error' => 'La base de datos no esta lista para venta en linea: faltan columnas (origen, estado_pago, ...), la tabla carrito_items, o id_empleado_FK sigue siendo obligatorio. Ejecuta sql/2026_05_20_venta_online_y_carrito.sql completa.',
            ];
        }

        $db->beginTransaction();
        try {
            $idImpuesto = $this->obtenerIdImpuestoDefault($db);
            $colsVentas = $this->columnasVentas($db);
            $creditoPersistir = isset($colsVentas['credito_aplicado']) ? $creditoAplicado : 0.0;

            // ventas.estado (POS) solo admite completada|cancelada|devuelta; el cobro pendiente va en estado_pago.
            $insCols = [
                'id_cliente_FK', 'id_empleado_FK', 'origen', 'id_tienda_FK', 'estado_pago', 'estado_entrega',
                'aceptacion_entrega_tienda', 'id_impuesto_FK', 'total', 'estado', 'impuesto_porcentaje', 'impuesto_monto',
            ];
            $insVals = [
                ':cli', 'NULL', "'online'", ':tnd', "'pendiente'", "'pendiente'",
                '1', ':imp', ':total', "'completada'", '0', '0',
            ];
            if (isset($colsVentas['credito_aplicado'])) {
                $insCols[] = 'credito_aplicado';
                $insVals[] = ':cred';
            }
            $stmt = $db->prepare(
                'INSERT INTO ventas (' . implode(', ', $insCols) . ')
                 VALUES (' . implode(', ', $insVals) . ')'
            );
            $stmt->bindValue(':cli', $idCliente, PDO::PARAM_INT);
            $stmt->bindValue(':tnd', $idTiendaPrincipal, PDO::PARAM_INT);
            $stmt->bindValue(':imp', $idImpuesto, PDO::PARAM_INT);
            $stmt->bindValue(':total', $this->normalizarDecimal($total), PDO::PARAM_STR);
            if (isset($colsVentas['credito_aplicado'])) {
                $stmt->bindValue(':cred', $this->normalizarDecimal($creditoPersistir), PDO::PARAM_STR);
            }
            $stmt->execute();
            $idVenta = (int) $db->lastInsertId();

            // Detalle (sin descontar stock todavia). Mismas columnas que el POS en ventas.php.
            $cols = $this->columnasVentaDetalle($db);
            $insCols = ['id_venta_FK'];
            $insVals = [':id_v'];
            if (isset($cols['tipo_linea'])) { $insCols[] = 'tipo_linea'; $insVals[] = ':tipo'; }
            if (isset($cols['id_pieza_stock_FK'])) { $insCols[] = 'id_pieza_stock_FK'; $insVals[] = ':id_ps'; }
            if (isset($cols['id_insumo_FK'])) { $insCols[] = 'id_insumo_FK'; $insVals[] = ':id_ins'; }
            if (isset($cols['id_tienda_FK'])) { $insCols[] = 'id_tienda_FK'; $insVals[] = ':id_tnd'; }
            if (isset($cols['cantidad'])) { $insCols[] = 'cantidad'; $insVals[] = ':cant'; }
            if (isset($cols['precio_unitario'])) { $insCols[] = 'precio_unitario'; $insVals[] = ':pu'; }
            if (isset($cols['subtotal'])) { $insCols[] = 'subtotal'; $insVals[] = ':sub'; }
            if (isset($cols['descuento_aplicado'])) { $insCols[] = 'descuento_aplicado'; $insVals[] = ':desc'; }
            if (isset($cols['precio_final'])) { $insCols[] = 'precio_final'; $insVals[] = ':pf'; }
            if (isset($cols['costo_unitario'])) { $insCols[] = 'costo_unitario'; $insVals[] = ':costo'; }

            $insDet = $db->prepare(
                'INSERT INTO venta_detalle (' . implode(', ', $insCols) . ')
                 VALUES (' . implode(', ', $insVals) . ')'
            );

            $stmtCosto = $db->prepare(
                'SELECT COALESCE(p.costo, 0) AS costo_unitario
                 FROM piezas_stock ps
                 INNER JOIN piezas p ON p.id_pieza = ps.id_pieza_FK
                 WHERE ps.id_pieza_stock = :id
                 LIMIT 1'
            );

            foreach ($items as $it) {
                $precioFinal = (float) $it['precio_unitario_snapshot'];
                $precioLista = isset($it['precio_lista_snapshot']) && $it['precio_lista_snapshot'] !== null && $it['precio_lista_snapshot'] !== ''
                    ? (float) $it['precio_lista_snapshot']
                    : $precioFinal;
                $descuentoLinea = max(0.0, $precioLista - $precioFinal);
                $idStock = (int) $it['id_pieza_stock_FK'];
                $idTndLinea = (int) ($it['id_tienda'] ?? $idTiendaPrincipal);
                $pu = $this->normalizarDecimal($precioLista);
                $pf = $this->normalizarDecimal($precioFinal);
                $desc = $this->normalizarDecimal($descuentoLinea);

                $costoUnitario = '0.01';
                if (isset($cols['costo_unitario'])) {
                    $stmtCosto->bindValue(':id', $idStock, PDO::PARAM_INT);
                    $stmtCosto->execute();
                    $costoRow = $stmtCosto->fetch(PDO::FETCH_ASSOC);
                    $costoUnitario = $this->normalizarDecimal(max(0.01, (float) ($costoRow['costo_unitario'] ?? 0)));
                }

                $insDet->bindValue(':id_v', $idVenta, PDO::PARAM_INT);
                if (isset($cols['tipo_linea']))        $insDet->bindValue(':tipo', 'joya', PDO::PARAM_STR);
                if (isset($cols['id_pieza_stock_FK'])) $insDet->bindValue(':id_ps', $idStock, PDO::PARAM_INT);
                if (isset($cols['id_insumo_FK']))      $insDet->bindValue(':id_ins', null, PDO::PARAM_NULL);
                if (isset($cols['id_tienda_FK']))      $insDet->bindValue(':id_tnd', $idTndLinea, PDO::PARAM_INT);
                if (isset($cols['cantidad']))          $insDet->bindValue(':cant', '1.000', PDO::PARAM_STR);
                if (isset($cols['precio_unitario']))   $insDet->bindValue(':pu', $pu, PDO::PARAM_STR);
                if (isset($cols['subtotal']))          $insDet->bindValue(':sub', $pf, PDO::PARAM_STR);
                if (isset($cols['descuento_aplicado'])) $insDet->bindValue(':desc', $desc, PDO::PARAM_STR);
                if (isset($cols['precio_final']))      $insDet->bindValue(':pf', $pf, PDO::PARAM_STR);
                if (isset($cols['costo_unitario']))    $insDet->bindValue(':costo', $costoUnitario, PDO::PARAM_STR);
                $insDet->execute();
            }

            $db->commit();
            return [
                'ok' => true,
                'id_venta' => $idVenta,
                'total' => $total,
                'credito_aplicado' => $creditoPersistir,
                'monto_a_pagar' => $montoAPagar,
            ];
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('VentaOnline::crearPedidoPendiente ' . $e->getMessage());
            return ['ok' => false, 'error' => $this->mensajeErrorCrearPedido($e)];
        }
    }

    public function registrarReferenciaPago(int $idVenta, string $idPagoExterno, string $referenciaPago = ''): bool
    {
        $stmt = $this->getDb()->prepare(
            "UPDATE ventas
             SET id_pago_externo = :ext,
                 referencia_pago = :ref
             WHERE id_venta = :id AND origen = 'online'"
        );
        $stmt->bindValue(':ext', $idPagoExterno !== '' ? $idPagoExterno : null, $idPagoExterno !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':ref', $referenciaPago !== '' ? $referenciaPago : null, $referenciaPago !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':id', $idVenta, PDO::PARAM_INT);
        return (bool) $stmt->execute();
    }

    /**
     * Marca venta como pagada (idempotente). Pasa piezas a 'vendida' y vacia el carrito.
     *
     * Si el UPDATE de stock no logra transicionar a 'vendida' tantas filas como
     * detalles tiene la venta (caso clasico: la reserva expiro y se vendio la
     * pieza en sucursal en paralelo), la venta queda en estado_pago='pagado'
     * pero estado_entrega='cancelada' y requiere_atencion=1 (si existe la
     * columna). Tambien se dispara una notificacion para que el admin haga
     * reembolso manual desde Mercado Pago.
     *
     * @return array{ok:bool, idempotente?:bool, stock_perdido?:bool, faltantes?:int, error?:string}
     */
    public function marcarPagada(int $idVenta, string $idPagoExternoConfirmado = '', string $referenciaPago = ''): array
    {
        $db = $this->getDb();
        $db->beginTransaction();
        $stockPerdido = false;
        $faltantes = 0;
        $creditoConsumoFallo = false;
        try {
            $colsVentas = $this->columnasVentas($db);
            $selCredito = isset($colsVentas['credito_aplicado']) ? ', credito_aplicado' : '';
            $stmt = $db->prepare(
                "SELECT id_venta, id_cliente_FK, estado_pago{$selCredito} FROM ventas
                 WHERE id_venta = :id AND origen = 'online' FOR UPDATE"
            );
            $stmt->bindValue(':id', $idVenta, PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                $db->rollBack();
                return ['ok' => false, 'error' => 'Venta no encontrada.'];
            }
            if ($row['estado_pago'] === self::ESTADO_PAGO_PAGADO) {
                $db->commit();
                return ['ok' => true, 'idempotente' => true];
            }

            $creditoAplicado = isset($colsVentas['credito_aplicado'])
                ? (float) ($row['credito_aplicado'] ?? 0)
                : 0.0;

            // Contar lineas de detalle (piezas esperadas)
            $stmtCount = $db->prepare(
                'SELECT COUNT(*) FROM venta_detalle WHERE id_venta_FK = :id'
            );
            $stmtCount->bindValue(':id', $idVenta, PDO::PARAM_INT);
            $stmtCount->execute();
            $esperadas = (int) $stmtCount->fetchColumn();

            $upd = $db->prepare(
                "UPDATE ventas
                 SET estado_pago = 'pagado',
                     estado_entrega = 'pendiente',
                     estado = 'completada',
                     id_pago_externo = COALESCE(NULLIF(:ext, ''), id_pago_externo),
                     referencia_pago = COALESCE(NULLIF(:ref, ''), referencia_pago)
                 WHERE id_venta = :id"
            );
            $upd->bindValue(':ext', $idPagoExternoConfirmado, PDO::PARAM_STR);
            $upd->bindValue(':ref', $referenciaPago, PDO::PARAM_STR);
            $upd->bindValue(':id', $idVenta, PDO::PARAM_INT);
            $upd->execute();

            // Obtener id_cliente para validar reservas (evitar marcar stock de otro cliente)
            $idClienteVenta = (int) ($row['id_cliente_FK'] ?? 0);

            // Marcar piezas como vendidas:
            // Acepta reservada_online del mismo cliente O (tolerancia) disponible si la reserva expiró.
            // Ya NO acepta reservada_online de otro cliente ni reservada_pos.
            $condPropietario = $idClienteVenta > 0
                ? "AND (ps.estado = 'disponible' OR (ps.estado = 'reservada_online' AND COALESCE(ps.id_carrito_owner,0) = {$idClienteVenta}))"
                : "AND ps.estado IN ('reservada_online','disponible')";
            $updStock = $db->prepare(
                "UPDATE piezas_stock ps
                 INNER JOIN venta_detalle vd ON vd.id_pieza_stock_FK = ps.id_pieza_stock
                 SET ps.estado = 'vendida',
                     ps.reservada_hasta = NULL,
                     ps.id_carrito_owner = NULL
                 WHERE vd.id_venta_FK = :id
                   {$condPropietario}"
            );
            $updStock->bindValue(':id', $idVenta, PDO::PARAM_INT);
            $updStock->execute();
            $actualizadas = (int) $updStock->rowCount();

            // Si faltan unidades por transicionar a 'vendida', alguna ya fue
            // vendida en sucursal mientras esperaba el pago de MP.
            if ($esperadas > 0 && $actualizadas < $esperadas) {
                $stockPerdido = true;
                $faltantes = $esperadas - $actualizadas;
                error_log(sprintf(
                    'VentaOnline::marcarPagada STOCK_PERDIDO id_venta=%d esperadas=%d actualizadas=%d faltantes=%d',
                    $idVenta,
                    $esperadas,
                    $actualizadas,
                    $faltantes
                ));

                // Set entrega cancelada porque no podemos surtir
                $colsVentas = $this->columnasVentas($db);
                $setSql = "estado_entrega = 'cancelada'";
                $params = [':id' => $idVenta];
                if (isset($colsVentas['requiere_atencion'])) {
                    $setSql .= ', requiere_atencion = 1';
                }
                if (isset($colsVentas['observaciones_admin'])) {
                    $setSql .= ", observaciones_admin = CONCAT(COALESCE(observaciones_admin,''),
                        IF(observaciones_admin IS NULL OR observaciones_admin = '', '', '\n'),
                        :obs)";
                    $params[':obs'] = 'STOCK_PERDIDO_TRAS_PAGO: '
                        . $faltantes . ' pieza(s) ya no estaban disponibles al confirmar el pago de MP. '
                        . 'Requiere reembolso manual al cliente.';
                }
                $updFlag = $db->prepare("UPDATE ventas SET $setSql WHERE id_venta = :id");
                foreach ($params as $k => $v) {
                    $updFlag->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
                }
                $updFlag->execute();
            }

            // Consumir monedero del cliente (solo al confirmar pago, no al crear pedido pendiente).
            $idCliente = (int) $row['id_cliente_FK'];
            if ($creditoAplicado > 0.009 && $idCliente > 0) {
                try {
                    $stUsr = $db->prepare(
                        'SELECT id_usuario_FK FROM clientes WHERE id_cliente = :c LIMIT 1'
                    );
                    $stUsr->bindValue(':c', $idCliente, PDO::PARAM_INT);
                    $stUsr->execute();
                    $idUsuario = (int) ($stUsr->fetchColumn() ?: 0);
                    if ($idUsuario <= 0) {
                        throw new InvalidArgumentException('Cliente sin usuario para consumir credito.');
                    }
                    (new ApartadoGestion())->aplicarConsumoCreditoCliente(
                        $db,
                        $idCliente,
                        $creditoAplicado,
                        'venta_online',
                        null,
                        $idVenta,
                        null,
                        null,
                        $idUsuario,
                        'Venta online #' . $idVenta
                    );
                } catch (Throwable $e) {
                    $creditoConsumoFallo = true;
                    error_log('VentaOnline::marcarPagada CREDITO_FALLO id_venta=' . $idVenta . ' ' . $e->getMessage());
                    $setParts = [];
                    $paramsFlag = [':id' => $idVenta];
                    if (isset($colsVentas['requiere_atencion'])) {
                        $setParts[] = 'requiere_atencion = 1';
                    }
                    if (isset($colsVentas['observaciones_admin'])) {
                        $setParts[] = "observaciones_admin = CONCAT(COALESCE(observaciones_admin,''),
                            IF(observaciones_admin IS NULL OR observaciones_admin = '', '', '\n'),
                            :obs)";
                        $paramsFlag[':obs'] = 'CREDITO_NO_CONSUMIDO_TRAS_PAGO: '
                            . 'Se cobro la venta pero no se pudo descontar el monedero ($'
                            . $this->normalizarDecimal($creditoAplicado) . '). Revisar manualmente.';
                    }
                    if ($setParts !== []) {
                        $updCredFlag = $db->prepare('UPDATE ventas SET ' . implode(', ', $setParts) . ' WHERE id_venta = :id');
                        foreach ($paramsFlag as $k => $v) {
                            $updCredFlag->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
                        }
                        $updCredFlag->execute();
                    }
                }
            }

            // Vaciar carrito del cliente (sin liberar piezas porque ya estan vendidas)
            $delCi = $db->prepare("DELETE FROM carrito_items WHERE id_cliente_FK = :cli");
            $delCi->bindValue(':cli', $idCliente, PDO::PARAM_INT);
            $delCi->execute();

            if ($idCliente > 0) {
                require_once __DIR__ . '/../includes/ReglasDescuentoService.php';
                require_once __DIR__ . '/../includes/DescuentoTiendaService.php';
                $subPlataLista = (new ReglasDescuentoService())->calcularSubtotalPlataListaVentaDetalle($db, $idVenta);
                (new DescuentoTiendaService())->persistirDescuentoMayoreoSiCalifica($idCliente, $subPlataLista);
            }

            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log('VentaOnline::marcarPagada ' . $e->getMessage());
            return ['ok' => false, 'error' => 'No se pudo marcar pagada.'];
        }

        // Notificacion fuera de la transaccion (no queremos que un fallo en
        // el inserto de notificacion revierta el marcado de la venta como pagada).
        if ($stockPerdido) {
            try {
                require_once __DIR__ . '/../includes/NotificacionService.php';
                (new NotificacionService())->notificarStockPerdidoTrasPago($idVenta, $faltantes);
            } catch (Throwable $e) {
                error_log('VentaOnline::marcarPagada notificacion: ' . $e->getMessage());
            }
        }

        if (!$stockPerdido) {
            try {
                require_once __DIR__ . '/../includes/factura_auto.php';
                joyeria_emitir_factura_tras_venta($idVenta);
            } catch (Throwable $e) {
                error_log('VentaOnline::marcarPagada factura: ' . $e->getMessage());
            }
        }

        $out = ['ok' => true];
        if ($stockPerdido) {
            $out['stock_perdido'] = true;
            $out['faltantes'] = $faltantes;
        }
        if (!empty($creditoConsumoFallo)) {
            $out['credito_consumo_fallo'] = true;
        }
        return $out;
    }

    /**
     * Devuelve mapa columna->true de columnas de `ventas` para hacer SQL
     * resiliente a migraciones pendientes (mismo patron que NotificacionService).
     */
    private function columnasVentas(PDO $db): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $cache = [];
        try {
            foreach ($db->query('SHOW COLUMNS FROM ventas')->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $f = isset($r['Field']) ? trim((string) $r['Field']) : '';
                if ($f !== '') $cache[$f] = true;
            }
        } catch (Throwable $e) {
            error_log('VentaOnline::columnasVentas ' . $e->getMessage());
        }
        return $cache;
    }

    public function marcarRechazada(int $idVenta): array
    {
        $db = $this->getDb();
        $db->beginTransaction();
        try {
            $upd = $db->prepare(
                "UPDATE ventas SET estado_pago = 'rechazado', estado = 'cancelada', estado_entrega = 'cancelada'
                 WHERE id_venta = :id AND origen = 'online' AND estado_pago = 'pendiente'"
            );
            $upd->bindValue(':id', $idVenta, PDO::PARAM_INT);
            $upd->execute();

            // Liberar piezas reservadas
            $updStock = $db->prepare(
                "UPDATE piezas_stock ps
                 INNER JOIN venta_detalle vd ON vd.id_pieza_stock_FK = ps.id_pieza_stock
                 SET ps.estado = 'disponible', ps.reservada_hasta = NULL, ps.id_carrito_owner = NULL
                 WHERE vd.id_venta_FK = :id AND ps.estado = 'reservada_online'"
            );
            $updStock->bindValue(':id', $idVenta, PDO::PARAM_INT);
            $updStock->execute();

            // Vaciar carrito del cliente
            $stmt = $db->prepare("SELECT id_cliente_FK FROM ventas WHERE id_venta = :id LIMIT 1");
            $stmt->bindValue(':id', $idVenta, PDO::PARAM_INT);
            $stmt->execute();
            $idCli = (int) ($stmt->fetchColumn() ?: 0);
            if ($idCli > 0) {
                $del = $db->prepare("DELETE FROM carrito_items WHERE id_cliente_FK = :c");
                $del->bindValue(':c', $idCli, PDO::PARAM_INT);
                $del->execute();
            }

            $db->commit();
            return ['ok' => true];
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log('VentaOnline::marcarRechazada ' . $e->getMessage());
            return ['ok' => false, 'error' => 'No se pudo marcar rechazada.'];
        }
    }

    public function marcarListaParaRecoger(int $idVenta): array
    {
        $stmt = $this->getDb()->prepare(
            "UPDATE ventas
             SET estado_entrega = 'lista_recoger',
                 fecha_lista_recoger = COALESCE(fecha_lista_recoger, NOW())
             WHERE id_venta = :id
               AND origen = 'online'
               AND estado_pago = 'pagado'
               AND estado_entrega IN ('pendiente','lista_recoger')"
        );
        $stmt->bindValue(':id', $idVenta, PDO::PARAM_INT);
        $stmt->execute();
        if ($stmt->rowCount() < 1) {
            // Puede que ya estuviera lista; lo dejamos pasar.
        }
        return ['ok' => true];
    }

    public function marcarEntregada(int $idVenta, int $idEmpleado): array
    {
        $stmt = $this->getDb()->prepare(
            "UPDATE ventas
             SET estado_entrega = 'entregada',
                 fecha_entregada = COALESCE(fecha_entregada, NOW()),
                 entregada_por_FK = :emp
             WHERE id_venta = :id
               AND origen = 'online'
               AND estado_pago = 'pagado'
               AND estado_entrega IN ('pendiente','lista_recoger')"
        );
        $stmt->bindValue(':emp', $idEmpleado, PDO::PARAM_INT);
        $stmt->bindValue(':id', $idVenta, PDO::PARAM_INT);
        $stmt->execute();
        return ['ok' => true];
    }

    public function leerUno(int $idVenta): ?array
    {
        $sql = "SELECT v.*,
                       COALESCE(CONCAT(uc.nombre, ' ', uc.primer_apellido, COALESCE(CONCAT(' ', uc.segundo_apellido), '')), 'Cliente') AS cliente_nombre,
                       uc.correo AS cliente_correo,
                       uc.telefono AS cliente_telefono,
                       t.nom_tienda
                FROM ventas v
                LEFT JOIN clientes c ON c.id_cliente = v.id_cliente_FK
                LEFT JOIN usuarios uc ON uc.id_usuario = c.id_usuario_FK
                LEFT JOIN tiendas t ON t.id_tienda = v.id_tienda_FK
                WHERE v.id_venta = :id AND v.origen = 'online'
                LIMIT 1";
        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(':id', $idVenta, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        $row['detalle'] = $this->leerDetalle($idVenta);
        return $row;
    }

    public function leerDetalle(int $idVenta): array
    {
        $sql = "SELECT vd.id_venta_detalle,
                       vd.id_pieza_stock_FK,
                       vd.precio_unitario,
                       p.id_pieza,
                       p.desc_pieza,
                       sf.nom_sub_familia,
                       m.nom_metal,
                       ps.codigo_auxiliar,
                       t.id_tienda,
                       t.nom_tienda
                FROM venta_detalle vd
                INNER JOIN piezas_stock ps ON ps.id_pieza_stock = vd.id_pieza_stock_FK
                INNER JOIN piezas p ON p.id_pieza = ps.id_pieza_FK
                INNER JOIN sub_familia sf ON sf.id_sub_familia = p.id_sub_familia_FK
                INNER JOIN metales m ON m.id_metal = p.id_metal_FK
                INNER JOIN tiendas t ON t.id_tienda = p.id_tienda_FK
                WHERE vd.id_venta_FK = :id
                ORDER BY vd.id_venta_detalle ASC";
        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(':id', $idVenta, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Lista para el panel admin con filtros.
     */
    public function listarParaAdmin(?string $busqueda, array $filtros = []): array
    {
        $pat = joyeria_like_pattern($busqueda);
        $params = [];
        $sql = "SELECT v.id_venta, v.fecha_venta, v.total, v.estado_pago, v.estado_entrega,
                       v.id_pago_externo, v.id_tienda_FK,
                       COALESCE(CONCAT(uc.nombre, ' ', uc.primer_apellido), 'Cliente') AS cliente_nombre,
                       uc.correo AS cliente_correo,
                       t.nom_tienda,
                       (SELECT COUNT(*) FROM venta_detalle vd WHERE vd.id_venta_FK = v.id_venta) AS items_count
                FROM ventas v
                LEFT JOIN clientes c ON c.id_cliente = v.id_cliente_FK
                LEFT JOIN usuarios uc ON uc.id_usuario = c.id_usuario_FK
                LEFT JOIN tiendas t ON t.id_tienda = v.id_tienda_FK
                WHERE v.origen = 'online'";

        if ($pat !== null) {
            $sql .= " AND (CAST(v.id_venta AS CHAR) LIKE :b1 OR uc.correo LIKE :b2
                          OR uc.nombre LIKE :b3 OR uc.primer_apellido LIKE :b4)";
            $params[':b1'] = $pat;
            $params[':b2'] = $pat;
            $params[':b3'] = $pat;
            $params[':b4'] = $pat;
        }
        if (!empty($filtros['estado_pago'])) {
            $sql .= " AND v.estado_pago = :ep";
            $params[':ep'] = (string) $filtros['estado_pago'];
        }
        if (!empty($filtros['estado_entrega'])) {
            $sql .= " AND v.estado_entrega = :ee";
            $params[':ee'] = (string) $filtros['estado_entrega'];
        }
        if (!empty($filtros['id_tienda'])) {
            $sql .= " AND v.id_tienda_FK = :tnd";
            $params[':tnd'] = (int) $filtros['id_tienda'];
        }
        if (!empty($filtros['fecha_desde'])) {
            $sql .= " AND DATE(v.fecha_venta) >= :fd";
            $params[':fd'] = (string) $filtros['fecha_desde'];
        }
        if (!empty($filtros['fecha_hasta'])) {
            $sql .= " AND DATE(v.fecha_venta) <= :fh";
            $params[':fh'] = (string) $filtros['fecha_hasta'];
        }
        $sql .= " ORDER BY v.fecha_venta DESC, v.id_venta DESC";

        $stmt = $this->getDb()->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarParaCliente(int $idCliente): array
    {
        if ($idCliente <= 0) return [];
        $sql = "SELECT v.id_venta, v.fecha_venta, v.total, v.estado_pago, v.estado_entrega,
                       t.nom_tienda,
                       (SELECT COUNT(*) FROM venta_detalle vd WHERE vd.id_venta_FK = v.id_venta) AS items_count
                FROM ventas v
                LEFT JOIN tiendas t ON t.id_tienda = v.id_tienda_FK
                WHERE v.origen = 'online' AND v.id_cliente_FK = :cli
                ORDER BY v.fecha_venta DESC, v.id_venta DESC";
        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(':cli', $idCliente, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscarPorPagoExterno(string $idPagoExterno): ?array
    {
        if (trim($idPagoExterno) === '') return null;
        $stmt = $this->getDb()->prepare(
            "SELECT id_venta, id_cliente_FK, estado_pago FROM ventas
             WHERE id_pago_externo = :ext AND origen = 'online' LIMIT 1"
        );
        $stmt->bindValue(':ext', $idPagoExterno, PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function listarTiendasActivas(): array
    {
        return $this->getDb()->query(
            "SELECT id_tienda, nom_tienda FROM tiendas WHERE COALESCE(activo,1) = 1 ORDER BY nom_tienda ASC"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    private function obtenerIdImpuestoDefault(PDO $db): int
    {
        // Intentar leer configuracion_general; fallback al primero
        try {
            $stmt = $db->query("SELECT id_impuesto_default FROM configuracion_general WHERE id_configuracion_general = 1 LIMIT 1");
            $val = $stmt ? (int) $stmt->fetchColumn() : 0;
            if ($val > 0) return $val;
        } catch (Throwable $e) {
            // ignore
        }
        $stmt = $db->query("SELECT id_impuesto FROM impuestos ORDER BY id_impuesto ASC LIMIT 1");
        return (int) ($stmt ? $stmt->fetchColumn() : 0) ?: 1;
    }

    private function columnasVentaDetalle(PDO $db): array
    {
        static $cache = null;
        if ($cache !== null) return $cache;
        $rows = $db->query("SHOW COLUMNS FROM venta_detalle")->fetchAll(PDO::FETCH_ASSOC);
        $map = [];
        foreach ($rows as $r) {
            $field = isset($r['Field']) ? trim((string) $r['Field']) : '';
            if ($field !== '') $map[$field] = true;
        }
        $cache = $map;
        return $cache;
    }

    /** Comprueba columnas minimas de sql/2026_05_20_venta_online_y_carrito.sql en ventas. */
    private function esquemaVentaOnlineListo(PDO $db): bool
    {
        static $listo = null;
        if ($listo !== null) {
            return $listo;
        }
        try {
            $rows = $db->query('SHOW COLUMNS FROM ventas')->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            $listo = false;
            return false;
        }
        $cols = [];
        $empleadoNullable = false;
        foreach ($rows as $r) {
            $field = isset($r['Field']) ? trim((string) $r['Field']) : '';
            if ($field === '') {
                continue;
            }
            $cols[$field] = true;
            if ($field === 'id_empleado_FK') {
                $empleadoNullable = strtoupper((string) ($r['Null'] ?? '')) === 'YES';
            }
        }
        foreach (['origen', 'estado_pago', 'estado_entrega', 'aceptacion_entrega_tienda'] as $req) {
            if (!isset($cols[$req])) {
                $listo = false;
                return false;
            }
        }
        if (!$empleadoNullable) {
            $listo = false;
            return false;
        }
        try {
            $db->query('SELECT 1 FROM carrito_items LIMIT 1');
        } catch (Throwable $e) {
            $listo = false;
            return false;
        }
        $listo = true;
        return true;
    }

    private function mensajeErrorCrearPedido(Throwable $e): string
    {
        $m = $e->getMessage();
        if (stripos($m, 'Data truncated') !== false && stripos($m, 'estado') !== false) {
            return 'El campo ventas.estado no admite el valor enviado. Sube al servidor la version corregida de venta_online.php (estado completada + estado_pago pendiente).';
        }
        if (stripos($m, 'id_empleado_FK') !== false
            && (stripos($m, 'cannot be null') !== false || stripos($m, 'default value') !== false)) {
            return 'Falta permitir ventas sin empleado: ALTER TABLE ventas MODIFY id_empleado_FK INT NULL; (linea 26-27 de la migracion venta online).';
        }
        if (stripos($m, 'costo_unitario') !== false || stripos($m, 'descuento_aplicado') !== false) {
            return 'El detalle del pedido requiere costo o descuento en venta_detalle. Actualiza venta_online.php en el servidor.';
        }
        if (stripos($m, 'Unknown column') !== false) {
            return 'La migracion de venta en linea esta incompleta. Ejecuta sql/2026_05_20_venta_online_y_carrito.sql completa.';
        }
        if (stripos($m, 'foreign key') !== false || stripos($m, '1452') !== false) {
            return 'Tienda o impuesto no valido en la venta. Revisa id_tienda_FK e id_impuesto_FK en configuracion_general.';
        }
        if (defined('JOYERIA_MP_MODO') && strtolower((string) JOYERIA_MP_MODO) === 'sandbox') {
            return 'No se pudo crear el pedido: ' . preg_replace('/\s+/', ' ', $m);
        }
        return 'No se pudo crear el pedido.';
    }
}
