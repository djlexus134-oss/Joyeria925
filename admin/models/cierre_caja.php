<?php

require_once __DIR__ . '/../../sistema.class.php';

class CierreCaja extends Sistema
{
    /** Ventas que siguen contando en caja aunque queden marcadas devueltas por canje POS. */
    private const SQL_VENTAS_ESTADO_CAJA = "v.estado IN ('completada', 'devuelta')";

    private function normalizarDecimal($valor, int $decimales = 2): string
    {
        $numero = (float) $valor;
        $epsilon = pow(10, -($decimales + 3));
        $ajustado = $numero + ($numero >= 0 ? $epsilon : -$epsilon);

        return number_format(round($ajustado, $decimales), $decimales, '.', '');
    }

    public function validarFechaOperacion(string $fecha): string
    {
        $fecha = trim($fecha);
        if ($fecha === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            throw new InvalidArgumentException('Fecha invalida. Use formato AAAA-MM-DD.');
        }
        $parts = explode('-', $fecha);
        if (count($parts) !== 3 || !checkdate((int) $parts[1], (int) $parts[2], (int) $parts[0])) {
            throw new InvalidArgumentException('La fecha no es un dia valido del calendario.');
        }

        return $fecha;
    }

    public function tieneColumnaEsEfectivo(): bool
    {
        try {
            $stmt = $this->getDb()->query("SHOW COLUMNS FROM forma_pago LIKE 'es_efectivo'");

            return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return false;
        }
    }

