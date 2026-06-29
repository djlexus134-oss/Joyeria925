<?php

require_once __DIR__ . '/../../sistema.class.php';



class ColaImpresion extends Sistema

{

    const TABLE = 'cola_impresion';

    const MAX_INTENTOS = 5;



    const TIPOS_TICKET = [
        'venta',
        'reimpresion',
        'apartado_alta',
        'apartado_abono',
        'apartado_liquidacion',
    ];

    const TIPOS_ETIQUETA_STOCK = ['etiqueta_stock', 'etiqueta_lote'];

    const TIPOS_ETIQUETA_INSUMO = ['etiqueta_insumo', 'etiqueta_insumo_lote'];

    const TIPOS_ETIQUETA = [
        'etiqueta_stock',
        'etiqueta_lote',
        'etiqueta_insumo',
        'etiqueta_insumo_lote',
    ];



    public function encolar(int $idVenta, string $tipo = 'venta', ?int $idTienda = null, ?string $payloadJson = null): int

    {

        $tipo = in_array($tipo, self::TIPOS_TICKET, true) ? $tipo : 'venta';

        $stmt = $this->getDb()->prepare(

            'INSERT INTO ' . self::TABLE . '

            (id_venta_FK, id_tienda_FK, tipo, estado, payload_json, intentos, fecha_creacion)

            VALUES (:id_venta, :id_tienda, :tipo, :estado, :payload, 0, NOW())'

        );

        $stmt->bindValue(':id_venta', $idVenta, PDO::PARAM_INT);

        if ($idTienda !== null && $idTienda > 0) {

            $stmt->bindValue(':id_tienda', $idTienda, PDO::PARAM_INT);

        } else {

            $stmt->bindValue(':id_tienda', null, PDO::PARAM_NULL);

        }

        $stmt->bindValue(':tipo', $tipo, PDO::PARAM_STR);

        $stmt->bindValue(':estado', 'pendiente', PDO::PARAM_STR);

        $stmt->bindValue(':payload', $payloadJson, $payloadJson === null ? PDO::PARAM_NULL : PDO::PARAM_STR);

        $stmt->execute();



        return (int) $this->getDb()->lastInsertId();

    }

    /**
     * Encola ticket termico de apartado (alta, abono o liquidacion). id_venta_FK queda NULL; datos en payload_json.
     *
     * @param 'alta'|'abono'|'liquidacion' $modo
     */
    public function encolarTicketApartado(int $idApartado, string $modo, ?int $idTienda = null): int
    {
        $map = [
            'alta' => 'apartado_alta',
            'abono' => 'apartado_abono',
            'liquidacion' => 'apartado_liquidacion',
        ];
        $modo = array_key_exists($modo, $map) ? $modo : 'abono';
        $tipo = $map[$modo];
        $payload = json_encode(
            ['id_apartado' => $idApartado, 'modo' => $modo],
            JSON_UNESCAPED_UNICODE
        );

        $columnas = 'id_venta_FK, id_tienda_FK, tipo, estado, payload_json, intentos, fecha_creacion';
        $valores = 'NULL, :id_tienda, :tipo, :estado, :payload, 0, NOW()';

        $sql = 'INSERT INTO ' . self::TABLE . ' (' . $columnas . ') VALUES (' . $valores . ')';
        $stmt = $this->getDb()->prepare($sql);

        if ($idTienda !== null && $idTienda > 0) {
            $stmt->bindValue(':id_tienda', $idTienda, PDO::PARAM_INT);
        } else {
            $stmt->bindValue(':id_tienda', null, PDO::PARAM_NULL);
        }
        $stmt->bindValue(':tipo', $tipo, PDO::PARAM_STR);
        $stmt->bindValue(':estado', 'pendiente', PDO::PARAM_STR);
        $stmt->bindValue(':payload', $payload, PDO::PARAM_STR);

        try {
            $stmt->execute();
        } catch (PDOException $e) {
            $msg = $e->getMessage();
            if (stripos($msg, 'Data truncated') !== false || stripos($msg, 'apartado_') !== false) {
                throw new RuntimeException(
                    'La cola no reconoce tickets de apartado. Ejecuta sql/2026_05_17_cola_impresion_apartados_ticket.sql',
                    0,
                    $e
                );
            }
            if (stripos($msg, 'id_venta_FK') !== false && stripos($msg, 'cannot be null') !== false) {
                throw new RuntimeException(
                    'cola_impresion.id_venta_FK debe ser nullable. Ejecuta sql/2026_05_14_cola_impresion_etiquetas.sql',
                    0,
                    $e
                );
            }
            throw new RuntimeException('No se pudo encolar ticket apartado: ' . $msg, 0, $e);
        }

        return (int) $this->getDb()->lastInsertId();
    }



