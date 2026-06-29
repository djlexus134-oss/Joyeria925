<?php
require_once __DIR__ . '/../../sistema.class.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/list_search.php';

class OrdenesTaller extends Sistema
{
    const TABLE = 'ordenes_taller';

    const ESTADOS = ['recibida', 'en_taller', 'lista', 'entregada', 'cancelada'];
    const TIPOS = ['reparacion', 'modificacion'];
    const ORIGENES = ['inventario', 'cliente'];

    public function leer(?string $busqueda = null): array
    {
        $pat = joyeria_like_pattern($busqueda);
        $sql = "SELECT ot.*,
                       t.nombre AS taller_nombre,
                       ps.codigo_auxiliar,
                       ps.codigo_barras,
                       p.desc_pieza,
                       CONCAT(u.nombre, ' ', u.primer_apellido, COALESCE(CONCAT(' ', u.segundo_apellido), '')) AS cliente_nombre
                FROM " . self::TABLE . " ot
                LEFT JOIN talleres t ON t.id_taller = ot.id_taller_FK
                LEFT JOIN piezas_stock ps ON ps.id_pieza_stock = ot.id_pieza_stock_FK
                LEFT JOIN piezas p ON p.id_pieza = ps.id_pieza_FK
                LEFT JOIN clientes c ON c.id_cliente = ot.id_cliente_FK
                LEFT JOIN usuarios u ON u.id_usuario = c.id_usuario_FK
                WHERE ot.activo = 1";

        if ($pat !== null) {
            $sql .= " AND (
                ot.folio LIKE :busq OR ot.pieza_descripcion LIKE :busq2
                OR ot.descripcion_problema LIKE :busq3 OR ot.estado LIKE :busq4
                OR IFNULL(t.nombre, '') LIKE :busq5
                OR IFNULL(ps.codigo_auxiliar, '') LIKE :busq6
                OR IFNULL(ps.codigo_barras, '') LIKE :busq7
                OR IFNULL(p.desc_pieza, '') LIKE :busq8
                OR IFNULL(CONCAT(u.nombre, ' ', u.primer_apellido), '') LIKE :busq9
                OR CAST(ot.id_orden_taller AS CHAR) LIKE :busq10
            )";
        }

        $sql .= " ORDER BY ot.id_orden_taller DESC";

