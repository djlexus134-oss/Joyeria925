<?php
require_once __DIR__ . '/../../sistema.class.php';
require_once __DIR__ . '/insumos.php';
require_once __DIR__ . '/configuracion_general.php';
require_once __DIR__ . '/../includes/list_search.php';
require_once __DIR__ . '/../includes/auth.php';

class Ventas extends Sistema
{
    private ?array $cacheColumnasVentaDetalle = null;
    private ?array $cacheColumnasVentaPagos = null;
    private ?array $cacheColumnasInsumos = null;

    private function normalizarDecimal($valor, int $decimales = 2): string
    {
        $numero = (float) $valor;
        $epsilon = pow(10, -($decimales + 3));
        $ajustado = $numero + ($numero >= 0 ? $epsilon : -$epsilon);
        return number_format(round($ajustado, $decimales), $decimales, '.', '');
    }

    /**
     * @param array<string, mixed>|null $filtros fecha_desde, fecha_hasta (Y-m-d), id_cliente (0 = publico general),
     *                                           id_empleado, estado, origen (liquidacion|directa)
     */
    public function leer(?string $busqueda = null, ?array $filtros = null)
    {
        $pat = joyeria_like_pattern($busqueda);
        $filtros = is_array($filtros) ? $filtros : [];

        $sql = "SELECT v.id_venta,
                       v.id_cliente_FK,
                       v.id_empleado_FK,
                       v.id_impuesto_FK,
                       v.id_apartado_FK,
                       v.fecha_venta,
                       v.total,
                       v.estado,
                       v.impuesto_porcentaje,
                       v.impuesto_monto,
                       (SELECT COALESCE(SUM(d.monto_reembolso), 0)
                        FROM devoluciones d
                        WHERE d.id_venta_destino_canje_FK = v.id_venta) AS monto_canje_aplicado,
                       COALESCE(CONCAT(uc.nombre, ' ', uc.primer_apellido, COALESCE(CONCAT(' ', uc.segundo_apellido), '')), 'Publico general') AS cliente_nombre,
                       uc.correo AS cliente_correo,
                       CONCAT(ue.nombre, ' ', ue.primer_apellido, COALESCE(CONCAT(' ', ue.segundo_apellido), '')) AS empleado_nombre,
                       ue.correo AS empleado_correo,
                       i.tipo_impuesto
                FROM ventas v
                LEFT JOIN clientes c ON c.id_cliente = v.id_cliente_FK
                LEFT JOIN usuarios uc ON uc.id_usuario = c.id_usuario_FK
                INNER JOIN empleados e ON e.id_empleado = v.id_empleado_FK
                INNER JOIN usuarios ue ON ue.id_usuario = e.id_usuario_FK
                INNER JOIN impuestos i ON i.id_impuesto = v.id_impuesto_FK
                WHERE 1=1";

        if (!empty($filtros['fecha_desde'])) {
            $sql .= ' AND DATE(v.fecha_venta) >= :fecha_desde';
        }
        if (!empty($filtros['fecha_hasta'])) {
            $sql .= ' AND DATE(v.fecha_venta) <= :fecha_hasta';
        }
        if (array_key_exists('id_cliente', $filtros) && $filtros['id_cliente'] !== null) {
            $idCliF = (int) $filtros['id_cliente'];
            if ($idCliF === 0) {
                $sql .= ' AND v.id_cliente_FK IS NULL';
            } else {
                $sql .= ' AND v.id_cliente_FK = :id_cliente';
            }
        }
        if (!empty($filtros['id_empleado'])) {
            $sql .= ' AND v.id_empleado_FK = :id_empleado';
        }
        if (!empty($filtros['estado'])) {
            $sql .= ' AND v.estado = :estado';
        }
        if (!empty($filtros['origen'])) {
            if ($filtros['origen'] === 'liquidacion') {
                $sql .= ' AND v.id_apartado_FK IS NOT NULL';
            } elseif ($filtros['origen'] === 'directa') {
                $sql .= ' AND v.id_apartado_FK IS NULL';
            }
        }

        if ($pat !== null) {
            $sql .= " AND (
                CONCAT(uc.nombre, ' ', uc.primer_apellido, COALESCE(CONCAT(' ', uc.segundo_apellido), '')) LIKE :busq
                OR CONCAT(ue.nombre, ' ', ue.primer_apellido, COALESCE(CONCAT(' ', ue.segundo_apellido), '')) LIKE :busq2
                OR v.estado LIKE :busq3 OR i.tipo_impuesto LIKE :busq4 OR CAST(v.total AS CHAR) LIKE :busq5
                OR CAST(v.id_venta AS CHAR) LIKE :busq6 OR uc.correo LIKE :busq7
                OR (v.id_apartado_FK IS NOT NULL AND CAST(v.id_apartado_FK AS CHAR) LIKE :busq8)
            )";
        }
        $sql .= " ORDER BY v.fecha_venta DESC, v.id_venta DESC";