    public function encolarEtiquetas(array $idsPiezaStock, string $tipo = 'etiqueta_lote', ?int $idTienda = null, ?int $idUsuario = null): int
    {
        $tipo = in_array($tipo, self::TIPOS_ETIQUETA_STOCK, true) ? $tipo : 'etiqueta_lote';
        $payload = json_encode(
            ['ids_pieza_stock' => array_values(array_unique(array_map('intval', $idsPiezaStock)))],
            JSON_UNESCAPED_UNICODE
        );

        return $this->insertarTrabajoEtiqueta($tipo, $payload, $idTienda, $idUsuario);
    }

    /**
     * @param array<int> $idsInsumo Lista expandida (repetir id por copias).
     * @param array<int, int>|null $copiasPorId Mapa id => copias (solo metadata UI).
     */
    public function encolarEtiquetasInsumos(array $idsInsumo, string $tipo = 'etiqueta_insumo_lote', ?int $idTienda = null, ?int $idUsuario = null, ?array $copiasPorId = null): int
    {
        $tipo = in_array($tipo, self::TIPOS_ETIQUETA_INSUMO, true) ? $tipo : 'etiqueta_insumo_lote';
        $ids = [];
        foreach ($idsInsumo as $id) {
            $n = (int) $id;
            if ($n > 0) {
                $ids[] = $n;
            }
        }
        if ($ids === []) {
            throw new InvalidArgumentException('No hay IDs de insumo para encolar.');
        }

        $payloadData = ['ids_insumo' => $ids];
        if (is_array($copiasPorId) && $copiasPorId !== []) {
            $payloadData['copias_por_id'] = $copiasPorId;
        }
        $payload = json_encode($payloadData, JSON_UNESCAPED_UNICODE);

        return $this->insertarTrabajoEtiqueta($tipo, $payload, $idTienda, $idUsuario);
    }

    private function insertarTrabajoEtiqueta(string $tipo, string $payload, ?int $idTienda, ?int $idUsuario): int
    {

        $columnas = 'id_venta_FK, id_tienda_FK, tipo, estado, payload_json, intentos, fecha_creacion';
        $valores = 'NULL, :id_tienda, :tipo, :estado, :payload, 0, NOW()';
        if ($this->tieneColumna('id_usuario_FK')) {
            $columnas = 'id_venta_FK, id_tienda_FK, id_usuario_FK, tipo, estado, payload_json, intentos, fecha_creacion';
            $valores = 'NULL, :id_tienda, :id_usuario, :tipo, :estado, :payload, 0, NOW()';
        }

        $sql = 'INSERT INTO ' . self::TABLE . ' (' . $columnas . ') VALUES (' . $valores . ')';
        $stmt = $this->getDb()->prepare($sql);

        if ($idTienda !== null && $idTienda > 0) {
            $stmt->bindValue(':id_tienda', $idTienda, PDO::PARAM_INT);
        } else {
            $stmt->bindValue(':id_tienda', null, PDO::PARAM_NULL);
        }
        if (str_contains($columnas, 'id_usuario_FK')) {
            if ($idUsuario !== null && $idUsuario > 0) {
                $stmt->bindValue(':id_usuario', $idUsuario, PDO::PARAM_INT);
            } else {
                $stmt->bindValue(':id_usuario', null, PDO::PARAM_NULL);
            }
        }
        $stmt->bindValue(':tipo', $tipo, PDO::PARAM_STR);
        $stmt->bindValue(':estado', 'pendiente', PDO::PARAM_STR);
        $stmt->bindValue(':payload', $payload, PDO::PARAM_STR);

        try {
            $stmt->execute();
        } catch (PDOException $e) {
            $msg = $e->getMessage();
            if (stripos($msg, 'etiqueta_stock') !== false
                || stripos($msg, 'etiqueta_insumo') !== false
                || stripos($msg, 'Data truncated') !== false) {
                throw new RuntimeException(
                    'La tabla cola_impresion no tiene soporte para etiquetas. Ejecuta sql/2026_05_14_cola_impresion_etiquetas.sql y sql/2026_06_05_cola_impresion_etiquetas_insumo.sql',
                    0,
                    $e
                );
            }
            if (stripos($msg, 'id_venta_FK') !== false && stripos($msg, 'cannot be null') !== false) {
                throw new RuntimeException(
                    'cola_impresion.id_venta_FK debe ser nullable para etiquetas. Ejecuta sql/2026_05_14_cola_impresion_etiquetas.sql',
                    0,
                    $e
                );
            }
            throw new RuntimeException('No se pudo insertar en cola_impresion: ' . $msg, 0, $e);
        }

        return (int) $this->getDb()->lastInsertId();
    }