        $stmt = $this->getDb()->prepare($sql);
        if ($pat !== null) {
            $stmt->bindValue(':busq', $pat, PDO::PARAM_STR);
            $stmt->bindValue(':busq2', $pat, PDO::PARAM_STR);
            $stmt->bindValue(':busq3', $pat, PDO::PARAM_STR);
            $stmt->bindValue(':busq4', $pat, PDO::PARAM_STR);
            $stmt->bindValue(':busq5', $pat, PDO::PARAM_STR);
            $stmt->bindValue(':busq6', $pat, PDO::PARAM_STR);
            $stmt->bindValue(':busq7', $pat, PDO::PARAM_STR);
            $stmt->bindValue(':busq8', $pat, PDO::PARAM_STR);
            $stmt->bindValue(':busq9', $pat, PDO::PARAM_STR);
            $stmt->bindValue(':busq10', $pat, PDO::PARAM_STR);
        }
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function leerUno(int $id): ?array
    {
        $sql = "SELECT ot.*,
                       t.nombre AS taller_nombre,
                       t.contacto AS taller_contacto,
                       t.telefono AS taller_telefono,
                       ps.codigo_auxiliar,
                       ps.codigo_barras,
                       ps.estado AS stock_estado,
                       p.desc_pieza,
                       CONCAT(u.nombre, ' ', u.primer_apellido, COALESCE(CONCAT(' ', u.segundo_apellido), '')) AS cliente_nombre,
                       u.telefono AS cliente_telefono
                FROM " . self::TABLE . " ot
                LEFT JOIN talleres t ON t.id_taller = ot.id_taller_FK
                LEFT JOIN piezas_stock ps ON ps.id_pieza_stock = ot.id_pieza_stock_FK
                LEFT JOIN piezas p ON p.id_pieza = ps.id_pieza_FK
                LEFT JOIN clientes c ON c.id_cliente = ot.id_cliente_FK
                LEFT JOIN usuarios u ON u.id_usuario = c.id_usuario_FK
                WHERE ot.id_orden_taller = :id AND ot.activo = 1";

        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function crear(array $data, int $idUsuario): int
    {
        if ($idUsuario <= 0) {
            throw new InvalidArgumentException('Usuario de sesion no valido.');
        }

        $origen = $this->validarEnum($data, 'origen', self::ORIGENES, 'El origen');
        $tipo = $this->validarEnum($data, 'tipo', self::TIPOS, 'El tipo');
        $descripcionProblema = $this->validarTexto($data, 'descripcion_problema', 2000, 'La descripcion del problema');
        $costoTotal = $this->validarDecimalNoNegativo($data, 'costo_total', 'El costo total');
        $observaciones = $this->validarTextoOpcional($data, 'observaciones', 500);
        $fechaCompromiso = $this->validarFechaOpcional($data, 'fecha_compromiso');
        $costoTaller = $this->validarDecimalOpcional($data, 'costo_taller');

        $idPiezaStock = null;
        $piezaDescripcion = null;

        if ($origen === 'inventario') {
            $idPiezaStock = $this->validarEnteroPositivo($data, 'id_pieza_stock_FK', 'La pieza de inventario');
            $stock = $this->obtenerStockActivo($idPiezaStock);
            if (!$stock) {
                throw new InvalidArgumentException('La pieza de inventario no existe o no esta activa.');
            }
            if ((string) $stock['estado'] === 'vendida') {
                throw new InvalidArgumentException('No se puede crear una orden para una pieza vendida.');
            }
            $piezaDescripcion = trim((string) ($stock['desc_pieza'] ?? ''));
            if ($piezaDescripcion === '') {
                $piezaDescripcion = 'Pieza #' . $idPiezaStock;
            }
        } else {
            $piezaDescripcion = $this->validarTexto($data, 'pieza_descripcion', 255, 'La descripcion de la pieza');
        }

        $idCliente = $this->validarEnteroOpcional($data, 'id_cliente_FK');
        if ($idCliente !== null) {
            $this->verificarClienteActivo($idCliente);
        }

        $idTaller = $this->validarEnteroOpcional($data, 'id_taller_FK');
        if ($idTaller !== null) {
            $this->verificarTallerActivo($idTaller);
        }

        $anticipo = $this->validarDecimalOpcional($data, 'anticipo_monto', 0.0);
        $idFormaAnticipo = $this->validarEnteroOpcional($data, 'id_forma_pago_anticipo');

        if ($anticipo > 0 && $idFormaAnticipo === null) {
            throw new InvalidArgumentException('Seleccione la forma de pago del anticipo.');
        }
        if ($anticipo > (float) $costoTotal + 0.01) {
            throw new InvalidArgumentException('El anticipo no puede ser mayor al costo total.');
        }

        $db = $this->getDb();
        $db->beginTransaction();
        try {
            auth_mysql_set_audit_vars($db, $idUsuario);

            $saldoStr = $this->normalizarDecimal((float) $costoTotal - $anticipo);

            $stmt = $db->prepare(
                "INSERT INTO " . self::TABLE . "
                    (folio, origen, id_pieza_stock_FK, pieza_descripcion, id_cliente_FK, id_taller_FK,
                     tipo, descripcion_problema, estado, costo_total, saldo_pendiente, costo_taller,
                     fecha_compromiso, observaciones, id_usuario_FK, activo)
                 VALUES
                    ('PENDIENTE', :origen, :id_stock, :pieza_desc, :id_cliente, :id_taller,
                     :tipo, :desc_prob, 'recibida', :costo_total, :saldo, :costo_taller,
                     :fecha_comp, :obs, :id_usuario, 1)"
            );
            $stmt->bindValue(':origen', $origen, PDO::PARAM_STR);
            $stmt->bindValue(':id_stock', $idPiezaStock, $idPiezaStock === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(':pieza_desc', $piezaDescripcion, PDO::PARAM_STR);
            $stmt->bindValue(':id_cliente', $idCliente, $idCliente === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(':id_taller', $idTaller, $idTaller === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(':tipo', $tipo, PDO::PARAM_STR);
            $stmt->bindValue(':desc_prob', $descripcionProblema, PDO::PARAM_STR);
            $stmt->bindValue(':costo_total', $costoTotal, PDO::PARAM_STR);
            $stmt->bindValue(':saldo', $saldoStr, PDO::PARAM_STR);
            $stmt->bindValue(':costo_taller', $costoTaller, $costoTaller === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':fecha_comp', $fechaCompromiso, $fechaCompromiso === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':obs', $observaciones, $observaciones === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':id_usuario', $idUsuario, PDO::PARAM_INT);
            $stmt->execute();

            $idOrden = (int) $db->lastInsertId();
            $folio = $this->generarFolio($idOrden);

            $updFolio = $db->prepare('UPDATE ' . self::TABLE . ' SET folio = :folio WHERE id_orden_taller = :id');
            $updFolio->bindValue(':folio', $folio, PDO::PARAM_STR);
            $updFolio->bindValue(':id', $idOrden, PDO::PARAM_INT);
            $updFolio->execute();

            $this->insertarHistorial($db, $idOrden, 'recibida', 'Orden creada', $idUsuario);

            if ($origen === 'inventario' && $idPiezaStock !== null) {
                $this->actualizarEstadoStock($db, $idPiezaStock, 'reparacion');
            }

            if ($anticipo > 0 && $idFormaAnticipo !== null) {
                $this->verificarFormaPago($db, $idFormaAnticipo);
                $this->insertarPago($db, $idOrden, $anticipo, $idFormaAnticipo, $idUsuario, 'Anticipo orden de taller');
            }

            $db->commit();

            return $idOrden;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public function actualizar(int $id, array $data, int $idUsuario): int
    {
        $orden = $this->leerUno($id);
        if (!$orden) {
            throw new InvalidArgumentException('La orden no existe.');
        }
        if (in_array((string) $orden['estado'], ['entregada', 'cancelada'], true)) {
            throw new InvalidArgumentException('No se puede editar una orden entregada o cancelada.');
        }

        $tipo = $this->validarEnum($data, 'tipo', self::TIPOS, 'El tipo');
        $descripcionProblema = $this->validarTexto($data, 'descripcion_problema', 2000, 'La descripcion del problema');
        $costoTotal = $this->validarDecimalNoNegativo($data, 'costo_total', 'El costo total');
        $observaciones = $this->validarTextoOpcional($data, 'observaciones', 500);
        $fechaCompromiso = $this->validarFechaOpcional($data, 'fecha_compromiso');
        $costoTaller = $this->validarDecimalOpcional($data, 'costo_taller');

        $idCliente = $this->validarEnteroOpcional($data, 'id_cliente_FK');
        if ($idCliente !== null) {
            $this->verificarClienteActivo($idCliente);
        }

        $idTaller = $this->validarEnteroOpcional($data, 'id_taller_FK');
        if ($idTaller !== null) {
            $this->verificarTallerActivo($idTaller);
        }

        $piezaDescripcion = (string) ($orden['pieza_descripcion'] ?? '');
        if ((string) $orden['origen'] === 'cliente') {
            $piezaDescripcion = $this->validarTexto($data, 'pieza_descripcion', 255, 'La descripcion de la pieza');
        }

        $db = $this->getDb();
        $db->beginTransaction();
        try {
            auth_mysql_set_audit_vars($db, $idUsuario);

            $abonado = $this->sumaAbonosRegistrados($db, $id);
            if ((float) $costoTotal + 0.01 < $abonado) {
                throw new InvalidArgumentException('El costo total no puede ser menor a los abonos ya registrados.');
            }
            $saldoStr = $this->normalizarDecimal((float) $costoTotal - $abonado);

            $stmt = $db->prepare(
                "UPDATE " . self::TABLE . " SET
                    pieza_descripcion = :pieza_desc,
                    id_cliente_FK = :id_cliente,
                    id_taller_FK = :id_taller,
                    tipo = :tipo,
                    descripcion_problema = :desc_prob,
                    costo_total = :costo_total,
                    saldo_pendiente = :saldo,
                    costo_taller = :costo_taller,
                    fecha_compromiso = :fecha_comp,
                    observaciones = :obs
                 WHERE id_orden_taller = :id AND activo = 1"
            );
            $stmt->bindValue(':pieza_desc', $piezaDescripcion, PDO::PARAM_STR);
            $stmt->bindValue(':id_cliente', $idCliente, $idCliente === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(':id_taller', $idTaller, $idTaller === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(':tipo', $tipo, PDO::PARAM_STR);
            $stmt->bindValue(':desc_prob', $descripcionProblema, PDO::PARAM_STR);
            $stmt->bindValue(':costo_total', $costoTotal, PDO::PARAM_STR);
            $stmt->bindValue(':saldo', $saldoStr, PDO::PARAM_STR);
            $stmt->bindValue(':costo_taller', $costoTaller, $costoTaller === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':fecha_comp', $fechaCompromiso, $fechaCompromiso === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':obs', $observaciones, $observaciones === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            $db->commit();

            return $stmt->rowCount();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public function cambiarEstado(int $id, string $estado, ?string $nota, int $idUsuario): int
    {
        $estado = trim($estado);
        if (!in_array($estado, self::ESTADOS, true)) {
            throw new InvalidArgumentException('Estado no valido.');
        }

        $db = $this->getDb();
        $db->beginTransaction();
        try {
            auth_mysql_set_audit_vars($db, $idUsuario);

            $st = $db->prepare('SELECT * FROM ' . self::TABLE . ' WHERE id_orden_taller = :id AND activo = 1 FOR UPDATE');
            $st->bindValue(':id', $id, PDO::PARAM_INT);
            $st->execute();
            $orden = $st->fetch(PDO::FETCH_ASSOC);
            if (!$orden) {
                throw new InvalidArgumentException('La orden no existe.');
            }

            $estadoActual = (string) $orden['estado'];
            if ($estadoActual === $estado) {
                $db->commit();
                return 0;
            }
            if (in_array($estadoActual, ['entregada', 'cancelada'], true)) {
                throw new InvalidArgumentException('La orden ya esta cerrada.');
            }

            $this->validarTransicionEstado($estadoActual, $estado);

            $fechaEntrega = null;
            if ($estado === 'entregada') {
                $fechaEntrega = date('Y-m-d H:i:s');
            }

            $upd = $db->prepare(
                "UPDATE " . self::TABLE . " SET estado = :estado, fecha_entrega = :fecha_entrega WHERE id_orden_taller = :id"
            );
            $upd->bindValue(':estado', $estado, PDO::PARAM_STR);
            $upd->bindValue(':fecha_entrega', $fechaEntrega, $fechaEntrega === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $upd->bindValue(':id', $id, PDO::PARAM_INT);
            $upd->execute();

            $this->insertarHistorial($db, $id, $estado, $nota, $idUsuario);

            if ((string) $orden['origen'] === 'inventario' && !empty($orden['id_pieza_stock_FK'])) {
                $idStock = (int) $orden['id_pieza_stock_FK'];
                if (in_array($estado, ['entregada', 'cancelada'], true)) {
                    $this->actualizarEstadoStock($db, $idStock, 'disponible');
                } elseif ($estado !== 'recibida') {
                    $this->actualizarEstadoStock($db, $idStock, 'reparacion');
                }
            }

            $db->commit();

            return $upd->rowCount();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public function registrarAbono(int $id, float $monto, int $idFormaPago, int $idUsuario, ?string $referencia = null): array
    {
        if ($idUsuario <= 0) {
            throw new InvalidArgumentException('Usuario de sesion no valido.');
        }
        if ($monto <= 0) {
            throw new InvalidArgumentException('El monto del abono debe ser mayor a 0.');
        }

        $db = $this->getDb();
        $db->beginTransaction();
        try {
            auth_mysql_set_audit_vars($db, $idUsuario);
            $this->verificarFormaPago($db, $idFormaPago);

            $st = $db->prepare('SELECT * FROM ' . self::TABLE . ' WHERE id_orden_taller = :id AND activo = 1 FOR UPDATE');
            $st->bindValue(':id', $id, PDO::PARAM_INT);
            $st->execute();
            $orden = $st->fetch(PDO::FETCH_ASSOC);
            if (!$orden) {
                throw new InvalidArgumentException('La orden no existe.');
            }
            if (in_array((string) $orden['estado'], ['cancelada'], true)) {
                throw new InvalidArgumentException('No se pueden registrar abonos en una orden cancelada.');
            }

            $saldo = (float) $orden['saldo_pendiente'];
            if ($monto > $saldo + 0.01) {
                throw new InvalidArgumentException('El abono excede el saldo pendiente.');
            }

            $ref = $referencia !== null && trim($referencia) !== '' ? trim($referencia) : 'Abono orden de taller';
            $this->insertarPago($db, $id, $monto, $idFormaPago, $idUsuario, $ref);

            $nuevoSaldo = $this->recalcularSaldo($db, $id);

            $db->commit();

            return [
                'id_orden_taller' => $id,
                'saldo_pendiente' => $nuevoSaldo,
            ];
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public function borrar(int $id, int $idUsuario): int
    {
        $orden = $this->leerUno($id);
        if (!$orden) {
            return 0;
        }

        $db = $this->getDb();
        $db->beginTransaction();
        try {
            auth_mysql_set_audit_vars($db, $idUsuario);

            $stmt = $db->prepare('UPDATE ' . self::TABLE . ' SET activo = 0 WHERE id_orden_taller = :id AND activo = 1');
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->rowCount();

            if ($rows > 0
                && (string) $orden['origen'] === 'inventario'
                && !empty($orden['id_pieza_stock_FK'])
                && !in_array((string) $orden['estado'], ['entregada'], true)) {
                $this->actualizarEstadoStock($db, (int) $orden['id_pieza_stock_FK'], 'disponible');
            }

            $db->commit();

            return $rows;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public function leerPagos(int $idOrden): array
    {
        $stmt = $this->getDb()->prepare(
            "SELECT op.*, fp.forma_pago,
                    CONCAT(u.nombre, ' ', u.primer_apellido) AS usuario_nombre
             FROM ordenes_taller_pagos op
             INNER JOIN forma_pago fp ON fp.id_forma_pago = op.id_forma_pago_FK
             INNER JOIN usuarios u ON u.id_usuario = op.id_usuario_FK
             WHERE op.id_orden_taller_FK = :id AND op.estado = 'registrado'
             ORDER BY op.fecha_registro ASC"
        );
        $stmt->bindValue(':id', $idOrden, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function obtenerHistorial(int $idOrden): array
    {
        $stmt = $this->getDb()->prepare(
            "SELECT h.*, CONCAT(u.nombre, ' ', u.primer_apellido) AS usuario_nombre
             FROM ordenes_taller_historial h
             INNER JOIN usuarios u ON u.id_usuario = h.id_usuario_FK
             WHERE h.id_orden_taller_FK = :id
             ORDER BY h.fecha_registro ASC"
        );
        $stmt->bindValue(':id', $idOrden, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function buscarStockPorCodigo(string $codigo): ?array
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            return null;
        }

        $stmt = $this->getDb()->prepare(
            "SELECT ps.id_pieza_stock, ps.codigo_auxiliar, ps.codigo_barras, ps.estado, ps.precio_venta,
                    p.desc_pieza, sf.nom_sub_familia, m.nom_metal
             FROM piezas_stock ps
             INNER JOIN piezas p ON p.id_pieza = ps.id_pieza_FK
             INNER JOIN sub_familia sf ON sf.id_sub_familia = p.id_sub_familia_FK
             INNER JOIN metales m ON m.id_metal = p.id_metal_FK
             WHERE ps.activo = 1
               AND (ps.codigo_barras = :cod OR ps.codigo_auxiliar = :cod2)
             LIMIT 1"
        );
        $stmt->bindValue(':cod', $codigo, PDO::PARAM_STR);
        $stmt->bindValue(':cod2', $codigo, PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function obtenerCatalogos(): array
    {
        $db = $this->getDb();

        return [
            'talleres' => $db->query(
                "SELECT id_taller, nombre, contacto, telefono FROM talleres WHERE activo = 1 ORDER BY nombre ASC"
            )->fetchAll(PDO::FETCH_ASSOC),
            'formasPago' => $db->query(
                "SELECT id_forma_pago, forma_pago FROM forma_pago WHERE activo = 1 ORDER BY forma_pago ASC"
            )->fetchAll(PDO::FETCH_ASSOC),
            'clientes' => $db->query(
                "SELECT c.id_cliente,
                        u.nombre, u.primer_apellido, u.segundo_apellido, u.telefono, u.correo,
                        CONCAT(u.nombre, ' ', u.primer_apellido, COALESCE(CONCAT(' ', u.segundo_apellido), '')) AS nombre_completo
                 FROM clientes c
                 INNER JOIN usuarios u ON u.id_usuario = c.id_usuario_FK
                 WHERE c.activo = 1
                 ORDER BY u.primer_apellido ASC, u.nombre ASC"
            )->fetchAll(PDO::FETCH_ASSOC),
            'estados' => self::ESTADOS,
            'tipos' => self::TIPOS,
            'origenes' => self::ORIGENES,
        ];
    }

    public function etiquetaEstado(string $estado): string
    {
        $map = [
            'recibida' => 'Recibida',
            'en_taller' => 'En taller',
            'lista' => 'Lista',
            'entregada' => 'Entregada',
            'cancelada' => 'Cancelada',
        ];

        return $map[$estado] ?? $estado;
    }

    private function generarFolio(int $id): string
    {
        return 'OT-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT);
    }

    private function insertarHistorial(PDO $db, int $idOrden, string $estado, ?string $nota, int $idUsuario): void
    {
        $nota = $nota !== null ? trim($nota) : null;
        if ($nota !== null && mb_strlen($nota) > 500) {
            $nota = mb_substr($nota, 0, 500);
        }

        $stmt = $db->prepare(
            "INSERT INTO ordenes_taller_historial (id_orden_taller_FK, estado, nota, id_usuario_FK)
             VALUES (:id, :estado, :nota, :usuario)"
        );
        $stmt->bindValue(':id', $idOrden, PDO::PARAM_INT);
        $stmt->bindValue(':estado', $estado, PDO::PARAM_STR);
        $stmt->bindValue(':nota', $nota, $nota === null || $nota === '' ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':usuario', $idUsuario, PDO::PARAM_INT);
        $stmt->execute();
    }

    private function insertarPago(PDO $db, int $idOrden, float $monto, int $idFormaPago, int $idUsuario, string $referencia): void
    {
        $montoStr = $this->normalizarDecimal($monto);
        $stmt = $db->prepare(
            "INSERT INTO ordenes_taller_pagos
                (id_orden_taller_FK, monto, id_forma_pago_FK, estado, referencia, id_usuario_FK, tipo_origen)
             VALUES
                (:id, :monto, :fp, 'registrado', :ref, :usuario, 'cobro_tienda')"
        );
        $stmt->bindValue(':id', $idOrden, PDO::PARAM_INT);
        $stmt->bindValue(':monto', $montoStr, PDO::PARAM_STR);
        $stmt->bindValue(':fp', $idFormaPago, PDO::PARAM_INT);
        $stmt->bindValue(':ref', $referencia, PDO::PARAM_STR);
        $stmt->bindValue(':usuario', $idUsuario, PDO::PARAM_INT);
        $stmt->execute();
    }

    private function recalcularSaldo(PDO $db, int $idOrden): string
    {
        $st = $db->prepare('SELECT costo_total FROM ' . self::TABLE . ' WHERE id_orden_taller = :id FOR UPDATE');
        $st->bindValue(':id', $idOrden, PDO::PARAM_INT);
        $st->execute();
        $costoTotal = (float) $st->fetchColumn();
        $abonado = $this->sumaAbonosRegistrados($db, $idOrden);
        $saldoStr = $this->normalizarDecimal($costoTotal - $abonado);

        $upd = $db->prepare('UPDATE ' . self::TABLE . ' SET saldo_pendiente = :saldo WHERE id_orden_taller = :id');
        $upd->bindValue(':saldo', $saldoStr, PDO::PARAM_STR);
        $upd->bindValue(':id', $idOrden, PDO::PARAM_INT);
        $upd->execute();

        return $saldoStr;
    }

    private function sumaAbonosRegistrados(PDO $db, int $idOrden): float
    {
        $st = $db->prepare(
            "SELECT COALESCE(SUM(monto), 0) FROM ordenes_taller_pagos
             WHERE id_orden_taller_FK = :id AND estado = 'registrado'"
        );
        $st->bindValue(':id', $idOrden, PDO::PARAM_INT);
        $st->execute();

        return (float) $st->fetchColumn();
    }

    private function actualizarEstadoStock(PDO $db, int $idPiezaStock, string $estado): void
    {
        $stmt = $db->prepare(
            "UPDATE piezas_stock SET estado = :estado WHERE id_pieza_stock = :id AND activo = 1"
        );
        $stmt->bindValue(':estado', $estado, PDO::PARAM_STR);
        $stmt->bindValue(':id', $idPiezaStock, PDO::PARAM_INT);
        $stmt->execute();
    }

    private function obtenerStockActivo(int $idPiezaStock): ?array
    {
        $stmt = $this->getDb()->prepare(
            "SELECT ps.*, p.desc_pieza
             FROM piezas_stock ps
             INNER JOIN piezas p ON p.id_pieza = ps.id_pieza_FK
             WHERE ps.id_pieza_stock = :id AND ps.activo = 1"
        );
        $stmt->bindValue(':id', $idPiezaStock, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    private function verificarClienteActivo(int $idCliente): void
    {
        $stmt = $this->getDb()->prepare('SELECT 1 FROM clientes WHERE id_cliente = :id AND activo = 1 LIMIT 1');
        $stmt->bindValue(':id', $idCliente, PDO::PARAM_INT);
        $stmt->execute();
        if (!$stmt->fetchColumn()) {
            throw new InvalidArgumentException('El cliente no es valido.');
        }
    }

    private function verificarTallerActivo(int $idTaller): void
    {
        $stmt = $this->getDb()->prepare('SELECT 1 FROM talleres WHERE id_taller = :id AND activo = 1 LIMIT 1');
        $stmt->bindValue(':id', $idTaller, PDO::PARAM_INT);
        $stmt->execute();
        if (!$stmt->fetchColumn()) {
            throw new InvalidArgumentException('El taller no es valido.');
        }
    }

    private function verificarFormaPago(PDO $db, int $idFormaPago): void
    {
        $stmt = $db->prepare('SELECT 1 FROM forma_pago WHERE id_forma_pago = :id AND activo = 1 LIMIT 1');
        $stmt->bindValue(':id', $idFormaPago, PDO::PARAM_INT);
        $stmt->execute();
        if (!$stmt->fetchColumn()) {
            throw new InvalidArgumentException('La forma de pago no es valida.');
        }
    }

    private function validarTransicionEstado(string $actual, string $nuevo): void
    {
        $permitidas = [
            'recibida' => ['en_taller', 'cancelada'],
            'en_taller' => ['lista', 'cancelada'],
            'lista' => ['entregada', 'en_taller'],
        ];

        if (!isset($permitidas[$actual]) || !in_array($nuevo, $permitidas[$actual], true)) {
            throw new InvalidArgumentException('Transicion de estado no permitida.');
        }
    }

    private function normalizarDecimal(float $valor, int $decimales = 2): string
    {
        return number_format(round($valor, $decimales), $decimales, '.', '');
    }

    private function validarTexto(array $data, string $campo, int $max, string $label): string
    {
        if (!isset($data[$campo])) {
            throw new InvalidArgumentException($label . ' es requerido.');
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

    private function validarEnteroPositivo(array $data, string $campo, string $label): int
    {
        if (!isset($data[$campo]) || (int) $data[$campo] <= 0) {
            throw new InvalidArgumentException($label . ' es requerido.');
        }

        return (int) $data[$campo];
    }

    private function validarEnteroOpcional(array $data, string $campo): ?int
    {
        if (!isset($data[$campo]) || trim((string) $data[$campo]) === '') {
            return null;
        }
        $valor = (int) $data[$campo];

        return $valor > 0 ? $valor : null;
    }

    private function validarDecimalNoNegativo(array $data, string $campo, string $label): string
    {
        if (!isset($data[$campo]) || trim((string) $data[$campo]) === '' || !is_numeric($data[$campo])) {
            throw new InvalidArgumentException($label . ' debe ser un numero valido.');
        }
        $valor = (float) $data[$campo];
        if ($valor < 0) {
            throw new InvalidArgumentException($label . ' no puede ser negativo.');
        }

        return $this->normalizarDecimal($valor);
    }

    private function validarDecimalOpcional(array $data, string $campo, ?float $default = null): ?string
    {
        if (!isset($data[$campo]) || trim((string) $data[$campo]) === '') {
            return $default !== null ? $this->normalizarDecimal($default) : null;
        }
        if (!is_numeric($data[$campo])) {
            throw new InvalidArgumentException('El monto no es valido.');
        }
        $valor = (float) $data[$campo];
        if ($valor < 0) {
            throw new InvalidArgumentException('El monto no puede ser negativo.');
        }

        return $this->normalizarDecimal($valor);
    }

    private function validarFechaOpcional(array $data, string $campo): ?string
    {
        if (!isset($data[$campo]) || trim((string) $data[$campo]) === '') {
            return null;
        }
        $fecha = trim((string) $data[$campo]);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            throw new InvalidArgumentException('La fecha no es valida.');
        }

        return $fecha;
    }

    private function validarEnum(array $data, string $campo, array $valores, string $label): string
    {
        if (!isset($data[$campo])) {
            throw new InvalidArgumentException($label . ' es requerido.');
        }
        $valor = trim((string) $data[$campo]);
        if (!in_array($valor, $valores, true)) {
            throw new InvalidArgumentException($label . ' no es valido.');
        }

        return $valor;
    }
}
