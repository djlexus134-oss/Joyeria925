<?php

require_once __DIR__ . '/../../sistema.class.php';
require_once __DIR__ . '/ventas.php';
require_once __DIR__ . '/apartado_gestion.php';
require_once __DIR__ . '/../includes/auth.php';

class Devoluciones extends Sistema
{
    private function normalizarDecimal($valor, int $decimales = 2): string
    {
        $numero = (float) $valor;
        $epsilon = pow(10, -($decimales + 3));
        $ajustado = $numero + ($numero >= 0 ? $epsilon : -$epsilon);

        return number_format(round($ajustado, $decimales), $decimales, '.', '');
    }

    public function listarPorVenta(int $idVenta): array
    {
        $sql = "SELECT d.*, fp.forma_pago,
                       TRIM(COALESCE(NULLIF(TRIM(ps.codigo_auxiliar), ''), TRIM(ps.codigo_barras), '')) AS pieza_codigo,
                       p.desc_pieza AS pieza_descripcion,
                       d.id_venta_destino_canje_FK,
                       CONCAT(u.nombre, ' ', u.primer_apellido, COALESCE(CONCAT(' ', u.segundo_apellido), '')) AS empleado_nombre
                FROM devoluciones d
                INNER JOIN empleados e ON e.id_empleado = d.id_empleado_FK
                INNER JOIN usuarios u ON u.id_usuario = e.id_usuario_FK
                LEFT JOIN piezas_stock ps ON ps.id_pieza_stock = d.id_pieza_stock_FK
                LEFT JOIN piezas p ON p.id_pieza = ps.id_pieza_FK
                LEFT JOIN forma_pago fp ON fp.id_forma_pago = d.id_forma_pago_FK
                WHERE d.id_venta_FK = :id
                ORDER BY d.fecha_devolucion DESC, d.id_devolucion DESC";
        try {
            $stmt = $this->getDb()->prepare($sql);
            $stmt->bindValue(':id', $idVenta, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            if (strpos($e->getMessage(), 'id_venta_destino_canje') === false
                && strpos($e->getMessage(), 'Unknown column') === false) {
                throw $e;
            }
            $sqlLegacy = "SELECT d.*, fp.forma_pago,
                       TRIM(COALESCE(NULLIF(TRIM(ps.codigo_auxiliar), ''), TRIM(ps.codigo_barras), '')) AS pieza_codigo,
                       p.desc_pieza AS pieza_descripcion,
                       CONCAT(u.nombre, ' ', u.primer_apellido, COALESCE(CONCAT(' ', u.segundo_apellido), '')) AS empleado_nombre
                FROM devoluciones d
                INNER JOIN empleados e ON e.id_empleado = d.id_empleado_FK
                INNER JOIN usuarios u ON u.id_usuario = e.id_usuario_FK
                LEFT JOIN piezas_stock ps ON ps.id_pieza_stock = d.id_pieza_stock_FK
                LEFT JOIN piezas p ON p.id_pieza = ps.id_pieza_FK
                LEFT JOIN forma_pago fp ON fp.id_forma_pago = d.id_forma_pago_FK
                WHERE d.id_venta_FK = :id
                ORDER BY d.fecha_devolucion DESC, d.id_devolucion DESC";
            $stmt = $this->getDb()->prepare($sqlLegacy);
            $stmt->bindValue(':id', $idVenta, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
    }

    /**
     * Piezas devueltas en ventas origen y aplicadas como credito en esta venta (canje POS).
     *
     * @return list<array<string, mixed>>
     */
    public function listarCanjeEnVentaDestino(int $idVentaDestino): array
    {
        if ($idVentaDestino <= 0) {
            return [];
        }

        $sql = "SELECT d.id_devolucion,
                       d.id_venta_FK AS id_venta_origen,
                       d.id_pieza_stock_FK,
                       d.id_venta_detalle_FK,
                       d.monto_reembolso AS monto_credito,
                       d.motivo,
                       d.fecha_devolucion,
                       TRIM(COALESCE(NULLIF(TRIM(ps.codigo_auxiliar), ''), TRIM(ps.codigo_barras), '')) AS pieza_codigo,
                       p.desc_pieza AS pieza_descripcion,
                       ps.estado AS estado_pieza,
                       CONCAT(u.nombre, ' ', u.primer_apellido, COALESCE(CONCAT(' ', u.segundo_apellido), '')) AS empleado_nombre
                FROM devoluciones d
                INNER JOIN empleados e ON e.id_empleado = d.id_empleado_FK
                INNER JOIN usuarios u ON u.id_usuario = e.id_usuario_FK
                LEFT JOIN piezas_stock ps ON ps.id_pieza_stock = d.id_pieza_stock_FK
                LEFT JOIN piezas p ON p.id_pieza = ps.id_pieza_FK
                WHERE d.id_venta_destino_canje_FK = :dest
                ORDER BY d.fecha_devolucion ASC, d.id_devolucion ASC";

        try {
            $stmt = $this->getDb()->prepare($sql);
            $stmt->bindValue(':dest', $idVentaDestino, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            if (strpos($e->getMessage(), 'id_venta_destino_canje') !== false
                || strpos($e->getMessage(), 'Unknown column') !== false) {
                return [];
            }
            throw $e;
        }
    }

    public function ventaTieneFacturaEmitida(PDO $db, int $idVenta): bool
    {
        $st = $db->prepare("SELECT 1 FROM facturas WHERE id_venta_FK = :id AND estado = 'emitida' LIMIT 1");
        $st->bindValue(':id', $idVenta, PDO::PARAM_INT);
        $st->execute();

        return (bool) $st->fetchColumn();
    }

    private function sumaPagosVenta(PDO $db, int $idVenta): float
    {
        $st = $db->prepare('SELECT COALESCE(SUM(monto), 0) FROM venta_pagos WHERE id_venta_FK = :v');
        $st->bindValue(':v', $idVenta, PDO::PARAM_INT);
        $st->execute();

        return (float) $st->fetchColumn();
    }

    private function sumarCanjeAplicadoEnVenta(PDO $db, int $idVenta): float
    {
        if ($idVenta <= 0) {
            return 0.0;
        }
        try {
            $st = $db->prepare(
                'SELECT COALESCE(SUM(monto_reembolso), 0)
                 FROM devoluciones
                 WHERE id_venta_destino_canje_FK = :id'
            );
            $st->bindValue(':id', $idVenta, PDO::PARAM_INT);
            $st->execute();

            return (float) $st->fetchColumn();
        } catch (Throwable $e) {
            if (strpos($e->getMessage(), 'id_venta_destino_canje') !== false
                || strpos($e->getMessage(), 'Unknown column') !== false) {
                return 0.0;
            }
            throw $e;
        }
    }

    private function sumarMontoDevueltoDesdeVenta(PDO $db, int $idVenta): float
    {
        if ($idVenta <= 0) {
            return 0.0;
        }
        $st = $db->prepare(
            'SELECT COALESCE(SUM(monto_reembolso), 0)
             FROM devoluciones
             WHERE id_venta_FK = :id'
        );
        $st->bindValue(':id', $idVenta, PDO::PARAM_INT);
        $st->execute();

        return (float) $st->fetchColumn();
    }

    /**
     * @param array<int, array<string, mixed>> $detallesConLineaDevuelta Activas incluyendo la linea devuelta
     * @return array{
     *   canje_aplicado: float,
     *   monto_ya_devuelto: float,
     *   canje_restante: float,
     *   total_referencia: float,
     *   total_antes_detalle: float,
     *   ratio_efectivo: float
     * }
     */
    private function resolverContextoCanjeVenta(
        PDO $db,
        Ventas $ventasModel,
        array $venta,
        array $detallesConLineaDevuelta,
        int $idVenta
    ): array {
        $totalCabecera = (float) ($venta['total'] ?? 0);
        $impPct = $ventasModel->resolverImpuestoPorcentajeVenta($venta);
        $totAntes = $ventasModel->calcularTotalDesdeSubtotalesDetalle($detallesConLineaDevuelta, $impPct);
        $totalAntesDetalle = (float) $totAntes['total'];
        $canjeAplicado = $this->sumarCanjeAplicadoEnVenta($db, $idVenta);
        $montoYaDevuelto = $this->sumarMontoDevueltoDesdeVenta($db, $idVenta);

        $canjeRestante = 0.0;
        $totalReferencia = $totalCabecera;
        if ($canjeAplicado > 0.009 && $totalAntesDetalle > 0.01) {
            $totalLineasOriginal = $totalAntesDetalle + max(0.0, $montoYaDevuelto);
            if ($totalLineasOriginal > 0.01) {
                $canjeRestante = $canjeAplicado * ($totalAntesDetalle / $totalLineasOriginal);
                $totalReferencia = $totalCabecera + $canjeRestante;
            }
        }

        $ratioEfectivo = 1.0;
        if ($canjeAplicado > 0.009 && $totalReferencia > 0.01) {
            $ratioEfectivo = $totalCabecera / $totalReferencia;
        }

        return [
            'canje_aplicado' => $canjeAplicado,
            'monto_ya_devuelto' => $montoYaDevuelto,
            'canje_restante' => $canjeRestante,
            'total_referencia' => $totalReferencia,
            'total_antes_detalle' => $totalAntesDetalle,
            'ratio_efectivo' => max(0.0, min(1.0, $ratioEfectivo)),
        ];
    }

    private function verificarCuadrePagosTrasDevolucion(
        PDO $db,
        int $idVenta,
        float $nuevoTotal,
        float $canjeAplicado,
        string $contexto
    ): void {
        $sumPagos = $this->sumaPagosVenta($db, $idVenta);
        if (abs($sumPagos - $nuevoTotal) <= 0.02) {
            return;
        }
        if ($canjeAplicado > 0.009) {
            return;
        }
        throw new RuntimeException(
            'Pagos de venta #' . $idVenta . ' no cuadran tras ' . $contexto
            . ' (pagos $' . number_format($sumPagos, 2)
            . ', total $' . number_format($nuevoTotal, 2) . ').'
        );
    }

    private function verificarFormaPago(PDO $db, int $idFormaPago): void
    {
        $st = $db->prepare('SELECT 1 FROM forma_pago WHERE id_forma_pago = :id AND activo = 1 LIMIT 1');
        $st->bindValue(':id', $idFormaPago, PDO::PARAM_INT);
        $st->execute();
        if (!$st->fetchColumn()) {
            throw new InvalidArgumentException('La forma de pago no es valida o esta inactiva.');
        }
    }

    private function verificarEmpleadoActivo(PDO $db, int $idEmpleado): void
    {
        $st = $db->prepare('SELECT 1 FROM empleados WHERE id_empleado = :id AND activo = 1 LIMIT 1');
        $st->bindValue(':id', $idEmpleado, PDO::PARAM_INT);
        $st->execute();
        if (!$st->fetchColumn()) {
            throw new InvalidArgumentException('El empleado no existe o no esta activo.');
        }
    }

    /**
     * Recalcula total de venta origen tras anular una linea (usa importes guardados en venta_detalle).
     *
     * @param array<int, array<string, mixed>> $detallesActivosRestantes
     * @return array{
     *   monto_linea: string,
     *   monto_ajuste_venta: string,
     *   tot_despues: array{subtotal: string, impuesto_monto: string, total: string},
     *   nuevo_total: float,
     *   nuevo_estado: string,
     *   canje_aplicado: float
     * }
     */
    private function anularLineaYRecalcularVentaOrigen(
        PDO $db,
        Ventas $ventasModel,
        array $venta,
        array $lineaAnulada,
        array $detallesActivosRestantes,
        int $idVenta
    ): array {
        $detallesConAnulada = $detallesActivosRestantes;
        $detallesConAnulada[] = $lineaAnulada;
        $ctx = $this->resolverContextoCanjeVenta($db, $ventasModel, $venta, $detallesConAnulada, $idVenta);
        $canjeAplicado = (float) $ctx['canje_aplicado'];

        $montoCredito = $ventasModel->resolverImporteDevolucionLineaVenta(
            $lineaAnulada,
            $venta,
            $detallesConAnulada,
            $canjeAplicado,
            (float) $ctx['monto_ya_devuelto']
        );
        if ($montoCredito <= 0) {
            throw new InvalidArgumentException(
                'No se pudo determinar el importe de la linea devuelta en venta #' . $idVenta . '.'
            );
        }

        $ratioEfectivo = (float) $ctx['ratio_efectivo'];
        $montoAjusteVenta = $canjeAplicado > 0.009
            ? $montoCredito * $ratioEfectivo
            : $montoCredito;

        $impPct = $ventasModel->resolverImpuestoPorcentajeVenta($venta);
        $totalCabecera = (float) ($venta['total'] ?? 0);
        $totalReferencia = (float) $ctx['total_referencia'];
        $totalAntesDetalle = (float) $ctx['total_antes_detalle'];
        $desfaseCabecera = abs($totalReferencia - $totalAntesDetalle);

        if ($desfaseCabecera <= 0.02) {
            $nuevoTotal = max(0.0, $totalCabecera - $montoAjusteVenta);
            $subtotalDespues = $impPct > 0 ? $nuevoTotal / (1 + $impPct / 100) : $nuevoTotal;
            $impuestoMonto = $nuevoTotal - $subtotalDespues;
            $totDespues = [
                'subtotal' => $this->normalizarDecimal($subtotalDespues),
                'impuesto_monto' => $this->normalizarDecimal($impuestoMonto),
                'total' => $this->normalizarDecimal($nuevoTotal),
            ];
        } else {
            if ($montoCredito > $totalReferencia + 0.02) {
                $montoCredito = $totalReferencia;
                $montoAjusteVenta = $canjeAplicado > 0.009
                    ? $montoCredito * $ratioEfectivo
                    : $montoCredito;
            }
            $nuevoTotal = max(0.0, $totalCabecera - $montoAjusteVenta);
            $subtotalDespues = $impPct > 0 ? $nuevoTotal / (1 + $impPct / 100) : $nuevoTotal;
            $impuestoMonto = $nuevoTotal - $subtotalDespues;
            $totDespues = [
                'subtotal' => $this->normalizarDecimal($subtotalDespues),
                'impuesto_monto' => $this->normalizarDecimal($impuestoMonto),
                'total' => $this->normalizarDecimal($nuevoTotal),
            ];
        }

        if ($nuevoTotal <= 0.009) {
            $nuevoTotal = 0.0;
            $totDespues = [
                'subtotal' => $this->normalizarDecimal(0.0),
                'impuesto_monto' => $this->normalizarDecimal(0.0),
                'total' => $this->normalizarDecimal(0.0),
            ];
        }

        return [
            'monto_linea' => $this->normalizarDecimal($montoCredito),
            'monto_ajuste_venta' => $this->normalizarDecimal($montoAjusteVenta),
            'tot_despues' => $totDespues,
            'nuevo_total' => $nuevoTotal,
            'nuevo_estado' => ($nuevoTotal <= 0.009) ? 'devuelta' : 'completada',
            'canje_aplicado' => $canjeAplicado,
        ];
    }

    public function obtenerIdFormaPagoCanjeInterno(PDO $db): int
    {
        $st = $db->prepare(
            "SELECT id_forma_pago FROM forma_pago WHERE forma_pago = 'Canje interno (sin efectivo)' AND activo = 1 LIMIT 1"
        );
        $st->execute();
        $id = (int) ($st->fetchColumn() ?: 0);
        if ($id <= 0) {
            throw new RuntimeException(
                'Falta la forma de pago interna "Canje interno (sin efectivo)". Ejecuta el script sql/2026_05_15_devoluciones_canje.sql en la base de datos.'
            );
        }

        return $id;
    }

    public function obtenerIdFormaPagoMonederoDevolucion(PDO $db): int
    {
        $st = $db->prepare(
            "SELECT id_forma_pago FROM forma_pago WHERE forma_pago = 'Credito monedero por devolucion (sin efectivo)' AND activo = 1 LIMIT 1"
        );
        $st->execute();
        $id = (int) ($st->fetchColumn() ?: 0);
        if ($id <= 0) {
            throw new RuntimeException(
                'Falta la forma de pago "Credito monedero por devolucion (sin efectivo)". Ejecuta sql/2026_05_20_devoluciones_monedero_cliente.sql.'
            );
        }

        return $id;
    }

    private function verificarClienteActivo(PDO $db, int $idCliente): void
    {
        $st = $db->prepare('SELECT 1 FROM clientes WHERE id_cliente = :id AND activo = 1 LIMIT 1');
        $st->bindValue(':id', $idCliente, PDO::PARAM_INT);
        $st->execute();
        if (!$st->fetchColumn()) {
            throw new InvalidArgumentException('El cliente no existe o no esta activo.');
        }
    }

    /**
     * Vista previa de devolucion con acreditacion al monedero (sin escribir en BD).
     *
     * @return array<string, mixed>
     */
    public function prepararDevolucionMonedero(
        int $idVenta,
        string $codigoPieza,
        int $idCliente,
        int $idEmpleado,
        ?int $idPiezaStockFk = null
    ): array {
        $this->verificarEmpleadoActivo($this->getDb(), $idEmpleado);
        if ($idCliente <= 0) {
            throw new InvalidArgumentException('Indica el cliente que recibira el credito en el monedero.');
        }
        $this->verificarClienteActivo($this->getDb(), $idCliente);

        $prep = $this->prepararCreditoCanjeParaPos($idVenta, $codigoPieza, '', $idEmpleado, $idPiezaStockFk);
        $idVentaResuelta = (int) ($prep['id_venta_origen'] ?? 0);

        $stV = $this->getDb()->prepare('SELECT id_cliente_FK FROM ventas WHERE id_venta = :id LIMIT 1');
        $stV->bindValue(':id', $idVentaResuelta, PDO::PARAM_INT);
        $stV->execute();
        $idCliVenta = (int) ($stV->fetchColumn() ?: 0);
        if ($idCliVenta > 0 && $idCliVenta !== $idCliente) {
            throw new InvalidArgumentException(
                'El cliente indicado no coincide con el de la venta origen (#'
                . $idVentaResuelta . ').'
            );
        }

        $ag = new ApartadoGestion();
        $saldoActual = $ag->totalCreditoDisponibleCliente($idCliente);
        $montoCredito = (string) ($prep['monto_credito'] ?? '0.00');

        return array_merge($prep, [
            'id_cliente_FK' => $idCliente,
            'monedero_saldo_actual' => $saldoActual,
            'monedero_saldo_tras' => $this->normalizarDecimal((float) $saldoActual + (float) $montoCredito),
            'modo' => $idVentaResuelta > 0 ? 'venta' : 'mostrador',
        ]);
    }

    /**
     * Registra devolucion y acredita el monedero del cliente (venta con ticket o mostrador).
     *
     * @return array<string, mixed>
     */
    public function registrarDevolucionConMonedero(array $data): array
    {
        $idEmpleado = (int) ($data['id_empleado_FK'] ?? 0);
        $idUsuario = (int) ($data['id_usuario_FK'] ?? 0);
        $idCliente = (int) ($data['id_cliente_FK'] ?? 0);
        $idVenta = (int) ($data['id_venta_FK'] ?? $data['id_venta'] ?? 0);
        $codigo = trim((string) ($data['codigo'] ?? ''));
        $idPiezaStock = (int) ($data['id_pieza_stock_FK'] ?? 0);
        $motivo = trim((string) ($data['motivo'] ?? ''));

        if ($idEmpleado <= 0 || $idUsuario <= 0) {
            throw new InvalidArgumentException('Empleado y usuario son obligatorios.');
        }
        if ($idCliente <= 0) {
            throw new InvalidArgumentException('El cliente es obligatorio para acreditar el monedero.');
        }

        $prep = $this->prepararDevolucionMonedero(
            $idVenta,
            $codigo,
            $idCliente,
            $idEmpleado,
            $idPiezaStock > 0 ? $idPiezaStock : null
        );

        $idVentaOrigen = (int) ($prep['id_venta_origen'] ?? 0);
        $idPiezaStock = (int) ($prep['id_pieza_stock_FK'] ?? 0);
        $idVentaDetalle = (int) ($prep['id_venta_detalle'] ?? 0);
        $montoStr = (string) ($prep['monto_credito'] ?? '0.00');

        $db = $this->getDb();
        $db->beginTransaction();

        try {
            auth_mysql_set_audit_vars($db, $idUsuario);
            $ag = new ApartadoGestion();
            $montoDelta = '0.00';
            $tipoOrigen = 'mostrador';
            $idDev = 0;

            if ($idVentaOrigen > 0 && $idVentaDetalle > 0) {
                $tipoOrigen = 'venta';
                $montoDelta = $this->liquidarLineaVentaParaDevolucionMonedero(
                    $db,
                    $idVentaOrigen,
                    $idPiezaStock,
                    $idVentaDetalle,
                    $motivo
                );
            } else {
                $this->liberarPiezaVendida($db, $idPiezaStock);
            }

            $idDev = $this->insertarDevolucionMonedero(
                $db,
                $tipoOrigen,
                $idVentaOrigen > 0 ? $idVentaOrigen : null,
                $idPiezaStock,
                $idVentaDetalle > 0 ? $idVentaDetalle : null,
                $idCliente,
                $motivo,
                $montoDelta !== '0.00' ? $montoDelta : $montoStr,
                $idEmpleado
            );

            $obsCred = ($motivo !== '' ? $motivo . ' | ' : '')
                . 'Devolucion #' . $idDev . ($idVentaOrigen > 0 ? ' venta #' . $idVentaOrigen : ' mostrador');
            $idCredito = $ag->registrarCreditoClienteEntrada(
                $db,
                $idCliente,
                $montoStr,
                'devolucion',
                $idEmpleado,
                $idUsuario,
                $obsCred,
                null,
                $idDev,
                $idVentaOrigen > 0 ? $idVentaOrigen : null
            );

            $this->vincularCreditoADevolucion($db, $idDev, $idCredito);

            $saldoNuevo = $ag->totalCreditoDisponibleCliente($idCliente);

            $db->commit();

            return [
                'id_devolucion' => $idDev,
                'id_credito' => $idCredito,
                'id_cliente_FK' => $idCliente,
                'id_pieza_stock_FK' => $idPiezaStock,
                'id_venta_FK' => $idVentaOrigen > 0 ? $idVentaOrigen : null,
                'monto_credito' => $montoStr,
                'monedero_saldo_disponible' => $saldoNuevo,
                'tipo_origen' => $tipoOrigen,
            ];
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    private function liberarPiezaVendida(PDO $db, int $idPiezaStock): void
    {
        $stPs = $db->prepare('SELECT id_pieza_stock, estado, activo FROM piezas_stock WHERE id_pieza_stock = :id FOR UPDATE');
        $stPs->bindValue(':id', $idPiezaStock, PDO::PARAM_INT);
        $stPs->execute();
        $ps = $stPs->fetch(PDO::FETCH_ASSOC);
        if (!$ps || (int) ($ps['activo'] ?? 0) !== 1 || ($ps['estado'] ?? '') !== 'vendida') {
            throw new InvalidArgumentException('La pieza no esta vendida o no esta activa.');
        }

        $updPs = $db->prepare(
            "UPDATE piezas_stock SET estado = 'disponible'
             WHERE id_pieza_stock = :id AND estado = 'vendida' AND activo = 1"
        );
        $updPs->bindValue(':id', $idPiezaStock, PDO::PARAM_INT);
        $updPs->execute();
        if ($updPs->rowCount() !== 1) {
            throw new RuntimeException('No se pudo reingresar la pieza al inventario.');
        }
    }

    /**
     * Anula linea, recalcula venta y registra pago negativo interno (sin venta destino de canje).
     */
    private function liquidarLineaVentaParaDevolucionMonedero(
        PDO $db,
        int $idVenta,
        int $idPiezaStock,
        int $idVentaDetalle,
        string $motivo
    ): string {
        if ($this->ventaTieneFacturaEmitida($db, $idVenta)) {
            throw new InvalidArgumentException('La venta tiene factura emitida; no se puede acreditar monedero.');
        }

        $dup = $db->prepare('SELECT 1 FROM devoluciones WHERE id_venta_FK = :v AND id_pieza_stock_FK = :p LIMIT 1');
        $dup->bindValue(':v', $idVenta, PDO::PARAM_INT);
        $dup->bindValue(':p', $idPiezaStock, PDO::PARAM_INT);
        $dup->execute();
        if ($dup->fetchColumn()) {
            throw new InvalidArgumentException('Esta pieza ya consta como devuelta en esa venta.');
        }

        $stV = $db->prepare('SELECT * FROM ventas WHERE id_venta = :id FOR UPDATE');
        $stV->bindValue(':id', $idVenta, PDO::PARAM_INT);
        $stV->execute();
        $venta = $stV->fetch(PDO::FETCH_ASSOC);
        if (!$venta || ($venta['estado'] ?? '') !== 'completada') {
            throw new InvalidArgumentException('Solo ventas completadas pueden generar credito en monedero.');
        }

        $totalAntes = (float) ($venta['total'] ?? 0);
        $sumPagosAntes = $this->sumaPagosVenta($db, $idVenta);
        $desfasePagosAntes = abs($sumPagosAntes - $totalAntes);
        if ($desfasePagosAntes > 1.00) {
            throw new InvalidArgumentException('La venta #' . $idVenta . ' no tiene pagos cuadrados con el total (desfase $' . number_format($desfasePagosAntes, 2) . ').');
        }

        $stLinea = $db->prepare('SELECT * FROM venta_detalle WHERE id_venta_detalle = :id FOR UPDATE');
        $stLinea->bindValue(':id', $idVentaDetalle, PDO::PARAM_INT);
        $stLinea->execute();
        $linea = $stLinea->fetch(PDO::FETCH_ASSOC);
        if (!$linea || (int) ($linea['id_venta_FK'] ?? 0) !== $idVenta) {
            throw new InvalidArgumentException('Linea de venta no encontrada para devolucion.');
        }

        $updL = $db->prepare('UPDATE venta_detalle SET anulada = 1 WHERE id_venta_detalle = :id');
        $updL->bindValue(':id', $idVentaDetalle, PDO::PARAM_INT);
        $updL->execute();

        $ventasModel = new Ventas();
        $stD = $db->prepare(
            'SELECT * FROM venta_detalle WHERE id_venta_FK = :v AND COALESCE(anulada, 0) = 0 ORDER BY id_venta_detalle ASC'
        );
        $stD->bindValue(':v', $idVenta, PDO::PARAM_INT);
        $stD->execute();
        $detallesActivos = $stD->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $rec = $this->anularLineaYRecalcularVentaOrigen($db, $ventasModel, $venta, $linea, $detallesActivos, $idVenta);
        $tot = $rec['tot_despues'];
        $nuevoTotal = $rec['nuevo_total'];
        $montoCredito = $rec['monto_linea'];
        $montoPagoNeg = $rec['monto_ajuste_venta'];
        $canjeAplicado = (float) ($rec['canje_aplicado'] ?? 0);

        $nuevoEstado = $rec['nuevo_estado'];
        $updV = $db->prepare(
            'UPDATE ventas SET total = :total, impuesto_monto = :imp, estado = :estado WHERE id_venta = :id'
        );
        $updV->bindValue(':total', $this->normalizarDecimal($nuevoTotal), PDO::PARAM_STR);
        $updV->bindValue(':imp', $this->normalizarDecimal((float) $tot['impuesto_monto']), PDO::PARAM_STR);
        $updV->bindValue(':estado', $nuevoEstado, PDO::PARAM_STR);
        $updV->bindValue(':id', $idVenta, PDO::PARAM_INT);
        $updV->execute();

        if ((float) $montoPagoNeg > 0.009) {
            $idForma = $this->obtenerIdFormaPagoMonederoDevolucion($db);
            $insP = $db->prepare(
                'INSERT INTO venta_pagos (id_venta_FK, id_forma_pago_FK, monto) VALUES (:v, :fp, :monto)'
            );
            $negativo = $this->normalizarDecimal(-(float) $montoPagoNeg);
            $insP->bindValue(':v', $idVenta, PDO::PARAM_INT);
            $insP->bindValue(':fp', $idForma, PDO::PARAM_INT);
            $insP->bindValue(':monto', $negativo, PDO::PARAM_STR);
            $insP->execute();
        }

        $this->verificarCuadrePagosTrasDevolucion($db, $idVenta, $nuevoTotal, $canjeAplicado, 'devolucion a monedero');

        $this->liberarPiezaVendida($db, $idPiezaStock);

        return $montoCredito;
    }

    private function insertarDevolucionMonedero(
        PDO $db,
        string $tipoOrigen,
        ?int $idVenta,
        int $idPiezaStock,
        ?int $idVentaDetalle,
        int $idCliente,
        string $motivo,
        string $montoReemb,
        int $idEmpleado
    ): int {
        $motivoIns = $motivo !== '' ? $motivo : 'Credito monedero cliente';
        $insD = $db->prepare(
            "INSERT INTO devoluciones
                (id_venta_FK, tipo_origen, id_pieza_stock_FK, id_venta_detalle_FK, id_venta_destino_canje_FK,
                 motivo, monto_reembolso, id_forma_pago_FK, id_empleado_FK, id_cliente_FK, id_credito_FK)
             VALUES
                (:v, :to, :ps, :vd, NULL, :motivo, :mreemb, NULL, :emp, :cli, NULL)"
        );
        $insD->bindValue(':v', $idVenta, $idVenta !== null && $idVenta > 0 ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $insD->bindValue(':to', $tipoOrigen, PDO::PARAM_STR);
        $insD->bindValue(':ps', $idPiezaStock, PDO::PARAM_INT);
        $insD->bindValue(':vd', $idVentaDetalle, $idVentaDetalle !== null && $idVentaDetalle > 0 ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $insD->bindValue(':motivo', $motivoIns, PDO::PARAM_STR);
        $insD->bindValue(':mreemb', $montoReemb, PDO::PARAM_STR);
        $insD->bindValue(':emp', $idEmpleado, PDO::PARAM_INT);
        $insD->bindValue(':cli', $idCliente, PDO::PARAM_INT);
        try {
            $insD->execute();
        } catch (Throwable $e) {
            if (strpos($e->getMessage(), 'id_cliente_FK') !== false
                || strpos($e->getMessage(), 'id_credito_FK') !== false
                || strpos($e->getMessage(), 'Unknown column') !== false) {
                $insD2 = $db->prepare(
                    'INSERT INTO devoluciones (id_venta_FK, tipo_origen, id_pieza_stock_FK, id_venta_detalle_FK, motivo, monto_reembolso, id_forma_pago_FK, id_empleado_FK)
                     VALUES (:v, :to, :ps, :vd, :motivo, :mreemb, NULL, :emp)'
                );
                $insD2->bindValue(':v', $idVenta, $idVenta !== null && $idVenta > 0 ? PDO::PARAM_INT : PDO::PARAM_NULL);
                $insD2->bindValue(':to', $tipoOrigen, PDO::PARAM_STR);
                $insD2->bindValue(':ps', $idPiezaStock, PDO::PARAM_INT);
                $insD2->bindValue(':vd', $idVentaDetalle, $idVentaDetalle !== null && $idVentaDetalle > 0 ? PDO::PARAM_INT : PDO::PARAM_NULL);
                $insD2->bindValue(':motivo', $motivoIns . ' [cliente #' . $idCliente . ']', PDO::PARAM_STR);
                $insD2->bindValue(':mreemb', $montoReemb, PDO::PARAM_STR);
                $insD2->bindValue(':emp', $idEmpleado, PDO::PARAM_INT);
                $insD2->execute();
            } else {
                throw $e;
            }
        }
        $idDev = (int) $db->lastInsertId();
        if ($idDev <= 0) {
            throw new RuntimeException('No se pudo registrar la devolucion.');
        }

        return $idDev;
    }

    private function vincularCreditoADevolucion(PDO $db, int $idDevolucion, int $idCredito): void
    {
        try {
            $upd = $db->prepare('UPDATE devoluciones SET id_credito_FK = :cr WHERE id_devolucion = :id');
            $upd->bindValue(':cr', $idCredito, PDO::PARAM_INT);
            $upd->bindValue(':id', $idDevolucion, PDO::PARAM_INT);
            $upd->execute();
        } catch (Throwable $e) {
            if (strpos($e->getMessage(), 'id_credito_FK') === false
                && strpos($e->getMessage(), 'Unknown column') === false) {
                throw $e;
            }
        }
    }

    private function resolverIdPiezaStockPorCodigoVendida(PDO $db, string $codigoPieza): int
    {
        $codigoPieza = trim($codigoPieza);
        if ($codigoPieza === '') {
            return 0;
        }

        $st = $db->prepare(
            "SELECT ps.id_pieza_stock FROM piezas_stock ps
             WHERE ps.activo = 1 AND ps.estado = 'vendida'
               AND (ps.codigo_auxiliar = :c OR ps.codigo_barras = :c2)
             ORDER BY ps.id_pieza_stock DESC
             LIMIT 1"
        );
        $st->bindValue(':c', $codigoPieza, PDO::PARAM_STR);
        $st->bindValue(':c2', $codigoPieza, PDO::PARAM_STR);
        $st->execute();

        return (int) ($st->fetchColumn() ?: 0);
    }

    private function resolverIdVentaCompletadaPorPieza(PDO $db, int $idPiezaStock): int
    {
        if ($idPiezaStock <= 0) {
            return 0;
        }

        $st = $db->prepare(
            "SELECT v.id_venta
             FROM venta_detalle vd
             INNER JOIN ventas v ON v.id_venta = vd.id_venta_FK
             WHERE vd.id_pieza_stock_FK = :ps
               AND COALESCE(vd.anulada, 0) = 0
               AND vd.tipo_linea = 'joya'
               AND v.estado = 'completada'
             ORDER BY vd.id_venta_detalle DESC
             LIMIT 1"
        );
        $st->bindValue(':ps', $idPiezaStock, PDO::PARAM_INT);
        $st->execute();

        return (int) ($st->fetchColumn() ?: 0);
    }

    /**
     * Valida venta + pieza y devuelve datos para agregar credito al ticket del POS (sin tocar BD de stock aun).
     * El numero de venta es opcional: si se omite, se infiere la venta completada mas reciente de la pieza.
     *
     * @return array{id_venta_origen:int,id_pieza_stock_FK:int,id_venta_detalle:int,monto_credito:string,motivo:string,descripcion:string}
     */
    public function prepararCreditoCanjeParaPos(int $idVenta, string $codigoPieza, string $motivo, int $idEmpleado, ?int $idPiezaStockFk = null): array
    {
        $idVenta = (int) $idVenta;
        $motivo = trim($motivo);
        $this->verificarEmpleadoActivo($this->getDb(), $idEmpleado);

        $db = $this->getDb();

        $idPiezaStock = 0;
        if ($idPiezaStockFk !== null && $idPiezaStockFk > 0) {
            $idPiezaStock = $idPiezaStockFk;
        } else {
            $codigoPieza = trim($codigoPieza);
            if ($codigoPieza === '') {
                throw new InvalidArgumentException('Indica codigo de pieza o id_pieza_stock_FK de la linea en la venta.');
            }
            if ($idVenta > 0) {
                $st = $db->prepare(
                    "SELECT vd.id_pieza_stock_FK FROM venta_detalle vd
                     WHERE vd.id_venta_FK = :v AND COALESCE(vd.anulada, 0) = 0 AND vd.tipo_linea = 'joya'
                       AND vd.id_pieza_stock_FK IN (
                           SELECT ps.id_pieza_stock FROM piezas_stock ps
                           WHERE ps.activo = 1 AND (ps.codigo_auxiliar = :c OR ps.codigo_barras = :c2)
                       )
                     LIMIT 1"
                );
                $st->bindValue(':v', $idVenta, PDO::PARAM_INT);
                $st->bindValue(':c', $codigoPieza, PDO::PARAM_STR);
                $st->bindValue(':c2', $codigoPieza, PDO::PARAM_STR);
                $st->execute();
                $idPiezaStock = (int) ($st->fetchColumn() ?: 0);
            } else {
                $idPiezaStock = $this->resolverIdPiezaStockPorCodigoVendida($db, $codigoPieza);
            }
        }

        if ($idPiezaStock <= 0) {
            if ($idVenta > 0) {
                throw new InvalidArgumentException('No se encontro una linea de joya en esa venta que coincida con el codigo o la pieza indicada.');
            }
            throw new InvalidArgumentException('No se encontro una pieza vendida con ese codigo auxiliar o de barras.');
        }

        if ($idVenta <= 0) {
            $idVenta = $this->resolverIdVentaCompletadaPorPieza($db, $idPiezaStock);
            if ($idVenta <= 0) {
                throw new InvalidArgumentException('No se encontro una venta completada asociada a esa pieza.');
            }
        }

        if ($this->ventaTieneFacturaEmitida($db, $idVenta)) {
            throw new InvalidArgumentException('La venta tiene factura emitida; no se puede preparar canje aqui.');
        }

        $dup = $db->prepare('SELECT 1 FROM devoluciones WHERE id_venta_FK = :v AND id_pieza_stock_FK = :p LIMIT 1');
        $dup->bindValue(':v', $idVenta, PDO::PARAM_INT);
        $dup->bindValue(':p', $idPiezaStock, PDO::PARAM_INT);
        $dup->execute();
        if ($dup->fetchColumn()) {
            throw new InvalidArgumentException('Esta pieza ya consta como devuelta en esa venta.');
        }

        $stV = $db->prepare('SELECT * FROM ventas WHERE id_venta = :id LIMIT 1');
        $stV->bindValue(':id', $idVenta, PDO::PARAM_INT);
        $stV->execute();
        $venta = $stV->fetch(PDO::FETCH_ASSOC);
        if (!$venta || ($venta['estado'] ?? '') !== 'completada') {
            throw new InvalidArgumentException('Solo se pueden usar ventas completadas para canje.');
        }

        $stL = $db->prepare(
            "SELECT vd.*, p.desc_pieza
             FROM venta_detalle vd
             INNER JOIN piezas_stock ps ON ps.id_pieza_stock = vd.id_pieza_stock_FK
             INNER JOIN piezas p ON p.id_pieza = ps.id_pieza_FK
             WHERE vd.id_venta_FK = :v AND vd.id_pieza_stock_FK = :p
               AND COALESCE(vd.anulada, 0) = 0 AND vd.tipo_linea = 'joya'
             LIMIT 1"
        );
        $stL->bindValue(':v', $idVenta, PDO::PARAM_INT);
        $stL->bindValue(':p', $idPiezaStock, PDO::PARAM_INT);
        $stL->execute();
        $linea = $stL->fetch(PDO::FETCH_ASSOC);
        if (!$linea) {
            throw new InvalidArgumentException('No hay linea activa para esa pieza en la venta.');
        }

        $stPs = $db->prepare(
            "SELECT estado, activo,
                    TRIM(COALESCE(codigo_auxiliar, '')) AS codigo_auxiliar,
                    TRIM(COALESCE(codigo_barras, '')) AS codigo_barras
             FROM piezas_stock WHERE id_pieza_stock = :id LIMIT 1"
        );
        $stPs->bindValue(':id', $idPiezaStock, PDO::PARAM_INT);
        $stPs->execute();
        $ps = $stPs->fetch(PDO::FETCH_ASSOC);
        if (!$ps || (int) ($ps['activo'] ?? 0) !== 1 || ($ps['estado'] ?? '') !== 'vendida') {
            throw new InvalidArgumentException('La pieza no esta en estado vendida en inventario.');
        }

        $codigoPieza = trim((string) ($ps['codigo_auxiliar'] ?? ''));
        if ($codigoPieza === '') {
            $codigoPieza = trim((string) ($ps['codigo_barras'] ?? ''));
        }

        $ventasModelPrep = new Ventas();
        $stDetActivos = $db->prepare(
            'SELECT * FROM venta_detalle WHERE id_venta_FK = :v AND COALESCE(anulada, 0) = 0 ORDER BY id_venta_detalle ASC'
        );
        $stDetActivos->bindValue(':v', $idVenta, PDO::PARAM_INT);
        $stDetActivos->execute();
        $detallesActivos = $stDetActivos->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $canjeAplicado = $this->sumarCanjeAplicadoEnVenta($db, $idVenta);
        $montoYaDevuelto = $this->sumarMontoDevueltoDesdeVenta($db, $idVenta);
        $montoCredito = $this->normalizarDecimal(
            $ventasModelPrep->resolverImporteDevolucionLineaVenta(
                $linea,
                $venta,
                $detallesActivos,
                $canjeAplicado,
                $montoYaDevuelto
            )
        );
        if ((float) $montoCredito <= 0) {
            throw new InvalidArgumentException(
                'No se pudo determinar el importe de la pieza en la venta #' . $idVenta . ' para el canje.'
            );
        }

        return [
            'id_venta_origen' => $idVenta,
            'id_pieza_stock_FK' => $idPiezaStock,
            'id_venta_detalle' => (int) $linea['id_venta_detalle'],
            'monto_credito' => $montoCredito,
            'motivo' => $motivo,
            'descripcion' => (string) ($linea['desc_pieza'] ?? 'Joya'),
            'codigo' => $codigoPieza,
        ];
    }

    /**
     * Dentro de la transaccion de Ventas::crear: liquida devoluciones en ventas origen y cuadra con forma interna (sin efectivo).
     *
     * @param array<int, array<string, mixed>> $creditos
     * @return string Monto total credito aplicado (delta real sumado)
     */
    public function aplicarCreditosCanjeTrasNuevaVenta(PDO $db, int $idVentaNueva, array $creditos, int $idEmpleado): string
    {
        if ($creditos === []) {
            return $this->normalizarDecimal(0.0);
        }

        $this->verificarEmpleadoActivo($db, $idEmpleado);
        $idFormaCanje = $this->obtenerIdFormaPagoCanjeInterno($db);
        $ventasModel = new Ventas();
        $sumaDelta = 0.0;

        foreach ($creditos as $cred) {
            if (!is_array($cred)) {
                continue;
            }
            $idVenta = (int) ($cred['id_venta_origen'] ?? 0);
            $idPiezaStock = (int) ($cred['id_pieza_stock_FK'] ?? 0);
            $motivo = trim((string) ($cred['motivo'] ?? ''));
            if ($idVenta <= 0 || $idPiezaStock <= 0) {
                throw new InvalidArgumentException('Credito de canje invalido (faltan venta o pieza).');
            }

            if ($this->ventaTieneFacturaEmitida($db, $idVenta)) {
                throw new InvalidArgumentException('Una de las ventas origen tiene factura emitida; no se puede canjear.');
            }

            $stV = $db->prepare('SELECT * FROM ventas WHERE id_venta = :id FOR UPDATE');
            $stV->bindValue(':id', $idVenta, PDO::PARAM_INT);
            $stV->execute();
            $venta = $stV->fetch(PDO::FETCH_ASSOC);
            if (!$venta || ($venta['estado'] ?? '') !== 'completada') {
                throw new InvalidArgumentException('Venta origen #' . $idVenta . ' no esta completada.');
            }

            $totalAntes = (float) ($venta['total'] ?? 0);
            $sumPagosAntes = $this->sumaPagosVenta($db, $idVenta);
            $desfasePagosAntes = abs($sumPagosAntes - $totalAntes);
            if ($desfasePagosAntes > 1.00) {
                throw new InvalidArgumentException('La venta #' . $idVenta . ' no tiene pagos cuadrados con el total (desfase $' . number_format($desfasePagosAntes, 2) . ').');
            }

            $dup = $db->prepare('SELECT 1 FROM devoluciones WHERE id_venta_FK = :v AND id_pieza_stock_FK = :p LIMIT 1');
            $dup->bindValue(':v', $idVenta, PDO::PARAM_INT);
            $dup->bindValue(':p', $idPiezaStock, PDO::PARAM_INT);
            $dup->execute();
            if ($dup->fetchColumn()) {
                throw new InvalidArgumentException('La pieza #' . $idPiezaStock . ' ya fue devuelta en la venta #' . $idVenta . '.');
            }

            $stL = $db->prepare(
                "SELECT vd.* FROM venta_detalle vd
                 WHERE vd.id_venta_FK = :v AND vd.id_pieza_stock_FK = :p
                   AND COALESCE(vd.anulada, 0) = 0 AND vd.tipo_linea = 'joya'
                 LIMIT 1 FOR UPDATE"
            );
            $stL->bindValue(':v', $idVenta, PDO::PARAM_INT);
            $stL->bindValue(':p', $idPiezaStock, PDO::PARAM_INT);
            $stL->execute();
            $linea = $stL->fetch(PDO::FETCH_ASSOC);
            if (!$linea) {
                throw new InvalidArgumentException('Linea de joya no disponible para devolucion en venta #' . $idVenta . '.');
            }

            $stPs = $db->prepare('SELECT id_pieza_stock, estado, activo FROM piezas_stock WHERE id_pieza_stock = :id FOR UPDATE');
            $stPs->bindValue(':id', $idPiezaStock, PDO::PARAM_INT);
            $stPs->execute();
            $ps = $stPs->fetch(PDO::FETCH_ASSOC);
            if (!$ps || (int) ($ps['activo'] ?? 0) !== 1 || ($ps['estado'] ?? '') !== 'vendida') {
                throw new InvalidArgumentException('Pieza #' . $idPiezaStock . ' no esta vendida.');
            }

            $updL = $db->prepare('UPDATE venta_detalle SET anulada = 1 WHERE id_venta_detalle = :id');
            $updL->bindValue(':id', (int) $linea['id_venta_detalle'], PDO::PARAM_INT);
            $updL->execute();

            $stD = $db->prepare(
                'SELECT * FROM venta_detalle WHERE id_venta_FK = :v AND COALESCE(anulada, 0) = 0 ORDER BY id_venta_detalle ASC'
            );
            $stD->bindValue(':v', $idVenta, PDO::PARAM_INT);
            $stD->execute();
            $detallesActivos = $stD->fetchAll(PDO::FETCH_ASSOC);

            $rec = $this->anularLineaYRecalcularVentaOrigen($db, $ventasModel, $venta, $linea, $detallesActivos, $idVenta);
            $tot = $rec['tot_despues'];
            $nuevoTotal = $rec['nuevo_total'];
            $nuevoImpuestoMonto = (float) $tot['impuesto_monto'];
            $montoCredito = $rec['monto_linea'];
            $montoPagoNeg = $rec['monto_ajuste_venta'];
            $canjeAplicadoOrigen = (float) ($rec['canje_aplicado'] ?? 0);
            $sumaDelta += (float) $montoCredito;

            $nuevoEstado = $rec['nuevo_estado'];
            $updV = $db->prepare(
                'UPDATE ventas SET total = :total, impuesto_monto = :imp, estado = :estado WHERE id_venta = :id'
            );
            $updV->bindValue(':total', $this->normalizarDecimal($nuevoTotal), PDO::PARAM_STR);
            $updV->bindValue(':imp', $this->normalizarDecimal($nuevoImpuestoMonto), PDO::PARAM_STR);
            $updV->bindValue(':estado', $nuevoEstado, PDO::PARAM_STR);
            $updV->bindValue(':id', $idVenta, PDO::PARAM_INT);
            $updV->execute();

            if ((float) $montoPagoNeg > 0.009) {
                $insP = $db->prepare(
                    'INSERT INTO venta_pagos (id_venta_FK, id_forma_pago_FK, monto) VALUES (:v, :fp, :monto)'
                );
                $negativo = $this->normalizarDecimal(-(float) $montoPagoNeg);
                $insP->bindValue(':v', $idVenta, PDO::PARAM_INT);
                $insP->bindValue(':fp', $idFormaCanje, PDO::PARAM_INT);
                $insP->bindValue(':monto', $negativo, PDO::PARAM_STR);
                $insP->execute();
            }

            $this->verificarCuadrePagosTrasDevolucion($db, $idVenta, $nuevoTotal, $canjeAplicadoOrigen, 'canje interno');

            $updPs = $db->prepare(
                "UPDATE piezas_stock SET estado = 'disponible'
                 WHERE id_pieza_stock = :id AND estado = 'vendida' AND activo = 1"
            );
            $updPs->bindValue(':id', $idPiezaStock, PDO::PARAM_INT);
            $updPs->execute();
            if ($updPs->rowCount() !== 1) {
                throw new RuntimeException('No se pudo reingresar la pieza #' . $idPiezaStock . ' al inventario.');
            }

            $insD = $db->prepare(
                "INSERT INTO devoluciones (id_venta_FK, tipo_origen, id_pieza_stock_FK, id_venta_detalle_FK, id_venta_destino_canje_FK, motivo, monto_reembolso, id_forma_pago_FK, id_empleado_FK)
                 VALUES (:v, 'venta', :ps, :vd, :dest, :motivo, :mreemb, NULL, :emp)"
            );
            $insD->bindValue(':v', $idVenta, PDO::PARAM_INT);
            $insD->bindValue(':ps', $idPiezaStock, PDO::PARAM_INT);
            $insD->bindValue(':vd', (int) $linea['id_venta_detalle'], PDO::PARAM_INT);
            $insD->bindValue(':dest', $idVentaNueva, PDO::PARAM_INT);
            $insD->bindValue(':motivo', $motivo, PDO::PARAM_STR);
            $insD->bindValue(':mreemb', $montoCredito, PDO::PARAM_STR);
            $insD->bindValue(':emp', $idEmpleado, PDO::PARAM_INT);
            try {
                $insD->execute();
            } catch (Throwable $e) {
                if (strpos($e->getMessage(), 'id_venta_destino_canje') !== false
                    || strpos($e->getMessage(), 'Unknown column') !== false) {
                    $insD2 = $db->prepare(
                        'INSERT INTO devoluciones (id_venta_FK, tipo_origen, id_pieza_stock_FK, id_venta_detalle_FK, motivo, monto_reembolso, id_forma_pago_FK, id_empleado_FK)
                         VALUES (:v, \'venta\', :ps, :vd, :motivo, :mreemb, NULL, :emp)'
                    );
                    $insD2->bindValue(':v', $idVenta, PDO::PARAM_INT);
                    $insD2->bindValue(':ps', $idPiezaStock, PDO::PARAM_INT);
                    $insD2->bindValue(':vd', (int) $linea['id_venta_detalle'], PDO::PARAM_INT);
                    $insD2->bindValue(':motivo', $motivo . ' [canje venta #' . $idVentaNueva . ']', PDO::PARAM_STR);
                    $insD2->bindValue(':mreemb', $montoCredito, PDO::PARAM_STR);
                    $insD2->bindValue(':emp', $idEmpleado, PDO::PARAM_INT);
                    $insD2->execute();
                } else {
                    throw $e;
                }
            }
        }

        return $this->normalizarDecimal($sumaDelta);
    }

    /**
     * @deprecated El flujo de reembolso en efectivo se reemplazo por canje en Punto de Venta.
     */
    public function registrarDevolucionVenta(array $data): array
    {
        throw new InvalidArgumentException(
            'Ya no se registra devolucion con reembolso en efectivo desde la API. '
            . 'En Punto de Venta abre la seccion Devoluciones, agrega el credito al ticket y vende piezas por el mismo o mayor valor al credito; el monto se aplica como descuento sobre esa venta.'
        );
    }

    /**
     * @return array{id_devolucion: int, id_pieza_stock_FK: int, monto_reembolso: string}
     */
    public function registrarDevolucionMostrador(array $data): array
    {
        $motivo = trim((string) ($data['motivo'] ?? ''));
        $idEmpleado = (int) ($data['id_empleado_FK'] ?? 0);
        $idUsuario = (int) ($data['id_usuario_FK'] ?? 0);
        $idCliente = (int) ($data['id_cliente_FK'] ?? 0);
        $acreditarMonedero = !empty($data['acreditar_monedero']);
        $idFormaPago = isset($data['id_forma_pago_FK']) && $data['id_forma_pago_FK'] !== '' && $data['id_forma_pago_FK'] !== null
            ? (int) $data['id_forma_pago_FK']
            : 0;
        $codigo = trim((string) ($data['codigo'] ?? ''));
        $idPiezaStock = (int) ($data['id_pieza_stock_FK'] ?? 0);

        if ($acreditarMonedero) {
            if ($idCliente <= 0) {
                throw new InvalidArgumentException('Indica el cliente para acreditar el monedero.');
            }
            if ($idUsuario <= 0) {
                throw new InvalidArgumentException('Usuario de sesion requerido para acreditar monedero.');
            }
        }

        if ($idEmpleado <= 0) {
            throw new InvalidArgumentException('El empleado es obligatorio.');
        }

        $db = $this->getDb();

        if ($idPiezaStock <= 0 && $codigo !== '') {
            $st = $db->prepare(
                "SELECT ps.id_pieza_stock FROM piezas_stock ps
                 WHERE ps.activo = 1 AND ps.estado = 'vendida'
                   AND (ps.codigo_auxiliar = :c OR ps.codigo_barras = :c2)
                 ORDER BY ps.id_pieza_stock DESC LIMIT 1"
            );
            $st->bindValue(':c', $codigo, PDO::PARAM_STR);
            $st->bindValue(':c2', $codigo, PDO::PARAM_STR);
            $st->execute();
            $idPiezaStock = (int) ($st->fetchColumn() ?: 0);

            if ($idPiezaStock <= 0) {
                $stAny = $db->prepare(
                    "SELECT ps.id_pieza_stock, ps.estado FROM piezas_stock ps
                     WHERE ps.activo = 1
                       AND (ps.codigo_auxiliar = :c OR ps.codigo_barras = :c2)
                     ORDER BY ps.id_pieza_stock DESC LIMIT 1"
                );
                $stAny->bindValue(':c', $codigo, PDO::PARAM_STR);
                $stAny->bindValue(':c2', $codigo, PDO::PARAM_STR);
                $stAny->execute();
                $rowAny = $stAny->fetch(PDO::FETCH_ASSOC);
                if ($rowAny) {
                    $estAny = (string) ($rowAny['estado'] ?? '');
                    if ($estAny === 'disponible') {
                        throw new InvalidArgumentException(
                            'Pieza ya disponible en inventario; no se registra devolucion de mostrador.'
                        );
                    }
                    throw new InvalidArgumentException(
                        'La pieza existe pero no esta vendida en sistema (estado: '
                        . ($estAny !== '' ? $estAny : 'desconocido')
                        . '). Solo se registran devoluciones de mostrador para piezas en estado vendida.'
                    );
                }
                throw new InvalidArgumentException(
                    'No se encontro una pieza activa con ese codigo auxiliar o de barras.'
                );
            }
        }

        if ($idPiezaStock <= 0) {
            throw new InvalidArgumentException('Indica id_pieza_stock_FK o el codigo de una pieza en estado vendida.');
        }

        $this->verificarEmpleadoActivo($db, $idEmpleado);

        if ($idFormaPago > 0) {
            $this->verificarFormaPago($db, $idFormaPago);
        }

        $db->beginTransaction();

        try {
            auth_mysql_set_audit_vars($db);

            $stPs = $db->prepare(
                'SELECT id_pieza_stock, estado, COALESCE(precio_venta, 0) AS precio_venta
                 FROM piezas_stock WHERE id_pieza_stock = :id AND activo = 1 FOR UPDATE'
            );
            $stPs->bindValue(':id', $idPiezaStock, PDO::PARAM_INT);
            $stPs->execute();
            $ps = $stPs->fetch(PDO::FETCH_ASSOC);
            if (!$ps) {
                throw new InvalidArgumentException('La pieza no existe o no esta activa.');
            }
            $estadoPs = (string) ($ps['estado'] ?? '');
            if ($estadoPs !== 'vendida') {
                if ($estadoPs === 'disponible') {
                    throw new InvalidArgumentException(
                        'Pieza ya disponible en inventario; no se registra devolucion de mostrador.'
                    );
                }
                throw new InvalidArgumentException(
                    'La pieza no esta en estado vendida (esta: '
                    . ($estadoPs !== '' ? $estadoPs : 'desconocido')
                    . '). Solo se registran devoluciones de mostrador para piezas vendidas.'
                );
            }

            // Prioridad: precio real cobrado en la última venta (subtotal con descuentos).
            // Fallback si no hay venta registrada: precio de tarjeta del stock.
            $stSub = $db->prepare(
                "SELECT vd.subtotal FROM venta_detalle vd
                 WHERE vd.id_pieza_stock_FK = :id AND COALESCE(vd.anulada, 0) = 0 AND vd.tipo_linea = 'joya'
                 ORDER BY vd.id_venta_detalle DESC LIMIT 1"
            );
            $stSub->bindValue(':id', $idPiezaStock, PDO::PARAM_INT);
            $stSub->execute();
            $montoVentaDetalle = (float) ($stSub->fetchColumn() ?: 0);

            $montoRef = $montoVentaDetalle > 0.009
                ? $montoVentaDetalle
                : (float) ($ps['precio_venta'] ?? 0);

            $updPs = $db->prepare(
                "UPDATE piezas_stock SET estado = 'disponible'
                 WHERE id_pieza_stock = :id AND estado = 'vendida' AND activo = 1"
            );
            $updPs->bindValue(':id', $idPiezaStock, PDO::PARAM_INT);
            $updPs->execute();
            if ($updPs->rowCount() !== 1) {
                throw new RuntimeException('No se pudo marcar la pieza como disponible.');
            }

            $montoStr = $this->normalizarDecimal($montoRef);
            $insD = $db->prepare(
                'INSERT INTO devoluciones (id_venta_FK, tipo_origen, id_pieza_stock_FK, id_venta_detalle_FK, motivo, monto_reembolso, id_forma_pago_FK, id_empleado_FK)
                 VALUES (NULL, \'mostrador\', :ps, NULL, :motivo, :mreemb, :fp, :emp)'
            );
            $insD->bindValue(':ps', $idPiezaStock, PDO::PARAM_INT);
            $insD->bindValue(':motivo', $motivo, PDO::PARAM_STR);
            $insD->bindValue(':mreemb', $montoStr, PDO::PARAM_STR);
            if ($idFormaPago > 0) {
                $insD->bindValue(':fp', $idFormaPago, PDO::PARAM_INT);
            } else {
                $insD->bindValue(':fp', null, PDO::PARAM_NULL);
            }
            $insD->bindValue(':emp', $idEmpleado, PDO::PARAM_INT);
            $insD->execute();
            $idDev = (int) $db->lastInsertId();

            $idCredito = null;
            $saldoMonedero = null;
            if ($acreditarMonedero) {
                $this->verificarClienteActivo($db, $idCliente);
                $ag = new ApartadoGestion();
                $obsCred = ($motivo !== '' ? $motivo . ' | ' : '') . 'Devolucion mostrador #' . $idDev;
                $idCredito = $ag->registrarCreditoClienteEntrada(
                    $db,
                    $idCliente,
                    $montoStr,
                    'devolucion',
                    $idEmpleado,
                    $idUsuario,
                    $obsCred,
                    null,
                    $idDev,
                    null
                );
                $this->vincularCreditoADevolucion($db, $idDev, $idCredito);
                try {
                    $updCli = $db->prepare('UPDATE devoluciones SET id_cliente_FK = :cli WHERE id_devolucion = :id');
                    $updCli->bindValue(':cli', $idCliente, PDO::PARAM_INT);
                    $updCli->bindValue(':id', $idDev, PDO::PARAM_INT);
                    $updCli->execute();
                } catch (Throwable $eCli) {
                    if (strpos($eCli->getMessage(), 'id_cliente_FK') === false
                        && strpos($eCli->getMessage(), 'Unknown column') === false) {
                        throw $eCli;
                    }
                }
                $saldoMonedero = $ag->totalCreditoDisponibleCliente($idCliente);
            }

            $db->commit();

            $out = [
                'id_devolucion' => $idDev,
                'id_pieza_stock_FK' => $idPiezaStock,
                'monto_reembolso' => $montoStr,
            ];
            if ($acreditarMonedero) {
                $out['id_credito'] = $idCredito;
                $out['id_cliente_FK'] = $idCliente;
                $out['monedero_saldo_disponible'] = $saldoMonedero;
            }

            return $out;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public function listarRecientes(int $limite = 100): array
    {
        $limite = max(1, min(500, $limite));
        $sql = "SELECT d.*, fp.forma_pago,
                       TRIM(COALESCE(ps.codigo_auxiliar, '')) AS pieza_codigo_auxiliar,
                       CONCAT(u.nombre, ' ', u.primer_apellido, COALESCE(CONCAT(' ', u.segundo_apellido), '')) AS empleado_nombre,
                       cc.id_credito AS credito_id,
                       cc.monto AS credito_monto,
                       cc.estado AS credito_estado,
                       CONCAT(uc.nombre, ' ', uc.primer_apellido, COALESCE(CONCAT(' ', uc.segundo_apellido), '')) AS cliente_credito_nombre
                FROM devoluciones d
                INNER JOIN empleados e ON e.id_empleado = d.id_empleado_FK
                INNER JOIN usuarios u ON u.id_usuario = e.id_usuario_FK
                LEFT JOIN piezas_stock ps ON ps.id_pieza_stock = d.id_pieza_stock_FK
                LEFT JOIN forma_pago fp ON fp.id_forma_pago = d.id_forma_pago_FK
                LEFT JOIN cliente_creditos cc ON cc.id_credito = d.id_credito_FK
                LEFT JOIN clientes c ON c.id_cliente = d.id_cliente_FK
                LEFT JOIN usuarios uc ON uc.id_usuario = c.id_usuario_FK
                ORDER BY d.fecha_devolucion DESC, d.id_devolucion DESC
                LIMIT " . (int) $limite;

        try {
            return $this->getDb()->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            if (strpos($e->getMessage(), 'id_credito_FK') !== false
                || strpos($e->getMessage(), 'id_cliente_FK') !== false
                || strpos($e->getMessage(), 'Unknown column') !== false) {
                $sqlLegacy = 'SELECT d.*, fp.forma_pago,
                       TRIM(COALESCE(ps.codigo_auxiliar, \'\')) AS pieza_codigo_auxiliar,
                       CONCAT(u.nombre, \' \', u.primer_apellido, COALESCE(CONCAT(\' \', u.segundo_apellido), \'\')) AS empleado_nombre
                FROM devoluciones d
                INNER JOIN empleados e ON e.id_empleado = d.id_empleado_FK
                INNER JOIN usuarios u ON u.id_usuario = e.id_usuario_FK
                LEFT JOIN piezas_stock ps ON ps.id_pieza_stock = d.id_pieza_stock_FK
                LEFT JOIN forma_pago fp ON fp.id_forma_pago = d.id_forma_pago_FK
                ORDER BY d.fecha_devolucion DESC, d.id_devolucion DESC
                LIMIT ' . (int) $limite;

                return $this->getDb()->query($sqlLegacy)->fetchAll(PDO::FETCH_ASSOC) ?: [];
            }
            throw $e;
        }
    }

    public function formasPagoActivas(): array
    {
        return $this->getDb()->query(
            'SELECT id_forma_pago, forma_pago FROM forma_pago WHERE activo = 1 ORDER BY forma_pago ASC'
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Formas de pago "reales" (no internas) seleccionables por el usuario en reembolso.
     * Excluye etiquetas internas usadas para cuadre contable.
     *
     * @return list<array{id_forma_pago: int, forma_pago: string, es_efectivo?: int}>
     */
    public function formasPagoParaReembolso(): array
    {
        $internas = [
            'Canje interno (sin efectivo)',
            'Credito monedero por devolucion (sin efectivo)',
            'Credito a favor cliente',
            'Credito interno (cambio apartado)',
        ];
        $placeholders = implode(',', array_fill(0, count($internas), '?'));
        $sql = "SELECT id_forma_pago, forma_pago"
            . (self::columnaEsEfectivoExiste($this->getDb()) ? ', es_efectivo' : '')
            . " FROM forma_pago WHERE activo = 1
              AND forma_pago NOT IN ($placeholders)
              ORDER BY forma_pago ASC";
        $st = $this->getDb()->prepare($sql);
        foreach ($internas as $i => $nombre) {
            $st->bindValue($i + 1, $nombre, PDO::PARAM_STR);
        }
        $st->execute();

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private static function columnaEsEfectivoExiste(PDO $db): bool
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        try {
            $cache = (bool) $db->query("SHOW COLUMNS FROM forma_pago LIKE 'es_efectivo'")->fetch();
        } catch (Throwable $e) {
            $cache = false;
        }

        return $cache;
    }

    /**
     * Determina si una forma_pago es efectivo (cuando existe la columna es_efectivo).
     */
    public function formaPagoEsEfectivo(PDO $db, int $idFormaPago): bool
    {
        if ($idFormaPago <= 0) {
            return false;
        }
        if (!self::columnaEsEfectivoExiste($db)) {
            $st = $db->prepare('SELECT forma_pago FROM forma_pago WHERE id_forma_pago = :id LIMIT 1');
            $st->bindValue(':id', $idFormaPago, PDO::PARAM_INT);
            $st->execute();
            $nombre = (string) ($st->fetchColumn() ?: '');

            return stripos($nombre, 'efectivo') !== false;
        }
        $st = $db->prepare('SELECT es_efectivo FROM forma_pago WHERE id_forma_pago = :id LIMIT 1');
        $st->bindValue(':id', $idFormaPago, PDO::PARAM_INT);
        $st->execute();

        return (int) ($st->fetchColumn() ?: 0) === 1;
    }

    /**
     * Vista previa unificada de devolucion.
     * Detecta si hay ticket (venta completada con la pieza), arma modos disponibles y devuelve bloqueos.
     *
     * @return array<string, mixed>
     */
    public function prepararDevolucionUnificada(
        string $codigoPieza,
        ?int $idCliente,
        ?int $idVenta,
        int $idEmpleado,
        ?int $idPiezaStockFk = null
    ): array {
        $codigoPieza = trim($codigoPieza);
        $idEmpleado = (int) $idEmpleado;
        if ($idEmpleado <= 0) {
            throw new InvalidArgumentException('Empleado requerido para la vista previa.');
        }
        $this->verificarEmpleadoActivo($this->getDb(), $idEmpleado);

        $db = $this->getDb();

        $idPiezaStock = $idPiezaStockFk !== null && $idPiezaStockFk > 0 ? (int) $idPiezaStockFk : 0;
        if ($idPiezaStock <= 0 && $codigoPieza !== '') {
            $idPiezaStock = $this->resolverIdPiezaStockPorCodigoVendida($db, $codigoPieza);
        }
        if ($idPiezaStock <= 0) {
            throw new InvalidArgumentException('No se encontro una pieza vendida con ese codigo (auxiliar o de barras).');
        }

        $stPs = $db->prepare(
            "SELECT ps.id_pieza_stock, ps.estado, ps.activo,
                    TRIM(COALESCE(ps.codigo_auxiliar, '')) AS codigo_auxiliar,
                    TRIM(COALESCE(ps.codigo_barras, '')) AS codigo_barras,
                    COALESCE(ps.precio_venta, 0) AS precio_venta,
                    p.desc_pieza
             FROM piezas_stock ps
             INNER JOIN piezas p ON p.id_pieza = ps.id_pieza_FK
             WHERE ps.id_pieza_stock = :id LIMIT 1"
        );
        $stPs->bindValue(':id', $idPiezaStock, PDO::PARAM_INT);
        $stPs->execute();
        $ps = $stPs->fetch(PDO::FETCH_ASSOC);
        if (!$ps || (int) ($ps['activo'] ?? 0) !== 1) {
            throw new InvalidArgumentException('La pieza no existe o no esta activa.');
        }

        $estadoPs = (string) ($ps['estado'] ?? '');
        $bloqueos = [];
        if ($estadoPs !== 'vendida') {
            if ($estadoPs === 'disponible') {
                $bloqueos[] = 'La pieza ya esta disponible en inventario; no se puede registrar devolucion.';
            } else {
                $bloqueos[] = 'La pieza no esta en estado vendida (esta: ' . ($estadoPs !== '' ? $estadoPs : 'desconocido') . ').';
            }
        }

        $codigo = $ps['codigo_auxiliar'] !== '' ? $ps['codigo_auxiliar'] : $ps['codigo_barras'];
        $descripcion = (string) ($ps['desc_pieza'] ?? 'Joya');

        $idVentaResuelta = 0;
        $idVentaDetalle = 0;
        $montoLineaVenta = 0.0;
        $idClienteVenta = 0;
        $facturaEmitida = false;
        $duplicada = false;

        if ($idVenta !== null && $idVenta > 0) {
            $idVentaResuelta = (int) $idVenta;
        } else {
            $idVentaResuelta = $this->resolverIdVentaCompletadaPorPieza($db, $idPiezaStock);
        }

        if ($idVentaResuelta > 0) {
            $stL = $db->prepare(
                "SELECT vd.*, v.id_cliente_FK, v.estado
                 FROM venta_detalle vd
                 INNER JOIN ventas v ON v.id_venta = vd.id_venta_FK
                 WHERE vd.id_venta_FK = :v AND vd.id_pieza_stock_FK = :p
                   AND COALESCE(vd.anulada, 0) = 0 AND vd.tipo_linea = 'joya'
                 LIMIT 1"
            );
            $stL->bindValue(':v', $idVentaResuelta, PDO::PARAM_INT);
            $stL->bindValue(':p', $idPiezaStock, PDO::PARAM_INT);
            $stL->execute();
            $linea = $stL->fetch(PDO::FETCH_ASSOC);
            if ($linea && (string) ($linea['estado'] ?? '') === 'completada') {
                $idVentaDetalle = (int) $linea['id_venta_detalle'];
                $ventasScan = new Ventas();
                $stVentaScan = $db->prepare('SELECT * FROM ventas WHERE id_venta = :id LIMIT 1');
                $stVentaScan->bindValue(':id', $idVentaResuelta, PDO::PARAM_INT);
                $stVentaScan->execute();
                $ventaScan = $stVentaScan->fetch(PDO::FETCH_ASSOC) ?: [];
                $stDetScan = $db->prepare(
                    'SELECT * FROM venta_detalle WHERE id_venta_FK = :v AND COALESCE(anulada, 0) = 0 ORDER BY id_venta_detalle ASC'
                );
                $stDetScan->bindValue(':v', $idVentaResuelta, PDO::PARAM_INT);
                $stDetScan->execute();
                $detallesScan = $stDetScan->fetchAll(PDO::FETCH_ASSOC) ?: [];
                $canjeScan = $this->sumarCanjeAplicadoEnVenta($db, $idVentaResuelta);
                $yaDevScan = $this->sumarMontoDevueltoDesdeVenta($db, $idVentaResuelta);
                $montoLineaVenta = $ventasScan->resolverImporteDevolucionLineaVenta(
                    $linea,
                    $ventaScan,
                    $detallesScan,
                    $canjeScan,
                    $yaDevScan
                );
                $idClienteVenta = (int) ($linea['id_cliente_FK'] ?? 0);

                if ($this->ventaTieneFacturaEmitida($db, $idVentaResuelta)) {
                    $facturaEmitida = true;
                    $bloqueos[] = 'La venta #' . $idVentaResuelta . ' tiene factura emitida; emite nota de credito en su lugar.';
                }

                $dup = $db->prepare('SELECT 1 FROM devoluciones WHERE id_venta_FK = :v AND id_pieza_stock_FK = :p LIMIT 1');
                $dup->bindValue(':v', $idVentaResuelta, PDO::PARAM_INT);
                $dup->bindValue(':p', $idPiezaStock, PDO::PARAM_INT);
                $dup->execute();
                if ($dup->fetchColumn()) {
                    $duplicada = true;
                    $bloqueos[] = 'Esta pieza ya consta como devuelta en la venta #' . $idVentaResuelta . '.';
                }
            } else {
                $idVentaResuelta = 0;
            }
        }

        $montoReferencia = $idVentaDetalle > 0
            ? $montoLineaVenta
            : (float) ($ps['precio_venta'] ?? 0);
        if ($montoReferencia <= 0 && $idVentaDetalle <= 0) {
            $stSub = $db->prepare(
                "SELECT vd.subtotal FROM venta_detalle vd
                 WHERE vd.id_pieza_stock_FK = :id AND COALESCE(vd.anulada, 0) = 0 AND vd.tipo_linea = 'joya'
                 ORDER BY vd.id_venta_detalle DESC LIMIT 1"
            );
            $stSub->bindValue(':id', $idPiezaStock, PDO::PARAM_INT);
            $stSub->execute();
            $montoReferencia = (float) ($stSub->fetchColumn() ?: 0);
        }

        $clienteSugerido = $idClienteVenta > 0 ? $idClienteVenta : ($idCliente !== null && $idCliente > 0 ? (int) $idCliente : 0);
        $idClienteResuelto = $clienteSugerido;
        if ($idCliente !== null && $idCliente > 0) {
            if ($idClienteVenta > 0 && $idClienteVenta !== (int) $idCliente) {
                $bloqueos[] = 'El cliente seleccionado (#' . (int) $idCliente . ') no coincide con el de la venta origen (#' . $idClienteVenta . ').';
            } else {
                $idClienteResuelto = (int) $idCliente;
            }
        }

        $modosPermitidos = [];
        if (count($bloqueos) === 0) {
            if ($idVentaResuelta > 0 && $idVentaDetalle > 0) {
                $modosPermitidos = ['efectivo', 'otra_forma', 'monedero'];
            } else {
                $modosPermitidos = ['efectivo', 'otra_forma', 'monedero', 'solo_inventario'];
            }
        }

        $saldoMonederoActual = '0.00';
        if ($idClienteResuelto > 0) {
            $ag = new ApartadoGestion();
            $saldoMonederoActual = $ag->totalCreditoDisponibleCliente($idClienteResuelto);
        }

        return [
            'id_pieza_stock_FK' => $idPiezaStock,
            'codigo' => $codigo,
            'descripcion' => $descripcion,
            'estado_pieza' => $estadoPs,
            'monto_referencia' => $this->normalizarDecimal($montoReferencia),
            'id_venta_origen' => $idVentaResuelta > 0 ? $idVentaResuelta : null,
            'id_venta_detalle' => $idVentaDetalle > 0 ? $idVentaDetalle : null,
            'tiene_ticket' => $idVentaResuelta > 0 && $idVentaDetalle > 0,
            'factura_emitida' => $facturaEmitida,
            'duplicada' => $duplicada,
            'cliente_sugerido' => $clienteSugerido > 0 ? $clienteSugerido : null,
            'id_cliente_FK' => $idClienteResuelto > 0 ? $idClienteResuelto : null,
            'monedero_saldo_actual' => $saldoMonederoActual,
            'monedero_saldo_tras_credito' => $this->normalizarDecimal(
                (float) $saldoMonederoActual + (float) $this->normalizarDecimal($montoReferencia)
            ),
            'modos_permitidos' => $modosPermitidos,
            'bloqueos' => $bloqueos,
        ];
    }

    /**
     * Despachador unificado. Dispatcha por modo y reusa los flujos existentes.
     *
     * @return array<string, mixed>
     */
    public function registrarDevolucionUnificada(array $data): array
    {
        $modo = strtolower(trim((string) ($data['modo'] ?? '')));
        $modosValidos = ['efectivo', 'otra_forma', 'monedero', 'solo_inventario'];
        if (!in_array($modo, $modosValidos, true)) {
            throw new InvalidArgumentException('Modo de devolucion invalido. Usa: efectivo, otra_forma, monedero o solo_inventario.');
        }

        $idEmpleado = (int) ($data['id_empleado_FK'] ?? 0);
        $idUsuario = (int) ($data['id_usuario_FK'] ?? 0);
        $idCliente = (int) ($data['id_cliente_FK'] ?? 0);
        $codigo = trim((string) ($data['codigo'] ?? ''));
        $idVenta = (int) ($data['id_venta_FK'] ?? $data['id_venta'] ?? 0);
        $idPiezaStock = (int) ($data['id_pieza_stock_FK'] ?? 0);
        $motivo = trim((string) ($data['motivo'] ?? ''));
        $idFormaPagoIn = isset($data['id_forma_pago_FK']) && $data['id_forma_pago_FK'] !== ''
            ? (int) $data['id_forma_pago_FK']
            : 0;

        if ($idEmpleado <= 0 || $idUsuario <= 0) {
            throw new InvalidArgumentException('Empleado y usuario son obligatorios.');
        }

        $prep = $this->prepararDevolucionUnificada(
            $codigo,
            $idCliente > 0 ? $idCliente : null,
            $idVenta > 0 ? $idVenta : null,
            $idEmpleado,
            $idPiezaStock > 0 ? $idPiezaStock : null
        );
        if (!empty($prep['bloqueos'])) {
            throw new InvalidArgumentException(implode(' ', (array) $prep['bloqueos']));
        }
        if (!in_array($modo, (array) $prep['modos_permitidos'], true)) {
            throw new InvalidArgumentException(
                'El modo "' . $modo . '" no esta permitido para esta pieza. '
                . 'Modos disponibles: ' . implode(', ', (array) $prep['modos_permitidos']) . '.'
            );
        }

        $idPiezaStock = (int) $prep['id_pieza_stock_FK'];
        $tieneTicket = (bool) $prep['tiene_ticket'];
        $codigoResuelto = (string) $prep['codigo'];

        if ($modo === 'monedero') {
            $clienteFinal = (int) ($prep['id_cliente_FK'] ?? $idCliente);
            if ($clienteFinal <= 0) {
                throw new InvalidArgumentException('Indica el cliente que recibira el credito en el monedero.');
            }

            return $this->registrarDevolucionConMonedero([
                'id_empleado_FK' => $idEmpleado,
                'id_usuario_FK' => $idUsuario,
                'id_cliente_FK' => $clienteFinal,
                'id_venta_FK' => $tieneTicket ? (int) ($prep['id_venta_origen'] ?? 0) : 0,
                'id_pieza_stock_FK' => $idPiezaStock,
                'codigo' => $codigoResuelto,
                'motivo' => $motivo,
            ]) + ['modo' => 'monedero'];
        }

        if ($modo === 'solo_inventario') {
            $out = $this->registrarDevolucionMostrador([
                'id_empleado_FK' => $idEmpleado,
                'id_usuario_FK' => $idUsuario,
                'id_pieza_stock_FK' => $idPiezaStock,
                'codigo' => $codigoResuelto,
                'motivo' => $motivo,
            ]);

            return $out + ['modo' => 'solo_inventario', 'afecta_caja' => false];
        }

        if ($idFormaPagoIn <= 0) {
            throw new InvalidArgumentException('Selecciona la forma de pago del reembolso.');
        }

        $db = $this->getDb();
        $this->verificarFormaPago($db, $idFormaPagoIn);
        $esEfectivo = $this->formaPagoEsEfectivo($db, $idFormaPagoIn);
        if ($modo === 'efectivo' && !$esEfectivo) {
            throw new InvalidArgumentException('La forma seleccionada no es efectivo. Usa modo "otra_forma" o cambia la forma.');
        }
        if ($modo === 'otra_forma' && $esEfectivo) {
            throw new InvalidArgumentException('La forma seleccionada es efectivo. Usa modo "efectivo".');
        }

        if (!$tieneTicket) {
            $clienteOpt = (int) ($prep['id_cliente_FK'] ?? $idCliente);

            $out = $this->registrarDevolucionMostrador([
                'id_empleado_FK' => $idEmpleado,
                'id_usuario_FK' => $idUsuario,
                'id_cliente_FK' => $clienteOpt > 0 ? $clienteOpt : 0,
                'id_pieza_stock_FK' => $idPiezaStock,
                'codigo' => $codigoResuelto,
                'motivo' => $motivo,
                'id_forma_pago_FK' => $idFormaPagoIn,
                'acreditar_monedero' => false,
            ]);

            return $out + [
                'modo' => $modo,
                'afecta_caja' => true,
                'id_forma_pago_FK' => $idFormaPagoIn,
            ];
        }

        return $this->ejecutarReembolsoConTicket(
            $idEmpleado,
            $idUsuario,
            (int) ($prep['id_venta_origen'] ?? 0),
            $idPiezaStock,
            (int) ($prep['id_venta_detalle'] ?? 0),
            $idFormaPagoIn,
            (string) $prep['monto_referencia'],
            $motivo,
            $modo
        );
    }

    /**
     * Reembolso con ticket: anula linea, recalcula venta y registra pago negativo
     * con la forma elegida (efectivo/tarjeta/etc.). El cierre cuenta la salida solo
     * desde venta_pagos (devoluciones queda con id_forma_pago_FK = NULL).
     *
     * @return array<string, mixed>
     */
    private function ejecutarReembolsoConTicket(
        int $idEmpleado,
        int $idUsuario,
        int $idVenta,
        int $idPiezaStock,
        int $idVentaDetalle,
        int $idFormaPago,
        string $montoReferencia,
        string $motivo,
        string $modo
    ): array {
        if ($idVenta <= 0 || $idVentaDetalle <= 0 || $idPiezaStock <= 0) {
            throw new InvalidArgumentException('Datos incompletos para reembolso con ticket.');
        }

        $db = $this->getDb();
        $db->beginTransaction();
        try {
            auth_mysql_set_audit_vars($db, $idUsuario);

            if ($this->ventaTieneFacturaEmitida($db, $idVenta)) {
                throw new InvalidArgumentException('La venta tiene factura emitida; no se puede reembolsar aqui.');
            }

            $dup = $db->prepare('SELECT 1 FROM devoluciones WHERE id_venta_FK = :v AND id_pieza_stock_FK = :p LIMIT 1');
            $dup->bindValue(':v', $idVenta, PDO::PARAM_INT);
            $dup->bindValue(':p', $idPiezaStock, PDO::PARAM_INT);
            $dup->execute();
            if ($dup->fetchColumn()) {
                throw new InvalidArgumentException('Esta pieza ya consta como devuelta en esa venta.');
            }

            $stV = $db->prepare('SELECT * FROM ventas WHERE id_venta = :id FOR UPDATE');
            $stV->bindValue(':id', $idVenta, PDO::PARAM_INT);
            $stV->execute();
            $venta = $stV->fetch(PDO::FETCH_ASSOC);
            if (!$venta || ($venta['estado'] ?? '') !== 'completada') {
                throw new InvalidArgumentException('Solo ventas completadas pueden reembolsarse.');
            }

            $totalAntes = (float) ($venta['total'] ?? 0);
            $sumPagosAntes = $this->sumaPagosVenta($db, $idVenta);
            $desfasePagosAntes = abs($sumPagosAntes - $totalAntes);
            if ($desfasePagosAntes > 1.00) {
                throw new InvalidArgumentException('La venta #' . $idVenta . ' no tiene pagos cuadrados con el total (desfase $' . number_format($desfasePagosAntes, 2) . ').');
            }

            $stL = $db->prepare(
                "SELECT vd.* FROM venta_detalle vd
                 WHERE vd.id_venta_FK = :v AND vd.id_pieza_stock_FK = :p
                   AND COALESCE(vd.anulada, 0) = 0 AND vd.tipo_linea = 'joya'
                 LIMIT 1 FOR UPDATE"
            );
            $stL->bindValue(':v', $idVenta, PDO::PARAM_INT);
            $stL->bindValue(':p', $idPiezaStock, PDO::PARAM_INT);
            $stL->execute();
            $linea = $stL->fetch(PDO::FETCH_ASSOC);
            if (!$linea) {
                throw new InvalidArgumentException('Linea de joya no disponible para reembolso en venta #' . $idVenta . '.');
            }

            $stPs = $db->prepare('SELECT id_pieza_stock, estado, activo FROM piezas_stock WHERE id_pieza_stock = :id FOR UPDATE');
            $stPs->bindValue(':id', $idPiezaStock, PDO::PARAM_INT);
            $stPs->execute();
            $ps = $stPs->fetch(PDO::FETCH_ASSOC);
            if (!$ps || (int) ($ps['activo'] ?? 0) !== 1 || ($ps['estado'] ?? '') !== 'vendida') {
                throw new InvalidArgumentException('Pieza #' . $idPiezaStock . ' no esta vendida.');
            }

            $updL = $db->prepare('UPDATE venta_detalle SET anulada = 1 WHERE id_venta_detalle = :id');
            $updL->bindValue(':id', (int) $linea['id_venta_detalle'], PDO::PARAM_INT);
            $updL->execute();

            $ventasModel = new Ventas();
            $stD = $db->prepare(
                'SELECT * FROM venta_detalle WHERE id_venta_FK = :v AND COALESCE(anulada, 0) = 0 ORDER BY id_venta_detalle ASC'
            );
            $stD->bindValue(':v', $idVenta, PDO::PARAM_INT);
            $stD->execute();
            $detallesActivos = $stD->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $rec = $this->anularLineaYRecalcularVentaOrigen($db, $ventasModel, $venta, $linea, $detallesActivos, $idVenta);
            $tot = $rec['tot_despues'];
            $nuevoTotal = $rec['nuevo_total'];
            $montoCreditoStr = $rec['monto_linea'];
            $canjeAplicadoVenta = (float) ($rec['canje_aplicado'] ?? 0);
            // Si la venta original recibió canje, el reembolso en efectivo debe limitarse
            // a la parte que fue realmente pagada en la forma de pago solicitada (monto_ajuste_venta),
            // no al valor íntegro de la línea (que puede incluir parte cubierta por canje).
            $montoAjusteVenta = (string) ($rec['monto_ajuste_venta'] ?? $montoCreditoStr);
            $montoPagoNegStr = $canjeAplicadoVenta > 0.009 ? $montoAjusteVenta : $montoCreditoStr;

            $nuevoEstado = $rec['nuevo_estado'];
            $updV = $db->prepare(
                'UPDATE ventas SET total = :total, impuesto_monto = :imp, estado = :estado WHERE id_venta = :id'
            );
            $updV->bindValue(':total', $this->normalizarDecimal($nuevoTotal), PDO::PARAM_STR);
            $updV->bindValue(':imp', $this->normalizarDecimal((float) $tot['impuesto_monto']), PDO::PARAM_STR);
            $updV->bindValue(':estado', $nuevoEstado, PDO::PARAM_STR);
            $updV->bindValue(':id', $idVenta, PDO::PARAM_INT);
            $updV->execute();

            if ((float) $montoPagoNegStr > 0.009) {
                $insP = $db->prepare(
                    'INSERT INTO venta_pagos (id_venta_FK, id_forma_pago_FK, monto) VALUES (:v, :fp, :monto)'
                );
                $negativo = $this->normalizarDecimal(-(float) $montoPagoNegStr);
                $insP->bindValue(':v', $idVenta, PDO::PARAM_INT);
                $insP->bindValue(':fp', $idFormaPago, PDO::PARAM_INT);
                $insP->bindValue(':monto', $negativo, PDO::PARAM_STR);
                $insP->execute();
            }

            $this->verificarCuadrePagosTrasDevolucion($db, $idVenta, $nuevoTotal, $canjeAplicadoVenta, 'reembolso');

            $updPs = $db->prepare(
                "UPDATE piezas_stock SET estado = 'disponible'
                 WHERE id_pieza_stock = :id AND estado = 'vendida' AND activo = 1"
            );
            $updPs->bindValue(':id', $idPiezaStock, PDO::PARAM_INT);
            $updPs->execute();
            if ($updPs->rowCount() !== 1) {
                throw new RuntimeException('No se pudo reingresar la pieza #' . $idPiezaStock . ' al inventario.');
            }

            $motivoIns = $motivo !== '' ? $motivo : 'Reembolso ' . $modo;
            $insD = $db->prepare(
                "INSERT INTO devoluciones
                    (id_venta_FK, tipo_origen, id_pieza_stock_FK, id_venta_detalle_FK,
                     motivo, monto_reembolso, id_forma_pago_FK, id_empleado_FK)
                 VALUES (:v, 'venta', :ps, :vd, :motivo, :mreemb, NULL, :emp)"
            );
            $insD->bindValue(':v', $idVenta, PDO::PARAM_INT);
            $insD->bindValue(':ps', $idPiezaStock, PDO::PARAM_INT);
            $insD->bindValue(':vd', (int) $linea['id_venta_detalle'], PDO::PARAM_INT);
            $insD->bindValue(':motivo', $motivoIns, PDO::PARAM_STR);
            $insD->bindValue(':mreemb', $montoCreditoStr, PDO::PARAM_STR);
            $insD->bindValue(':emp', $idEmpleado, PDO::PARAM_INT);
            $insD->execute();
            $idDev = (int) $db->lastInsertId();

            $db->commit();

            return [
                'id_devolucion' => $idDev,
                'id_venta_FK' => $idVenta,
                'id_pieza_stock_FK' => $idPiezaStock,
                'monto_reembolso' => $montoCreditoStr,
                'id_forma_pago_FK' => $idFormaPago,
                'modo' => $modo,
                'afecta_caja' => true,
                'tipo_origen' => 'venta',
            ];
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }
}
