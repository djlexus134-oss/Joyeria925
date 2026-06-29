<?php

require_once __DIR__ . '/../../sistema.class.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/ventas.php';

/**
 * Cambio de pieza en apartado con credito no reembolsable (1 linea: nuevo apartado)
 * o reemplazo de una linea en el mismo apartado (multilinea).
 */
class ApartadoCambio extends Sistema
{
    public const FORMA_PAGO_CREDITO_LABEL = 'Credito interno (cambio apartado)';

    private function normalizarDecimal($valor, int $decimales = 2): string
    {
        $numero = (float) $valor;
        $epsilon = pow(10, -($decimales + 3));
        $ajustado = $numero + ($numero >= 0 ? $epsilon : -$epsilon);

        return number_format(round($ajustado, $decimales), $decimales, '.', '');
    }

    private function precioApartadoConDescuentoCliente(float $precioVenta, int $idCliente): string
    {
        if ($precioVenta <= 0) {
            return '0.00';
        }
        $ventas = new Ventas();
        $pct = $ventas->resolverDescuentoPorcentajeLinea('joya', $idCliente > 0 ? $idCliente : null);
        $precioFinal = max(0.0, $precioVenta * (1.0 - ($pct / 100.0)));

        return $this->normalizarDecimal($precioFinal);
    }

