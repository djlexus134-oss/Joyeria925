<?php
require_once __DIR__ . "/../../sistema.class.php";
require_once __DIR__ . "/../includes/list_search.php";

class Gastos extends Sistema
{
    private function normalizarDecimal($valor, int $decimales = 2): string
    {
        $numero = (float) $valor;
        $epsilon = pow(10, -($decimales + 3));
        $ajustado = $numero + ($numero >= 0 ? $epsilon : -$epsilon);
        return number_format(round($ajustado, $decimales), $decimales, '.', '');
    }

    public function leer(?string $busqueda = null)
    {
        $pat = joyeria_like_pattern($busqueda);
        $sql = "SELECT g.id_gasto,
                       g.id_categoria_FK,
                       g.concepto,
                       g.monto,
                       g.fecha_gasto,
                       g.id_forma_pago_FK,
                       g.id_empleado_FK,
                       g.afecta_caja,
                       g.observaciones,
                       g.fecha_registro,
                       gc.nombre AS categoria_nombre,
                       fp.forma_pago,
                       e.id_usuario_FK,
                       CONCAT(u.nombre, ' ', u.primer_apellido, COALESCE(CONCAT(' ', u.segundo_apellido), '')) AS empleado_nombre
                FROM gastos g
                INNER JOIN gastos_categoria gc ON gc.id_categoria_gasto = g.id_categoria_FK
                LEFT JOIN forma_pago fp ON fp.id_forma_pago = g.id_forma_pago_FK
                INNER JOIN empleados e ON e.id_empleado = g.id_empleado_FK
                INNER JOIN usuarios u ON u.id_usuario = e.id_usuario_FK
                WHERE g.activo = 1";
        if ($pat !== null) {
            $sql .= " AND (
                g.concepto LIKE :busq OR gc.nombre LIKE :busq2 OR fp.forma_pago LIKE :busq3
                OR g.observaciones LIKE :busq4 OR CAST(g.monto AS CHAR) LIKE :busq5
                OR CONCAT(u.nombre, ' ', u.primer_apellido, COALESCE(CONCAT(' ', u.segundo_apellido), '')) LIKE :busq6
                OR CAST(g.id_gasto AS CHAR) LIKE :busq7
            )";
        }
        $sql .= " ORDER BY g.fecha_gasto DESC, g.id_gasto DESC";

