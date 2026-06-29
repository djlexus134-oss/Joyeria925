<?php

require_once __DIR__ . '/../../sistema.class.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/apartado_cambio.php';
require_once __DIR__ . '/ventas.php';

/**
 * Alta de apartados (multilinea) y abonos (cobro en tienda).
 */
class ApartadoGestion extends Sistema
{
    public const FORMA_PAGO_CREDITO_CLIENTE_LABEL = 'Credito a favor cliente';

    public static function fechaVencimientoUnMesDesdeHoy(): string
    {
        return joyeria_add_months_ymd(1);
    }

    private function normalizarDecimal($valor, int $decimales = 2): string
    {
        $numero = (float) $valor;
        $epsilon = pow(10, -($decimales + 3));
        $ajustado = $numero + ($numero >= 0 ? $epsilon : -$epsilon);

        return number_format(round($ajustado, $decimales), $decimales, '.', '');
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
     * @return list<array{id_forma_pago: int, forma_pago: string}>
     */
    public function formasPagoAbono(PDO $db): array
    {
        $st = $db->prepare(
            'SELECT id_forma_pago, forma_pago FROM forma_pago WHERE activo = 1 AND forma_pago <> :ex ORDER BY forma_pago ASC'
        );
        $st->bindValue(':ex', ApartadoCambio::FORMA_PAGO_CREDITO_LABEL, PDO::PARAM_STR);
        $st->execute();

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function verificarFormaPagoAbono(PDO $db, int $idFormaPago): void
    {
        $st = $db->prepare(
            'SELECT 1 FROM forma_pago WHERE id_forma_pago = :id AND activo = 1 AND forma_pago <> :ex LIMIT 1'
        );
        $st->bindValue(':id', $idFormaPago, PDO::PARAM_INT);
        $st->bindValue(':ex', ApartadoCambio::FORMA_PAGO_CREDITO_LABEL, PDO::PARAM_STR);
        $st->execute();
        if (!$st->fetchColumn()) {
            throw new InvalidArgumentException('La forma de pago no es valida para abonos en tienda.');
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listarApartados(int $limite = 150, ?string $estado = null, ?int $idCliente = null): array
    {
        $lim = (int) max(1, min(500, $limite));
        $allowedEstados = ['activo', 'liquidado', 'cancelado', 'vencido', 'reemplazado'];
        $estadoF = $estado !== null && $estado !== '' && in_array($estado, $allowedEstados, true) ? $estado : null;
        $filtroCliente = $idCliente !== null && $idCliente > 0;

        $sql = "SELECT a.id_apartado,
                       a.id_cliente_FK,
                       a.estado,
                       a.fecha_apartado,
                       a.fecha_vencimiento,
                       a.total_apartado,
                       a.saldo_pendiente,
                       a.impuesto_monto,
                       CONCAT(u.nombre, ' ', u.primer_apellido, COALESCE(CONCAT(' ', u.segundo_apellido), '')) AS cliente_nombre,
                       (SELECT COUNT(*) FROM apartado_detalle adn WHERE adn.id_apartado_FK = a.id_apartado) AS lineas_count,
                       (SELECT ps.codigo_barras
                        FROM apartado_detalle ad
                        INNER JOIN piezas_stock ps ON ps.id_pieza_stock = ad.id_pieza_stock_FK
                        WHERE ad.id_apartado_FK = a.id_apartado
                        ORDER BY ad.id_apartado_detalle ASC
                        LIMIT 1) AS codigo_pieza
                FROM apartados a
                INNER JOIN clientes c ON c.id_cliente = a.id_cliente_FK
                INNER JOIN usuarios u ON u.id_usuario = c.id_usuario_FK
                WHERE 1=1";
        if ($estadoF !== null) {
            $sql .= ' AND a.estado = :estado';
        }
        if ($filtroCliente) {
            $sql .= ' AND a.id_cliente_FK = :id_cliente';
        }
        $sql .= ' ORDER BY a.id_apartado DESC LIMIT ' . $lim;

        $st = $this->getDb()->prepare($sql);
        if ($estadoF !== null) {
            $st->bindValue(':estado', $estadoF, PDO::PARAM_STR);
        }
        if ($filtroCliente) {
            $st->bindValue(':id_cliente', $idCliente, PDO::PARAM_INT);
        }
        $st->execute();

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function obtenerIdTiendaPorApartado(int $idApartado): ?int
    {
        if ($idApartado <= 0) {
            return null;
        }
        $st = $this->getDb()->prepare(
            "SELECT p.id_tienda_FK
             FROM apartado_detalle ad
             INNER JOIN piezas_stock ps ON ps.id_pieza_stock = ad.id_pieza_stock_FK
             INNER JOIN piezas p ON p.id_pieza = ps.id_pieza_FK
             WHERE ad.id_apartado_FK = :id
             ORDER BY ad.id_apartado_detalle ASC
             LIMIT 1"
        );
        $st->bindValue(':id', $idApartado, PDO::PARAM_INT);
        $st->execute();
        $v = $st->fetchColumn();
        if ($v === false || $v === null) {
            return null;
        }
        $tid = (int) $v;

        return $tid > 0 ? $tid : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function leerApartadoCompleto(int $idApartado): array
    {
        if ($idApartado <= 0) {
            throw new InvalidArgumentException('Apartado invalido.');
        }

        $db = $this->getDb();
        $st = $db->prepare(
            "SELECT a.*,
                    CONCAT(u.nombre, ' ', u.primer_apellido, COALESCE(CONCAT(' ', u.segundo_apellido), '')) AS cliente_nombre
             FROM apartados a
             INNER JOIN clientes c ON c.id_cliente = a.id_cliente_FK
             INNER JOIN usuarios u ON u.id_usuario = c.id_usuario_FK
             WHERE a.id_apartado = :id
             LIMIT 1"
        );
        $st->bindValue(':id', $idApartado, PDO::PARAM_INT);
        $st->execute();
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new InvalidArgumentException('El apartado no existe.');
        }

        $stD = $db->prepare(
            "SELECT ad.*, ps.codigo_auxiliar, ps.codigo_barras, ps.estado AS estado_pieza, p.desc_pieza
             FROM apartado_detalle ad
             INNER JOIN piezas_stock ps ON ps.id_pieza_stock = ad.id_pieza_stock_FK
             INNER JOIN piezas p ON p.id_pieza = ps.id_pieza_FK
             WHERE ad.id_apartado_FK = :id
             ORDER BY ad.id_apartado_detalle ASC"
        );
        $stD->bindValue(':id', $idApartado, PDO::PARAM_INT);
        $stD->execute();
        $row['detalles'] = $stD->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $stP = $db->prepare(
            "SELECT ap.*, fp.forma_pago
             FROM apartado_pagos ap
             INNER JOIN forma_pago fp ON fp.id_forma_pago = ap.id_forma_pago_FK
             WHERE ap.id_apartado_FK = :id
             ORDER BY ap.id_pago ASC"
        );
        $stP->bindValue(':id', $idApartado, PDO::PARAM_INT);
        $stP->execute();
        $row['pagos'] = $stP->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $row['abonado'] = $this->sumaAbonosRegistrados($db, $idApartado);

        return $row;
    }

    public function resolverIdPiezaStockDisponible(PDO $db, string $codigo): int
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            throw new InvalidArgumentException('Codigo de pieza vacio.');
        }

        $st = $db->prepare(
            "SELECT ps.id_pieza_stock
             FROM piezas_stock ps
             WHERE ps.activo = 1
               AND ps.estado = 'disponible'
               AND (ps.codigo_barras = :c OR ps.codigo_auxiliar = :c2)
             ORDER BY ps.id_pieza_stock DESC
             LIMIT 1"
        );
        $st->bindValue(':c', $codigo, PDO::PARAM_STR);
        $st->bindValue(':c2', $codigo, PDO::PARAM_STR);
        $st->execute();
        $id = $st->fetchColumn();
        if ($id === false) {
            throw new InvalidArgumentException('No se encontro pieza disponible con ese codigo.');
        }

        return (int) $id;
    }

    /**
     * @return array<string, mixed>
     */
    public function vistaPreviaPiezaPorCodigo(string $codigo): array
    {
        $db = $this->getDb();
        $id = $this->resolverIdPiezaStockDisponible($db, $codigo);
        $st = $db->prepare(
            "SELECT ps.id_pieza_stock,
                    ps.codigo_barras,
                    ps.precio_venta,
                    ps.estado,
                    p.desc_pieza,
                    p.id_tienda_FK
             FROM piezas_stock ps
             INNER JOIN piezas p ON p.id_pieza = ps.id_pieza_FK
             WHERE ps.id_pieza_stock = :id
             LIMIT 1"
        );
        $st->bindValue(':id', $id, PDO::PARAM_INT);
        $st->execute();
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new InvalidArgumentException('Pieza no encontrada.');
        }

        return $row;
    }

    private function sumaAbonosRegistrados(PDO $db, int $idApartado): string
    {
        $st = $db->prepare(
            "SELECT COALESCE(SUM(monto), 0)
             FROM apartado_pagos
             WHERE id_apartado_FK = :a
               AND estado = 'registrado'"
        );
        $st->bindValue(':a', $idApartado, PDO::PARAM_INT);
        $st->execute();

        return $this->normalizarDecimal($st->fetchColumn());
    }

    /**
     * Resuelve el id de la forma de pago sintetica que representa
     * "Credito a favor del cliente" (monedero) en venta_pagos / apartado_pagos.
     */
    public function obtenerIdFormaPagoCreditoCliente(PDO $db): int
    {
        $st = $db->prepare(
            'SELECT id_forma_pago FROM forma_pago WHERE forma_pago = :fp AND activo = 1 LIMIT 1'
        );
        $st->bindValue(':fp', self::FORMA_PAGO_CREDITO_CLIENTE_LABEL, PDO::PARAM_STR);
        $st->execute();
        $id = $st->fetchColumn();
        if ($id === false) {
            throw new RuntimeException(
                'Falta la forma de pago "' . self::FORMA_PAGO_CREDITO_CLIENTE_LABEL
                    . '". Ejecuta la migracion SQL del monedero del cliente.'
            );
        }

        return (int) $id;
    }

    /**
     * Suma del monedero disponible para un cliente (todas las filas en estado disponible).
     */
    public function totalCreditoDisponibleCliente(int $idCliente): string
    {
        if ($idCliente <= 0) {
            return '0.00';
        }
        $st = $this->getDb()->prepare(
            "SELECT COALESCE(SUM(monto_disponible), 0)
             FROM cliente_creditos
             WHERE id_cliente_FK = :c AND estado = 'disponible'"
        );
        $st->bindValue(':c', $idCliente, PDO::PARAM_INT);
        $st->execute();

        return $this->normalizarDecimal($st->fetchColumn());
    }

    /**
     * Aplica el monedero del cliente para cubrir un monto (FIFO sobre creditos
     * en estado 'disponible'). Descuenta monto_disponible de cliente_creditos,
     * marca como 'consumido' cuando llega a 0 y registra cada consumo en
     * cliente_credito_consumos. Debe ejecutarse dentro de una transaccion del
     * caller. Devuelve los detalles del consumo realizado.
     *
     * @param string $tipoUso        Uno de: abono_apartado, venta_pos, alta_apartado, ajuste.
     * @param int    $idApartado     Opcional, id_apartado relacionado al consumo.
     * @param int    $idVenta        Opcional, id_venta relacionado al consumo.
     * @param int    $idApartadoPago Opcional, id_pago de apartado_pagos generado.
     * @return array{monto_aplicado: string, consumos: list<array<string, mixed>>}
     */
    public function aplicarConsumoCreditoCliente(
        PDO $db,
        int $idCliente,
        float $monto,
        string $tipoUso,
        ?int $idApartado,
        ?int $idVenta,
        ?int $idApartadoPago,
        ?int $idEmpleado,
        int $idUsuario,
        ?string $observaciones = null
    ): array {
        if ($idCliente <= 0) {
            throw new InvalidArgumentException('Para usar credito a favor se requiere cliente identificado.');
        }
        if ($monto <= 0) {
            throw new InvalidArgumentException('El monto a aplicar del monedero debe ser mayor a cero.');
        }
        $tiposValidos = ['abono_apartado', 'venta_pos', 'alta_apartado', 'ajuste', 'venta_online'];
        if (!in_array($tipoUso, $tiposValidos, true)) {
            throw new InvalidArgumentException('Tipo de uso de credito invalido.');
        }
        if ($idUsuario <= 0) {
            throw new InvalidArgumentException('Falta usuario para registrar consumo de credito.');
        }
        if (!$db->inTransaction()) {
            throw new RuntimeException('aplicarConsumoCreditoCliente requiere transaccion abierta.');
        }

        $stCred = $db->prepare(
            "SELECT id_credito, monto_disponible
             FROM cliente_creditos
             WHERE id_cliente_FK = :c AND estado = 'disponible' AND monto_disponible > 0
             ORDER BY id_credito ASC
             FOR UPDATE"
        );
        $stCred->bindValue(':c', $idCliente, PDO::PARAM_INT);
        $stCred->execute();
        $creditos = $stCred->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $totalDisponible = 0.0;
        foreach ($creditos as $cr) {
            $totalDisponible += (float) ($cr['monto_disponible'] ?? 0);
        }
        if ($monto - $totalDisponible > 0.02) {
            throw new InvalidArgumentException(
                'El monto a aplicar (' . $this->normalizarDecimal($monto)
                    . ') excede el credito disponible del cliente (' . $this->normalizarDecimal($totalDisponible) . ').'
            );
        }

        $updCred = $db->prepare(
            "UPDATE cliente_creditos
             SET monto_disponible = :nd,
                 estado = CASE WHEN :nd2 <= 0 THEN 'consumido' ELSE estado END
             WHERE id_credito = :id"
        );

        $insCon = $db->prepare(
            "INSERT INTO cliente_credito_consumos
                (id_credito_FK, monto, tipo_uso, id_apartado_FK, id_venta_FK, id_apartado_pago_FK, id_empleado_FK, id_usuario_FK, observaciones)
             VALUES
                (:cr, :m, :tu, :ap, :ve, :app, :emp, :usr, :obs)"
        );

        $restante = (float) $this->normalizarDecimal($monto);
        $consumos = [];
        foreach ($creditos as $cr) {
            if ($restante <= 0.0001) {
                break;
            }
            $idCredito = (int) $cr['id_credito'];
            $disponible = (float) $cr['monto_disponible'];
            $aplicar = min($restante, $disponible);
            $aplicarStr = $this->normalizarDecimal($aplicar);
            $nuevoDisp = $this->normalizarDecimal($disponible - (float) $aplicarStr);
            $nuevoDispF = (float) $nuevoDisp;

            $updCred->bindValue(':nd', $nuevoDisp, PDO::PARAM_STR);
            $updCred->bindValue(':nd2', $nuevoDispF, PDO::PARAM_STR);
            $updCred->bindValue(':id', $idCredito, PDO::PARAM_INT);
            $updCred->execute();

            $insCon->bindValue(':cr', $idCredito, PDO::PARAM_INT);
            $insCon->bindValue(':m', $aplicarStr, PDO::PARAM_STR);
            $insCon->bindValue(':tu', $tipoUso, PDO::PARAM_STR);
            $insCon->bindValue(':ap', $idApartado, $idApartado === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $insCon->bindValue(':ve', $idVenta, $idVenta === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $insCon->bindValue(':app', $idApartadoPago, $idApartadoPago === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $insCon->bindValue(':emp', $idEmpleado, ($idEmpleado === null || $idEmpleado <= 0) ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $insCon->bindValue(':usr', $idUsuario, PDO::PARAM_INT);
            if ($observaciones !== null && $observaciones !== '') {
                $insCon->bindValue(':obs', $observaciones, PDO::PARAM_STR);
            } else {
                $insCon->bindValue(':obs', null, PDO::PARAM_NULL);
            }
            $insCon->execute();

            $consumos[] = [
                'id_credito' => $idCredito,
                'monto' => $aplicarStr,
                'monto_disponible_resultante' => $nuevoDisp,
            ];

            $restante = (float) $this->normalizarDecimal($restante - (float) $aplicarStr);
        }

        if ($restante > 0.02) {
            throw new RuntimeException('No se pudo aplicar el total del credito (residuo inesperado).');
        }

        return [
            'monto_aplicado' => $this->normalizarDecimal((float) $monto - $restante),
            'consumos' => $consumos,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listarConsumosCreditoCliente(int $idCliente, int $limite = 100): array
    {
        if ($idCliente <= 0) {
            return [];
        }
        $lim = (int) max(1, min(500, $limite));
        $sql = "SELECT ccu.id_consumo,
                       ccu.id_credito_FK,
                       ccu.monto,
                       ccu.tipo_uso,
                       ccu.id_apartado_FK,
                       ccu.id_venta_FK,
                       ccu.id_apartado_pago_FK,
                       ccu.fecha_registro,
                       ccu.observaciones,
                       cc.id_apartado_origen_FK,
                       cc.tipo AS credito_tipo
                FROM cliente_credito_consumos ccu
                INNER JOIN cliente_creditos cc ON cc.id_credito = ccu.id_credito_FK
                WHERE cc.id_cliente_FK = :c
                ORDER BY ccu.id_consumo DESC
                LIMIT $lim";

        $st = $this->getDb()->prepare($sql);
        $st->bindValue(':c', $idCliente, PDO::PARAM_INT);
        $st->execute();

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Precio pactado por pieza tras descuento del cliente (misma regla que POS).
     */
    public function precioApartadoDesdePrecioVenta(float $precioVenta, int $idCliente): string
    {
        if ($precioVenta <= 0) {
            return '0.00';
        }
        $ventas = new Ventas();
        $pct = $ventas->resolverDescuentoPorcentajeLinea('joya', $idCliente > 0 ? $idCliente : null);
        $precioFinal = max(0.0, $precioVenta * (1.0 - ($pct / 100.0)));

        return $this->normalizarDecimal($precioFinal);
    }

    /**
     * Calcula totales de apartado con descuento del cliente e impuesto (fuente de verdad servidor).
     *
     * @param list<array<string, mixed>> $rawLineas codigo_pieza y/o id_pieza_stock_FK
     *
     * @return array<string, mixed>
     */
    public function calcularTotalesApartado(int $idCliente, int $idImpuesto, array $rawLineas): array
    {
        if ($idCliente <= 0) {
            throw new InvalidArgumentException('Selecciona un cliente valido.');
        }
        if ($idImpuesto <= 0) {
            throw new InvalidArgumentException('Selecciona un impuesto valido.');
        }
        if ($rawLineas === []) {
            throw new InvalidArgumentException('Indica al menos una pieza.');
        }

        $db = $this->getDb();
        $ventas = new Ventas();
        $this->verificarClienteActivo($db, $idCliente);

        $impuesto = $ventas->obtenerImpuestoPorId($idImpuesto);
        if (!$impuesto) {
            throw new InvalidArgumentException('El impuesto seleccionado no existe.');
        }

        $descPct = $ventas->resolverDescuentoPorcentajeLinea('joya', $idCliente);
        $descCliente = $ventas->obtenerDescuentoClientePorcentaje($idCliente);
        $descGeneral = $ventas->obtenerDescuentoGeneralMostrador();

        $lineasOut = [];
        $subtotalLista = 0.0;
        $subtotalApartado = 0.0;
        $vistos = [];

        foreach ($rawLineas as $idx => $ln) {
            if (!is_array($ln)) {
                throw new InvalidArgumentException('Linea de apartado invalida en indice ' . $idx);
            }
            $idP = (int) ($ln['id_pieza_stock_FK'] ?? 0);
            $cod = isset($ln['codigo_pieza']) ? trim((string) $ln['codigo_pieza']) : '';
            if ($idP <= 0 && $cod !== '') {
                $idP = $this->resolverIdPiezaStockDisponible($db, $cod);
            }
            if ($idP <= 0) {
                throw new InvalidArgumentException('Linea ' . ($idx + 1) . ': falta pieza valida.');
            }
            if (isset($vistos[$idP])) {
                throw new InvalidArgumentException('No puedes repetir la misma pieza de stock en un apartado.');
            }
            $vistos[$idP] = true;

            $stPs = $db->prepare(
                'SELECT ps.precio_venta, ps.codigo_barras, ps.codigo_auxiliar
                 FROM piezas_stock ps
                 WHERE ps.id_pieza_stock = :id AND ps.activo = 1 LIMIT 1'
            );
            $stPs->bindValue(':id', $idP, PDO::PARAM_INT);
            $stPs->execute();
            $rowPs = $stPs->fetch(PDO::FETCH_ASSOC);
            if (!$rowPs) {
                throw new InvalidArgumentException('Pieza stock ' . $idP . ' no encontrada.');
            }

            $precioVentaStr = $this->normalizarDecimal($rowPs['precio_venta'] ?? 0);
            $precioVenta = (float) $precioVentaStr;
            if ($precioVenta <= 0) {
                throw new InvalidArgumentException('Linea ' . ($idx + 1) . ': precio de venta invalido.');
            }

            $precioApartadoStr = $this->precioApartadoDesdePrecioVenta($precioVenta, $idCliente);
            $subtotalLista += $precioVenta;
            $subtotalApartado += (float) $precioApartadoStr;

            $codigoVisible = $cod !== '' ? $cod : (string) ($rowPs['codigo_barras'] ?? $rowPs['codigo_auxiliar'] ?? '');
            $lineasOut[] = [
                'id_pieza_stock_FK' => $idP,
                'codigo_pieza' => $codigoVisible,
                'precio_venta' => $precioVentaStr,
                'precio_apartado' => $precioApartadoStr,
            ];
        }

        $descMonto = max(0.0, $subtotalLista - $subtotalApartado);
        $impPct = isset($impuesto['porcentaje']) ? (float) $impuesto['porcentaje'] : 0.0;
        $impMonto = $subtotalApartado * ($impPct / 100.0);
        $total = $subtotalApartado + $impMonto;

        return [
            'subtotal_lista' => $this->normalizarDecimal($subtotalLista),
            'subtotal_apartado' => $this->normalizarDecimal($subtotalApartado),
            'descuento_porcentaje' => $this->normalizarDecimal($descPct),
            'descuento_monto' => $this->normalizarDecimal($descMonto),
            'descuento_general_mostrador' => $this->normalizarDecimal($descGeneral),
            'descuento_cliente_especial' => $descCliente !== null
                ? $this->normalizarDecimal($descCliente)
                : null,
            'tiene_descuento_especial' => $descCliente !== null,
            'impuesto_porcentaje' => $this->normalizarDecimal($impPct),
            'impuesto_monto' => $this->normalizarDecimal($impMonto),
            'total' => $this->normalizarDecimal($total),
            'lineas' => $lineasOut,
        ];
    }

    /**
     * Aplica descuento del cliente a lineas ya normalizadas usando precio_venta de BD.
     *
     * @param list<array{id_pieza_stock_FK: int, precio_str: string}> $lineasNorm
     * @param array<int, array<string, mixed>>                       $preciosVentaPorId id_pieza_stock => row bloqueada
     *
     * @return list<array{id_pieza_stock_FK: int, precio_str: string, precio_lista_str: string}>
     */
    private function aplicarDescuentoClienteALineasApartado(int $idCliente, array $lineasNorm, array $preciosVentaPorId): array
    {
        $resultado = [];
        foreach ($lineasNorm as $ln) {
            $idP = (int) ($ln['id_pieza_stock_FK'] ?? 0);
            $row = $preciosVentaPorId[$idP] ?? null;
            if (!is_array($row)) {
                throw new InvalidArgumentException('No se encontro precio de venta para la pieza ' . $idP . '.');
            }
            $precioListaStr = $this->normalizarDecimal($row['precio_venta'] ?? 0);
            $precioLista = (float) $precioListaStr;
            if ($precioLista <= 0) {
                throw new InvalidArgumentException('Precio de venta invalido para la pieza ' . $idP . '.');
            }
            $precioConDescStr = $this->precioApartadoDesdePrecioVenta($precioLista, $idCliente);
            $resultado[] = [
                'id_pieza_stock_FK' => $idP,
                'precio_str' => $precioConDescStr,
                'precio_lista_str' => $precioListaStr,
            ];
        }

        return $resultado;
    }

    /**
     * Normaliza entrada a lista de lineas { id_pieza_stock_FK, precio_apartado_str }.
     *
     * @return list<array{id_pieza_stock_FK: int, precio_str: string}>
     */
    private function normalizarLineasApartado(PDO $db, array $data): array
    {
        $rawLineas = $data['lineas'] ?? null;
        $lineasIn = [];
        if (is_array($rawLineas) && $rawLineas !== []) {
            $lineasIn = $rawLineas;
        } else {
            $idPieza = (int) ($data['id_pieza_stock_FK'] ?? 0);
            $codigo = isset($data['codigo_pieza']) ? trim((string) $data['codigo_pieza']) : '';
            if ($idPieza <= 0 && $codigo !== '') {
                $idPieza = $this->resolverIdPiezaStockDisponible($db, $codigo);
            }
            if ($idPieza > 0) {
                $lineasIn = [
                    [
                        'id_pieza_stock_FK' => $idPieza,
                        'codigo_pieza' => $codigo,
                        'precio_apartado' => $data['precio_apartado'] ?? null,
                    ],
                ];
            }
        }

        if ($lineasIn === []) {
            throw new InvalidArgumentException('Indica al menos una pieza (lineas[] o codigo_pieza / id_pieza_stock_FK).');
        }

        $vistos = [];
        $resultado = [];
        foreach ($lineasIn as $idx => $ln) {
            if (!is_array($ln)) {
                throw new InvalidArgumentException('Linea de apartado invalida en indice ' . $idx);
            }
            $idP = (int) ($ln['id_pieza_stock_FK'] ?? 0);
            $cod = isset($ln['codigo_pieza']) ? trim((string) $ln['codigo_pieza']) : '';
            if ($idP <= 0 && $cod !== '') {
                $idP = $this->resolverIdPiezaStockDisponible($db, $cod);
            }
            if ($idP <= 0) {
                throw new InvalidArgumentException('Linea ' . ($idx + 1) . ': falta pieza valida.');
            }
            if (isset($vistos[$idP])) {
                throw new InvalidArgumentException('No puedes repetir la misma pieza de stock en un apartado.');
            }
            $vistos[$idP] = true;

            $stPs = $db->prepare(
                'SELECT ps.precio_venta FROM piezas_stock ps WHERE ps.id_pieza_stock = :id AND ps.activo = 1 LIMIT 1'
            );
            $stPs->bindValue(':id', $idP, PDO::PARAM_INT);
            $stPs->execute();
            $pv = $stPs->fetchColumn();
            if ($pv === false) {
                throw new InvalidArgumentException('Pieza stock ' . $idP . ' no encontrada.');
            }
            $precioVentaStr = $this->normalizarDecimal($pv);
            $precioStr = isset($ln['precio_apartado']) && $ln['precio_apartado'] !== '' && $ln['precio_apartado'] !== null
                ? $this->normalizarDecimal($ln['precio_apartado'])
                : $precioVentaStr;
            if ((float) $precioStr <= 0) {
                throw new InvalidArgumentException('Linea ' . ($idx + 1) . ': precio invalido.');
            }
            $resultado[] = ['id_pieza_stock_FK' => $idP, 'precio_str' => $precioStr];
        }

        return $resultado;
    }

    /**
     * @param array<string, mixed> $data
     * @return array{id_apartado: int, saldo_pendiente: string, total_apartado: string, lineas: int}
     */
    public function crearApartado(array $data): array
    {
        $idCliente = (int) ($data['id_cliente_FK'] ?? 0);
        $idEmpleado = (int) ($data['id_empleado_FK'] ?? 0);
        $idUsuario = (int) ($data['id_usuario_FK'] ?? 0);
        $fechaVenc = trim((string) ($data['fecha_vencimiento'] ?? ''));

        if ($idCliente <= 0 || $idEmpleado <= 0 || $idUsuario <= 0) {
            throw new InvalidArgumentException('Cliente, empleado y usuario son obligatorios.');
        }

        $db = $this->getDb();
        $this->verificarClienteActivo($db, $idCliente);
        $this->verificarEmpleadoActivo($db, $idEmpleado);

        if ($fechaVenc === '') {
            $fechaVenc = self::fechaVencimientoUnMesDesdeHoy();
        }

        $lineasNorm = $this->normalizarLineasApartado($db, $data);

        $db->beginTransaction();
        try {
            auth_mysql_set_audit_vars($db, $idUsuario);

            $idsPiezas = array_column($lineasNorm, 'id_pieza_stock_FK');
            $placeholders = implode(',', array_fill(0, count($idsPiezas), '?'));
            $stLock = $db->prepare(
                "SELECT ps.id_pieza_stock, ps.estado, ps.activo, ps.precio_venta, p.id_tienda_FK AS id_tienda_pieza
                 FROM piezas_stock ps
                 INNER JOIN piezas p ON p.id_pieza = ps.id_pieza_FK
                 WHERE ps.id_pieza_stock IN ($placeholders)
                 ORDER BY ps.id_pieza_stock ASC
                 FOR UPDATE"
            );
            foreach ($idsPiezas as $k => $pid) {
                $stLock->bindValue($k + 1, $pid, PDO::PARAM_INT);
            }
            $stLock->execute();
            $rowsPs = $stLock->fetchAll(PDO::FETCH_ASSOC) ?: [];
            if (count($rowsPs) !== count($idsPiezas)) {
                throw new InvalidArgumentException('No se pudieron bloquear todas las piezas; verifica codigos.');
            }

            $tiendaRef = null;
            $preciosVentaPorId = [];
            foreach ($rowsPs as $rps) {
                if ((int) ($rps['activo'] ?? 0) !== 1 || ($rps['estado'] ?? '') !== 'disponible') {
                    throw new InvalidArgumentException('La pieza ' . (int) ($rps['id_pieza_stock'] ?? 0) . ' no esta disponible.');
                }
                $tid = (int) ($rps['id_tienda_pieza'] ?? 0);
                if ($tiendaRef === null) {
                    $tiendaRef = $tid;
                } elseif ($tid !== $tiendaRef) {
                    throw new InvalidArgumentException('Todas las piezas del apartado deben ser de la misma tienda.');
                }
                $preciosVentaPorId[(int) ($rps['id_pieza_stock'] ?? 0)] = $rps;
            }

            $lineasNorm = $this->aplicarDescuentoClienteALineasApartado($idCliente, $lineasNorm, $preciosVentaPorId);

            $idImpuesto = (int) ($data['id_impuesto_FK'] ?? 0);
            if ($idImpuesto <= 0) {
                $ventasTmp = new Ventas();
                $idImpuesto = (int) ($ventasTmp->obtenerIdImpuestoDefault() ?? 0);
            }
            if ($idImpuesto <= 0) {
                throw new InvalidArgumentException('Selecciona un impuesto valido.');
            }

            $rawCalc = [];
            foreach ($lineasNorm as $ln) {
                $rawCalc[] = ['id_pieza_stock_FK' => (int) $ln['id_pieza_stock_FK']];
            }
            $totalesCalc = $this->calcularTotalesApartado($idCliente, $idImpuesto, $rawCalc);
            $totalStr = (string) ($totalesCalc['subtotal_apartado'] ?? '0.00');
            $impuestoStr = (string) ($totalesCalc['impuesto_monto'] ?? '0.00');
            $total = (float) $totalStr;
            if ($total <= 0) {
                throw new InvalidArgumentException('El total del apartado debe ser mayor a cero.');
            }

            $abonoStr = isset($data['abono_monto']) && $data['abono_monto'] !== '' && $data['abono_monto'] !== null
                ? $this->normalizarDecimal($data['abono_monto'])
                : '0.00';
            $abono = (float) $abonoStr;
            if ($abono < 0) {
                throw new InvalidArgumentException('El abono inicial no puede ser negativo.');
            }

            $abonoCreditoStr = isset($data['abono_credito_monto']) && $data['abono_credito_monto'] !== '' && $data['abono_credito_monto'] !== null
                ? $this->normalizarDecimal($data['abono_credito_monto'])
                : '0.00';
            $abonoCredito = (float) $abonoCreditoStr;
            if ($abonoCredito < 0) {
                throw new InvalidArgumentException('El abono con credito no puede ser negativo.');
            }
            $abonoTotalInicial = $abono + $abonoCredito;
            if ($abonoTotalInicial - $total > 0.02) {
                throw new InvalidArgumentException('Los abonos iniciales (efectivo + credito) no pueden exceder el total del apartado.');
            }

            $idFormaAbono = isset($data['id_forma_pago_abono']) ? (int) $data['id_forma_pago_abono'] : 0;
            if ($abono > 0 && $idFormaAbono <= 0) {
                throw new InvalidArgumentException('Si registras abono inicial en efectivo, la forma de pago es obligatoria.');
            }
            if ($abono > 0) {
                $this->verificarFormaPagoAbono($db, $idFormaAbono);
            }

            $saldoStr = $this->normalizarDecimal($total - $abonoTotalInicial);
            $tienda = (int) $tiendaRef;

            $insA = $db->prepare(
                "INSERT INTO apartados
                    (id_cliente_FK, id_empleado_FK, fecha_vencimiento, total_apartado, saldo_pendiente, estado, impuesto_monto)
                 VALUES
                    (:cli, :emp, :fv, :tot, :saldo, 'activo', :imp)"
            );
            $insA->bindValue(':cli', $idCliente, PDO::PARAM_INT);
            $insA->bindValue(':emp', $idEmpleado, PDO::PARAM_INT);
            $insA->bindValue(':fv', $fechaVenc, PDO::PARAM_STR);
            $insA->bindValue(':tot', $totalStr, PDO::PARAM_STR);
            $insA->bindValue(':saldo', $saldoStr, PDO::PARAM_STR);
            $insA->bindValue(':imp', $impuestoStr, PDO::PARAM_STR);
            $insA->execute();
            $idApartado = (int) $db->lastInsertId();
            if ($idApartado <= 0) {
                throw new RuntimeException('No se pudo crear el apartado.');
            }

            $insD = $db->prepare(
                'INSERT INTO apartado_detalle (id_apartado_FK, id_pieza_stock_FK, precio_apartado) VALUES (:a, :p, :pr)'
            );
            $updPs = $db->prepare("UPDATE piezas_stock SET estado = 'apartada' WHERE id_pieza_stock = :id");
            $insMov = $db->prepare(
                "INSERT INTO movimientos_inventario
                    (id_pieza_stock_FK, tipo_movimiento, referencia, observaciones, id_usuario_FK, id_tienda_origen_FK, id_apartado_FK, tipo_referencia)
                 VALUES
                    (:ps, 'apartado', 'ALTA_APARTADO', NULL, :u, :t, :ap, 'apartado')"
            );

            foreach ($lineasNorm as $ln) {
                $idP = (int) $ln['id_pieza_stock_FK'];
                $pr = $ln['precio_str'];
                $insD->bindValue(':a', $idApartado, PDO::PARAM_INT);
                $insD->bindValue(':p', $idP, PDO::PARAM_INT);
                $insD->bindValue(':pr', $pr, PDO::PARAM_STR);
                $insD->execute();

                $updPs->bindValue(':id', $idP, PDO::PARAM_INT);
                $updPs->execute();

                $insMov->bindValue(':ps', $idP, PDO::PARAM_INT);
                $insMov->bindValue(':u', $idUsuario, PDO::PARAM_INT);
                $insMov->bindValue(':t', $tienda, PDO::PARAM_INT);
                $insMov->bindValue(':ap', $idApartado, PDO::PARAM_INT);
                $insMov->execute();
            }

            if ($abono > 0) {
                $insP = $db->prepare(
                    "INSERT INTO apartado_pagos
                        (id_apartado_FK, monto, id_forma_pago_FK, estado, referencia, id_usuario_FK, tipo_origen)
                     VALUES
                        (:a, :m, :fp, 'registrado', 'Abono inicial apartado', :u, 'cobro_tienda')"
                );
                $insP->bindValue(':a', $idApartado, PDO::PARAM_INT);
                $insP->bindValue(':m', $abonoStr, PDO::PARAM_STR);
                $insP->bindValue(':fp', $idFormaAbono, PDO::PARAM_INT);
                $insP->bindValue(':u', $idUsuario, PDO::PARAM_INT);
                $insP->execute();
            }

            $creditoConsumido = null;
            if ($abonoCredito > 0) {
                $idFormaCredito = $this->obtenerIdFormaPagoCreditoCliente($db);
                $insPC = $db->prepare(
                    "INSERT INTO apartado_pagos
                        (id_apartado_FK, monto, id_forma_pago_FK, estado, referencia, id_usuario_FK, tipo_origen)
                     VALUES
                        (:a, :m, :fp, 'registrado', 'Abono inicial apartado (credito cliente)', :u, 'credito_cliente')"
                );
                $insPC->bindValue(':a', $idApartado, PDO::PARAM_INT);
                $insPC->bindValue(':m', $abonoCreditoStr, PDO::PARAM_STR);
                $insPC->bindValue(':fp', $idFormaCredito, PDO::PARAM_INT);
                $insPC->bindValue(':u', $idUsuario, PDO::PARAM_INT);
                $insPC->execute();
                $idPagoCredito = (int) $db->lastInsertId();

                $creditoConsumido = $this->aplicarConsumoCreditoCliente(
                    $db,
                    $idCliente,
                    $abonoCredito,
                    'alta_apartado',
                    $idApartado,
                    null,
                    $idPagoCredito,
                    $idEmpleado > 0 ? $idEmpleado : null,
                    $idUsuario,
                    'Alta apartado #' . $idApartado . ' con monedero'
                );
            }

            $db->commit();

            return [
                'id_apartado' => $idApartado,
                'total_apartado' => $totalStr,
                'saldo_pendiente' => $saldoStr,
                'lineas' => count($lineasNorm),
                'abono_credito_aplicado' => $abonoCreditoStr,
                'credito_consumido' => $creditoConsumido,
            ];
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }



    /**
     * Tras liquidar un apartado (saldo cubierto), pasa a "vendida" cada pieza de stock
     * listada en apartado_detalle que siga en estado apartada.
     * Debe ejecutarse dentro de la misma transaccion que liquida el apartado.
     */
    public function marcarPiezasStockVendidasTrasLiquidacionApartado(PDO $db, int $idApartado): void
    {
        if ($idApartado <= 0) {
            return;
        }

        $stCnt = $db->prepare('SELECT COUNT(*) FROM apartado_detalle WHERE id_apartado_FK = :id');
        $stCnt->bindValue(':id', $idApartado, PDO::PARAM_INT);
        $stCnt->execute();
        $nDet = (int) $stCnt->fetchColumn();
        if ($nDet <= 0) {
            return;
        }

        $stLock = $db->prepare(
            "SELECT ps.id_pieza_stock, ps.estado, ps.activo
             FROM apartado_detalle ad
             INNER JOIN piezas_stock ps ON ps.id_pieza_stock = ad.id_pieza_stock_FK
             WHERE ad.id_apartado_FK = :id
             ORDER BY ad.id_apartado_detalle ASC
             FOR UPDATE"
        );
        $stLock->bindValue(':id', $idApartado, PDO::PARAM_INT);
        $stLock->execute();
        $rows = $stLock->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if (count($rows) !== $nDet) {
            throw new RuntimeException('Inconsistencia en lineas del apartado al actualizar inventario vendido.');
        }

        foreach ($rows as $row) {
            if ((int) ($row['activo'] ?? 0) !== 1) {
                throw new RuntimeException('Hay piezas inactivas en el apartado; no se puede liquidar inventario.');
            }
            $est = (string) ($row['estado'] ?? '');
            if ($est !== 'apartada') {
                throw new RuntimeException(
                    'La pieza #' . (int) ($row['id_pieza_stock'] ?? 0) . ' no esta en estado apartada (' . $est . '); no se puede liquidar automaticamente.'
                );
            }
        }

        $updPs = $db->prepare(
            "UPDATE piezas_stock ps
             INNER JOIN apartado_detalle ad ON ad.id_pieza_stock_FK = ps.id_pieza_stock
             SET ps.estado = 'vendida'
             WHERE ad.id_apartado_FK = :id
               AND ps.activo = 1
               AND ps.estado = 'apartada'"
        );
        $updPs->bindValue(':id', $idApartado, PDO::PARAM_INT);
        $updPs->execute();

        if ($updPs->rowCount() !== $nDet) {
            throw new RuntimeException('No se pudieron marcar como vendidas todas las piezas del apartado liquidado.');
        }
    }

    /**
     * Obtiene la tienda actual del apartado (tienda de la primera linea).
     */
    private function tiendaActualApartado(PDO $db, int $idApartado): ?int
    {
        $st = $db->prepare(
            "SELECT p.id_tienda_FK
             FROM apartado_detalle ad
             INNER JOIN piezas_stock ps ON ps.id_pieza_stock = ad.id_pieza_stock_FK
             INNER JOIN piezas p ON p.id_pieza = ps.id_pieza_FK
             WHERE ad.id_apartado_FK = :id
             ORDER BY ad.id_apartado_detalle ASC
             LIMIT 1"
        );
        $st->bindValue(':id', $idApartado, PDO::PARAM_INT);
        $st->execute();
        $v = $st->fetchColumn();
        if ($v === false || $v === null) {
            return null;
        }
        $tid = (int) $v;

        return $tid > 0 ? $tid : null;
    }

    private function insertarMovimientoApartado(
        PDO $db,
        int $idPiezaStock,
        string $tipoMovimiento,
        string $referencia,
        int $idUsuario,
        int $idTienda,
        int $idApartado,
        ?string $observaciones = null
    ): void {
        $insMov = $db->prepare(
            "INSERT INTO movimientos_inventario
                (id_pieza_stock_FK, tipo_movimiento, referencia, observaciones, id_usuario_FK, id_tienda_origen_FK, id_apartado_FK, tipo_referencia)
             VALUES
                (:ps, :tm, :ref, :obs, :u, :t, :ap, 'apartado')"
        );
        $insMov->bindValue(':ps', $idPiezaStock, PDO::PARAM_INT);
        $insMov->bindValue(':tm', $tipoMovimiento, PDO::PARAM_STR);
        $insMov->bindValue(':ref', $referencia, PDO::PARAM_STR);
        if ($observaciones !== null && $observaciones !== '') {
            $insMov->bindValue(':obs', $observaciones, PDO::PARAM_STR);
        } else {
            $insMov->bindValue(':obs', null, PDO::PARAM_NULL);
        }
        $insMov->bindValue(':u', $idUsuario, PDO::PARAM_INT);
        $insMov->bindValue(':t', $idTienda, PDO::PARAM_INT);
        $insMov->bindValue(':ap', $idApartado, PDO::PARAM_INT);
        $insMov->execute();
    }

    /**
     * Registra un lote en el monedero del cliente. Debe ejecutarse dentro de la transaccion del caller.
     *
     * @param string $tipo excedente_apartado | devolucion | ajuste
     */
    public function registrarCreditoClienteEntrada(
        PDO $db,
        int $idCliente,
        string $monto,
        string $tipo,
        int $idEmpleado,
        int $idUsuario,
        ?string $observaciones = null,
        ?int $idApartadoOrigen = null,
        ?int $idDevolucionOrigen = null,
        ?int $idVentaOrigen = null
    ): int {
        $tiposValidos = ['excedente_apartado', 'devolucion', 'ajuste'];
        if (!in_array($tipo, $tiposValidos, true)) {
            throw new InvalidArgumentException('Tipo de credito invalido.');
        }
        $montoStr = $this->normalizarDecimal($monto);
        if ((float) $montoStr <= 0) {
            throw new InvalidArgumentException('Monto de credito a registrar invalido.');
        }
        $this->verificarClienteActivo($db, $idCliente);
        $this->verificarEmpleadoActivo($db, $idEmpleado);

        $ins = $db->prepare(
            "INSERT INTO cliente_creditos
                (id_cliente_FK, monto, monto_disponible, tipo, estado,
                 id_apartado_origen_FK, id_devolucion_origen_FK, id_venta_origen_FK,
                 observaciones, id_empleado_FK, id_usuario_FK)
             VALUES
                (:cli, :m, :md, :tipo, 'disponible',
                 :ap, :dev, :venta, :obs, :emp, :usr)"
        );
        $ins->bindValue(':cli', $idCliente, PDO::PARAM_INT);
        $ins->bindValue(':m', $montoStr, PDO::PARAM_STR);
        $ins->bindValue(':md', $montoStr, PDO::PARAM_STR);
        $ins->bindValue(':tipo', $tipo, PDO::PARAM_STR);
        $ins->bindValue(':ap', $idApartadoOrigen, $idApartadoOrigen !== null && $idApartadoOrigen > 0 ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $ins->bindValue(':dev', $idDevolucionOrigen, $idDevolucionOrigen !== null && $idDevolucionOrigen > 0 ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $ins->bindValue(':venta', $idVentaOrigen, $idVentaOrigen !== null && $idVentaOrigen > 0 ? PDO::PARAM_INT : PDO::PARAM_NULL);
        if ($observaciones !== null && $observaciones !== '') {
            $ins->bindValue(':obs', $observaciones, PDO::PARAM_STR);
        } else {
            $ins->bindValue(':obs', null, PDO::PARAM_NULL);
        }
        $ins->bindValue(':emp', $idEmpleado, PDO::PARAM_INT);
        $ins->bindValue(':usr', $idUsuario, PDO::PARAM_INT);
        try {
            $ins->execute();
        } catch (Throwable $e) {
            if (strpos($e->getMessage(), 'id_devolucion_origen') !== false
                || strpos($e->getMessage(), 'id_venta_origen') !== false
                || strpos($e->getMessage(), 'Unknown column') !== false) {
                $insLegacy = $db->prepare(
                    "INSERT INTO cliente_creditos
                        (id_cliente_FK, monto, monto_disponible, tipo, estado, id_apartado_origen_FK, observaciones, id_empleado_FK, id_usuario_FK)
                     VALUES
                        (:cli, :m, :md, :tipo, 'disponible', :ap, :obs, :emp, :usr)"
                );
                $insLegacy->bindValue(':cli', $idCliente, PDO::PARAM_INT);
                $insLegacy->bindValue(':m', $montoStr, PDO::PARAM_STR);
                $insLegacy->bindValue(':md', $montoStr, PDO::PARAM_STR);
                $insLegacy->bindValue(':tipo', $tipo, PDO::PARAM_STR);
                $insLegacy->bindValue(':ap', $idApartadoOrigen, $idApartadoOrigen !== null && $idApartadoOrigen > 0 ? PDO::PARAM_INT : PDO::PARAM_NULL);
                if ($observaciones !== null && $observaciones !== '') {
                    $insLegacy->bindValue(':obs', $observaciones, PDO::PARAM_STR);
                } else {
                    $insLegacy->bindValue(':obs', null, PDO::PARAM_NULL);
                }
                $insLegacy->bindValue(':emp', $idEmpleado, PDO::PARAM_INT);
                $insLegacy->bindValue(':usr', $idUsuario, PDO::PARAM_INT);
                $insLegacy->execute();
            } else {
                throw $e;
            }
        }
        $id = (int) $db->lastInsertId();
        if ($id <= 0) {
            throw new RuntimeException('No se pudo registrar el credito del cliente.');
        }

        return $id;
    }

    private function registrarCreditoCliente(
        PDO $db,
        int $idCliente,
        string $monto,
        int $idApartadoOrigen,
        int $idEmpleado,
        int $idUsuario,
        ?string $observaciones
    ): int {
        return $this->registrarCreditoClienteEntrada(
            $db,
            $idCliente,
            $monto,
            'excedente_apartado',
            $idEmpleado,
            $idUsuario,
            $observaciones,
            $idApartadoOrigen,
            null,
            null
        );
    }

    /**
     * Quita una linea del apartado (libera la pieza) y recalcula total/saldo.
     * Si lo abonado supera el nuevo total, auto-liquida el apartado y genera
     * credito a favor del cliente en cliente_creditos. Si queda 0 lineas,
     * cancela el apartado y vuelca todo lo abonado al credito del cliente.
     *
     * @param array{id_apartado_FK: int, id_apartado_detalle: int, id_usuario_FK: int, id_empleado_FK: int, observaciones?: string|null} $data
     * @return array<string, mixed>
     */
    public function quitarPiezaDelApartado(array $data): array
    {
        $idApartado = (int) ($data['id_apartado_FK'] ?? 0);
        $idDetalle = (int) ($data['id_apartado_detalle'] ?? 0);
        $idUsuario = (int) ($data['id_usuario_FK'] ?? 0);
        $idEmpleado = (int) ($data['id_empleado_FK'] ?? 0);
        $obs = isset($data['observaciones']) ? trim((string) $data['observaciones']) : '';
        if ($obs === '') {
            $obs = null;
        }

        if ($idApartado <= 0 || $idDetalle <= 0 || $idUsuario <= 0 || $idEmpleado <= 0) {
            throw new InvalidArgumentException('Apartado, linea, empleado y usuario son obligatorios.');
        }

        $db = $this->getDb();
        $this->verificarEmpleadoActivo($db, $idEmpleado);

        $db->beginTransaction();
        try {
            auth_mysql_set_audit_vars($db, $idUsuario);

            $stA = $db->prepare('SELECT * FROM apartados WHERE id_apartado = :id FOR UPDATE');
            $stA->bindValue(':id', $idApartado, PDO::PARAM_INT);
            $stA->execute();
            $ap = $stA->fetch(PDO::FETCH_ASSOC);
            if (!$ap) {
                throw new InvalidArgumentException('El apartado no existe.');
            }
            if (($ap['estado'] ?? '') !== 'activo') {
                throw new InvalidArgumentException('Solo se pueden modificar apartados en estado activo.');
            }

            $stDet = $db->prepare(
                "SELECT ad.*, p.id_tienda_FK AS id_tienda_pieza
                 FROM apartado_detalle ad
                 INNER JOIN piezas_stock ps ON ps.id_pieza_stock = ad.id_pieza_stock_FK
                 INNER JOIN piezas p ON p.id_pieza = ps.id_pieza_FK
                 WHERE ad.id_apartado_detalle = :id
                 FOR UPDATE"
            );
            $stDet->bindValue(':id', $idDetalle, PDO::PARAM_INT);
            $stDet->execute();
            $det = $stDet->fetch(PDO::FETCH_ASSOC);
            if (!$det || (int) ($det['id_apartado_FK'] ?? 0) !== $idApartado) {
                throw new InvalidArgumentException('La linea no pertenece a este apartado.');
            }

            $idPieza = (int) ($det['id_pieza_stock_FK'] ?? 0);
            $precioLineaStr = $this->normalizarDecimal($det['precio_apartado'] ?? 0);
            $idTienda = (int) ($det['id_tienda_pieza'] ?? 0);
            if ($idPieza <= 0 || $idTienda <= 0) {
                throw new InvalidArgumentException('Datos de la linea inconsistentes.');
            }

            $stPs = $db->prepare('SELECT estado, activo FROM piezas_stock WHERE id_pieza_stock = :id FOR UPDATE');
            $stPs->bindValue(':id', $idPieza, PDO::PARAM_INT);
            $stPs->execute();
            $ps = $stPs->fetch(PDO::FETCH_ASSOC);
            if (!$ps || (int) ($ps['activo'] ?? 0) !== 1) {
                throw new InvalidArgumentException('La pieza de la linea no existe o esta inactiva.');
            }
            if (($ps['estado'] ?? '') !== 'apartada') {
                throw new InvalidArgumentException('La pieza no esta en estado apartada; no se puede quitar del apartado.');
            }

            $delDet = $db->prepare('DELETE FROM apartado_detalle WHERE id_apartado_detalle = :id');
            $delDet->bindValue(':id', $idDetalle, PDO::PARAM_INT);
            $delDet->execute();

            $updPs = $db->prepare("UPDATE piezas_stock SET estado = 'disponible' WHERE id_pieza_stock = :id");
            $updPs->bindValue(':id', $idPieza, PDO::PARAM_INT);
            $updPs->execute();

            $this->insertarMovimientoApartado(
                $db,
                $idPieza,
                'liberado',
                'QUITAR_LINEA_APARTADO',
                $idUsuario,
                $idTienda,
                $idApartado,
                $obs
            );

            $stCount = $db->prepare('SELECT COUNT(*) FROM apartado_detalle WHERE id_apartado_FK = :id');
            $stCount->bindValue(':id', $idApartado, PDO::PARAM_INT);
            $stCount->execute();
            $lineasRestantes = (int) $stCount->fetchColumn();

            $abonadoStr = $this->sumaAbonosRegistrados($db, $idApartado);
            $abonado = (float) $abonadoStr;
            $idCliente = (int) ($ap['id_cliente_FK'] ?? 0);
            $totalActualStr = $this->normalizarDecimal($ap['total_apartado'] ?? 0);
            $totalNuevoStr = $this->normalizarDecimal((float) $totalActualStr - (float) $precioLineaStr);
            $totalNuevo = (float) $totalNuevoStr;

            $idCreditoCliente = null;
            $excedenteStr = '0.00';
            $estadoFinal = 'activo';

            if ($lineasRestantes <= 0) {
                $estadoFinal = 'cancelado';
                $upd = $db->prepare(
                    "UPDATE apartados
                     SET total_apartado = 0.00, saldo_pendiente = 0.00, estado = 'cancelado'
                     WHERE id_apartado = :id"
                );
                $upd->bindValue(':id', $idApartado, PDO::PARAM_INT);
                $upd->execute();

                if ($abonado > 0.0) {
                    $excedenteStr = $this->normalizarDecimal($abonado);
                    $obsCred = ($obs !== null ? $obs . ' | ' : '')
                        . 'Cancelacion apartado #' . $idApartado . ' - reembolso de abonos como credito';
                    $idCreditoCliente = $this->registrarCreditoCliente(
                        $db,
                        $idCliente,
                        $excedenteStr,
                        $idApartado,
                        $idEmpleado,
                        $idUsuario,
                        $obsCred
                    );
                }

                $db->commit();

                return [
                    'id_apartado' => $idApartado,
                    'total_apartado' => '0.00',
                    'saldo_pendiente' => '0.00',
                    'estado' => $estadoFinal,
                    'lineas_restantes' => 0,
                    'precio_linea_quitada' => $precioLineaStr,
                    'abonado' => $abonadoStr,
                    'excedente' => $excedenteStr,
                    'id_credito_cliente' => $idCreditoCliente,
                ];
            }

            if ($abonado - $totalNuevo > 0.02) {
                $excedente = $abonado - $totalNuevo;
                $excedenteStr = $this->normalizarDecimal($excedente);
                $upd = $db->prepare(
                    "UPDATE apartados
                     SET total_apartado = :tot, saldo_pendiente = 0.00, estado = 'liquidado'
                     WHERE id_apartado = :id"
                );
                $upd->bindValue(':tot', $totalNuevoStr, PDO::PARAM_STR);
                $upd->bindValue(':id', $idApartado, PDO::PARAM_INT);
                $upd->execute();

                $obsCred = ($obs !== null ? $obs . ' | ' : '')
                    . 'Excedente por baja de pieza en apartado #' . $idApartado;
                $idCreditoCliente = $this->registrarCreditoCliente(
                    $db,
                    $idCliente,
                    $excedenteStr,
                    $idApartado,
                    $idEmpleado,
                    $idUsuario,
                    $obsCred
                );

                $this->marcarPiezasStockVendidasTrasLiquidacionApartado($db, $idApartado);
                $estadoFinal = 'liquidado';

                $db->commit();

                try {
                    require_once __DIR__ . '/ventas.php';
                    require_once __DIR__ . '/../includes/factura_auto.php';
                    $ventasM = new Ventas();
                    $idVentaAp = $ventasM->crearDesdeApartadoLiquidado($idApartado, $idEmpleado, $idUsuario);
                    if ($idVentaAp > 0) {
                        joyeria_emitir_factura_tras_venta($idVentaAp);
                    }
                } catch (Throwable $e) {
                    error_log('ApartadoGestion::quitarPieza excedente factura apartado #' . $idApartado . ': ' . $e->getMessage());
                }

                return [
                    'id_apartado' => $idApartado,
                    'total_apartado' => $totalNuevoStr,
                    'saldo_pendiente' => '0.00',
                    'estado' => $estadoFinal,
                    'lineas_restantes' => $lineasRestantes,
                    'precio_linea_quitada' => $precioLineaStr,
                    'abonado' => $abonadoStr,
                    'excedente' => $excedenteStr,
                    'id_credito_cliente' => $idCreditoCliente,
                ];
            }

            $nuevoSaldo = $totalNuevo - $abonado;
            if ($nuevoSaldo < 0.0) {
                $nuevoSaldo = 0.0;
            }
            $nuevoSaldoStr = $this->normalizarDecimal($nuevoSaldo);
            $liquidar = $nuevoSaldo <= 0.02;

            if ($liquidar) {
                $upd = $db->prepare(
                    "UPDATE apartados
                     SET total_apartado = :tot, saldo_pendiente = 0.00, estado = 'liquidado'
                     WHERE id_apartado = :id"
                );
                $upd->bindValue(':tot', $totalNuevoStr, PDO::PARAM_STR);
                $upd->bindValue(':id', $idApartado, PDO::PARAM_INT);
                $upd->execute();

                $this->marcarPiezasStockVendidasTrasLiquidacionApartado($db, $idApartado);
                $estadoFinal = 'liquidado';
                $saldoGuardar = '0.00';
            } else {
                $upd = $db->prepare(
                    "UPDATE apartados
                     SET total_apartado = :tot, saldo_pendiente = :sal
                     WHERE id_apartado = :id"
                );
                $upd->bindValue(':tot', $totalNuevoStr, PDO::PARAM_STR);
                $upd->bindValue(':sal', $nuevoSaldoStr, PDO::PARAM_STR);
                $upd->bindValue(':id', $idApartado, PDO::PARAM_INT);
                $upd->execute();
                $saldoGuardar = $nuevoSaldoStr;
            }

            $db->commit();

            if ($liquidar) {
                try {
                    require_once __DIR__ . '/ventas.php';
                    require_once __DIR__ . '/../includes/factura_auto.php';
                    $ventasM = new Ventas();
                    $idVentaAp = $ventasM->crearDesdeApartadoLiquidado($idApartado, $idEmpleado, $idUsuario);
                    if ($idVentaAp > 0) {
                        joyeria_emitir_factura_tras_venta($idVentaAp);
                    }
                } catch (Throwable $e) {
                    error_log('ApartadoGestion::quitarPieza liquidar factura apartado #' . $idApartado . ': ' . $e->getMessage());
                }
            }

            return [
                'id_apartado' => $idApartado,
                'total_apartado' => $totalNuevoStr,
                'saldo_pendiente' => $saldoGuardar,
                'estado' => $estadoFinal,
                'lineas_restantes' => $lineasRestantes,
                'precio_linea_quitada' => $precioLineaStr,
                'abonado' => $abonadoStr,
                'excedente' => '0.00',
                'id_credito_cliente' => null,
            ];
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Agrega una pieza disponible al apartado activo (misma tienda).
     * Sube total y recalcula saldo manteniendo lo ya abonado.
     *
     * @param array{id_apartado_FK: int, id_pieza_stock_FK?: int, codigo_pieza?: string, precio_apartado?: mixed, id_usuario_FK: int, id_empleado_FK: int, observaciones?: string|null} $data
     * @return array<string, mixed>
     */
    public function agregarPiezaAlApartado(array $data): array
    {
        $idApartado = (int) ($data['id_apartado_FK'] ?? 0);
        $idUsuario = (int) ($data['id_usuario_FK'] ?? 0);
        $idEmpleado = (int) ($data['id_empleado_FK'] ?? 0);
        $obs = isset($data['observaciones']) ? trim((string) $data['observaciones']) : '';
        if ($obs === '') {
            $obs = null;
        }

        if ($idApartado <= 0 || $idUsuario <= 0 || $idEmpleado <= 0) {
            throw new InvalidArgumentException('Apartado, empleado y usuario son obligatorios.');
        }

        $db = $this->getDb();
        $this->verificarEmpleadoActivo($db, $idEmpleado);

        $idPieza = (int) ($data['id_pieza_stock_FK'] ?? 0);
        $codigo = isset($data['codigo_pieza']) ? trim((string) $data['codigo_pieza']) : '';
        if ($idPieza <= 0 && $codigo !== '') {
            $idPieza = $this->resolverIdPiezaStockDisponible($db, $codigo);
        }
        if ($idPieza <= 0) {
            throw new InvalidArgumentException('Indica una pieza disponible (id o codigo).');
        }

        $db->beginTransaction();
        try {
            auth_mysql_set_audit_vars($db, $idUsuario);

            $stA = $db->prepare('SELECT * FROM apartados WHERE id_apartado = :id FOR UPDATE');
            $stA->bindValue(':id', $idApartado, PDO::PARAM_INT);
            $stA->execute();
            $ap = $stA->fetch(PDO::FETCH_ASSOC);
            if (!$ap) {
                throw new InvalidArgumentException('El apartado no existe.');
            }
            if (($ap['estado'] ?? '') !== 'activo') {
                throw new InvalidArgumentException('Solo se pueden agregar piezas a apartados en estado activo.');
            }

            $tiendaActual = $this->tiendaActualApartado($db, $idApartado);
            if ($tiendaActual === null) {
                throw new InvalidArgumentException('No se pudo determinar la tienda del apartado (sin lineas activas).');
            }

            $stPs = $db->prepare(
                'SELECT ps.id_pieza_stock, ps.estado, ps.activo, ps.precio_venta, p.id_tienda_FK AS id_tienda_pieza
                 FROM piezas_stock ps
                 INNER JOIN piezas p ON p.id_pieza = ps.id_pieza_FK
                 WHERE ps.id_pieza_stock = :id
                 FOR UPDATE'
            );
            $stPs->bindValue(':id', $idPieza, PDO::PARAM_INT);
            $stPs->execute();
            $ps = $stPs->fetch(PDO::FETCH_ASSOC);
            if (!$ps || (int) ($ps['activo'] ?? 0) !== 1) {
                throw new InvalidArgumentException('La pieza no existe o esta inactiva.');
            }
            if (($ps['estado'] ?? '') !== 'disponible') {
                throw new InvalidArgumentException('La pieza no esta disponible (estado: ' . (string) ($ps['estado'] ?? '') . ').');
            }
            $tiendaPieza = (int) ($ps['id_tienda_pieza'] ?? 0);
            if ($tiendaPieza !== $tiendaActual) {
                throw new InvalidArgumentException('La pieza nueva debe ser de la misma tienda del apartado.');
            }

            $stDup = $db->prepare(
                'SELECT 1 FROM apartado_detalle WHERE id_apartado_FK = :a AND id_pieza_stock_FK = :p LIMIT 1'
            );
            $stDup->bindValue(':a', $idApartado, PDO::PARAM_INT);
            $stDup->bindValue(':p', $idPieza, PDO::PARAM_INT);
            $stDup->execute();
            if ($stDup->fetchColumn()) {
                throw new InvalidArgumentException('La pieza ya esta en este apartado.');
            }

            $precioVentaStr = $this->normalizarDecimal($ps['precio_venta'] ?? 0);
            $idClienteApartado = (int) ($ap['id_cliente_FK'] ?? 0);
            $precioManual = isset($data['precio_apartado']) && $data['precio_apartado'] !== '' && $data['precio_apartado'] !== null;
            if ($precioManual) {
                $precioStr = $this->normalizarDecimal($data['precio_apartado']);
            } else {
                $precioStr = $this->precioApartadoDesdePrecioVenta((float) $precioVentaStr, $idClienteApartado);
            }
            if ((float) $precioStr <= 0) {
                throw new InvalidArgumentException('Precio de la pieza para apartado invalido.');
            }

            $insD = $db->prepare(
                'INSERT INTO apartado_detalle (id_apartado_FK, id_pieza_stock_FK, precio_apartado) VALUES (:a, :p, :pr)'
            );
            $insD->bindValue(':a', $idApartado, PDO::PARAM_INT);
            $insD->bindValue(':p', $idPieza, PDO::PARAM_INT);
            $insD->bindValue(':pr', $precioStr, PDO::PARAM_STR);
            $insD->execute();
            $idDetalle = (int) $db->lastInsertId();

            $updPs = $db->prepare("UPDATE piezas_stock SET estado = 'apartada' WHERE id_pieza_stock = :id");
            $updPs->bindValue(':id', $idPieza, PDO::PARAM_INT);
            $updPs->execute();

            $this->insertarMovimientoApartado(
                $db,
                $idPieza,
                'apartado',
                'AGREGAR_LINEA_APARTADO',
                $idUsuario,
                $tiendaPieza,
                $idApartado,
                $obs
            );

            $totalActualStr = $this->normalizarDecimal($ap['total_apartado'] ?? 0);
            $totalNuevoStr = $this->normalizarDecimal((float) $totalActualStr + (float) $precioStr);
            $abonadoStr = $this->sumaAbonosRegistrados($db, $idApartado);
            $abonado = (float) $abonadoStr;
            $saldoNuevo = (float) $totalNuevoStr - $abonado;
            if ($saldoNuevo < 0.0) {
                $saldoNuevo = 0.0;
            }
            $saldoNuevoStr = $this->normalizarDecimal($saldoNuevo);

            $upd = $db->prepare(
                'UPDATE apartados SET total_apartado = :tot, saldo_pendiente = :sal WHERE id_apartado = :id'
            );
            $upd->bindValue(':tot', $totalNuevoStr, PDO::PARAM_STR);
            $upd->bindValue(':sal', $saldoNuevoStr, PDO::PARAM_STR);
            $upd->bindValue(':id', $idApartado, PDO::PARAM_INT);
            $upd->execute();

            $db->commit();

            return [
                'id_apartado' => $idApartado,
                'id_apartado_detalle' => $idDetalle,
                'id_pieza_stock_FK' => $idPieza,
                'precio_apartado' => $precioStr,
                'total_apartado' => $totalNuevoStr,
                'saldo_pendiente' => $saldoNuevoStr,
                'abonado' => $abonadoStr,
                'estado' => 'activo',
            ];
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listarCreditosCliente(int $idCliente, ?string $estado = 'disponible'): array
    {
        if ($idCliente <= 0) {
            return [];
        }
        $allowed = ['disponible', 'consumido', 'anulado'];
        $estadoF = $estado !== null && in_array($estado, $allowed, true) ? $estado : null;

        $sql = "SELECT cc.*,
                       d.id_devolucion AS devolucion_id,
                       d.tipo_origen AS devolucion_tipo_origen,
                       d.fecha_devolucion AS devolucion_fecha,
                       a.id_apartado AS apartado_origen_id
                FROM cliente_creditos cc
                LEFT JOIN devoluciones d ON d.id_devolucion = cc.id_devolucion_origen_FK
                LEFT JOIN apartados a ON a.id_apartado = cc.id_apartado_origen_FK
                WHERE cc.id_cliente_FK = :cli";
        if ($estadoF !== null) {
            $sql .= ' AND cc.estado = :est';
        }
        $sql .= ' ORDER BY cc.id_credito ASC';

        $st = $this->getDb()->prepare($sql);
        $st->bindValue(':cli', $idCliente, PDO::PARAM_INT);
        if ($estadoF !== null) {
            $st->bindValue(':est', $estadoF, PDO::PARAM_STR);
        }
        try {
            $st->execute();
        } catch (Throwable $e) {
            if (strpos($e->getMessage(), 'id_devolucion_origen') !== false
                || strpos($e->getMessage(), 'Unknown column') !== false) {
                $sqlLegacy = 'SELECT cc.* FROM cliente_creditos cc WHERE cc.id_cliente_FK = :cli';
                if ($estadoF !== null) {
                    $sqlLegacy .= ' AND cc.estado = :est';
                }
                $sqlLegacy .= ' ORDER BY cc.id_credito ASC';
                $st = $this->getDb()->prepare($sqlLegacy);
                $st->bindValue(':cli', $idCliente, PDO::PARAM_INT);
                if ($estadoF !== null) {
                    $st->bindValue(':est', $estadoF, PDO::PARAM_STR);
                }
                $st->execute();
            } else {
                throw $e;
            }
        }

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return array{id_pago: int, saldo_pendiente: string, estado: string}
     */
    public function registrarAbono(array $data): array
    {
        $idApartado = (int) ($data['id_apartado_FK'] ?? 0);
        $monto = isset($data['monto']) ? $this->normalizarDecimal($data['monto']) : '0.00';
        $montoF = (float) $monto;
        $idUsuario = (int) ($data['id_usuario_FK'] ?? 0);
        $usarCredito = !empty($data['usar_credito_cliente']);
        $idForma = (int) ($data['id_forma_pago_FK'] ?? 0);
        $idEmpleado = (int) ($data['id_empleado_FK'] ?? 0);

        if ($idApartado <= 0 || $montoF <= 0 || $idUsuario <= 0) {
            throw new InvalidArgumentException('Apartado, monto y usuario son obligatorios para el abono.');
        }
        if (!$usarCredito && $idForma <= 0) {
            throw new InvalidArgumentException('La forma de pago es obligatoria para el abono.');
        }

        $db = $this->getDb();
        if ($usarCredito) {
            $idForma = $this->obtenerIdFormaPagoCreditoCliente($db);
            $tipoOrigen = 'credito_cliente';
            $referenciaPago = 'Abono apartado (credito cliente)';
        } else {
            $this->verificarFormaPagoAbono($db, $idForma);
            $tipoOrigen = 'cobro_tienda';
            $referenciaPago = 'Abono apartado';
        }

        $db->beginTransaction();
        try {
            auth_mysql_set_audit_vars($db, $idUsuario);

            $st = $db->prepare('SELECT * FROM apartados WHERE id_apartado = :id FOR UPDATE');
            $st->bindValue(':id', $idApartado, PDO::PARAM_INT);
            $st->execute();
            $a = $st->fetch(PDO::FETCH_ASSOC);
            if (!$a) {
                throw new InvalidArgumentException('El apartado no existe.');
            }
            if (($a['estado'] ?? '') !== 'activo') {
                throw new InvalidArgumentException('Solo se pueden abonar apartados activos.');
            }

            $idClienteApartado = (int) ($a['id_cliente_FK'] ?? 0);
            if ($usarCredito && $idClienteApartado <= 0) {
                throw new InvalidArgumentException('El apartado no tiene cliente; no se puede usar credito a favor.');
            }

            $totalStr = $this->normalizarDecimal($a['total_apartado'] ?? 0);
            $total = (float) $totalStr;
            $yaStr = $this->sumaAbonosRegistrados($db, $idApartado);
            $ya = (float) $yaStr;
            if ($ya + $montoF - $total > 0.02) {
                throw new InvalidArgumentException(
                    'El abono excede el saldo. Abonado: ' . $yaStr . ', intento: ' . $monto . ', total: ' . $totalStr
                );
            }

            $nuevoSaldoStr = $this->normalizarDecimal($total - ($ya + $montoF));

            $insP = $db->prepare(
                "INSERT INTO apartado_pagos
                    (id_apartado_FK, monto, id_forma_pago_FK, estado, referencia, id_usuario_FK, tipo_origen)
                 VALUES
                    (:a, :m, :fp, 'registrado', :ref, :u, :to)"
            );
            $insP->bindValue(':a', $idApartado, PDO::PARAM_INT);
            $insP->bindValue(':m', $monto, PDO::PARAM_STR);
            $insP->bindValue(':fp', $idForma, PDO::PARAM_INT);
            $insP->bindValue(':ref', $referenciaPago, PDO::PARAM_STR);
            $insP->bindValue(':u', $idUsuario, PDO::PARAM_INT);
            $insP->bindValue(':to', $tipoOrigen, PDO::PARAM_STR);
            $insP->execute();
            $idPago = (int) $db->lastInsertId();

            $consumoInfo = null;
            if ($usarCredito) {
                $consumoInfo = $this->aplicarConsumoCreditoCliente(
                    $db,
                    $idClienteApartado,
                    (float) $monto,
                    'abono_apartado',
                    $idApartado,
                    null,
                    $idPago,
                    $idEmpleado > 0 ? $idEmpleado : null,
                    $idUsuario,
                    'Abono apartado #' . $idApartado . ' con monedero'
                );
            }

            $liquidar = (float) $nuevoSaldoStr <= 0.02;
            if ($liquidar) {
                $upd = $db->prepare(
                    "UPDATE apartados SET saldo_pendiente = :s, estado = 'liquidado' WHERE id_apartado = :id AND estado = 'activo'"
                );
            } else {
                $upd = $db->prepare(
                    'UPDATE apartados SET saldo_pendiente = :s WHERE id_apartado = :id AND estado = \'activo\''
                );
            }
            $saldoGuardar = $liquidar ? '0.00' : $nuevoSaldoStr;
            $upd->bindValue(':s', $saldoGuardar, PDO::PARAM_STR);
            $upd->bindValue(':id', $idApartado, PDO::PARAM_INT);
            $upd->execute();
            if ($upd->rowCount() !== 1) {
                throw new RuntimeException('No se pudo actualizar el apartado.');
            }

            if ($liquidar) {
                $this->marcarPiezasStockVendidasTrasLiquidacionApartado($db, $idApartado);
            }

            $db->commit();

            if ($liquidar) {
                try {
                    require_once __DIR__ . '/ventas.php';
                    require_once __DIR__ . '/../includes/factura_auto.php';
                    $ventas = new Ventas();
                    $idVentaAp = $ventas->crearDesdeApartadoLiquidado($idApartado, $idEmpleado, $idUsuario);
                    if ($idVentaAp > 0) {
                        joyeria_emitir_factura_tras_venta($idVentaAp);
                    }
                } catch (Throwable $e) {
                    error_log('ApartadoGestion::registrarAbono factura apartado #' . $idApartado . ': ' . $e->getMessage());
                }
            }

            return [
                'id_pago' => $idPago,
                'saldo_pendiente' => $saldoGuardar,
                'estado' => $liquidar ? 'liquidado' : 'activo',
                'usar_credito_cliente' => $usarCredito,
                'credito_consumido' => $consumoInfo,
            ];
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }
}