    public function obtenerIdFormaPagoCreditoInterno(PDO $db): int
    {
        $st = $db->prepare(
            'SELECT id_forma_pago FROM forma_pago WHERE forma_pago = :fp AND activo = 1 LIMIT 1'
        );
        $st->bindValue(':fp', self::FORMA_PAGO_CREDITO_LABEL, PDO::PARAM_STR);
        $st->execute();
        $id = $st->fetchColumn();
        if ($id === false) {
            throw new RuntimeException(
                'Falta la forma de pago "' . self::FORMA_PAGO_CREDITO_LABEL . '". Ejecuta la migracion SQL del modulo de cambio de apartado.'
            );
        }

        return (int) $id;
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
     * Suma de abonos en efectivo / caja registrados (excluye creditos por cambio).
     */
    public function sumaAbonosCobroTienda(PDO $db, int $idApartado): string
    {
        $st = $db->prepare(
            "SELECT COALESCE(SUM(monto), 0)
             FROM apartado_pagos
             WHERE id_apartado_FK = :a
               AND estado = 'registrado'
               AND tipo_origen = 'cobro_tienda'"
        );
        $st->bindValue(':a', $idApartado, PDO::PARAM_INT);
        $st->execute();

        return $this->normalizarDecimal($st->fetchColumn());
    }

    private function sumaAbonosParaSaldo(PDO $db, int $idApartado): string
    {
        $st = $db->prepare(
            "SELECT COALESCE(SUM(monto), 0)
             FROM apartado_pagos
             WHERE id_apartado_FK = :a
               AND estado = 'registrado'
               AND tipo_origen IN ('cobro_tienda', 'credito_por_cambio')"
        );
        $st->bindValue(':a', $idApartado, PDO::PARAM_INT);
        $st->execute();

        return $this->normalizarDecimal($st->fetchColumn());
    }

    /**
     * @return array<string, mixed>
     */
    public function obtenerVistaPreviaApartado(int $idApartado): array
    {
        if ($idApartado <= 0) {
            throw new InvalidArgumentException('Apartado invalido.');
        }

        $db = $this->getDb();

        $stA = $db->prepare(
            "SELECT a.*,
                    CONCAT(u.nombre, ' ', u.primer_apellido, COALESCE(CONCAT(' ', u.segundo_apellido), '')) AS cliente_nombre
             FROM apartados a
             INNER JOIN clientes c ON c.id_cliente = a.id_cliente_FK
             INNER JOIN usuarios u ON u.id_usuario = c.id_usuario_FK
             WHERE a.id_apartado = :id
             LIMIT 1"
        );
        $stA->bindValue(':id', $idApartado, PDO::PARAM_INT);
        $stA->execute();
        $apartado = $stA->fetch(PDO::FETCH_ASSOC);
        if (!$apartado) {
            throw new InvalidArgumentException('El apartado no existe.');
        }

        $stD = $db->prepare(
            "SELECT ad.id_apartado_detalle,
                    ad.id_pieza_stock_FK,
                    ad.precio_apartado,
                    ps.codigo_barras,
                    ps.estado AS estado_pieza,
                    p.desc_pieza,
                    p.id_tienda_FK AS id_tienda_pieza
             FROM apartado_detalle ad
             INNER JOIN piezas_stock ps ON ps.id_pieza_stock = ad.id_pieza_stock_FK
             INNER JOIN piezas p ON p.id_pieza = ps.id_pieza_FK
             WHERE ad.id_apartado_FK = :id
             ORDER BY ad.id_apartado_detalle ASC"
        );
        $stD->bindValue(':id', $idApartado, PDO::PARAM_INT);
        $stD->execute();
        $detalles = $stD->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $abonosCaja = $this->sumaAbonosCobroTienda($db, $idApartado);

        return [
            'apartado' => $apartado,
            'detalles' => $detalles,
            'lineas_count' => count($detalles),
            'abonos_cobro_tienda' => $abonosCaja,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listarCambiosRecientes(int $limite = 100): array
    {
        $lim = (int) max(1, min(500, $limite));
        $sql = "SELECT c.*,
                       ao.estado AS estado_apartado_origen,
                       ad.estado AS estado_apartado_destino,
                       CONCAT(u.nombre, ' ', u.primer_apellido, COALESCE(CONCAT(' ', u.segundo_apellido), '')) AS usuario_nombre,
                       CONCAT(ue.nombre, ' ', ue.primer_apellido, COALESCE(CONCAT(' ', ue.segundo_apellido), '')) AS empleado_nombre
                FROM apartado_cambios_pieza c
                INNER JOIN apartados ao ON ao.id_apartado = c.id_apartado_origen_FK
                INNER JOIN apartados ad ON ad.id_apartado = c.id_apartado_destino_FK
                INNER JOIN usuarios u ON u.id_usuario = c.id_usuario_FK
                INNER JOIN empleados e ON e.id_empleado = c.id_empleado_FK
                INNER JOIN usuarios ue ON ue.id_usuario = e.id_usuario_FK
                ORDER BY c.fecha_registro DESC, c.id_apartado_cambio DESC
                LIMIT " . $lim;

        return $this->getDb()->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function buscarApartadoActivoPorCodigoPieza(string $codigo): ?array
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            return null;
        }

        $db = $this->getDb();
        $st = $db->prepare(
            "SELECT DISTINCT a.id_apartado
             FROM apartados a
             INNER JOIN apartado_detalle ad ON ad.id_apartado_FK = a.id_apartado
             INNER JOIN piezas_stock ps ON ps.id_pieza_stock = ad.id_pieza_stock_FK
             WHERE a.estado = 'activo'
               AND ps.estado = 'apartada'
               AND ps.activo = 1
               AND (ps.codigo_barras = :c OR ps.codigo_auxiliar = :c2)
             ORDER BY a.id_apartado DESC
             LIMIT 1"
        );
        $st->bindValue(':c', $codigo, PDO::PARAM_STR);
        $st->bindValue(':c2', $codigo, PDO::PARAM_STR);
        $st->execute();
        $id = $st->fetchColumn();
        if ($id === false) {
            return null;
        }

        return $this->obtenerVistaPreviaApartado((int) $id);
    }

    public function resolverIdPiezaStockDisponiblePorCodigo(string $codigo): int
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            throw new InvalidArgumentException('Codigo de pieza vacio.');
        }

        $db = $this->getDb();
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
     * @param array<string, mixed> $data
     * @return array{id_apartado_destino: int, id_apartado_cambio: int, monto_credito_aplicado: string, saldo_pendiente_destino: string}
     */
    public function cambiarPiezaConCredito(array $data): array
    {
        $idOrigen = (int) ($data['id_apartado_origen'] ?? 0);
        if ($idOrigen <= 0) {
            throw new InvalidArgumentException('Apartado origen invalido.');
        }

        $db = $this->getDb();
        $stC = $db->prepare('SELECT COUNT(*) FROM apartado_detalle WHERE id_apartado_FK = :a');
        $stC->bindValue(':a', $idOrigen, PDO::PARAM_INT);
        $stC->execute();
        $nLineas = (int) $stC->fetchColumn();

        $idDetalle = (int) ($data['id_apartado_detalle'] ?? 0);

        if ($nLineas > 1) {
            if ($idDetalle <= 0) {
                throw new InvalidArgumentException(
                    'Este apartado tiene ' . $nLineas . ' piezas. Indica id_apartado_detalle de la linea a reemplazar (mismo apartado, sin credito aparte).'
                );
            }

            return $this->reemplazarLineaMismoApartado($data);
        }

        return $this->ejecutarCambioUnaLineaNuevoApartado($data);
    }

    /**
     * Multilinea: sustituye una linea, recalcula total y saldo en el mismo apartado.
     *
     * @param array{id_apartado_origen: int, id_apartado_detalle?: int, id_pieza_stock_nueva: int, id_empleado_FK: int, id_usuario_FK: int, observaciones?: string|null} $data
     * @return array{id_apartado: int, id_apartado_cambio: int, total_apartado: string, saldo_pendiente: string}
     */
    public function reemplazarLineaMismoApartado(array $data): array
    {
        $idApartado = (int) ($data['id_apartado_origen'] ?? 0);
        $idDetalle = (int) ($data['id_apartado_detalle'] ?? 0);
        $idPiezaNueva = (int) ($data['id_pieza_stock_nueva'] ?? 0);
        $idEmpleado = (int) ($data['id_empleado_FK'] ?? 0);
        $idUsuario = (int) ($data['id_usuario_FK'] ?? 0);
        $obs = isset($data['observaciones']) ? trim((string) $data['observaciones']) : '';

        if ($idApartado <= 0 || $idDetalle <= 0 || $idPiezaNueva <= 0 || $idEmpleado <= 0 || $idUsuario <= 0) {
            throw new InvalidArgumentException('Faltan datos para el reemplazo de linea.');
        }

        $db = $this->getDb();
        $this->verificarEmpleadoActivo($db, $idEmpleado);

        $db->beginTransaction();
        try {
            auth_mysql_set_audit_vars($db, $idUsuario);

            $stAo = $db->prepare('SELECT * FROM apartados WHERE id_apartado = :id FOR UPDATE');
            $stAo->bindValue(':id', $idApartado, PDO::PARAM_INT);
            $stAo->execute();
            $ap = $stAo->fetch(PDO::FETCH_ASSOC);
            if (!$ap) {
                throw new InvalidArgumentException('El apartado no existe.');
            }
            if (($ap['estado'] ?? '') !== 'activo') {
                throw new InvalidArgumentException('Solo se pueden modificar apartados activos.');
            }

            $stDet = $db->prepare(
                'SELECT ad.* FROM apartado_detalle ad WHERE ad.id_apartado_detalle = :id FOR UPDATE'
            );
            $stDet->bindValue(':id', $idDetalle, PDO::PARAM_INT);
            $stDet->execute();
            $det = $stDet->fetch(PDO::FETCH_ASSOC);
            if (!$det || (int) ($det['id_apartado_FK'] ?? 0) !== $idApartado) {
                throw new InvalidArgumentException('La linea no pertenece a este apartado.');
            }

            $idPiezaOrigen = (int) ($det['id_pieza_stock_FK'] ?? 0);
            if ($idPiezaOrigen === $idPiezaNueva) {
                throw new InvalidArgumentException('La pieza nueva debe ser distinta a la actual.');
            }

            $stPsO = $db->prepare(
                'SELECT ps.*, p.id_tienda_FK AS id_tienda_pieza
                 FROM piezas_stock ps
                 INNER JOIN piezas p ON p.id_pieza = ps.id_pieza_FK
                 WHERE ps.id_pieza_stock = :id FOR UPDATE'
            );
            $stPsO->bindValue(':id', $idPiezaOrigen, PDO::PARAM_INT);
            $stPsO->execute();
            $psOrigen = $stPsO->fetch(PDO::FETCH_ASSOC);
            if (!$psOrigen || (int) ($psOrigen['activo'] ?? 0) !== 1 || ($psOrigen['estado'] ?? '') !== 'apartada') {
                throw new InvalidArgumentException('La pieza actual de la linea no esta apartada.');
            }

            $stPsN = $db->prepare(
                'SELECT ps.*, p.id_tienda_FK AS id_tienda_pieza
                 FROM piezas_stock ps
                 INNER JOIN piezas p ON p.id_pieza = ps.id_pieza_FK
                 WHERE ps.id_pieza_stock = :id FOR UPDATE'
            );
            $stPsN->bindValue(':id', $idPiezaNueva, PDO::PARAM_INT);
            $stPsN->execute();
            $psNueva = $stPsN->fetch(PDO::FETCH_ASSOC);
            if (!$psNueva || (int) ($psNueva['activo'] ?? 0) !== 1 || ($psNueva['estado'] ?? '') !== 'disponible') {
                throw new InvalidArgumentException('La pieza nueva debe estar disponible.');
            }

            $tOr = (int) ($psOrigen['id_tienda_pieza'] ?? 0);
            $tNv = (int) ($psNueva['id_tienda_pieza'] ?? 0);
            if ($tOr <= 0 || $tNv <= 0 || $tOr !== $tNv) {
                throw new InvalidArgumentException('La pieza nueva debe ser de la misma tienda que la linea actual.');
            }

            $precioNuevoStr = $this->normalizarDecimal($psNueva['precio_venta'] ?? 0);
            if ((float) $precioNuevoStr <= 0) {
                throw new InvalidArgumentException('La pieza nueva no tiene precio de venta valido.');
            }
            $idClienteApr = (int) ($ap['id_cliente_FK'] ?? 0);
            $precioNuevoStr = $this->precioApartadoConDescuentoCliente((float) $precioNuevoStr, $idClienteApr);

            $updPsO = $db->prepare("UPDATE piezas_stock SET estado = 'disponible' WHERE id_pieza_stock = :id");
            $updPsO->bindValue(':id', $idPiezaOrigen, PDO::PARAM_INT);
            $updPsO->execute();

            $updPsN = $db->prepare("UPDATE piezas_stock SET estado = 'apartada' WHERE id_pieza_stock = :id");
            $updPsN->bindValue(':id', $idPiezaNueva, PDO::PARAM_INT);
            $updPsN->execute();

            $insMovLib = $db->prepare(
                "INSERT INTO movimientos_inventario
                    (id_pieza_stock_FK, tipo_movimiento, referencia, observaciones, id_usuario_FK, id_tienda_origen_FK, id_apartado_FK, tipo_referencia)
                 VALUES
                    (:ps, 'liberado', 'CAMBIO_LINEA_APARTADO', :obs, :u, :t, :ap, 'apartado')"
            );
            $insMovLib->bindValue(':ps', $idPiezaOrigen, PDO::PARAM_INT);
            $insMovLib->bindValue(':obs', $obs !== '' ? $obs : null, $obs !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $insMovLib->bindValue(':u', $idUsuario, PDO::PARAM_INT);
            $insMovLib->bindValue(':t', $tOr, PDO::PARAM_INT);
            $insMovLib->bindValue(':ap', $idApartado, PDO::PARAM_INT);
            $insMovLib->execute();

            $insMovApr = $db->prepare(
                "INSERT INTO movimientos_inventario
                    (id_pieza_stock_FK, tipo_movimiento, referencia, observaciones, id_usuario_FK, id_tienda_origen_FK, id_apartado_FK, tipo_referencia)
                 VALUES
                    (:ps, 'apartado', 'CAMBIO_LINEA_APARTADO', :obs, :u, :t, :ap, 'apartado')"
            );
            $insMovApr->bindValue(':ps', $idPiezaNueva, PDO::PARAM_INT);
            $insMovApr->bindValue(':obs', $obs !== '' ? $obs : null, $obs !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $insMovApr->bindValue(':u', $idUsuario, PDO::PARAM_INT);
            $insMovApr->bindValue(':t', $tNv, PDO::PARAM_INT);
            $insMovApr->bindValue(':ap', $idApartado, PDO::PARAM_INT);
            $insMovApr->execute();

            $updDet = $db->prepare(
                'UPDATE apartado_detalle SET id_pieza_stock_FK = :ps, precio_apartado = :pr WHERE id_apartado_detalle = :id'
            );
            $updDet->bindValue(':ps', $idPiezaNueva, PDO::PARAM_INT);
            $updDet->bindValue(':pr', $precioNuevoStr, PDO::PARAM_STR);
            $updDet->bindValue(':id', $idDetalle, PDO::PARAM_INT);
            $updDet->execute();

            $stSum = $db->prepare(
                'SELECT COALESCE(SUM(precio_apartado), 0) FROM apartado_detalle WHERE id_apartado_FK = :a'
            );
            $stSum->bindValue(':a', $idApartado, PDO::PARAM_INT);
            $stSum->execute();
            $nuevoTotalStr = $this->normalizarDecimal($stSum->fetchColumn());

            $abonosStr = $this->sumaAbonosParaSaldo($db, $idApartado);
            $abonos = (float) $abonosStr;
            $nuevoTotal = (float) $nuevoTotalStr;
            if ($abonos - $nuevoTotal > 0.02) {
                throw new InvalidArgumentException(
                    'Los abonos registrados (' . $abonosStr . ') superan el nuevo total del apartado (' . $nuevoTotalStr
                    . '). Ajusta precios o registra el caso antes de continuar.'
                );
            }
            $nuevoSaldoStr = $this->normalizarDecimal(max(0.0, $nuevoTotal - $abonos));

            $updA = $db->prepare(
                'UPDATE apartados SET total_apartado = :tot, saldo_pendiente = :sal WHERE id_apartado = :id'
            );
            $updA->bindValue(':tot', $nuevoTotalStr, PDO::PARAM_STR);
            $updA->bindValue(':sal', $nuevoSaldoStr, PDO::PARAM_STR);
            $updA->bindValue(':id', $idApartado, PDO::PARAM_INT);
            $updA->execute();

            $insCambio = $db->prepare(
                "INSERT INTO apartado_cambios_pieza
                    (id_apartado_origen_FK, id_apartado_destino_FK, id_pieza_stock_origen_FK, id_pieza_stock_destino_FK,
                     monto_credito_aplicado, id_pago_credito_FK, observaciones, id_empleado_FK, id_usuario_FK, tipo_operacion, id_apartado_detalle_FK)
                 VALUES
                    (:o, :d, :pso, :psn, 0.00, NULL, :obs, :emp, :usr, 'reemplazo_mismo', :iddet)"
            );
            $insCambio->bindValue(':o', $idApartado, PDO::PARAM_INT);
            $insCambio->bindValue(':d', $idApartado, PDO::PARAM_INT);
            $insCambio->bindValue(':pso', $idPiezaOrigen, PDO::PARAM_INT);
            $insCambio->bindValue(':psn', $idPiezaNueva, PDO::PARAM_INT);
            $insCambio->bindValue(':obs', $obs !== '' ? $obs : null, $obs !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $insCambio->bindValue(':emp', $idEmpleado, PDO::PARAM_INT);
            $insCambio->bindValue(':usr', $idUsuario, PDO::PARAM_INT);
            $insCambio->bindValue(':iddet', $idDetalle, PDO::PARAM_INT);
            $insCambio->execute();
            $idCambio = (int) $db->lastInsertId();

            $db->commit();

            return [
                'id_apartado' => $idApartado,
                'id_apartado_destino' => $idApartado,
                'id_apartado_cambio' => $idCambio,
                'monto_credito_aplicado' => '0.00',
                'saldo_pendiente_destino' => $nuevoSaldoStr,
                'total_apartado' => $nuevoTotalStr,
            ];
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Una sola linea: cierra origen y crea apartado destino con credito_por_cambio.
     *
     * @param array<string, mixed> $data
     * @return array{id_apartado_destino: int, id_apartado_cambio: int, monto_credito_aplicado: string, saldo_pendiente_destino: string}
     */
    private function ejecutarCambioUnaLineaNuevoApartado(array $data): array
    {
        $idOrigen = (int) ($data['id_apartado_origen'] ?? 0);
        $idPiezaNueva = (int) ($data['id_pieza_stock_nueva'] ?? 0);
        $idEmpleado = (int) ($data['id_empleado_FK'] ?? 0);
        $idUsuario = (int) ($data['id_usuario_FK'] ?? 0);
        $obs = isset($data['observaciones']) ? trim((string) $data['observaciones']) : '';

        if ($idOrigen <= 0 || $idPiezaNueva <= 0 || $idEmpleado <= 0 || $idUsuario <= 0) {
            throw new InvalidArgumentException('Faltan datos obligatorios para el cambio de pieza.');
        }

        $db = $this->getDb();
        $this->verificarEmpleadoActivo($db, $idEmpleado);
        $idFormaPagoCredito = $this->obtenerIdFormaPagoCreditoInterno($db);

        $db->beginTransaction();

        try {
            auth_mysql_set_audit_vars($db, $idUsuario);

            $stAo = $db->prepare('SELECT * FROM apartados WHERE id_apartado = :id FOR UPDATE');
            $stAo->bindValue(':id', $idOrigen, PDO::PARAM_INT);
            $stAo->execute();
            $apOrigen = $stAo->fetch(PDO::FETCH_ASSOC);
            if (!$apOrigen) {
                throw new InvalidArgumentException('El apartado origen no existe.');
            }
            if (($apOrigen['estado'] ?? '') !== 'activo') {
                throw new InvalidArgumentException('Solo se pueden cambiar apartados en estado activo.');
            }

            $stDet = $db->prepare(
                'SELECT ad.* FROM apartado_detalle ad WHERE ad.id_apartado_FK = :a ORDER BY ad.id_apartado_detalle ASC FOR UPDATE'
            );
            $stDet->bindValue(':a', $idOrigen, PDO::PARAM_INT);
            $stDet->execute();
            $detalles = $stDet->fetchAll(PDO::FETCH_ASSOC) ?: [];
            if (count($detalles) !== 1) {
                throw new InvalidArgumentException(
                    'El flujo de nuevo apartado aplica solo con una pieza. Lineas: ' . count($detalles)
                );
            }
            $detOrigen = $detalles[0];
            $idPiezaOrigen = (int) ($detOrigen['id_pieza_stock_FK'] ?? 0);
            if ($idPiezaOrigen <= 0) {
                throw new InvalidArgumentException('Detalle de apartado origen invalido.');
            }
            if ($idPiezaOrigen === $idPiezaNueva) {
                throw new InvalidArgumentException('La pieza nueva debe ser distinta a la actual del apartado.');
            }

            $stPsO = $db->prepare(
                'SELECT ps.*, p.id_tienda_FK AS id_tienda_pieza
                 FROM piezas_stock ps
                 INNER JOIN piezas p ON p.id_pieza = ps.id_pieza_FK
                 WHERE ps.id_pieza_stock = :id FOR UPDATE'
            );
            $stPsO->bindValue(':id', $idPiezaOrigen, PDO::PARAM_INT);
            $stPsO->execute();
            $psOrigen = $stPsO->fetch(PDO::FETCH_ASSOC);
            if (!$psOrigen || (int) ($psOrigen['activo'] ?? 0) !== 1) {
                throw new InvalidArgumentException('La pieza origen no existe o no esta activa.');
            }
            if (($psOrigen['estado'] ?? '') !== 'apartada') {
                throw new InvalidArgumentException('La pieza del apartado no esta en estado apartada.');
            }

            $stPsN = $db->prepare(
                'SELECT ps.*, p.id_tienda_FK AS id_tienda_pieza
                 FROM piezas_stock ps
                 INNER JOIN piezas p ON p.id_pieza = ps.id_pieza_FK
                 WHERE ps.id_pieza_stock = :id FOR UPDATE'
            );
            $stPsN->bindValue(':id', $idPiezaNueva, PDO::PARAM_INT);
            $stPsN->execute();
            $psNueva = $stPsN->fetch(PDO::FETCH_ASSOC);
            if (!$psNueva || (int) ($psNueva['activo'] ?? 0) !== 1) {
                throw new InvalidArgumentException('La pieza nueva no existe o no esta activa.');
            }
            if (($psNueva['estado'] ?? '') !== 'disponible') {
                throw new InvalidArgumentException('La pieza nueva debe estar en estado disponible.');
            }

            $tiendaOrigen = (int) ($psOrigen['id_tienda_pieza'] ?? 0);
            $tiendaNueva = (int) ($psNueva['id_tienda_pieza'] ?? 0);
            if ($tiendaOrigen <= 0 || $tiendaNueva <= 0 || $tiendaOrigen !== $tiendaNueva) {
                throw new InvalidArgumentException('La pieza nueva debe pertenecer a la misma tienda que la pieza del apartado.');
            }

            $creditoStr = $this->sumaAbonosCobroTienda($db, $idOrigen);
            $credito = (float) $creditoStr;
            if ($credito <= 0) {
                throw new InvalidArgumentException('No hay abonos en tienda registrados para trasladar como credito.');
            }

            $precioLista = (float) $this->normalizarDecimal($psNueva['precio_venta'] ?? 0);
            if ($precioLista <= 0) {
                throw new InvalidArgumentException('La pieza nueva no tiene precio de venta valido.');
            }
            $idClienteApr = (int) ($apOrigen['id_cliente_FK'] ?? 0);
            $precioNuevoStr = $this->precioApartadoConDescuentoCliente($precioLista, $idClienteApr);
            $precioNuevo = (float) $precioNuevoStr;

            if ($credito - $precioNuevo > 0.02) {
                throw new InvalidArgumentException(
                    'El monto abonado en tienda (' . $creditoStr
                    . ') supera el precio de la nueva pieza (' . $precioNuevoStr
                    . '). Elige una pieza de mayor o igual precio.'
                );
            }

            $montoCreditoStr = $this->normalizarDecimal(min($credito, $precioNuevo));
            $saldoDestStr = $this->normalizarDecimal($precioNuevo - (float) $montoCreditoStr);

            $impuestoOrigen = $this->normalizarDecimal($apOrigen['impuesto_monto'] ?? 0);
            $fechaVenc = $apOrigen['fecha_vencimiento'] ?? null;
            if ($fechaVenc === null || $fechaVenc === '') {
                throw new InvalidArgumentException('El apartado origen no tiene fecha de vencimiento.');
            }

            $insAd = $db->prepare(
                "INSERT INTO apartados
                    (id_cliente_FK, id_empleado_FK, fecha_vencimiento, total_apartado, saldo_pendiente, estado, impuesto_monto)
                 VALUES
                    (:id_cliente, :id_emp, :fv, :total, :saldo, 'activo', :imp)"
            );
            $insAd->bindValue(':id_cliente', (int) $apOrigen['id_cliente_FK'], PDO::PARAM_INT);
            $insAd->bindValue(':id_emp', $idEmpleado, PDO::PARAM_INT);
            $insAd->bindValue(':fv', (string) $fechaVenc, PDO::PARAM_STR);
            $insAd->bindValue(':total', $precioNuevoStr, PDO::PARAM_STR);
            $insAd->bindValue(':saldo', $saldoDestStr, PDO::PARAM_STR);
            $insAd->bindValue(':imp', $impuestoOrigen, PDO::PARAM_STR);
            $insAd->execute();
            $idDestino = (int) $db->lastInsertId();
            if ($idDestino <= 0) {
                throw new RuntimeException('No se pudo crear el apartado destino.');
            }

            $insDet = $db->prepare(
                'INSERT INTO apartado_detalle (id_apartado_FK, id_pieza_stock_FK, precio_apartado) VALUES (:a, :p, :pr)'
            );
            $insDet->bindValue(':a', $idDestino, PDO::PARAM_INT);
            $insDet->bindValue(':p', $idPiezaNueva, PDO::PARAM_INT);
            $insDet->bindValue(':pr', $precioNuevoStr, PDO::PARAM_STR);
            $insDet->execute();

            $insPago = $db->prepare(
                "INSERT INTO apartado_pagos
                    (id_apartado_FK, monto, id_forma_pago_FK, estado, referencia, id_usuario_FK, tipo_origen, id_apartado_credito_origen_FK)
                 VALUES
                    (:a, :m, :fp, 'registrado', :ref, :u, 'credito_por_cambio', :origen)"
            );
            $ref = 'CAMBIO_PIEZA desde apartado #' . $idOrigen;
            $insPago->bindValue(':a', $idDestino, PDO::PARAM_INT);
            $insPago->bindValue(':m', $montoCreditoStr, PDO::PARAM_STR);
            $insPago->bindValue(':fp', $idFormaPagoCredito, PDO::PARAM_INT);
            $insPago->bindValue(':ref', $ref, PDO::PARAM_STR);
            $insPago->bindValue(':u', $idUsuario, PDO::PARAM_INT);
            $insPago->bindValue(':origen', $idOrigen, PDO::PARAM_INT);
            $insPago->execute();
            $idPagoCredito = (int) $db->lastInsertId();

            $updPsO = $db->prepare("UPDATE piezas_stock SET estado = 'disponible' WHERE id_pieza_stock = :id");
            $updPsO->bindValue(':id', $idPiezaOrigen, PDO::PARAM_INT);
            $updPsO->execute();

            $updPsN = $db->prepare("UPDATE piezas_stock SET estado = 'apartada' WHERE id_pieza_stock = :id");
            $updPsN->bindValue(':id', $idPiezaNueva, PDO::PARAM_INT);
            $updPsN->execute();

            $insMovLib = $db->prepare(
                "INSERT INTO movimientos_inventario
                    (id_pieza_stock_FK, tipo_movimiento, referencia, observaciones, id_usuario_FK, id_tienda_origen_FK, id_apartado_FK, tipo_referencia)
                 VALUES
                    (:ps, 'liberado', 'CAMBIO_APARTADO', :obs, :u, :t, :ap, 'apartado')"
            );
            $insMovLib->bindValue(':ps', $idPiezaOrigen, PDO::PARAM_INT);
            $insMovLib->bindValue(':obs', $obs !== '' ? $obs : null, $obs !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $insMovLib->bindValue(':u', $idUsuario, PDO::PARAM_INT);
            $insMovLib->bindValue(':t', $tiendaOrigen, PDO::PARAM_INT);
            $insMovLib->bindValue(':ap', $idOrigen, PDO::PARAM_INT);
            $insMovLib->execute();

            $insMovApr = $db->prepare(
                "INSERT INTO movimientos_inventario
                    (id_pieza_stock_FK, tipo_movimiento, referencia, observaciones, id_usuario_FK, id_tienda_origen_FK, id_apartado_FK, tipo_referencia)
                 VALUES
                    (:ps, 'apartado', 'CAMBIO_APARTADO', :obs, :u, :t, :ap, 'apartado')"
            );
            $insMovApr->bindValue(':ps', $idPiezaNueva, PDO::PARAM_INT);
            $insMovApr->bindValue(':obs', $obs !== '' ? $obs : null, $obs !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $insMovApr->bindValue(':u', $idUsuario, PDO::PARAM_INT);
            $insMovApr->bindValue(':t', $tiendaNueva, PDO::PARAM_INT);
            $insMovApr->bindValue(':ap', $idDestino, PDO::PARAM_INT);
            $insMovApr->execute();

            $updAo = $db->prepare(
                "UPDATE apartados SET estado = 'reemplazado', saldo_pendiente = 0.00 WHERE id_apartado = :id"
            );
            $updAo->bindValue(':id', $idOrigen, PDO::PARAM_INT);
            $updAo->execute();

            $insCambio = $db->prepare(
                "INSERT INTO apartado_cambios_pieza
                    (id_apartado_origen_FK, id_apartado_destino_FK, id_pieza_stock_origen_FK, id_pieza_stock_destino_FK,
                     monto_credito_aplicado, id_pago_credito_FK, observaciones, id_empleado_FK, id_usuario_FK, tipo_operacion, id_apartado_detalle_FK)
                 VALUES
                    (:o, :d, :pso, :psn, :m, :pago, :obs, :emp, :usr, 'nuevo_apartado', NULL)"
            );
            $insCambio->bindValue(':o', $idOrigen, PDO::PARAM_INT);
            $insCambio->bindValue(':d', $idDestino, PDO::PARAM_INT);
            $insCambio->bindValue(':pso', $idPiezaOrigen, PDO::PARAM_INT);
            $insCambio->bindValue(':psn', $idPiezaNueva, PDO::PARAM_INT);
            $insCambio->bindValue(':m', $montoCreditoStr, PDO::PARAM_STR);
            $insCambio->bindValue(':pago', $idPagoCredito, PDO::PARAM_INT);
            $insCambio->bindValue(':obs', $obs !== '' ? $obs : null, $obs !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $insCambio->bindValue(':emp', $idEmpleado, PDO::PARAM_INT);
            $insCambio->bindValue(':usr', $idUsuario, PDO::PARAM_INT);
            $insCambio->execute();
            $idCambio = (int) $db->lastInsertId();

            $db->commit();

            return [
                'id_apartado_destino' => $idDestino,
                'id_apartado_cambio' => $idCambio,
                'monto_credito_aplicado' => $montoCreditoStr,
                'saldo_pendiente_destino' => $saldoDestStr,
            ];
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }
}