        $stmt = $this->getDb()->prepare($sql);
        if ($pat !== null) {
            $stmt->bindValue(':busq', $pat, PDO::PARAM_STR);
            $stmt->bindValue(':busq2', $pat, PDO::PARAM_STR);
            $stmt->bindValue(':busq3', $pat, PDO::PARAM_STR);
            $stmt->bindValue(':busq4', $pat, PDO::PARAM_STR);
            $stmt->bindValue(':busq5', $pat, PDO::PARAM_STR);
            $stmt->bindValue(':busq6', $pat, PDO::PARAM_STR);
            $stmt->bindValue(':busq7', $pat, PDO::PARAM_STR);
        }
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function leerUno($idGasto)
    {
        $sql = "SELECT g.id_gasto,
                       g.id_categoria_FK,
                       g.concepto,
                       g.monto,
                       g.fecha_gasto,
                       g.id_forma_pago_FK,
                       g.id_empleado_FK,
                       g.afecta_caja,
                       g.observaciones,
                       g.fecha_registro,
                       gc.nombre AS categoria_nombre,
                       fp.forma_pago,
                       e.id_usuario_FK,
                       CONCAT(u.nombre, ' ', u.primer_apellido, COALESCE(CONCAT(' ', u.segundo_apellido), '')) AS empleado_nombre
                FROM gastos g
                INNER JOIN gastos_categoria gc ON gc.id_categoria_gasto = g.id_categoria_FK
                LEFT JOIN forma_pago fp ON fp.id_forma_pago = g.id_forma_pago_FK
                INNER JOIN empleados e ON e.id_empleado = g.id_empleado_FK
                INNER JOIN usuarios u ON u.id_usuario = e.id_usuario_FK
                WHERE g.id_gasto = :id_gasto AND g.activo = 1";

        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(':id_gasto', (int) $idGasto, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function obtenerCatalogos()
    {
        $db = $this->getDb();

        return [
            'categorias' => $db->query("SELECT id_categoria_gasto, nombre FROM gastos_categoria WHERE activo = 1 ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC),
            'formas_pago' => $db->query("SELECT id_forma_pago, forma_pago FROM forma_pago WHERE activo = 1 ORDER BY forma_pago ASC")->fetchAll(PDO::FETCH_ASSOC),
            'empleados' => $db->query(
                "SELECT e.id_empleado,
                        CONCAT(u.nombre, ' ', u.primer_apellido, COALESCE(CONCAT(' ', u.segundo_apellido), '')) AS nombre_completo,
                        p.nombre_puesto
                 FROM empleados e
                 INNER JOIN usuarios u ON u.id_usuario = e.id_usuario_FK
                 INNER JOIN puestos p ON p.id_puesto = e.id_puesto_FK
                 WHERE e.activo = 1
                 ORDER BY u.primer_apellido ASC, u.nombre ASC"
            )->fetchAll(PDO::FETCH_ASSOC),
        ];
    }

    public function obtenerIdEmpleadoPorUsuario(int $idUsuario): ?int
    {
        $stmt = $this->getDb()->prepare(
            "SELECT id_empleado
             FROM empleados
             WHERE id_usuario_FK = :id_usuario
               AND activo = 1
             LIMIT 1"
        );
        $stmt->bindValue(':id_usuario', $idUsuario, PDO::PARAM_INT);
        $stmt->execute();
        $idEmpleado = $stmt->fetchColumn();

        if (!$idEmpleado) {
            return null;
        }

        return (int) $idEmpleado;
    }

    public function crear($data)
    {
        $idCategoria = $this->validarEntero($data, 'id_categoria_FK', 'La categoria');
        $concepto = $this->validarTexto($data, 'concepto', 150, 'El concepto');
        $monto = $this->validarDecimal($data, 'monto', 'El monto');
        $fechaGasto = $this->validarFecha($data, 'fecha_gasto', 'La fecha del gasto');
        $idFormaPago = $this->validarEnteroOpcional($data, 'id_forma_pago_FK');
        $idEmpleado = $this->validarEntero($data, 'id_empleado_FK', 'El empleado');
        $afectaCaja = $this->validarBooleano($data, 'afecta_caja');
        $observaciones = $this->validarTextoOpcional($data, 'observaciones', 65535);

        $db = $this->getDb();
        $db->beginTransaction();

        try {
            $this->verificarExiste($db, 'SELECT 1 FROM gastos_categoria WHERE id_categoria_gasto = :id', ':id', $idCategoria, 'La categoria no existe.');
            $this->verificarExiste($db, 'SELECT 1 FROM empleados WHERE id_empleado = :id AND activo = 1', ':id', $idEmpleado, 'El empleado no existe o esta inactivo.');

            if ($idFormaPago !== null) {
                $this->verificarExiste($db, 'SELECT 1 FROM forma_pago WHERE id_forma_pago = :id AND activo = 1', ':id', $idFormaPago, 'La forma de pago no existe o esta inactiva.');
            }

            $stmt = $db->prepare(
                "INSERT INTO gastos
                (id_categoria_FK, concepto, monto, fecha_gasto, id_forma_pago_FK, id_empleado_FK, afecta_caja, observaciones)
                VALUES
                (:id_categoria_FK, :concepto, :monto, :fecha_gasto, :id_forma_pago_FK, :id_empleado_FK, :afecta_caja, :observaciones)"
            );

            $stmt->bindValue(':id_categoria_FK', $idCategoria, PDO::PARAM_INT);
            $stmt->bindValue(':concepto', $concepto, PDO::PARAM_STR);
            $stmt->bindValue(':monto', $monto, PDO::PARAM_STR);
            $stmt->bindValue(':fecha_gasto', $fechaGasto, PDO::PARAM_STR);
            $stmt->bindValue(':id_forma_pago_FK', $idFormaPago, $idFormaPago === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(':id_empleado_FK', $idEmpleado, PDO::PARAM_INT);
            $stmt->bindValue(':afecta_caja', $afectaCaja, PDO::PARAM_INT);
            $stmt->bindValue(':observaciones', $observaciones, $observaciones === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->execute();

            $idGasto = (int) $db->lastInsertId();
            if ($idGasto <= 0) {
                $idGasto = $this->resolverIdGastoRecienCreado($db, $idEmpleado, $concepto, $monto, $fechaGasto);
            }
            if ($idGasto <= 0) {
                throw new RuntimeException('No se obtuvo el identificador del gasto creado.');
            }

            $db->commit();

            return $idGasto;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    private function resolverIdGastoRecienCreado(
        PDO $db,
        int $idEmpleado,
        string $concepto,
        string $monto,
        string $fechaGasto
    ): int {
        $stmt = $db->prepare(
            'SELECT id_gasto
             FROM gastos
             WHERE id_empleado_FK = :emp
               AND concepto = :concepto
               AND monto = :monto
               AND fecha_gasto = :fecha
             ORDER BY id_gasto DESC
             LIMIT 1'
        );
        $stmt->bindValue(':emp', $idEmpleado, PDO::PARAM_INT);
        $stmt->bindValue(':concepto', $concepto, PDO::PARAM_STR);
        $stmt->bindValue(':monto', $monto, PDO::PARAM_STR);
        $stmt->bindValue(':fecha', $fechaGasto, PDO::PARAM_STR);
        $stmt->execute();
        $id = $stmt->fetchColumn();

        return $id !== false ? (int) $id : 0;
    }

    public function actualizar($idGasto, $data)
    {
        $idCategoria = $this->validarEntero($data, 'id_categoria_FK', 'La categoria');
        $concepto = $this->validarTexto($data, 'concepto', 150, 'El concepto');
        $monto = $this->validarDecimal($data, 'monto', 'El monto');
        $fechaGasto = $this->validarFecha($data, 'fecha_gasto', 'La fecha del gasto');
        $idFormaPago = $this->validarEnteroOpcional($data, 'id_forma_pago_FK');
        $idEmpleado = $this->validarEntero($data, 'id_empleado_FK', 'El empleado');
        $afectaCaja = $this->validarBooleano($data, 'afecta_caja');
        $observaciones = $this->validarTextoOpcional($data, 'observaciones', 65535);

        $db = $this->getDb();
        $db->beginTransaction();

        try {
            $this->verificarExiste($db, 'SELECT 1 FROM gastos_categoria WHERE id_categoria_gasto = :id', ':id', $idCategoria, 'La categoria no existe.');
            $this->verificarExiste($db, 'SELECT 1 FROM empleados WHERE id_empleado = :id AND activo = 1', ':id', $idEmpleado, 'El empleado no existe o esta inactivo.');

            if ($idFormaPago !== null) {
                $this->verificarExiste($db, 'SELECT 1 FROM forma_pago WHERE id_forma_pago = :id AND activo = 1', ':id', $idFormaPago, 'La forma de pago no existe o esta inactiva.');
            }

            $stmt = $db->prepare(
                "UPDATE gastos
                 SET id_categoria_FK = :id_categoria_FK,
                     concepto = :concepto,
                     monto = :monto,
                     fecha_gasto = :fecha_gasto,
                     id_forma_pago_FK = :id_forma_pago_FK,
                     id_empleado_FK = :id_empleado_FK,
                     afecta_caja = :afecta_caja,
                     observaciones = :observaciones
                 WHERE id_gasto = :id_gasto"
            );

            $stmt->bindValue(':id_categoria_FK', $idCategoria, PDO::PARAM_INT);
            $stmt->bindValue(':concepto', $concepto, PDO::PARAM_STR);
            $stmt->bindValue(':monto', $monto, PDO::PARAM_STR);
            $stmt->bindValue(':fecha_gasto', $fechaGasto, PDO::PARAM_STR);
            $stmt->bindValue(':id_forma_pago_FK', $idFormaPago, $idFormaPago === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(':id_empleado_FK', $idEmpleado, PDO::PARAM_INT);
            $stmt->bindValue(':afecta_caja', $afectaCaja, PDO::PARAM_INT);
            $stmt->bindValue(':observaciones', $observaciones, $observaciones === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':id_gasto', (int) $idGasto, PDO::PARAM_INT);
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

    public function borrar($idGasto){
    $stmt = $this->getDb()->prepare(
        "UPDATE gastos SET activo = 0 WHERE id_gasto = :id_gasto AND activo = 1"
    );
    $stmt->bindValue(':id_gasto', (int) $idGasto, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->rowCount();
    }

    /** GET /api: buscar categorias de gasto por texto (para autocompletar). */
    public function buscarCategorias(?string $q, int $limit = 20): array
    {
        $q = $q !== null ? trim($q) : '';
        $limit = max(1, min(50, (int) $limit));

        if ($q === '') {
            $stmt = $this->getDb()->prepare(
                'SELECT id_categoria_gasto, nombre
                 FROM gastos_categoria
                 WHERE activo = 1
                 ORDER BY nombre ASC
                 LIMIT ' . $limit
            );
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $stmt = $this->getDb()->prepare(
            'SELECT id_categoria_gasto, nombre
             FROM gastos_categoria
             WHERE activo = 1 AND nombre LIKE :q
             ORDER BY nombre ASC
             LIMIT ' . $limit
        );
        $stmt->bindValue(':q', '%' . $q . '%', PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** POST /api: crear categoria de gasto si no existe; retorna id. */
    public function crearCategoria(string $nombre): int
    {
        $nombre = trim(strip_tags($nombre));
        if ($nombre === '') {
            throw new InvalidArgumentException('El nombre de la categoria es obligatorio.');
        }
        if (mb_strlen($nombre) > 50) {
            $nombre = mb_substr($nombre, 0, 50);
        }

        $db = $this->getDb();
        $stmt = $db->prepare(
            'SELECT id_categoria_gasto FROM gastos_categoria WHERE nombre = :n LIMIT 1'
        );
        $stmt->bindValue(':n', $nombre, PDO::PARAM_STR);
        $stmt->execute();
        $id = $stmt->fetchColumn();
        if ($id) {
            return (int) $id;
        }

        $ins = $db->prepare(
            'INSERT INTO gastos_categoria (nombre, descripcion, activo) VALUES (:n, NULL, 1)'
        );
        $ins->bindValue(':n', $nombre, PDO::PARAM_STR);
        $ins->execute();

        return (int) $db->lastInsertId();
    }

    private function verificarExiste(PDO $db, string $sql, string $paramName, int $valor, string $mensaje): void
    {
        $stmt = $db->prepare($sql);
        $stmt->bindValue($paramName, $valor, PDO::PARAM_INT);
        $stmt->execute();
        if (!$stmt->fetchColumn()) {
            throw new InvalidArgumentException($mensaje);
        }
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

    private function validarTextoOpcional($data, $campo, $max)
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

    private function validarEntero($data, $campo, $label)
    {
        if (!isset($data[$campo]) || trim((string) $data[$campo]) === '' || (int) $data[$campo] <= 0) {
            throw new InvalidArgumentException($label . ' es obligatorio.');
        }

        return (int) $data[$campo];
    }

    private function validarEnteroOpcional($data, $campo)
    {
        if (!isset($data[$campo]) || trim((string) $data[$campo]) === '' || (int) $data[$campo] <= 0) {
            return null;
        }

        return (int) $data[$campo];
    }

    private function validarDecimal($data, $campo, $label)
    {
        if (!isset($data[$campo]) || trim((string) $data[$campo]) === '') {
            throw new InvalidArgumentException($label . ' es obligatorio.');
        }

        $valor = (float) $data[$campo];
        if ($valor <= 0) {
            throw new InvalidArgumentException($label . ' debe ser mayor a cero.');
        }

        return $this->normalizarDecimal($valor, 2);
    }

    private function validarFecha($data, $campo, $label)
    {
        if (!isset($data[$campo]) || trim((string) $data[$campo]) === '') {
            throw new InvalidArgumentException($label . ' es obligatoria.');
        }

        $valor = trim((string) $data[$campo]);
        $fecha = DateTime::createFromFormat('Y-m-d', $valor);
        if ($fecha === false || $fecha->format('Y-m-d') !== $valor) {
            throw new InvalidArgumentException($label . ' no tiene un formato valido.');
        }

        return $valor;
    }

    private function validarBooleano($data, $campo)
    {
        if (!isset($data[$campo])) {
            return 0;
        }
        $val = $data[$campo];
        return in_array($val, [1, '1', true, 'true', 'on', 'yes', 'si'], true) ? 1 : 0;
    }
}