        $stmt = $this->getDb()->prepare($sql);
        if (!empty($filtros['fecha_desde'])) {
            $stmt->bindValue(':fecha_desde', (string) $filtros['fecha_desde'], PDO::PARAM_STR);
        }
        if (!empty($filtros['fecha_hasta'])) {
            $stmt->bindValue(':fecha_hasta', (string) $filtros['fecha_hasta'], PDO::PARAM_STR);
        }
        if (array_key_exists('id_cliente', $filtros) && $filtros['id_cliente'] !== null && (int) $filtros['id_cliente'] > 0) {
            $stmt->bindValue(':id_cliente', (int) $filtros['id_cliente'], PDO::PARAM_INT);
        }
        if (!empty($filtros['id_empleado'])) {
            $stmt->bindValue(':id_empleado', (int) $filtros['id_empleado'], PDO::PARAM_INT);
        }
        if (!empty($filtros['estado'])) {
            $stmt->bindValue(':estado', (string) $filtros['estado'], PDO::PARAM_STR);
        }
        if ($pat !== null) {
            $stmt->bindValue(':busq', $pat, PDO::PARAM_STR);
            $stmt->bindValue(':busq2', $pat, PDO::PARAM_STR);
            $stmt->bindValue(':busq3', $pat, PDO::PARAM_STR);
            $stmt->bindValue(':busq4', $pat, PDO::PARAM_STR);
            $stmt->bindValue(':busq5', $pat, PDO::PARAM_STR);
            $stmt->bindValue(':busq6', $pat, PDO::PARAM_STR);
            $stmt->bindValue(':busq7', $pat, PDO::PARAM_STR);
            $stmt->bindValue(':busq8', $pat, PDO::PARAM_STR);
        }
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function leerUno($idVenta)
    {
        $sql = "SELECT v.id_venta,
                       v.id_cliente_FK,
                       v.id_empleado_FK,
                       v.id_impuesto_FK,
                       v.id_apartado_FK,
                       v.fecha_venta,
                       v.total,
                       v.estado,
                       v.impuesto_porcentaje,
                       v.impuesto_monto,
                       COALESCE(CONCAT(uc.nombre, ' ', uc.primer_apellido, COALESCE(CONCAT(' ', uc.segundo_apellido), '')), 'Publico general') AS cliente_nombre,
                       uc.correo AS cliente_correo,
                       CONCAT(ue.nombre, ' ', ue.primer_apellido, COALESCE(CONCAT(' ', ue.segundo_apellido), '')) AS empleado_nombre,
                       ue.correo AS empleado_correo,
                       i.tipo_impuesto
                FROM ventas v
                LEFT JOIN clientes c ON c.id_cliente = v.id_cliente_FK
                LEFT JOIN usuarios uc ON uc.id_usuario = c.id_usuario_FK
                INNER JOIN empleados e ON e.id_empleado = v.id_empleado_FK
                INNER JOIN usuarios ue ON ue.id_usuario = e.id_usuario_FK
                INNER JOIN impuestos i ON i.id_impuesto = v.id_impuesto_FK
                WHERE v.id_venta = :id_venta";

        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(':id_venta', (int) $idVenta, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (is_array($row)) {
            $row['detalle'] = $this->leerDetallesVenta((int) $row['id_venta']);
        }

        return $row;
    }

    public function leerDetallesVenta(int $idVenta): array
    {
        $sql = "                SELECT vd.*,
                       CASE vd.tipo_linea
                           WHEN 'joya' THEN p.desc_pieza
                           ELSE i.nombre
                       END AS nombre_item,
                       ps.codigo_auxiliar AS pieza_codigo_auxiliar,
                       ps.codigo_barras AS pieza_codigo_barras,
                       ps.estado AS estado_pieza,
                       i.sku_codigo AS insumo_codigo,
                       p.id_metal_FK AS pieza_id_metal,
                       m.nom_metal AS pieza_metal_nombre
                FROM venta_detalle vd
                LEFT JOIN piezas_stock ps ON vd.id_pieza_stock_FK = ps.id_pieza_stock
                LEFT JOIN piezas p ON ps.id_pieza_FK = p.id_pieza
                LEFT JOIN metales m ON m.id_metal = p.id_metal_FK
                LEFT JOIN insumos i ON vd.id_insumo_FK = i.id_insumo
                WHERE vd.id_venta_FK = :id
                ORDER BY vd.id_venta_detalle ASC";

        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(':id', $idVenta, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function leerPagosVenta(int $idVenta): array
    {
        $stmt = $this->getDb()->prepare(
            "SELECT vp.monto,
                    fp.forma_pago
             FROM venta_pagos vp
             INNER JOIN forma_pago fp ON fp.id_forma_pago = vp.id_forma_pago_FK
             WHERE vp.id_venta_FK = :id
             ORDER BY vp.monto ASC"
        );
        $stmt->bindValue(':id', $idVenta, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function obtenerCatalogos()
    {
        $db = $this->getDb();

        return [
            'clientes' => $db->query(
                "SELECT c.id_cliente,
                        c.descuento_porcentaje,
                        u.nombre,
                        u.primer_apellido,
                        u.segundo_apellido,
                        u.telefono,
                        CONCAT(u.nombre, ' ', u.primer_apellido, COALESCE(CONCAT(' ', u.segundo_apellido), '')) AS nombre_completo,
                        u.correo
                 FROM clientes c
                 INNER JOIN usuarios u ON u.id_usuario = c.id_usuario_FK
                 WHERE c.activo = 1
                 ORDER BY u.primer_apellido ASC, u.nombre ASC"
            )->fetchAll(PDO::FETCH_ASSOC),
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
            'impuestos' => $db->query("SELECT id_impuesto, tipo_impuesto, porcentaje FROM impuestos ORDER BY tipo_impuesto ASC")->fetchAll(PDO::FETCH_ASSOC),
            'apartados' => $db->query(
                "SELECT a.id_apartado,
                        a.estado,
                        CONCAT(u.nombre, ' ', u.primer_apellido, COALESCE(CONCAT(' ', u.segundo_apellido), '')) AS cliente_nombre
                 FROM apartados a
                 INNER JOIN clientes c ON c.id_cliente = a.id_cliente_FK
                 INNER JOIN usuarios u ON u.id_usuario = c.id_usuario_FK
                 WHERE a.estado = 'activo'
                 ORDER BY a.id_apartado DESC"
            )->fetchAll(PDO::FETCH_ASSOC),
        ];
    }

    public function obtenerEmpleadoIdPorUsuario(int $idUsuario): ?int
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
        $value = $stmt->fetchColumn();

        return $value !== false ? (int) $value : null;
    }

    public function obtenerImpuestoPorId(int $idImpuesto): ?array
    {
        $stmt = $this->getDb()->prepare(
            "SELECT id_impuesto, tipo_impuesto, porcentaje
             FROM impuestos
             WHERE id_impuesto = :id
             LIMIT 1"
        );
        $stmt->bindValue(':id', $idImpuesto, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * Impuesto inicial para formularios: configuracion_general.id_impuesto_default,
     * o el primero del catalogo si la clave no existe o no es valida.
     */
    public function obtenerIdImpuestoDefault(): ?int
    {
        $config = new ConfiguracionGeneral();
        $id = $config->resolverIdImpuestoDefault();
        if ($id !== null) {
            return $id;
        }

        $stmt = $this->getDb()->query(
            'SELECT id_impuesto FROM impuestos ORDER BY tipo_impuesto ASC LIMIT 1'
        );
        $value = $stmt ? $stmt->fetchColumn() : false;

        return $value !== false ? (int) $value : null;
    }

    public function obtenerDescuentoClientePorcentaje(?int $idCliente): ?float
    {
        if ($idCliente === null || $idCliente <= 0) {
            return null;
        }

        $stmt = $this->getDb()->prepare(
            "SELECT descuento_porcentaje
             FROM clientes
             WHERE id_cliente = :id
               AND activo = 1
             LIMIT 1"
        );
        $stmt->bindValue(':id', $idCliente, PDO::PARAM_INT);
        $stmt->execute();
        $value = $stmt->fetchColumn();
        if ($value === false || $value === null || $value === '') {
            return null;
        }

        $parsed = (float) $value;
        if ($parsed < 0) {
            return 0.0;
        }
        if ($parsed > 100) {
            return 100.0;
        }

        return $parsed;
    }

    public function obtenerDescuentoGeneralMostrador(): float
    {
        $config = new ConfiguracionGeneral();
        $map = $config->leerPorClaves(['descuento_general_mostrador']);
        $value = isset($map['descuento_general_mostrador']) ? (float) $map['descuento_general_mostrador'] : 0.0;

        return $this->acotarPorcentajeDescuento($value);
    }

    public function obtenerDescuentoInsumosMostrador(): float
    {
        $config = new ConfiguracionGeneral();
        $map = $config->leerPorClaves(['descuento_insumos_mostrador']);
        $value = isset($map['descuento_insumos_mostrador']) ? (float) $map['descuento_insumos_mostrador'] : 0.0;

        return $this->acotarPorcentajeDescuento($value);
    }

    /**
     * % de descuento para una linea de venta POS segun tipo y cliente.
     */
    public function resolverDescuentoPorcentajeLinea(string $tipoLinea, ?int $idCliente): float
    {
        $tipo = mb_strtolower(trim($tipoLinea));
        if ($tipo === 'insumo') {
            return $this->obtenerDescuentoInsumosMostrador();
        }

        $descuentoCliente = $this->obtenerDescuentoClientePorcentaje($idCliente);

        return $descuentoCliente !== null
            ? $descuentoCliente
            : $this->obtenerDescuentoGeneralMostrador();
    }

    private function acotarPorcentajeDescuento(float $value): float
    {
        if ($value < 0) {
            return 0.0;
        }
        if ($value > 100) {
            return 100.0;
        }

        return $value;
    }

    /** Prefijo en mensajes de excepcion para errores de inventario en POS (confirmar venta). */
    public const PREFIJO_ERROR_INVENTARIO_POS = '[INVENTARIO_NO_DISPONIBLE] ';

    /**
     * Resuelve pieza o insumo para POS con motivo de fallo estructurado.
     *
     * @return array{ok:bool, item?:array, codigo_error?:string, mensaje?:string}
     */
    public function resolverItemPuntoVenta(string $codigo, ?int $idTiendaPreferida = null): array
    {
        require_once __DIR__ . '/../../includes/barcode_scan_helpers.php';
        $codigo = joyeria_normalizar_codigo_escaneo($codigo);
        if ($codigo === '') {
            return [
                'ok' => false,
                'codigo_error' => 'codigo_no_encontrado',
                'mensaje' => 'Ingresa un codigo valido.',
            ];
        }

        $db = $this->getDb();

        require_once __DIR__ . '/pieza.php';
        $piezaModelCols = new Pieza();
        require_once __DIR__ . '/../../includes/variantes_stock_helpers.php';
        $usaCatalogo = joyeria_tiene_columnas_variante_catalogo($db);
        $colsMatriz = $piezaModelCols->tieneColumnasVarianteMatriz()
            ? "ps.variante_talla,
                    ps.variante_color,"
            : '';
        $joinCatalogo = $usaCatalogo ? joyeria_sql_join_variantes_stock('ps') : '';
        $selectCatalogo = $usaCatalogo ? joyeria_sql_select_variantes_stock() . ',' : '';

        $stmtPieza = $db->prepare(
            "SELECT ps.id_pieza_stock,
                    ps.codigo_auxiliar,
                    ps.codigo_barras,
                    ps.precio_venta,
                    ps.estado,
                    ps.activo,
                    ps.variante_tipo,
                    ps.variante_valor,
                    {$colsMatriz}
                    {$selectCatalogo}
                    p.id_pieza,
                    p.id_metal_FK,
                    p.id_sub_familia_FK,
                    sf.id_familia_FK,
                    p.desc_pieza,
                    p.costo,
                    p.aumento_pct,
                    m.nom_metal
             FROM piezas_stock ps
             INNER JOIN piezas p ON p.id_pieza = ps.id_pieza_FK
             INNER JOIN metales m ON m.id_metal = p.id_metal_FK
             INNER JOIN sub_familia sf ON sf.id_sub_familia = p.id_sub_familia_FK
             {$joinCatalogo}
             WHERE (ps.codigo_auxiliar = :codigo OR ps.codigo_barras = :codigo2)
             ORDER BY ps.id_pieza_stock DESC
             LIMIT 1"
        );
        $stmtPieza->bindValue(':codigo', $codigo, PDO::PARAM_STR);
        $stmtPieza->bindValue(':codigo2', $codigo, PDO::PARAM_STR);
        $stmtPieza->execute();
        $pieza = $stmtPieza->fetch(PDO::FETCH_ASSOC);
        if (is_array($pieza)) {
            $activo = (int) ($pieza['activo'] ?? 0);
            $estado = trim((string) ($pieza['estado'] ?? ''));
            $codigoPieza = (string) ($pieza['codigo_auxiliar'] ?: $pieza['codigo_barras'] ?: $codigo);
            $desc = trim((string) ($pieza['desc_pieza'] ?? ''));
            require_once __DIR__ . '/../../includes/variantes_stock_helpers.php';
            $textoVar = joyeria_texto_variante_stock($pieza);
            if ($textoVar !== '') {
                $desc = trim($desc . ' (' . $textoVar . ')');
            }
            if ($activo !== 1 || $estado !== 'disponible') {
                $estadoLabel = $estado !== '' ? $estado : ($activo !== 1 ? 'inactiva' : 'no disponible');
                if ($estado === 'reservada_pos') {
                    $estadoLabel = 'reservada en punto de venta';
                } elseif ($estado === 'reservada_online') {
                    $estadoLabel = 'reservada en carrito en linea';
                }
                $titulo = $desc !== '' ? $desc : ('Inventario #' . (int) $pieza['id_pieza_stock']);
                return [
                    'ok' => false,
                    'codigo_error' => 'inventario_no_disponible',
                    'mensaje' => 'La pieza «' . $titulo . '» (codigo ' . $codigoPieza
                        . ') no esta disponible: ' . $estadoLabel . '.',
                ];
            }
            $precio = isset($pieza['precio_venta']) ? (float) $pieza['precio_venta'] : 0.0;

            // Fallback: si el stock no tiene precio_venta (alta previa a grilla), calcular desde costo+aumento.
            if ($precio <= 0.009) {
                $costo = (float) ($pieza['costo'] ?? 0);
                $aumento = ($pieza['aumento_pct'] !== null && $pieza['aumento_pct'] !== '')
                    ? (float) $pieza['aumento_pct']
                    : 0.0;
                if ($costo > 0.009) {
                    $pvCalc = $costo * (1 + $aumento / 100);
                    if ($pvCalc > 0) {
                        $pvCalc = (float) (ceil($pvCalc / 5) * 5);
                    }
                    if ($pvCalc > 0.009) {
                        $precio = $pvCalc;
                    }
                }
            }

            return [
                'ok' => true,
                'item' => [
                    'tipo_linea' => 'joya',
                    'id_pieza_stock_FK' => (int) $pieza['id_pieza_stock'],
                    'id_pieza_FK' => (int) ($pieza['id_pieza'] ?? 0),
                    'id_metal_FK' => (int) ($pieza['id_metal_FK'] ?? 0),
                    'id_subfamilia_FK' => (int) ($pieza['id_sub_familia_FK'] ?? 0),
                    'id_familia_FK' => (int) ($pieza['id_familia_FK'] ?? 0),
                    'nom_metal' => (string) ($pieza['nom_metal'] ?? ''),
                    'codigo' => $codigoPieza,
                    'descripcion' => $desc,
                    'cantidad' => 1,
                    'precio_unitario' => $this->normalizarDecimal($precio),
                ],
            ];
        }

        $sqlInsumo = "SELECT i.id_insumo,
                             i.nombre,
                             i.sku_codigo";
        $colsInsumo = $this->obtenerColumnasInsumos($db);
        if (!empty($colsInsumo['promo_paga_unidades']) && !empty($colsInsumo['promo_lleva_unidades'])) {
            $sqlInsumo .= ",
                             i.promo_paga_unidades,
                             i.promo_lleva_unidades";
        }
        $sqlInsumo .= ",
                             COALESCE(i.precio_venta_sugerido, 0) AS precio_venta_sugerido,
                             ie.id_tienda_FK,
                             ie.cantidad,
                             t.nom_tienda
                      FROM insumos i
                      INNER JOIN insumo_existencia ie ON ie.id_insumo_FK = i.id_insumo
                      INNER JOIN tiendas t ON t.id_tienda = ie.id_tienda_FK
                      WHERE i.activo = 1
                        AND COALESCE(t.activo, 1) = 1
                        AND ie.cantidad > 0
                        AND i.sku_codigo = :codigo";

        if ($idTiendaPreferida !== null && $idTiendaPreferida > 0) {
            $sqlInsumo .= " AND ie.id_tienda_FK = :id_tienda";
        }

        $sqlInsumo .= " ORDER BY ie.cantidad DESC LIMIT 1";
        $stmtInsumo = $db->prepare($sqlInsumo);
        $stmtInsumo->bindValue(':codigo', $codigo, PDO::PARAM_STR);
        if ($idTiendaPreferida !== null && $idTiendaPreferida > 0) {
            $stmtInsumo->bindValue(':id_tienda', $idTiendaPreferida, PDO::PARAM_INT);
        }
        $stmtInsumo->execute();
        $insumo = $stmtInsumo->fetch(PDO::FETCH_ASSOC);
        if (is_array($insumo)) {
            $precioInsumo = isset($insumo['precio_venta_sugerido']) ? (float) $insumo['precio_venta_sugerido'] : 0.0;

            return [
                'ok' => true,
                'item' => [
                    'tipo_linea' => 'insumo',
                    'id_insumo_FK' => (int) $insumo['id_insumo'],
                    'id_tienda_FK' => (int) $insumo['id_tienda_FK'],
                    'codigo' => (string) ($insumo['sku_codigo'] ?? ''),
                    'descripcion' => (string) $insumo['nombre'] . ' [' . (string) $insumo['nom_tienda'] . ']',
                    'cantidad' => 1,
                    'precio_unitario' => $this->normalizarDecimal($precioInsumo > 0 ? $precioInsumo : 0.01),
                    'existencia_tienda' => number_format((float) $insumo['cantidad'], 3, '.', ''),
                    'promo_paga_unidades' => isset($insumo['promo_paga_unidades']) && $insumo['promo_paga_unidades'] !== null
                        ? (int) $insumo['promo_paga_unidades'] : null,
                    'promo_lleva_unidades' => isset($insumo['promo_lleva_unidades']) && $insumo['promo_lleva_unidades'] !== null
                        ? (int) $insumo['promo_lleva_unidades'] : null,
                ],
            ];
        }

        if ($idTiendaPreferida !== null && $idTiendaPreferida > 0) {
            $stmtOtraTienda = $db->prepare(
                "SELECT t.nom_tienda
                 FROM insumos i
                 INNER JOIN insumo_existencia ie ON ie.id_insumo_FK = i.id_insumo
                 INNER JOIN tiendas t ON t.id_tienda = ie.id_tienda_FK
                 WHERE i.activo = 1
                   AND COALESCE(t.activo, 1) = 1
                   AND ie.cantidad > 0
                   AND i.sku_codigo = :codigo
                   AND ie.id_tienda_FK <> :id_tienda
                 ORDER BY ie.cantidad DESC
                 LIMIT 1"
            );
            $stmtOtraTienda->bindValue(':codigo', $codigo, PDO::PARAM_STR);
            $stmtOtraTienda->bindValue(':id_tienda', $idTiendaPreferida, PDO::PARAM_INT);
            $stmtOtraTienda->execute();
            $otra = $stmtOtraTienda->fetch(PDO::FETCH_ASSOC);
            if (is_array($otra)) {
                return [
                    'ok' => false,
                    'codigo_error' => 'insumo_sin_existencia',
                    'mensaje' => 'El insumo con codigo «' . $codigo
                        . '» no tiene existencia en la tienda seleccionada'
                        . (isset($otra['nom_tienda']) ? ' (hay stock en ' . (string) $otra['nom_tienda'] . ').' : '.'),
                ];
            }
        }

        return [
            'ok' => false,
            'codigo_error' => 'codigo_no_encontrado',
            'mensaje' => 'No se encontro una pieza o insumo con el codigo «' . $codigo . '».',
        ];
    }

    public function buscarItemParaPuntoVenta(string $codigo, ?int $idTiendaPreferida = null): ?array
    {
        $res = $this->resolverItemPuntoVenta($codigo, $idTiendaPreferida);

        return ($res['ok'] ?? false) ? ($res['item'] ?? null) : null;
    }

    public function calcularTotalesPuntoVenta(array $detalles, ?int $idCliente, int $idImpuesto, float $montoCreditoCanje = 0.0): array
    {
        require_once __DIR__ . '/../includes/ReglasDescuentoService.php';
        $reglas = new ReglasDescuentoService();
        $calc = $reglas->calcularTotalesPos($detalles, $idCliente);

        $impuesto = $this->obtenerImpuestoPorId($idImpuesto);
        if (!$impuesto) {
            throw new InvalidArgumentException('El impuesto seleccionado no existe.');
        }

        $subtotal = (float) ($calc['subtotal'] ?? 0);
        $descuentoMonto = (float) ($calc['descuento_monto'] ?? 0);
        $basePreCredito = max(0, $subtotal - $descuentoMonto);
        $credito = max(0.0, min((float) $montoCreditoCanje, $basePreCredito));
        $base = max(0, $basePreCredito - $credito);
        $impuestoRate = isset($impuesto['porcentaje']) ? (float) $impuesto['porcentaje'] : 0.0;
        $impuestoMonto = $base * ($impuestoRate / 100);
        $total = $base + $impuestoMonto;

        return [
            'subtotal' => $this->normalizarDecimal($subtotal),
            'subtotal_piezas' => $this->normalizarDecimal((float) ($calc['subtotal_piezas'] ?? 0)),
            'subtotal_insumos' => $this->normalizarDecimal((float) ($calc['subtotal_insumos'] ?? 0)),
            'descuento_porcentaje' => $this->normalizarDecimal((float) ($calc['descuento_porcentaje'] ?? 0)),
            'descuento_porcentaje_piezas' => $this->normalizarDecimal((float) ($calc['descuento_porcentaje_piezas'] ?? 0)),
            'descuento_porcentaje_insumos' => $this->normalizarDecimal((float) ($calc['descuento_porcentaje_insumos'] ?? 0)),
            'descuento_monto' => $this->normalizarDecimal($descuentoMonto),
            'monto_credito_canje' => $this->normalizarDecimal($credito),
            'base_gravable' => $this->normalizarDecimal($base),
            'impuesto_porcentaje' => $this->normalizarDecimal($impuestoRate),
            'impuesto_monto' => $this->normalizarDecimal($impuestoMonto),
            'total' => $this->normalizarDecimal($total),
            'conteo_piezas' => (int) ($calc['conteo_piezas'] ?? 0),
            'ticket_mixto' => !empty($calc['ticket_mixto']),
            'lineas_calculadas' => $calc['lineas_calculadas'] ?? [],
            'descuento_progreso' => $calc['descuento_progreso'] ?? [],
            'insumo_promo_progreso' => $calc['insumo_promo_progreso'] ?? [],
            'descuento_detalle' => $calc['descuento_detalle'] ?? [],
        ];
    }

    /**
     * Importe de una linea de venta_detalle (subtotal con descuento de la venta original).
     */
    public function resolverSubtotalLineaDetalle(array $d): float
    {
        $cant = isset($d['cantidad']) ? (float) $d['cantidad'] : 1.0;
        if ($cant <= 0) {
            $cant = 1.0;
        }
        $pu = isset($d['precio_unitario']) && is_numeric($d['precio_unitario'])
            ? (float) $d['precio_unitario']
            : 0.0;
        $bruto = $pu > 0 ? $pu * $cant : 0.0;

        $pf = isset($d['precio_final']) && is_numeric($d['precio_final']) ? (float) $d['precio_final'] : 0.0;
        if ($pf > 0 && ($pu <= 0.01 || $pf < $pu - 0.001)) {
            return $pf * $cant;
        }

        $desc = isset($d['descuento_aplicado']) && is_numeric($d['descuento_aplicado'])
            ? (float) $d['descuento_aplicado']
            : 0.0;
        if ($bruto > 0 && $desc > 0.009) {
            return max(0.0, $bruto - $desc);
        }

        $st = isset($d['subtotal']) && is_numeric($d['subtotal']) ? (float) $d['subtotal'] : 0.0;
        if ($st > 0.01 && ($bruto <= 0.01 || $st <= $bruto + 0.01)) {
            return $st;
        }
        if ($st > 0 && $bruto <= 0.01) {
            return $st;
        }

        if ($bruto > 0) {
            return $bruto;
        }

        if ($st > 0) {
            return $st;
        }

        return 0.0;
    }

    /**
     * Importe a devolver o canjear por una linea. Si la venta recibio canje (canjeAplicado > 0),
     * usa totalReferencia = efectivo cobrado + canje restante para acreditar valor integro de mercancia.
     *
     * @param array<int, array<string, mixed>> $detallesActivosIncluyeLinea Lineas activas incluyendo la devuelta
     */
    public function resolverImporteDevolucionLineaVenta(
        array $linea,
        array $venta,
        array $detallesActivosIncluyeLinea,
        float $canjeAplicado = 0.0,
        float $montoYaDevuelto = 0.0
    ): float {
        $montoLinea = $this->resolverSubtotalLineaDetalle($linea);
        if ($montoLinea <= 0) {
            return 0.0;
        }

        $totalCabecera = (float) ($venta['total'] ?? 0);
        $impPct = $this->resolverImpuestoPorcentajeVenta($venta);
        $totAntes = $this->calcularTotalDesdeSubtotalesDetalle($detallesActivosIncluyeLinea, $impPct);
        $totalAntesDetalle = (float) $totAntes['total'];

        if ($canjeAplicado > 0.009 && $totalAntesDetalle > 0.01) {
            $totalLineasOriginal = $totalAntesDetalle + max(0.0, $montoYaDevuelto);
            if ($totalLineasOriginal <= 0.01) {
                return $montoLinea;
            }
            $canjeRestante = $canjeAplicado * ($totalAntesDetalle / $totalLineasOriginal);
            $totalReferencia = $totalCabecera + $canjeRestante;
            if ($totalReferencia <= 0.01) {
                return $montoLinea;
            }
            if (abs($totalReferencia - $totalAntesDetalle) <= 0.02) {
                return $montoLinea;
            }
            $factor = $totalReferencia / $totalAntesDetalle;

            return max(0.0, min($totalReferencia, $montoLinea * $factor));
        }

        if ($totalCabecera <= 0.01) {
            return $montoLinea;
        }

        if ($totalAntesDetalle <= 0.01 || abs($totalCabecera - $totalAntesDetalle) <= 0.02) {
            return $montoLinea;
        }

        $lineasActivas = array_values(array_filter(
            $detallesActivosIncluyeLinea,
            static fn ($d) => is_array($d)
        ));
        if (count($lineasActivas) === 1) {
            return $totalCabecera;
        }

        $factor = $totalCabecera / $totalAntesDetalle;

        return max(0.0, min($totalCabecera, $montoLinea * $factor));
    }

    /**
     * Total desde venta_detalle ya persistido (subtotal con descuento de la venta original).
     * Evita inconsistencias al devolver/canjear si el descuento del cliente cambio despues.
     *
     * @param array<int, array<string, mixed>> $detallesActivos
     * @return array{subtotal: string, impuesto_monto: string, total: string}
     */
    public function calcularTotalDesdeSubtotalesDetalle(array $detallesActivos, float $impuestoPorcentaje): array
    {
        $subtotal = 0.0;
        foreach ($detallesActivos as $d) {
            if (!is_array($d)) {
                continue;
            }
            $subtotal += $this->resolverSubtotalLineaDetalle($d);
        }
        $impuestoPorcentaje = max(0.0, $impuestoPorcentaje);
        $impuestoMonto = $subtotal * ($impuestoPorcentaje / 100);
        $total = $subtotal + $impuestoMonto;

        return [
            'subtotal' => $this->normalizarDecimal($subtotal),
            'impuesto_monto' => $this->normalizarDecimal($impuestoMonto),
            'total' => $this->normalizarDecimal($total),
        ];
    }

    /**
     * Porcentaje de impuesto de la venta original (cabecera o tabla impuestos).
     */
    public function resolverImpuestoPorcentajeVenta(array $venta): float
    {
        $impPct = isset($venta['impuesto_porcentaje']) && is_numeric($venta['impuesto_porcentaje'])
            ? (float) $venta['impuesto_porcentaje']
            : 0.0;
        if ($impPct > 0) {
            return $impPct;
        }
        $idImpuesto = (int) ($venta['id_impuesto_FK'] ?? 0);
        if ($idImpuesto <= 0) {
            return 0.0;
        }
        $impuesto = $this->obtenerImpuestoPorId($idImpuesto);

        return isset($impuesto['porcentaje']) ? (float) $impuesto['porcentaje'] : 0.0;
    }

    private function decodificarDetallesDesdePayload(array $data): array
    {
        $raw = $data['detalles'] ?? null;
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }

        return is_array($raw) ? $raw : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function parseCreditosCanjeDesdePayload(array $data): array
    {
        $raw = $data['creditos_canje'] ?? null;
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $c) {
            if (is_array($c)) {
                $out[] = $c;
            }
        }

        return $out;
    }

    private function sumarMontosCreditoCanje(array $creditos): float
    {
        $s = 0.0;
        foreach ($creditos as $c) {
            if (!is_array($c)) {
                continue;
            }
            $s += (float) ($c['monto_credito'] ?? 0);
        }

        return $s;
    }

    /**
     * Valida creditos de canje contra lineas del ticket (subtotal lista >= credito bruto).
     * El descuento al cliente puede capar el credito aplicado; eso no es error aqui.
     */
    public function validarCreditosCanjeContraDetalles(
        array $detalles,
        ?int $idCliente,
        int $idImpuesto,
        float $mCanjeBruto
    ): void {
        if ($mCanjeBruto <= 0) {
            return;
        }
        $tot0 = $this->calcularTotalesPuntoVenta($detalles, $idCliente, $idImpuesto, 0.0);
        if ((float) $tot0['subtotal'] + 0.01 < $mCanjeBruto) {
            throw new InvalidArgumentException(
                'El subtotal de piezas nuevas debe ser mayor o igual al credito por devolucion (mismo valor o mayor).'
            );
        }
    }

    public function crear($data)
    {
        $idCliente = $this->validarEnteroOpcional($data, 'id_cliente_FK');
        $idEmpleado = $this->validarEntero($data, 'id_empleado_FK', 'El empleado');
        $idImpuesto = $this->validarEntero($data, 'id_impuesto_FK', 'El impuesto');
        $idApartado = $this->validarEnteroOpcional($data, 'id_apartado_FK');
        $estado = $this->validarEstado($data['estado'] ?? 'completada');

        $creditosRaw = $this->parseCreditosCanjeDesdePayload($data);
        $creditos = [];
        if ($creditosRaw !== []) {
            require_once __DIR__ . '/devoluciones.php';
            $devPre = new Devoluciones();
            $vistos = [];
            foreach ($creditosRaw as $c) {
                if (!is_array($c)) {
                    continue;
                }
                $idV = (int) ($c['id_venta_origen'] ?? $c['id_venta_FK'] ?? 0);
                $idPs = (int) ($c['id_pieza_stock_FK'] ?? 0);
                $motivo = trim((string) ($c['motivo'] ?? ''));
                if ($idV <= 0 || $idPs <= 0) {
                    throw new InvalidArgumentException('Cada credito de canje requiere venta origen y pieza.');
                }
                $clave = $idV . '_' . $idPs;
                if (isset($vistos[$clave])) {
                    throw new InvalidArgumentException('Hay un credito de canje duplicado en la peticion.');
                }
                $vistos[$clave] = true;
                $credValidado = $devPre->prepararCreditoCanjeParaPos($idV, '', $motivo, $idEmpleado, $idPs);
                // Preservar el monto_credito de sesión (calculado por el servidor al
                // agregar el crédito al ticket). prepararCreditoCanjeParaPos solo valida
                // que el crédito siga disponible; el monto para el total de la nueva
                // venta viene de lo que el cajero ya vio en pantalla.
                $montoSesion = isset($c['monto_credito']) && is_numeric($c['monto_credito'])
                    ? (float) $c['monto_credito']
                    : 0.0;
                if ($montoSesion > 0.009) {
                    $credValidado['monto_credito'] = $this->normalizarDecimal($montoSesion);
                }
                $creditos[] = $credValidado;
            }
        }

        if ($creditos !== []) {
            $detDecoded = $this->decodificarDetallesDesdePayload($data);
            if ($detDecoded === []) {
                throw new InvalidArgumentException('Venta con credito de canje requiere lineas en el ticket.');
            }
            $mCanje = $this->sumarMontosCreditoCanje($creditos);
            if ($mCanje <= 0) {
                throw new InvalidArgumentException('El monto de canje no es valido.');
            }
            $this->validarCreditosCanjeContraDetalles($detDecoded, $idCliente, $idImpuesto, $mCanje);
            $totSrv = $this->calcularTotalesPuntoVenta($detDecoded, $idCliente, $idImpuesto, $mCanje);
            $total = (string) $totSrv['total'];
            $impuestoPorcentaje = (string) $totSrv['impuesto_porcentaje'];
            $impuestoMonto = (string) $totSrv['impuesto_monto'];
        } else {
            $detDecoded = $this->decodificarDetallesDesdePayload($data);
            if ($detDecoded !== []) {
                // Recalcular servidor: ignora total del cliente
                $totSrv = $this->calcularTotalesPuntoVenta($detDecoded, $idCliente, $idImpuesto, 0.0);
                $total = (string) $totSrv['total'];
                $impuestoPorcentaje = (string) $totSrv['impuesto_porcentaje'];
                $impuestoMonto = (string) $totSrv['impuesto_monto'];
            } else {
                // Venta sin detalles (ajuste manual): acepta valores del cliente
                $total = $this->validarDecimal($data, 'total', 'El total');
                $impuestoPorcentaje = $this->validarDecimalRango($data, 'impuesto_porcentaje', 'El porcentaje de impuesto', 0, 100);
                $impuestoMonto = $this->validarDecimalNoNegativo($data, 'impuesto_monto', 'El monto del impuesto');
            }
        }
        

        $db = $this->getDb();
        $db->beginTransaction();

        try {
            auth_mysql_set_audit_vars($db);

            if ($idCliente !== null) {
                $this->verificarExiste($db, 'SELECT 1 FROM clientes WHERE id_cliente = :id AND activo = 1', ':id', $idCliente, 'El cliente no existe o esta inactivo.');
            }
            $this->verificarExiste($db, 'SELECT 1 FROM empleados WHERE id_empleado = :id AND activo = 1', ':id', $idEmpleado, 'El empleado no existe o esta inactivo.');
            $this->verificarExiste($db, 'SELECT 1 FROM impuestos WHERE id_impuesto = :id', ':id', $idImpuesto, 'El impuesto no existe.');

            if ($idApartado !== null) {
                $this->verificarExiste(
                    $db,
                    "SELECT 1 FROM apartados WHERE id_apartado = :id AND estado = 'activo'",
                    ':id',
                    $idApartado,
                    'El apartado no existe o no esta activo (no se puede liquidar en venta).'
                );
            }

            $stmt = $db->prepare(
                "INSERT INTO ventas
                (id_cliente_FK, id_empleado_FK, id_impuesto_FK, id_apartado_FK, total, estado, impuesto_porcentaje, impuesto_monto)
                VALUES
                (:id_cliente_FK, :id_empleado_FK, :id_impuesto_FK, :id_apartado_FK, :total, :estado, :impuesto_porcentaje, :impuesto_monto)"
            );

            $stmt->bindValue(':id_cliente_FK', $idCliente, $idCliente === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(':id_empleado_FK', $idEmpleado, PDO::PARAM_INT);
            $stmt->bindValue(':id_impuesto_FK', $idImpuesto, PDO::PARAM_INT);
            $stmt->bindValue(':id_apartado_FK', $idApartado, $idApartado === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(':total', $total, PDO::PARAM_STR);
            $stmt->bindValue(':estado', $estado, PDO::PARAM_STR);
            $stmt->bindValue(':impuesto_porcentaje', $impuestoPorcentaje, PDO::PARAM_STR);
            $stmt->bindValue(':impuesto_monto', $impuestoMonto, PDO::PARAM_STR);
            $stmt->execute();

            $idVenta = (int) $db->lastInsertId();
            $this->procesarDetallesSiExisten($db, $idVenta, $data);
            $this->procesarPagosSiExisten($db, $idVenta, $data, (float) $total);

            if ($creditos !== []) {
                require_once __DIR__ . '/devoluciones.php';
                $devoluciones = new Devoluciones();
                $devoluciones->aplicarCreditosCanjeTrasNuevaVenta($db, $idVenta, $creditos, $idEmpleado);
            }

            if ($idApartado !== null && $idApartado > 0) {
                $updAp = $db->prepare(
                    "UPDATE apartados SET estado = 'liquidado', saldo_pendiente = '0.00' WHERE id_apartado = :id AND estado = 'activo'"
                );
                $updAp->bindValue(':id', $idApartado, PDO::PARAM_INT);
                $updAp->execute();
                if ($updAp->rowCount() !== 1) {
                    throw new InvalidArgumentException('No se pudo marcar el apartado como liquidado.');
                }
                require_once __DIR__ . '/apartado_gestion.php';
                (new ApartadoGestion())->marcarPiezasStockVendidasTrasLiquidacionApartado($db, $idApartado);
            }

            if ($idCliente !== null && $idCliente > 0) {
                require_once __DIR__ . '/../includes/ReglasDescuentoService.php';
                require_once __DIR__ . '/../includes/DescuentoTiendaService.php';
                $detDecoded = $this->decodificarDetallesDesdePayload($data);
                $subPlataLista = (new ReglasDescuentoService())->calcularSubtotalPlataListaPos($detDecoded);
                (new DescuentoTiendaService())->persistirDescuentoMayoreoSiCalifica((int) $idCliente, $subPlataLista);
            }

            $db->commit();

            if ($idApartado !== null && $idApartado > 0) {
                require_once __DIR__ . '/../includes/ImpresionTicketHelper.php';
                require_once __DIR__ . '/apartado_gestion.php';
                $agTk = new ApartadoGestion();
                $idTiendaTk = $agTk->obtenerIdTiendaPorApartado($idApartado);
                joyeria_encolar_ticket_apartado($idApartado, 'liquidacion', $idTiendaTk);
            }

            require_once __DIR__ . '/../includes/factura_auto.php';
            joyeria_emitir_factura_tras_venta($idVenta);

            return $idVenta;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Opcionalmente registra líneas en venta_detalle y aplica movimientos de stock.
     *
     * Cada elemento de $data['detalles'] debe incluir tipo_linea o inferirse:
     * joya: id_pieza_stock_FK obligatorio.
     * insumo: id_insumo_FK, id_tienda_FK, cantidad (>= 0.001).
     */
    private function procesarDetallesSiExisten(PDO $db, int $idVenta, array $data): void
    {
        $raw = $data['detalles'] ?? null;
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($raw) || $raw === []) {
            return;
        }

        $posReservaToken = trim((string) ($data['pos_reserva_token'] ?? ''));

        $idClienteVenta = isset($data['id_cliente_FK']) && (int) $data['id_cliente_FK'] > 0
            ? (int) $data['id_cliente_FK']
            : null;

        require_once __DIR__ . '/../includes/ReglasDescuentoService.php';
        $lineasCalc = (new ReglasDescuentoService())->calcularTotalesPos($raw, $idClienteVenta)['lineas_calculadas'] ?? [];

        $cols = $this->obtenerColumnasVentaDetalle($db);
        $insertCols = ['id_venta_FK'];
        $insertVals = [':id_venta'];

        if (isset($cols['tipo_linea'])) {
            $insertCols[] = 'tipo_linea';
            $insertVals[] = ':tipo';
        }
        if (isset($cols['id_pieza_stock_FK'])) {
            $insertCols[] = 'id_pieza_stock_FK';
            $insertVals[] = ':id_ps';
        }
        if (isset($cols['id_insumo_FK'])) {
            $insertCols[] = 'id_insumo_FK';
            $insertVals[] = ':id_ins';
        }
        if (isset($cols['id_tienda_FK'])) {
            $insertCols[] = 'id_tienda_FK';
            $insertVals[] = ':id_tnd';
        }
        if (isset($cols['cantidad'])) {
            $insertCols[] = 'cantidad';
            $insertVals[] = ':cantidad';
        }
        if (isset($cols['precio_unitario'])) {
            $insertCols[] = 'precio_unitario';
            $insertVals[] = ':pu';
        }
        if (isset($cols['subtotal'])) {
            $insertCols[] = 'subtotal';
            $insertVals[] = ':sub';
        }
        if (isset($cols['descuento_aplicado'])) {
            $insertCols[] = 'descuento_aplicado';
            $insertVals[] = ':descuento_aplicado';
        }
        // Compatibilidad con esquemas anteriores donde precio_final es requerido.
        if (isset($cols['precio_final'])) {
            $insertCols[] = 'precio_final';
            $insertVals[] = ':precio_final';
        }
        if (isset($cols['costo_unitario'])) {
            $insertCols[] = 'costo_unitario';
            $insertVals[] = ':costo_unitario';
        }

        $stmtInsDet = $db->prepare(
            'INSERT INTO venta_detalle (' . implode(', ', $insertCols) . ')
             VALUES (' . implode(', ', $insertVals) . ')'
        );

        $updJoyas = $db->prepare(
            "UPDATE piezas_stock
             SET estado = 'vendida',
                 reservada_hasta = NULL,
                 pos_reserva_token = NULL,
                 id_carrito_owner = NULL
             WHERE id_pieza_stock = :id
               AND activo = 1
               AND (
                   estado = 'disponible'
                   OR (estado = 'reservada_pos' AND pos_reserva_token = :token)
               )"
        );

        // Auditoria: usuario que ejecuta la venta (mismo patron que
        // procesarPagosSiExisten). Sirve para movimientos_inventario.
        $idUsuarioVenta = isset($data['id_usuario_FK']) && (int) $data['id_usuario_FK'] > 0
            ? (int) $data['id_usuario_FK']
            : 0;
        if ($idUsuarioVenta <= 0 && function_exists('auth_user')) {
            $u = auth_user();
            if (is_array($u)) {
                $idUsuarioVenta = (int) ($u['id_usuario'] ?? 0);
            }
        }

        // Movimiento de inventario por cada joya vendida (resiliente: solo si
        // la tabla existe; en pruebas con schema incompleto se omite el INSERT).
        $insMovVenta = null;
        try {
            $db->query('SELECT 1 FROM movimientos_inventario LIMIT 1')->fetch();
            $insMovVenta = $db->prepare(
                "INSERT INTO movimientos_inventario
                    (id_pieza_stock_FK, tipo_movimiento, referencia, observaciones,
                     id_usuario_FK, id_tienda_origen_FK, id_venta_FK, tipo_referencia)
                 VALUES
                    (:ps, 'venta', :ref, NULL,
                     :u, :tnd, :ve, 'venta')"
            );
        } catch (Throwable $e) {
            $insMovVenta = null;
            error_log('Ventas::procesarDetallesSiExisten movimientos_inventario no disponible: ' . $e->getMessage());
        }

        $insumoCtrl = new Insumos();

        $piezasIncluidas = [];
        $insumosIncluidos = [];

        foreach ($raw as $index => $linea) {
            if (!is_array($linea)) {
                throw new InvalidArgumentException('Linea de detalle invalida en posicion ' . $index . '.');
            }

            $tipo = isset($linea['tipo_linea']) ? mb_strtolower(trim((string) $linea['tipo_linea'])) : '';
            if ($tipo === '') {
                $tipo = isset($linea['id_insumo_FK']) ? 'insumo' : 'joya';
            }

            $precio = $linea['precio_unitario'] ?? null;
            if ($precio === null || trim((string) $precio) === '' || !is_numeric($precio) || (float) $precio <= 0) {
                throw new InvalidArgumentException('precio_unitario invalido en linea ' . ($index + 1) . '.');
            }
            $pu = $this->normalizarDecimal($precio);

            if ($tipo === 'joya') {
                $idPiezaStock = isset($linea['id_pieza_stock_FK']) ? (int) $linea['id_pieza_stock_FK'] : 0;
                if ($idPiezaStock <= 0) {
                    throw new InvalidArgumentException('id_pieza_stock_FK obligatorio para linea tipo joya.');
                }
                if (isset($piezasIncluidas[$idPiezaStock])) {
                    throw new InvalidArgumentException('La pieza de stock #' . $idPiezaStock . ' ya fue agregada a la venta.');
                }
                $piezasIncluidas[$idPiezaStock] = true;

                $cantJoy = isset($linea['cantidad']) ? (float) $linea['cantidad'] : 1.0;
                if (abs($cantJoy - 1.0) > 0.00001) {
                    throw new InvalidArgumentException('Las lineas de joyeria deben tener cantidad 1.');
                }
                $qtyStrJoy = $this->normalizarCantidadLinea($cantJoy);

                $st = $db->prepare(
                    "SELECT ps.estado, ps.activo, ps.pos_reserva_token,
                            COALESCE(p.costo, 0) AS costo_unitario,
                            p.id_tienda_FK AS id_tienda_origen
                     FROM piezas_stock ps
                     INNER JOIN piezas p ON p.id_pieza = ps.id_pieza_FK
                     WHERE ps.id_pieza_stock = :id
                     LIMIT 1"
                );
                $st->bindValue(':id', $idPiezaStock, PDO::PARAM_INT);
                $st->execute();
                $psRow = $st->fetch(PDO::FETCH_ASSOC);
                $estadoPs = trim((string) ($psRow['estado'] ?? ''));
                $tokenPs = trim((string) ($psRow['pos_reserva_token'] ?? ''));
                $reservaPosOk = $estadoPs === 'reservada_pos'
                    && $posReservaToken !== ''
                    && $tokenPs === $posReservaToken;
                if (!$psRow || (int) ($psRow['activo'] ?? 0) !== 1
                    || ($estadoPs !== 'disponible' && !$reservaPosOk)) {
                    $estadoLabel = $estadoPs !== '' ? $estadoPs : 'no disponible';
                    if ($estadoPs === 'reservada_pos') {
                        $estadoLabel = 'reservada en otro ticket de punto de venta';
                    }
                    throw new InvalidArgumentException(
                        self::PREFIJO_ERROR_INVENTARIO_POS
                        . 'El inventario #' . $idPiezaStock . ' ya no esta disponible para la venta (estado: '
                        . $estadoLabel . '). Quita la linea del ticket o escanea otra pieza.'
                    );
                }
                $idTiendaOrigenLinea = (int) ($psRow['id_tienda_origen'] ?? 0);

                $subtotalBruto = (float) $pu * $cantJoy;
                $calcLinea = isset($lineasCalc[$index]) && is_array($lineasCalc[$index]) ? $lineasCalc[$index] : null;
                $descuentoLinea = $calcLinea !== null
                    ? $this->normalizarDecimal((float) ($calcLinea['descuento_monto'] ?? 0))
                    : $this->normalizarDecimal($subtotalBruto * ($this->resolverDescuentoPorcentajeLinea('joya', $idClienteVenta) / 100));
                $subtotal = $calcLinea !== null
                    ? $this->normalizarDecimal((float) ($calcLinea['subtotal_neto'] ?? max(0, $subtotalBruto - (float) $descuentoLinea)))
                    : $this->normalizarDecimal(max(0, $subtotalBruto - (float) $descuentoLinea));
                $precioFinalUnit = $this->normalizarDecimal((float) $subtotal / max(1.0, $cantJoy));
                $costoUnitario = $this->normalizarDecimal(max(0.01, (float) ($psRow['costo_unitario'] ?? 0)));

                $stmtInsDet->bindValue(':id_venta', $idVenta, PDO::PARAM_INT);
                if (isset($cols['tipo_linea'])) {
                    $stmtInsDet->bindValue(':tipo', 'joya', PDO::PARAM_STR);
                }
                if (isset($cols['id_pieza_stock_FK'])) {
                    $stmtInsDet->bindValue(':id_ps', $idPiezaStock, PDO::PARAM_INT);
                }
                if (isset($cols['id_insumo_FK'])) {
                    $stmtInsDet->bindValue(':id_ins', null, PDO::PARAM_NULL);
                }
                if (isset($cols['id_tienda_FK'])) {
                    $stmtInsDet->bindValue(':id_tnd', null, PDO::PARAM_NULL);
                }
                if (isset($cols['cantidad'])) {
                    $stmtInsDet->bindValue(':cantidad', $qtyStrJoy, PDO::PARAM_STR);
                }
                if (isset($cols['precio_unitario'])) {
                    $stmtInsDet->bindValue(':pu', $pu, PDO::PARAM_STR);
                }
                if (isset($cols['subtotal'])) {
                    $stmtInsDet->bindValue(':sub', $subtotal, PDO::PARAM_STR);
                }
                if (isset($cols['descuento_aplicado'])) {
                    $stmtInsDet->bindValue(':descuento_aplicado', $descuentoLinea, PDO::PARAM_STR);
                }
                if (isset($cols['precio_final'])) {
                    $stmtInsDet->bindValue(':precio_final', $precioFinalUnit, PDO::PARAM_STR);
                }
                if (isset($cols['costo_unitario'])) {
                    $stmtInsDet->bindValue(':costo_unitario', $costoUnitario, PDO::PARAM_STR);
                }
                $stmtInsDet->execute();

                $updJoyas->bindValue(':id', $idPiezaStock, PDO::PARAM_INT);
                $updJoyas->bindValue(':token', $posReservaToken, PDO::PARAM_STR);
                $updJoyas->execute();

                if ($updJoyas->rowCount() !== 1) {
                    throw new InvalidArgumentException('No se pudo marcar vendida la pieza de stock #' . $idPiezaStock . '.');
                }

                // Movimiento de inventario tipo 'venta' (auditoria/trazabilidad).
                if ($insMovVenta !== null && $idUsuarioVenta > 0) {
                    try {
                        $insMovVenta->bindValue(':ps', $idPiezaStock, PDO::PARAM_INT);
                        $insMovVenta->bindValue(':ref', 'VENTA_POS_' . $idVenta, PDO::PARAM_STR);
                        $insMovVenta->bindValue(':u', $idUsuarioVenta, PDO::PARAM_INT);
                        if ($idTiendaOrigenLinea > 0) {
                            $insMovVenta->bindValue(':tnd', $idTiendaOrigenLinea, PDO::PARAM_INT);
                        } else {
                            $insMovVenta->bindValue(':tnd', null, PDO::PARAM_NULL);
                        }
                        $insMovVenta->bindValue(':ve', $idVenta, PDO::PARAM_INT);
                        $insMovVenta->execute();
                    } catch (Throwable $e) {
                        // No abortamos la venta por un fallo de auditoria; solo log.
                        error_log('Ventas::procesarDetallesSiExisten movimiento venta: ' . $e->getMessage());
                    }
                }

                continue;
            }

            if ($tipo !== 'insumo') {
                throw new InvalidArgumentException('tipo_linea invalido en linea ' . ($index + 1) . '.');
            }

            $idInsumo = isset($linea['id_insumo_FK']) ? (int) $linea['id_insumo_FK'] : 0;
            $idTienda = isset($linea['id_tienda_FK']) ? (int) $linea['id_tienda_FK'] : 0;

            if ($idInsumo <= 0 || $idTienda <= 0) {
                throw new InvalidArgumentException('Linea insumo: id_insumo_FK e id_tienda_FK son obligatorios.');
            }
            $insumoKey = $idInsumo . ':' . $idTienda;
            if (isset($insumosIncluidos[$insumoKey])) {
                throw new InvalidArgumentException('No se permite repetir el mismo insumo/tienda en una venta.');
            }
            $insumosIncluidos[$insumoKey] = true;

            $cantIn = isset($linea['cantidad']) ? $linea['cantidad'] : null;
            if ($cantIn === null || trim((string) $cantIn) === '' || !is_numeric($cantIn)) {
                throw new InvalidArgumentException('cantidad obligatoria en linea insumo.');
            }
            $cantFloat = (float) $cantIn;
            if ($cantFloat <= 0) {
                throw new InvalidArgumentException('La cantidad del insumo debe ser mayor que cero.');
            }

            $qtyStrIns = $this->normalizarCantidadLinea($cantFloat);
            $subtotalBrutoIns = (float) $pu * $cantFloat;
            $calcLineaIns = isset($lineasCalc[$index]) && is_array($lineasCalc[$index]) ? $lineasCalc[$index] : null;
            $descuentoLineaIns = $calcLineaIns !== null
                ? $this->normalizarDecimal((float) ($calcLineaIns['descuento_monto'] ?? 0))
                : $this->normalizarDecimal($subtotalBrutoIns * ($this->resolverDescuentoPorcentajeLinea('insumo', $idClienteVenta) / 100));
            $subtotalIns = $calcLineaIns !== null
                ? $this->normalizarDecimal((float) ($calcLineaIns['subtotal_neto'] ?? max(0, $subtotalBrutoIns - (float) $descuentoLineaIns)))
                : $this->normalizarDecimal(max(0, $subtotalBrutoIns - (float) $descuentoLineaIns));
            $precioFinalUnitIns = $this->normalizarDecimal((float) $subtotalIns / max(0.001, $cantFloat));

            $stAc = $db->prepare('SELECT COALESCE(costo_referencia, 0) AS costo_referencia FROM insumos WHERE id_insumo = :id AND activo = 1 LIMIT 1');
            $stAc->bindValue(':id', $idInsumo, PDO::PARAM_INT);
            $stAc->execute();
            $rowInsumoCosto = $stAc->fetch(PDO::FETCH_ASSOC);
            if (!$rowInsumoCosto) {
                throw new InvalidArgumentException('El insumo #' . $idInsumo . ' no existe o esta inactivo.');
            }
            $costoUnitarioIns = $this->normalizarDecimal(max(0.01, (float) ($rowInsumoCosto['costo_referencia'] ?? 0)));

            $stT = $db->prepare(
                'SELECT 1 FROM tiendas WHERE id_tienda = :id AND COALESCE(activo, 1) = 1 LIMIT 1'
            );
            $stT->bindValue(':id', $idTienda, PDO::PARAM_INT);
            $stT->execute();
            if (!$stT->fetchColumn()) {
                throw new InvalidArgumentException('La tienda no existe o esta inactiva.');
            }

            $existenteAntes = $insumoCtrl->obtenerExistenciaNumericaTienda($db, $idInsumo, $idTienda);
            if ($existenteAntes + 1e-9 < $cantFloat) {
                throw new InvalidArgumentException(
                    'Stock insuficiente del insumo (disponible: ' . number_format($existenteAntes, 3, '.', '') . ').'
                );
            }

            $stmtInsDet->bindValue(':id_venta', $idVenta, PDO::PARAM_INT);
            if (isset($cols['tipo_linea'])) {
                $stmtInsDet->bindValue(':tipo', 'insumo', PDO::PARAM_STR);
            }
            if (isset($cols['id_pieza_stock_FK'])) {
                $stmtInsDet->bindValue(':id_ps', null, PDO::PARAM_NULL);
            }
            if (isset($cols['id_insumo_FK'])) {
                $stmtInsDet->bindValue(':id_ins', $idInsumo, PDO::PARAM_INT);
            }
            if (isset($cols['id_tienda_FK'])) {
                $stmtInsDet->bindValue(':id_tnd', $idTienda, PDO::PARAM_INT);
            }
            if (isset($cols['cantidad'])) {
                $stmtInsDet->bindValue(':cantidad', $qtyStrIns, PDO::PARAM_STR);
            }
            if (isset($cols['precio_unitario'])) {
                $stmtInsDet->bindValue(':pu', $pu, PDO::PARAM_STR);
            }
            if (isset($cols['subtotal'])) {
                $stmtInsDet->bindValue(':sub', $subtotalIns, PDO::PARAM_STR);
            }
            if (isset($cols['descuento_aplicado'])) {
                $stmtInsDet->bindValue(':descuento_aplicado', $descuentoLineaIns, PDO::PARAM_STR);
            }
            if (isset($cols['precio_final'])) {
                $stmtInsDet->bindValue(':precio_final', $precioFinalUnitIns, PDO::PARAM_STR);
            }
            if (isset($cols['costo_unitario'])) {
                $stmtInsDet->bindValue(':costo_unitario', $costoUnitarioIns, PDO::PARAM_STR);
            }
            $stmtInsDet->execute();

            $insumoCtrl->decrementarExistenciaTienda($db, $idInsumo, $idTienda, $qtyStrIns);
        }
    }

    private function obtenerColumnasVentaDetalle(PDO $db): array
    {
        if ($this->cacheColumnasVentaDetalle !== null) {
            return $this->cacheColumnasVentaDetalle;
        }

        $stmt = $db->query("SHOW COLUMNS FROM venta_detalle");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $map = [];
        foreach ($rows as $row) {
            $field = isset($row['Field']) ? trim((string) $row['Field']) : '';
            if ($field !== '') {
                $map[$field] = true;
            }
        }
        $this->cacheColumnasVentaDetalle = $map;

        return $map;
    }

    /**
     * @return array<string, bool>
     */
    private function obtenerColumnasInsumos(PDO $db): array
    {
        if ($this->cacheColumnasInsumos !== null) {
            return $this->cacheColumnasInsumos;
        }

        $map = [];
        try {
            $stmt = $db->query('SHOW COLUMNS FROM insumos');
            $rows = $stmt !== false ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
            foreach ($rows as $row) {
                $field = isset($row['Field']) ? trim((string) $row['Field']) : '';
                if ($field !== '') {
                    $map[$field] = true;
                }
            }
        } catch (Throwable $e) {
            error_log('Ventas::obtenerColumnasInsumos ' . $e->getMessage());
        }

        return $this->cacheColumnasInsumos = $map;
    }

    private function obtenerColumnasVentaPagos(PDO $db): array
    {
        if ($this->cacheColumnasVentaPagos !== null) {
            return $this->cacheColumnasVentaPagos;
        }
        $stmt = $db->query("SHOW COLUMNS FROM venta_pagos");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $map = [];
        foreach ($rows as $row) {
            $field = isset($row['Field']) ? trim((string) $row['Field']) : '';
            if ($field !== '') {
                $map[$field] = true;
            }
        }
        $this->cacheColumnasVentaPagos = $map;
        return $map;
    }

    private function procesarPagosSiExisten(PDO $db, int $idVenta, array $data, float $totalVenta): void
    {
        $raw = $data['pagos'] ?? null;
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($raw)) {
            $raw = [];
        }
        if ($raw === []) {
            if (abs($totalVenta) < 0.02) {
                return;
            }
            throw new InvalidArgumentException('Debes registrar al menos un pago cuando el total es mayor a cero.');
        }

        $cols = $this->obtenerColumnasVentaPagos($db);
        if (!isset($cols['id_venta_FK']) || !isset($cols['id_forma_pago_FK']) || !isset($cols['monto'])) {
            throw new InvalidArgumentException('La tabla venta_pagos no tiene las columnas requeridas.');
        }

        require_once __DIR__ . '/apartado_gestion.php';
        $apartadoGestion = new ApartadoGestion();
        try {
            $idFormaCreditoCliente = $apartadoGestion->obtenerIdFormaPagoCreditoCliente($db);
        } catch (Throwable $e) {
            $idFormaCreditoCliente = null;
        }
        $idCliente = isset($data['id_cliente_FK']) && $data['id_cliente_FK'] !== null && $data['id_cliente_FK'] !== ''
            ? (int) $data['id_cliente_FK']
            : 0;
        $idEmpleado = isset($data['id_empleado_FK']) ? (int) $data['id_empleado_FK'] : 0;
        $idUsuarioAudit = isset($data['id_usuario_FK']) && (int) $data['id_usuario_FK'] > 0
            ? (int) $data['id_usuario_FK']
            : 0;
        if ($idUsuarioAudit <= 0 && function_exists('auth_user')) {
            $u = auth_user();
            if (is_array($u)) {
                $idUsuarioAudit = (int) ($u['id_usuario'] ?? 0);
            }
        }

        $stmt = $db->prepare(
            "INSERT INTO venta_pagos (id_venta_FK, id_forma_pago_FK, monto)
             VALUES (:id_venta, :id_forma, :monto)"
        );

        $sumaPagos = 0.0;
        $montoCreditoCliente = 0.0;
        $pagosCreditoCliente = [];
        foreach ($raw as $idx => $pago) {
            if (!is_array($pago)) {
                throw new InvalidArgumentException('Linea de pago invalida en posicion ' . $idx . '.');
            }
            $idForma = isset($pago['id_forma_pago_FK']) ? (int) $pago['id_forma_pago_FK'] : 0;
            $monto = isset($pago['monto']) && is_numeric($pago['monto']) ? (float) $pago['monto'] : 0.0;
            if ($idForma <= 0) {
                throw new InvalidArgumentException('Debe indicar una forma de pago valida.');
            }
            if ($monto <= 0) {
                throw new InvalidArgumentException('El monto del pago debe ser mayor a cero.');
            }
            $this->verificarExiste($db, 'SELECT 1 FROM forma_pago WHERE id_forma_pago = :id AND activo = 1', ':id', $idForma, 'Forma de pago no valida.');
            $montoStr = $this->normalizarDecimal($monto);
            $sumaPagos += (float) $montoStr;
            $stmt->bindValue(':id_venta', $idVenta, PDO::PARAM_INT);
            $stmt->bindValue(':id_forma', $idForma, PDO::PARAM_INT);
            $stmt->bindValue(':monto', $montoStr, PDO::PARAM_STR);
            $stmt->execute();
            $idPagoVenta = (int) $db->lastInsertId();

            if ($idFormaCreditoCliente !== null && $idForma === $idFormaCreditoCliente) {
                $pagosCreditoCliente[] = [
                    'monto' => (float) $montoStr,
                    'id_venta_pago' => $idPagoVenta,
                ];
                $montoCreditoCliente += (float) $montoStr;
            }
        }

        if (abs($sumaPagos - $totalVenta) > 0.01) {
            throw new InvalidArgumentException(
                'La suma de pagos ($' . $this->normalizarDecimal($sumaPagos)
                    . ') no coincide con el total de la venta ($' . $this->normalizarDecimal($totalVenta) . ').'
            );
        }

        if ($pagosCreditoCliente !== []) {
            if ($idCliente <= 0) {
                throw new InvalidArgumentException('Para pagar con credito a favor se requiere un cliente identificado.');
            }
            if ($idUsuarioAudit <= 0) {
                throw new InvalidArgumentException('No se identifico el usuario para registrar consumo de credito.');
            }
            foreach ($pagosCreditoCliente as $pg) {
                $apartadoGestion->aplicarConsumoCreditoCliente(
                    $db,
                    $idCliente,
                    (float) $pg['monto'],
                    'venta_pos',
                    null,
                    $idVenta,
                    null,
                    $idEmpleado > 0 ? $idEmpleado : null,
                    $idUsuarioAudit,
                    'Venta POS #' . $idVenta . ' con credito a favor'
                );
            }
        }
    }

    private function normalizarCantidadLinea(float $cantidad): string
    {
        $epsilon = 1e-6;
        $ajustado = $cantidad + ($cantidad >= 0 ? $epsilon : -$epsilon);

        return number_format(round($ajustado, 3), 3, '.', '');
    }

    public function actualizar($idVenta, $data)
    {
        $idCliente = $this->validarEnteroOpcional($data, 'id_cliente_FK');
        $idEmpleado = $this->validarEntero($data, 'id_empleado_FK', 'El empleado');
        $idImpuesto = $this->validarEntero($data, 'id_impuesto_FK', 'El impuesto');
        $idApartado = $this->validarEnteroOpcional($data, 'id_apartado_FK');
        $total = $this->validarDecimal($data, 'total', 'El total');
        $estado = $this->validarEstado($data['estado'] ?? 'completada');
        $impuestoPorcentaje = $this->validarDecimalRango($data, 'impuesto_porcentaje', 'El porcentaje de impuesto', 0, 100);
        $impuestoMonto = $this->validarDecimalNoNegativo($data, 'impuesto_monto', 'El monto del impuesto');

        $db = $this->getDb();
        $db->beginTransaction();

        try {
            auth_mysql_set_audit_vars($db);

            if ($idCliente !== null) {
                $this->verificarExiste($db, 'SELECT 1 FROM clientes WHERE id_cliente = :id AND activo = 1', ':id', $idCliente, 'El cliente no existe o esta inactivo.');
            }
            $this->verificarExiste($db, 'SELECT 1 FROM empleados WHERE id_empleado = :id AND activo = 1', ':id', $idEmpleado, 'El empleado no existe o esta inactivo.');
            $this->verificarExiste($db, 'SELECT 1 FROM impuestos WHERE id_impuesto = :id', ':id', $idImpuesto, 'El impuesto no existe.');

            if ($idApartado !== null) {
                $this->verificarExiste(
                    $db,
                    "SELECT 1 FROM apartados WHERE id_apartado = :id AND estado = 'activo'",
                    ':id',
                    $idApartado,
                    'El apartado no existe o no esta activo (no se puede liquidar en venta).'
                );
            }

            $stmt = $db->prepare(
                "UPDATE ventas
                 SET id_cliente_FK = :id_cliente_FK,
                     id_empleado_FK = :id_empleado_FK,
                     id_impuesto_FK = :id_impuesto_FK,
                     id_apartado_FK = :id_apartado_FK,
                     total = :total,
                     estado = :estado,
                     impuesto_porcentaje = :impuesto_porcentaje,
                     impuesto_monto = :impuesto_monto
                 WHERE id_venta = :id_venta"
            );

            $stmt->bindValue(':id_cliente_FK', $idCliente, $idCliente === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(':id_empleado_FK', $idEmpleado, PDO::PARAM_INT);
            $stmt->bindValue(':id_impuesto_FK', $idImpuesto, PDO::PARAM_INT);
            $stmt->bindValue(':id_apartado_FK', $idApartado, $idApartado === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(':total', $total, PDO::PARAM_STR);
            $stmt->bindValue(':estado', $estado, PDO::PARAM_STR);
            $stmt->bindValue(':impuesto_porcentaje', $impuestoPorcentaje, PDO::PARAM_STR);
            $stmt->bindValue(':impuesto_monto', $impuestoMonto, PDO::PARAM_STR);
            $stmt->bindValue(':id_venta', (int) $idVenta, PDO::PARAM_INT);
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

    public function borrar($idVenta)
    {
        $db = $this->getDb();
        $db->beginTransaction();
        try {
            auth_mysql_set_audit_vars($db);

            // Revertir estado de piezas de joyeria vendidas en esta venta
            $db->prepare(
                "UPDATE piezas_stock ps
                 INNER JOIN venta_detalle vd ON vd.id_pieza_stock_FK = ps.id_pieza_stock
                 SET ps.estado = 'disponible'
                 WHERE vd.id_venta_FK = :id
                   AND vd.tipo_linea = 'joya'
                   AND ps.estado = 'vendida'"
            )->execute([':id' => (int) $idVenta]);

            // Restaurar existencias de insumos consumidos en esta venta
            $db->prepare(
                "UPDATE insumo_existencia ie
                 INNER JOIN venta_detalle vd
                     ON vd.id_insumo_FK = ie.id_insumo_FK
                     AND vd.id_tienda_FK = ie.id_tienda_FK
                 SET ie.cantidad = ie.cantidad + vd.cantidad
                 WHERE vd.id_venta_FK = :id
                   AND vd.tipo_linea = 'insumo'"
            )->execute([':id' => (int) $idVenta]);

            $stmt = $db->prepare("UPDATE ventas SET estado = 'cancelada' WHERE id_venta = :id_venta");
            $stmt->bindValue(':id_venta', (int) $idVenta, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->rowCount();

            $db->commit();
            return $rows;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
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

    private function validarDecimalRango($data, $campo, $label, $min, $max)
    {
        if (!isset($data[$campo]) || trim((string) $data[$campo]) === '') {
            throw new InvalidArgumentException($label . ' es obligatorio.');
        }

        $valor = (float) $data[$campo];
        if ($valor < $min || $valor > $max) {
            throw new InvalidArgumentException($label . ' debe estar entre ' . $min . ' y ' . $max . '.');
        }

        return $this->normalizarDecimal($valor, 2);
    }

    private function validarDecimalNoNegativo($data, $campo, $label)
    {
        if (!isset($data[$campo]) || trim((string) $data[$campo]) === '') {
            throw new InvalidArgumentException($label . ' es obligatorio.');
        }

        $valor = (float) $data[$campo];
        if ($valor < 0) {
            throw new InvalidArgumentException($label . ' no puede ser negativo.');
        }

        return $this->normalizarDecimal($valor, 2);
    }

    private function validarEstado(string $estado): string
    {
        $estado = mb_strtolower(trim($estado));
        $permitidos = ['completada', 'cancelada', 'devuelta'];

        if (!in_array($estado, $permitidos, true)) {
            throw new InvalidArgumentException('El estado de la venta no es valido.');
        }

        return $estado;
    }

    /**
     * Crea venta + detalle + pagos al liquidar apartado por abonos (sin tocar stock).
     */
    public function crearDesdeApartadoLiquidado(int $idApartado, int $idEmpleado, int $idUsuario): int
    {
        if ($idApartado <= 0) {
            throw new InvalidArgumentException('Apartado invalido.');
        }

        $db = $this->getDb();
        $stEx = $db->prepare('SELECT id_venta FROM ventas WHERE id_apartado_FK = :id LIMIT 1');
        $stEx->bindValue(':id', $idApartado, PDO::PARAM_INT);
        $stEx->execute();
        $idExistente = (int) ($stEx->fetchColumn() ?: 0);
        if ($idExistente > 0) {
            return $idExistente;
        }

        $stA = $db->prepare('SELECT * FROM apartados WHERE id_apartado = :id AND estado = \'liquidado\' LIMIT 1');
        $stA->bindValue(':id', $idApartado, PDO::PARAM_INT);
        $stA->execute();
        $ap = $stA->fetch(PDO::FETCH_ASSOC);
        if (!is_array($ap)) {
            throw new InvalidArgumentException('Apartado liquidado no encontrado.');
        }

        $idImpuesto = $this->obtenerIdImpuestoDefault();
        if ($idImpuesto === null || $idImpuesto <= 0) {
            $idImpuesto = (int) ($db->query('SELECT id_impuesto FROM impuestos ORDER BY id_impuesto ASC LIMIT 1')->fetchColumn() ?: 0);
        }
        if ($idImpuesto <= 0) {
            throw new RuntimeException('No hay impuesto configurado para generar la venta del apartado.');
        }

        $stImp = $db->prepare('SELECT porcentaje FROM impuestos WHERE id_impuesto = :id LIMIT 1');
        $stImp->bindValue(':id', $idImpuesto, PDO::PARAM_INT);
        $stImp->execute();
        $pctImp = (float) ($stImp->fetchColumn() ?: 0);

        $total = (float) ($ap['total_apartado'] ?? 0);
        $impuestoMonto = (float) ($ap['impuesto_monto'] ?? 0);
        if ($pctImp <= 0 && $total > 0 && $impuestoMonto > 0) {
            $base = $total - $impuestoMonto;
            if ($base > 0) {
                $pctImp = round(($impuestoMonto / $base) * 100, 2);
            }
        }

        $idCliente = (int) ($ap['id_cliente_FK'] ?? 0);
        $idEmp = $idEmpleado > 0 ? $idEmpleado : (int) ($ap['id_empleado_FK'] ?? 0);

        $db->beginTransaction();
        try {
            if ($idUsuario > 0) {
                auth_mysql_set_audit_vars($db, $idUsuario);
            }

            $stV = $db->prepare(
                "INSERT INTO ventas
                    (id_cliente_FK, id_empleado_FK, id_impuesto_FK, id_apartado_FK, total, estado,
                     impuesto_porcentaje, impuesto_monto)
                 VALUES
                    (:cli, :emp, :imp, :ap, :tot, 'completada', :pct, :impm)"
            );
            $stV->bindValue(':cli', $idCliente, PDO::PARAM_INT);
            $stV->bindValue(':emp', $idEmp, PDO::PARAM_INT);
            $stV->bindValue(':imp', $idImpuesto, PDO::PARAM_INT);
            $stV->bindValue(':ap', $idApartado, PDO::PARAM_INT);
            $stV->bindValue(':tot', $this->normalizarDecimal($total), PDO::PARAM_STR);
            $stV->bindValue(':pct', $this->normalizarDecimal($pctImp), PDO::PARAM_STR);
            $stV->bindValue(':impm', $this->normalizarDecimal($impuestoMonto), PDO::PARAM_STR);
            $stV->execute();
            $idVenta = (int) $db->lastInsertId();

            $stDet = $db->prepare(
                'SELECT ad.id_pieza_stock_FK, ad.precio_apartado, p.costo, p.id_tienda_FK
                 FROM apartado_detalle ad
                 INNER JOIN piezas_stock ps ON ps.id_pieza_stock = ad.id_pieza_stock_FK
                 INNER JOIN piezas p ON p.id_pieza = ps.id_pieza_FK
                 WHERE ad.id_apartado_FK = :id'
            );
            $stDet->bindValue(':id', $idApartado, PDO::PARAM_INT);
            $stDet->execute();
            $lineas = $stDet->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $cols = $this->obtenerColumnasVentaDetalle($db);
            foreach ($lineas as $ln) {
                $precio = (float) ($ln['precio_apartado'] ?? 0);
                $costo = (float) ($ln['costo'] ?? 0);
                $idPs = (int) ($ln['id_pieza_stock_FK'] ?? 0);
                $idTienda = (int) ($ln['id_tienda_FK'] ?? 0);

                $insCols = ['id_venta_FK', 'id_pieza_stock_FK', 'precio_unitario', 'subtotal', 'costo_unitario'];
                $insVals = [':v', ':ps', ':pu', ':sub', ':co'];
                $bind = [
                    ':v' => $idVenta,
                    ':ps' => $idPs,
                    ':pu' => $this->normalizarDecimal($precio),
                    ':sub' => $this->normalizarDecimal($precio),
                    ':co' => $this->normalizarDecimal($costo),
                ];
                if (isset($cols['tipo_linea'])) {
                    $insCols[] = 'tipo_linea';
                    $insVals[] = ':tipo';
                    $bind[':tipo'] = 'joya';
                }
                if (isset($cols['cantidad'])) {
                    $insCols[] = 'cantidad';
                    $insVals[] = ':cant';
                    $bind[':cant'] = '1.000';
                }
                if (isset($cols['precio_final'])) {
                    $insCols[] = 'precio_final';
                    $insVals[] = ':pf';
                    $bind[':pf'] = $this->normalizarDecimal($precio);
                }
                if (isset($cols['id_tienda_FK']) && $idTienda > 0) {
                    $insCols[] = 'id_tienda_FK';
                    $insVals[] = ':tnd';
                    $bind[':tnd'] = $idTienda;
                }

                $stIns = $db->prepare(
                    'INSERT INTO venta_detalle (' . implode(', ', $insCols) . ') VALUES (' . implode(', ', $insVals) . ')'
                );
                foreach ($bind as $k => $v) {
                    $stIns->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
                }
                $stIns->execute();
            }

            $stPag = $db->prepare(
                "SELECT id_forma_pago_FK, SUM(monto) AS monto
                 FROM apartado_pagos
                 WHERE id_apartado_FK = :id AND estado = 'registrado'
                 GROUP BY id_forma_pago_FK"
            );
            $stPag->bindValue(':id', $idApartado, PDO::PARAM_INT);
            $stPag->execute();
            $pagos = $stPag->fetchAll(PDO::FETCH_ASSOC) ?: [];

            if ($pagos !== [] && $this->obtenerColumnasVentaPagos($db) !== []) {
                $stVp = $db->prepare(
                    'INSERT INTO venta_pagos (id_venta_FK, id_forma_pago_FK, monto) VALUES (:v, :fp, :m)'
                );
                foreach ($pagos as $pg) {
                    $stVp->bindValue(':v', $idVenta, PDO::PARAM_INT);
                    $stVp->bindValue(':fp', (int) ($pg['id_forma_pago_FK'] ?? 0), PDO::PARAM_INT);
                    $stVp->bindValue(':m', $this->normalizarDecimal((float) ($pg['monto'] ?? 0)), PDO::PARAM_STR);
                    $stVp->execute();
                }
            }

            $db->commit();
            return $idVenta;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }
}