    public function tieneColumnaSaldoInicial(): bool
    {
        try {
            $stmt = $this->getDb()->query("SHOW COLUMNS FROM cierre_caja LIKE 'saldo_inicial'");

            return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Saldo con el que deberia abrirse la caja en la fecha indicada.
     *
     * @return array{saldo_inicial: float, origen: string, fecha_referencia: ?string, mensaje: string}
     */
    public function obtenerSaldoInicialSugerido(string $fechaYmd): array
{
    $fecha = $this->validarFechaOperacion($fechaYmd);
    $db = $this->getDb();
    $usarSaldoInicial = $this->tieneColumnaSaldoInicial();

    // Seleccionar el campo correcto según el esquema
    $campoCarry = $usarSaldoInicial ? 'saldo_inicial' : 'efectivo_contado';
    $condNotNull = $usarSaldoInicial
        ? 'saldo_inicial IS NOT NULL'
        : 'efectivo_contado IS NOT NULL';

    $stmt = $db->prepare(
        "SELECT fecha_operacion, $campoCarry AS valor_carry
         FROM cierre_caja
         WHERE fecha_operacion < :fecha
           AND $condNotNull
         ORDER BY fecha_operacion DESC
         LIMIT 1"
    );
    $stmt->bindValue(':fecha', $fecha, PDO::PARAM_STR);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (is_array($row) && $row['valor_carry'] !== null && $row['valor_carry'] !== '') {
        $ref = (string) $row['fecha_operacion'];
        $etiqueta = $usarSaldoInicial ? 'saldo inicial registrado' : 'efectivo contado';

        return [
            'saldo_inicial' => (float) $row['valor_carry'],
            'origen' => 'cierre_anterior',
            'fecha_referencia' => $ref,
            'mensaje' => 'Tomado del ' . $etiqueta . ' al cierre del ' . $ref . '.',
        ];
    }

    return [
        'saldo_inicial' => 0.0,
        'origen' => 'sin_referencia',
        'fecha_referencia' => null,
        'mensaje' => 'No hay cierre anterior con arqueo. Capture el saldo inicial manualmente si aplica.',
    ];
    }


    private function sumarApartadosEfectivo(PDO $db, string $fecha): float
    {
        try {
            $stmt = $db->prepare(
                "SELECT COALESCE(SUM(ap.monto), 0) AS total
                 FROM apartado_pagos ap
                 INNER JOIN forma_pago fp ON fp.id_forma_pago = ap.id_forma_pago_FK
                 WHERE ap.estado = 'registrado'
                   AND DATE(ap.fecha_registro) = :fecha
                   AND fp.activo = 1
                   AND fp.es_efectivo = 1
                   AND COALESCE(ap.tipo_origen, 'cobro_tienda') NOT IN ('credito_cliente', 'credito_por_cambio')"
            );
            $stmt->bindValue(':fecha', $fecha, PDO::PARAM_STR);
            $stmt->execute();

            return (float) $stmt->fetchColumn();
        } catch (Throwable $e) {
            return 0.0;
        }
    }

    private function sumarTallerEfectivo(PDO $db, string $fecha): float
    {
        try {
            $stmt = $db->prepare(
                "SELECT COALESCE(SUM(otp.monto), 0) AS total
                 FROM ordenes_taller_pagos otp
                 INNER JOIN forma_pago fp ON fp.id_forma_pago = otp.id_forma_pago_FK
                 WHERE otp.estado = 'registrado'
                   AND DATE(otp.fecha_registro) = :fecha
                   AND fp.activo = 1
                   AND fp.es_efectivo = 1
                   AND COALESCE(otp.tipo_origen, 'cobro_tienda') = 'cobro_tienda'"
            );
            $stmt->bindValue(':fecha', $fecha, PDO::PARAM_STR);
            $stmt->execute();

            return (float) $stmt->fetchColumn();
        } catch (Throwable $e) {
            return 0.0;
        }
    }

    private function sumarCanjeInternoNetoDia(PDO $db, string $fecha): float
    {
        try {
            $stmt = $db->prepare(
                'SELECT COALESCE(SUM(vp.monto), 0) AS total
                 FROM venta_pagos vp
                 INNER JOIN ventas v ON v.id_venta = vp.id_venta_FK
                 INNER JOIN forma_pago fp ON fp.id_forma_pago = vp.id_forma_pago_FK
                 WHERE ' . self::SQL_VENTAS_ESTADO_CAJA . '
                   AND DATE(v.fecha_venta) = :fecha
                   AND fp.activo = 1
                   AND fp.forma_pago = :forma_canje'
            );
            $stmt->bindValue(':fecha', $fecha, PDO::PARAM_STR);
            $stmt->bindValue(':forma_canje', 'Canje interno (sin efectivo)', PDO::PARAM_STR);
            $stmt->execute();

            return (float) $stmt->fetchColumn();
        } catch (Throwable $e) {
            return 0.0;
        }
    }

    /**
     * Movimiento neto del dia en efectivo (sin saldo inicial).
     */
    public function calcularMovimientoEfectivoDia(string $fechaYmd): array
    {
        if (!$this->tieneColumnaEsEfectivo()) {
            throw new RuntimeException(
                'Falta la columna forma_pago.es_efectivo. Ejecuta el script sql/2026_05_15_cierre_caja_modulo.sql en la base de datos.'
            );
        }

        $fecha = $this->validarFechaOperacion($fechaYmd);
        $db = $this->getDb();

        $stmtVen = $db->prepare(
            'SELECT COALESCE(SUM(vp.monto), 0) AS total
             FROM venta_pagos vp
             INNER JOIN ventas v ON v.id_venta = vp.id_venta_FK
             INNER JOIN forma_pago fp ON fp.id_forma_pago = vp.id_forma_pago_FK
             WHERE ' . self::SQL_VENTAS_ESTADO_CAJA . '
               AND DATE(v.fecha_venta) = :fecha
               AND fp.activo = 1
               AND fp.es_efectivo = 1'
        );
        $stmtVen->bindValue(':fecha', $fecha, PDO::PARAM_STR);
        $stmtVen->execute();
        $ventasEfectivo = (float) $stmtVen->fetchColumn();

        $stmtGas = $db->prepare(
            'SELECT COALESCE(SUM(g.monto), 0) AS total
             FROM gastos g
             INNER JOIN forma_pago fp ON fp.id_forma_pago = g.id_forma_pago_FK
             WHERE DATE(g.fecha_gasto) = :fecha
               AND g.afecta_caja = 1
               AND fp.activo = 1
               AND fp.es_efectivo = 1'
        );
        $stmtGas->bindValue(':fecha', $fecha, PDO::PARAM_STR);
        $stmtGas->execute();
        $gastosEfectivo = (float) $stmtGas->fetchColumn();

        $devolucionesEfectivo = 0.0;
        try {
            $stmtDev = $db->prepare(
                'SELECT COALESCE(SUM(d.monto_reembolso), 0) AS total
                 FROM devoluciones d
                 INNER JOIN forma_pago fp ON fp.id_forma_pago = d.id_forma_pago_FK
                 WHERE DATE(d.fecha_devolucion) = :fecha
                   AND d.monto_reembolso > 0
                   AND d.id_forma_pago_FK IS NOT NULL
                   AND fp.activo = 1
                   AND fp.es_efectivo = 1'
            );
            $stmtDev->bindValue(':fecha', $fecha, PDO::PARAM_STR);
            $stmtDev->execute();
            $devolucionesEfectivo = (float) $stmtDev->fetchColumn();
        } catch (Throwable $e) {
            $devolucionesEfectivo = 0.0;
        }

        $apartadosEfectivo = $this->sumarApartadosEfectivo($db, $fecha);
        $tallerEfectivo = $this->sumarTallerEfectivo($db, $fecha);
        $canjeInternoNeto = $this->sumarCanjeInternoNetoDia($db, $fecha);

        $movimiento = $ventasEfectivo + $apartadosEfectivo + $tallerEfectivo - $gastosEfectivo - $devolucionesEfectivo;

        return [
            'fecha' => $fecha,
            'ventas_efectivo' => $ventasEfectivo,
            'apartados_efectivo' => $apartadosEfectivo,
            'taller_efectivo' => $tallerEfectivo,
            'gastos_efectivo' => $gastosEfectivo,
            'devoluciones_efectivo' => $devolucionesEfectivo,
            'canje_interno_neto_dia' => $canjeInternoNeto,
            'movimiento_efectivo_dia' => $movimiento,
        ];
    }

  /**
     * Calcula totales del dia incluyendo saldo inicial y efectivo esperado en caja al cierre.
     *
     * @param float|null $saldoInicialManual Si es null, usa el saldo sugerido del cierre anterior.
     */
    public function calcularResumen(string $fechaYmd, ?float $saldoInicialManual = null): array
    {
        $mov = $this->calcularMovimientoEfectivoDia($fechaYmd);
        $fecha = $mov['fecha'];

        $sugerido = $this->obtenerSaldoInicialSugerido($fecha);
        $saldoInicial = $saldoInicialManual !== null
            ? (float) $saldoInicialManual
            : (float) $sugerido['saldo_inicial'];
        $saldoOrigen = $saldoInicialManual !== null ? 'manual' : (string) $sugerido['origen'];

        $efectivoEsperado = $saldoInicial + (float) $mov['movimiento_efectivo_dia'];

        $db = $this->getDb();
        $porForma = [];

        $sqlFormas = 'SELECT fp.id_forma_pago,
                             fp.forma_pago,
                             fp.es_efectivo,
                             COALESCE(ven.total, 0) AS monto_ventas,
                             COALESCE(gas.total, 0) AS monto_gastos,
                             COALESCE(dev.total, 0) AS monto_devoluciones
                      FROM forma_pago fp
                      LEFT JOIN (
                          SELECT vp.id_forma_pago_FK AS id_fp, SUM(vp.monto) AS total
                          FROM venta_pagos vp
                          INNER JOIN ventas v ON v.id_venta = vp.id_venta_FK
                          WHERE ' . self::SQL_VENTAS_ESTADO_CAJA . '
                            AND DATE(v.fecha_venta) = :fecha_ven
                          GROUP BY vp.id_forma_pago_FK
                      ) ven ON ven.id_fp = fp.id_forma_pago
                      LEFT JOIN (
                          SELECT g.id_forma_pago_FK AS id_fp, SUM(g.monto) AS total
                          FROM gastos g
                          WHERE DATE(g.fecha_gasto) = :fecha_gas
                            AND g.afecta_caja = 1
                            AND g.id_forma_pago_FK IS NOT NULL
                          GROUP BY g.id_forma_pago_FK
                      ) gas ON gas.id_fp = fp.id_forma_pago
                      LEFT JOIN (
                          SELECT d.id_forma_pago_FK AS id_fp, SUM(d.monto_reembolso) AS total
                          FROM devoluciones d
                          WHERE DATE(d.fecha_devolucion) = :fecha_dev
                            AND d.monto_reembolso > 0
                            AND d.id_forma_pago_FK IS NOT NULL
                          GROUP BY d.id_forma_pago_FK
                      ) dev ON dev.id_fp = fp.id_forma_pago
                      WHERE fp.activo = 1
                      HAVING ABS(monto_ventas) > 0.00001
                          OR ABS(monto_gastos) > 0.00001
                          OR ABS(monto_devoluciones) > 0.00001
                      ORDER BY fp.forma_pago ASC';

        try {
            $stmtFormas = $db->prepare($sqlFormas);
            $stmtFormas->bindValue(':fecha_ven', $fecha, PDO::PARAM_STR);
            $stmtFormas->bindValue(':fecha_gas', $fecha, PDO::PARAM_STR);
            $stmtFormas->bindValue(':fecha_dev', $fecha, PDO::PARAM_STR);
            $stmtFormas->execute();
            $porForma = $stmtFormas->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            $porForma = [];
        }

        foreach ($porForma as &$row) {
            $row['id_forma_pago'] = (int) $row['id_forma_pago'];
            $row['es_efectivo'] = (int) $row['es_efectivo'];
            $row['monto_ventas'] = (float) $row['monto_ventas'];
            $row['monto_gastos'] = (float) $row['monto_gastos'];
            $row['monto_devoluciones'] = (float) $row['monto_devoluciones'];
        }
        unset($row);

        return array_merge($mov, [
            'saldo_inicial' => $saldoInicial,
            'saldo_inicial_origen' => $saldoOrigen,
            'saldo_inicial_fecha_ref' => $sugerido['fecha_referencia'],
            'saldo_inicial_mensaje' => (string) $sugerido['mensaje'],
            'efectivo_esperado' => $efectivoEsperado,
            'por_forma_pago' => $porForma,
        ]);
    }

      /**
     * Arqueo en vivo: descuadre sin guardar cierre.
     *
     * @return array<string, mixed>
     */
    public function calcularArqueo(string $fechaYmd, ?float $saldoInicial, ?float $efectivoContado): array
    {
        $resumen = $this->calcularResumen($fechaYmd, $saldoInicial);

        $descuadre = null;
        $contadoNorm = null;
        if ($efectivoContado !== null && trim((string) $efectivoContado) !== '') {
            if (!is_numeric($efectivoContado)) {
                throw new InvalidArgumentException('El efectivo contado debe ser un numero valido.');
            }
            $contadoNorm = (float) $this->normalizarDecimal((float) $efectivoContado);
            $esperado = (float) $this->normalizarDecimal((float) $resumen['efectivo_esperado']);
            $descuadre = (float) $this->normalizarDecimal($contadoNorm - $esperado);
        }

        return array_merge($resumen, [
            'efectivo_contado' => $contadoNorm,
            'descuadre' => $descuadre,
            'cuadra' => $descuadre !== null ? abs($descuadre) < 0.01 : null,
        ]);
    }

    public function existeCierreParaFecha(string $fechaYmd): bool
    {
        $fecha = $this->validarFechaOperacion($fechaYmd);
        $stmt = $this->getDb()->prepare('SELECT 1 FROM cierre_caja WHERE fecha_operacion = :f LIMIT 1');
        $stmt->bindValue(':f', $fecha, PDO::PARAM_STR);
        $stmt->execute();

        return (bool) $stmt->fetchColumn();
    }

    public function leerPorFecha(string $fechaYmd): ?array
    {
        $fecha = $this->validarFechaOperacion($fechaYmd);
        $stmt = $this->getDb()->prepare(
            'SELECT c.*,
                    CONCAT(u.nombre, \' \', u.primer_apellido, COALESCE(CONCAT(\' \', u.segundo_apellido), \'\')) AS usuario_nombre
             FROM cierre_caja c
             INNER JOIN usuarios u ON u.id_usuario = c.id_usuario_FK
             WHERE c.fecha_operacion = :f
             LIMIT 1'
        );
        $stmt->bindValue(':f', $fecha, PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function leerHistorial(int $limit = 60): array
    {
        $limit = max(1, min($limit, 500));
        $stmt = $this->getDb()->prepare(
            'SELECT c.*,
                    CONCAT(u.nombre, \' \', u.primer_apellido, COALESCE(CONCAT(\' \', u.segundo_apellido), \'\')) AS usuario_nombre
             FROM cierre_caja c
             INNER JOIN usuarios u ON u.id_usuario = c.id_usuario_FK
             ORDER BY c.fecha_operacion DESC, c.id_cierre_caja DESC
             LIMIT ' . (int) $limit
        );
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @param array{fecha_operacion?:string, saldo_inicial?:string|null, efectivo_contado?:string|null, observaciones?:string|null} $data
     */
    public function crear(int $idUsuario, array $data): int
    {
        if ($idUsuario <= 0) {
            throw new InvalidArgumentException('Usuario invalido para registrar el cierre.');
        }

        $fecha = $this->validarFechaOperacion((string) ($data['fecha_operacion'] ?? ''));

        if ($this->existeCierreParaFecha($fecha)) {
            throw new InvalidArgumentException('Ya existe un cierre registrado para esa fecha.');
        }

        $saldoInicialInput = null;
        if (isset($data['saldo_inicial']) && trim((string) $data['saldo_inicial']) !== '') {
            if (!is_numeric($data['saldo_inicial'])) {
                throw new InvalidArgumentException('El saldo inicial debe ser un numero valido.');
            }
            $saldoInicialInput = (float) $data['saldo_inicial'];
        }

        $resumen = $this->calcularResumen($fecha, $saldoInicialInput);
        $saldoStr = $this->normalizarDecimal($resumen['saldo_inicial']);
        $esperadoStr = $this->normalizarDecimal($resumen['efectivo_esperado']);

        $contadoStr = null;
        $diferenciaStr = null;
        $rawContado = $data['efectivo_contado'] ?? null;
        if ($rawContado !== null && trim((string) $rawContado) !== '') {
            if (!is_numeric($rawContado)) {
                throw new InvalidArgumentException('El efectivo contado debe ser un numero valido.');
            }
            $contadoStr = $this->normalizarDecimal((float) $rawContado);
            $diferenciaStr = $this->normalizarDecimal((float) $contadoStr - (float) $esperadoStr);
        }

        $obs = isset($data['observaciones']) ? trim(strip_tags((string) $data['observaciones'])) : '';
        if (mb_strlen($obs) > 500) {
            $obs = mb_substr($obs, 0, 500);
        }
        $obsNull = $obs === '' ? null : $obs;

        $payloadJson = json_encode($resumen, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $db = $this->getDb();
        $db->beginTransaction();
        try {
            if ($this->tieneColumnaSaldoInicial()) {
                $stmt = $db->prepare(
                    'INSERT INTO cierre_caja
                        (fecha_operacion, saldo_inicial, efectivo_esperado, efectivo_contado, diferencia, observaciones, resumen_json, id_usuario_FK)
                     VALUES
                        (:fecha, :saldo_ini, :esperado, :contado, :diferencia, :obs, :json, :usuario)'
                );
                $stmt->bindValue(':saldo_ini', $saldoStr, PDO::PARAM_STR);
            } else {
                $stmt = $db->prepare(
                    'INSERT INTO cierre_caja
                        (fecha_operacion, efectivo_esperado, efectivo_contado, diferencia, observaciones, resumen_json, id_usuario_FK)
                     VALUES
                        (:fecha, :esperado, :contado, :diferencia, :obs, :json, :usuario)'
                );
            }

            $stmt->bindValue(':fecha', $fecha, PDO::PARAM_STR);
            $stmt->bindValue(':esperado', $esperadoStr, PDO::PARAM_STR);
            $stmt->bindValue(':contado', $contadoStr, $contadoStr === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':diferencia', $diferenciaStr, $diferenciaStr === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':obs', $obsNull, $obsNull === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':json', $payloadJson, PDO::PARAM_STR);
            $stmt->bindValue(':usuario', $idUsuario, PDO::PARAM_INT);
            $stmt->execute();
            $id = (int) $db->lastInsertId();
            $db->commit();

            return $id;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }
}