    private function tieneColumna(string $columna): bool
    {
        static $cache = [];
        $clave = self::TABLE . '.' . $columna;
        if (array_key_exists($clave, $cache)) {
            return $cache[$clave];
        }

        $stmt = $this->getDb()->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :tabla
               AND COLUMN_NAME = :columna'
        );
        $stmt->bindValue(':tabla', self::TABLE, PDO::PARAM_STR);
        $stmt->bindValue(':columna', $columna, PDO::PARAM_STR);
        $stmt->execute();
        $cache[$clave] = ((int) $stmt->fetchColumn()) > 0;

        return $cache[$clave];
    }



    public function obtenerSiguientePendiente(?int $idTiendaCaja = null, ?array $tiposPermitidos = null): ?array

    {
        // Toma atomica: SELECT ... FOR UPDATE + UPDATE a 'imprimiendo' dentro
        // de una transaccion. Asi varias instancias del agente o llamadas
        // concurrentes nunca reciben el mismo job dos veces.

        $db = $this->getDb();
        $usaTransaccion = !$db->inTransaction();

        try {
            if ($usaTransaccion) {
                $db->beginTransaction();
            }

            $sql = 'SELECT * FROM ' . self::TABLE . "
                    WHERE estado = 'pendiente'
                      AND intentos < :max_intentos";

            if ($idTiendaCaja !== null && $idTiendaCaja > 0) {
                $sql .= ' AND (id_tienda_FK IS NULL OR id_tienda_FK = :id_tienda)';
            }
            if ($tiposPermitidos !== null && $tiposPermitidos !== []) {
                $placeholders = [];
                foreach (array_values($tiposPermitidos) as $i => $tipo) {
                    $placeholders[] = ':tipo' . $i;
                }
                $sql .= ' AND tipo IN (' . implode(', ', $placeholders) . ')';
            }
            $sql .= ' ORDER BY fecha_creacion ASC, id_cola_impresion ASC LIMIT 1 FOR UPDATE';

            $stmt = $db->prepare($sql);
            $stmt->bindValue(':max_intentos', self::MAX_INTENTOS, PDO::PARAM_INT);
            if ($idTiendaCaja !== null && $idTiendaCaja > 0) {
                $stmt->bindValue(':id_tienda', $idTiendaCaja, PDO::PARAM_INT);
            }
            if ($tiposPermitidos !== null && $tiposPermitidos !== []) {
                foreach (array_values($tiposPermitidos) as $i => $tipo) {
                    $stmt->bindValue(':tipo' . $i, $tipo, PDO::PARAM_STR);
                }
            }
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!is_array($row)) {
                if ($usaTransaccion) {
                    $db->commit();
                }
                return null;
            }

            $idCola = (int) ($row['id_cola_impresion'] ?? 0);
            if ($idCola > 0) {
                $up = $db->prepare(
                    'UPDATE ' . self::TABLE . "
                     SET estado = 'imprimiendo'
                     WHERE id_cola_impresion = :id"
                );
                $up->bindValue(':id', $idCola, PDO::PARAM_INT);
                $up->execute();
                $row['estado'] = 'imprimiendo';
            }

            if ($usaTransaccion) {
                $db->commit();
            }

            return $row;
        } catch (Throwable $e) {
            if ($usaTransaccion && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Devuelve a 'pendiente' un job que estaba 'imprimiendo' pero no se confirmo
     * (ej. el agente fallo antes de confirmar). Util si en el futuro se hace una
     * limpieza periodica de jobs colgados.
     */
    public function liberarImprimiendo(int $idCola): void
    {
        $stmt = $this->getDb()->prepare(
            'UPDATE ' . self::TABLE . "
             SET estado = 'pendiente'
             WHERE id_cola_impresion = :id AND estado = 'imprimiendo'"
        );
        $stmt->bindValue(':id', $idCola, PDO::PARAM_INT);
        $stmt->execute();
    }



    public function marcarImpreso(int $idCola): bool

    {

        $stmt = $this->getDb()->prepare(

            'UPDATE ' . self::TABLE . "

             SET estado = 'impreso',

                 fecha_impreso = NOW(),

                 mensaje_error = NULL

             WHERE id_cola_impresion = :id"

        );

        $stmt->bindValue(':id', $idCola, PDO::PARAM_INT);

        $stmt->execute();



        return $stmt->rowCount() > 0;

    }



    public function marcarError(int $idCola, string $mensaje): bool

    {

        $mensaje = mb_substr(trim($mensaje), 0, 500);

        $stmt = $this->getDb()->prepare(

            'UPDATE ' . self::TABLE . "

             SET estado = 'error',

                 intentos = intentos + 1,

                 mensaje_error = :mensaje

             WHERE id_cola_impresion = :id"

        );

        $stmt->bindValue(':id', $idCola, PDO::PARAM_INT);

        $stmt->bindValue(':mensaje', $mensaje, PDO::PARAM_STR);

        $stmt->execute();



        return $stmt->rowCount() > 0;

    }



    public function incrementarIntento(int $idCola, string $mensaje): void

    {

        $mensaje = mb_substr(trim($mensaje), 0, 500);

        $stmt = $this->getDb()->prepare(

            'UPDATE ' . self::TABLE . '

             SET intentos = intentos + 1,

                 mensaje_error = :mensaje,

                 estado = CASE WHEN intentos + 1 >= :max_intentos THEN :estado_error ELSE estado END

             WHERE id_cola_impresion = :id'

        );

        $stmt->bindValue(':id', $idCola, PDO::PARAM_INT);

        $stmt->bindValue(':mensaje', $mensaje, PDO::PARAM_STR);

        $stmt->bindValue(':max_intentos', self::MAX_INTENTOS, PDO::PARAM_INT);

        $stmt->bindValue(':estado_error', 'error', PDO::PARAM_STR);

        $stmt->execute();

    }



    public function estadoPorVenta(int $idVenta): ?array

    {

        $stmt = $this->getDb()->prepare(

            'SELECT id_cola_impresion, id_venta_FK, tipo, estado, mensaje_error, fecha_creacion, fecha_impreso

             FROM ' . self::TABLE . '

             WHERE id_venta_FK = :id_venta

             ORDER BY id_cola_impresion DESC

             LIMIT 1'

        );

        $stmt->bindValue(':id_venta', $idVenta, PDO::PARAM_INT);

        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);



        return is_array($row) ? $row : null;

    }



    public function estadoPorId(int $idCola): ?array

    {

        $stmt = $this->getDb()->prepare(

            'SELECT id_cola_impresion, id_venta_FK, tipo, estado, mensaje_error, payload_json,

                    fecha_creacion, fecha_impreso, intentos

             FROM ' . self::TABLE . '

             WHERE id_cola_impresion = :id'

        );

        $stmt->bindValue(':id', $idCola, PDO::PARAM_INT);

        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);



        return is_array($row) ? $row : null;

    }



    public function contarEtiquetasEnPayload(?string $payloadJson): int

    {

        if ($payloadJson === null || trim($payloadJson) === '') {

            return 0;

        }

        $decoded = json_decode($payloadJson, true);

        if (!is_array($decoded)) {

            return 0;

        }

        if (!empty($decoded['ids_pieza_stock']) && is_array($decoded['ids_pieza_stock'])) {

            return count($decoded['ids_pieza_stock']);

        }

        if (!empty($decoded['ids_insumo']) && is_array($decoded['ids_insumo'])) {

            return count($decoded['ids_insumo']);

        }



        return 0;

    }

    /**
     * Historial reciente de impresion de etiquetas de insumos.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listarUltimasEtiquetasInsumo(int $limite = 30): array
    {
        $limite = max(1, min(100, $limite));
        $tipos = self::TIPOS_ETIQUETA_INSUMO;
        $placeholders = implode(',', array_fill(0, count($tipos), '?'));
        $cols = 'c.id_cola_impresion, c.tipo, c.estado, c.payload_json, c.fecha_creacion, c.fecha_impreso, c.mensaje_error';
        if ($this->tieneColumna('id_usuario_FK')) {
            $cols .= ', c.id_usuario_FK, u.nombre AS usuario_nombre, u.primer_apellido AS usuario_apellido';
        }

        $sql = 'SELECT ' . $cols . ' FROM ' . self::TABLE . ' c';
        if ($this->tieneColumna('id_usuario_FK')) {
            $sql .= ' LEFT JOIN usuarios u ON u.id_usuario = c.id_usuario_FK';
        }
        $sql .= ' WHERE c.tipo IN (' . $placeholders . ') ORDER BY c.fecha_creacion DESC LIMIT ' . $limite;

        $stmt = $this->getDb()->prepare($sql);
        foreach ($tipos as $i => $t) {
            $stmt->bindValue($i + 1, $t, PDO::PARAM_STR);
        }
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

}

