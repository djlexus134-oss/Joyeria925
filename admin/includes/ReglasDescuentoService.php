<?php
declare(strict_types=1);

require_once __DIR__ . '/../../sistema.class.php';
require_once __DIR__ . '/DescuentoTiendaService.php';
require_once __DIR__ . '/PromocionTiendaResolver.php';
require_once __DIR__ . '/../models/configuracion_general.php';
require_once __DIR__ . '/configuracion_plantilla_defaults.php';
require_once __DIR__ . '/../models/ventas.php';

/**
 * Reglas de descuento por metal, mayoreo selectivo, umbral de piezas e insumos 5+1.
 */
class ReglasDescuentoService extends Sistema
{
    /** @var array<int, array<string, mixed>>|null */
    private static ?array $cacheMetales = null;

    /** @var array<string, bool>|null */
    private static ?array $cacheColumnasMetales = null;

    /**
     * @return array<string, bool>
     */
    private function columnasMetalesTabla(PDO $db): array
    {
        if (self::$cacheColumnasMetales !== null) {
            return self::$cacheColumnasMetales;
        }

        $out = [];
        try {
            $stmt = $db->query('SHOW COLUMNS FROM metales');
            $cols = $stmt !== false ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
            if (is_array($cols)) {
                foreach ($cols as $col) {
                    $out[(string) $col] = true;
                }
            }
        } catch (Throwable $e) {
            error_log('ReglasDescuentoService::columnasMetalesTabla ' . $e->getMessage());
        }

        return self::$cacheColumnasMetales = $out;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizarFilaMetal(array $row): array
    {
        return array_merge($row, [
            'descuento_mostrador_pct' => isset($row['descuento_mostrador_pct'])
                ? (float) $row['descuento_mostrador_pct']
                : 0.0,
            'aplica_mayoreo' => !empty($row['aplica_mayoreo']) ? (int) $row['aplica_mayoreo'] : 0,
            'umbral_piezas_descuento' => isset($row['umbral_piezas_descuento']) && $row['umbral_piezas_descuento'] !== null
                ? (int) $row['umbral_piezas_descuento']
                : null,
            'descuento_umbral_pct' => isset($row['descuento_umbral_pct']) && $row['descuento_umbral_pct'] !== null
                ? (float) $row['descuento_umbral_pct']
                : null,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function cargarMetalesActivos(): array
    {
        if (self::$cacheMetales !== null) {
            return self::$cacheMetales;
        }

        $db = $this->getDb();
        $cols = $this->columnasMetalesTabla($db);
        $extra = [];
        foreach (['descuento_mostrador_pct', 'aplica_mayoreo', 'umbral_piezas_descuento', 'descuento_umbral_pct'] as $col) {
            if (!empty($cols[$col])) {
                $extra[] = $col;
            }
        }
        $selectExtra = $extra !== [] ? ', ' . implode(', ', $extra) : '';

        $stmt = $db->query(
            "SELECT id_metal, nom_metal{$selectExtra}
             FROM metales
             WHERE activo = 1"
        );
        $rows = $stmt !== false ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        $map = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = (int) ($row['id_metal'] ?? 0);
            if ($id > 0) {
                $map[$id] = $this->normalizarFilaMetal($row);
            }
        }

        return self::$cacheMetales = $map;
    }

    public static function limpiarCacheMetales(): void
    {
        self::$cacheMetales = null;
        self::$cacheColumnasMetales = null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function obtenerMetalPorId(int $idMetal): ?array
    {
        if ($idMetal <= 0) {
            return null;
        }
        $map = $this->cargarMetalesActivos();

        return $map[$idMetal] ?? null;
    }

    public function obtenerDescuentoGeneralFallback(): float
    {
        $config = new ConfiguracionGeneral();
        $map = $config->leerPorClaves(['descuento_general_mostrador']);
        $defaults = configuracion_plantilla_defaults();
        $value = isset($map['descuento_general_mostrador']) && $map['descuento_general_mostrador'] !== null && $map['descuento_general_mostrador'] !== ''
            ? (float) $map['descuento_general_mostrador']
            : (float) ($defaults['descuento_general_mostrador'] ?? 0.0);

        return $this->acotarPorcentaje($value);
    }

    public function obtenerDescuentoInsumosMostrador(): float
    {
        $config = new ConfiguracionGeneral();
        $map = $config->leerPorClaves(['descuento_insumos_mostrador']);
        $value = isset($map['descuento_insumos_mostrador']) ? (float) $map['descuento_insumos_mostrador'] : 0.0;

        return $this->acotarPorcentaje($value);
    }

    /**
     * El descuento de ficha del cliente solo aplica en metales con descuento de mostrador (ej. plata).
     */
    public function descuentoClienteAplicaEnMetal(?array $metal): bool
    {
        if ($metal === null) {
            return false;
        }
        $pctMetal = (float) ($metal['descuento_mostrador_pct'] ?? 0);
        $mayoreo = !empty($metal['aplica_mayoreo']) && (int) $metal['aplica_mayoreo'] === 1;

        return $pctMetal > 0.0001 || $mayoreo;
    }

    /**
     * % efectivo de descuento para una joya segun metal, cliente y conteo del documento.
     */
    public function resolverPorcentajeJoyaLinea(
        ?int $idCliente,
        int $idMetal,
        array $conteoPorMetal,
        float $subtotalPlataLista,
        bool $mayoreoActivo,
        float $pctMayoreo
    ): float {
        $pctCliente = $idCliente !== null && $idCliente > 0
            ? ((new Ventas())->obtenerDescuentoClientePorcentaje($idCliente) ?? 0.0)
            : 0.0;

        $metal = $this->obtenerMetalPorId($idMetal);
        $pctMetal = 0.0;
        if ($metal !== null) {
            $pctMetal = (float) ($metal['descuento_mostrador_pct'] ?? 0);
        } elseif ($idMetal <= 0) {
            $pctMetal = $this->obtenerDescuentoGeneralFallback();
        }

        $pctClienteLinea = $this->descuentoClienteAplicaEnMetal($metal) ? $pctCliente : 0.0;
        $pctLinea = max($pctClienteLinea, $pctMetal);

        if ($metal !== null && !empty($metal['aplica_mayoreo']) && (int) $metal['aplica_mayoreo'] === 1 && $mayoreoActivo) {
            $pctLinea = max($pctLinea, $pctMayoreo);
        }

        $umbral = isset($metal['umbral_piezas_descuento']) && $metal['umbral_piezas_descuento'] !== null
            ? (int) $metal['umbral_piezas_descuento']
            : 0;
        $pctUmbral = isset($metal['descuento_umbral_pct']) && $metal['descuento_umbral_pct'] !== null
            ? (float) $metal['descuento_umbral_pct']
            : 0.0;
        $conteoMetal = $idMetal > 0 ? (int) ($conteoPorMetal[$idMetal] ?? 0) : 0;
        if ($umbral > 0 && $conteoMetal >= $umbral && $pctUmbral > 0) {
            $pctLinea = max($pctLinea, $this->acotarPorcentaje($pctUmbral));
        }

        return $this->acotarPorcentaje($pctLinea);
    }

    /**
     * Descuentos por linea de joya para apartados u otros documentos sin insumos.
     *
     * @param list<array{precio_venta: float, id_metal_FK: int}> $piezasJoyas
     * @return list<array{descuento_porcentaje: float, precio_final: float, descuento_monto: float}>
     */
    public function calcularLineasJoyasDocumento(array $piezasJoyas, ?int $idCliente): array
    {
        $detalles = [];
        foreach ($piezasJoyas as $pieza) {
            if (!is_array($pieza)) {
                continue;
            }
            $pu = (float) ($pieza['precio_venta'] ?? 0);
            if ($pu <= 0) {
                continue;
            }
            $detalles[] = [
                'tipo_linea' => 'joya',
                'precio_unitario' => $pu,
                'cantidad' => 1,
                'id_metal_FK' => (int) ($pieza['id_metal_FK'] ?? 0),
            ];
        }

        $calc = $this->calcularTotalesPos($detalles, $idCliente);
        $lineasCalc = $calc['lineas_calculadas'] ?? [];
        $out = [];
        foreach ($piezasJoyas as $idx => $pieza) {
            if (!is_array($pieza)) {
                continue;
            }
            $pu = (float) ($pieza['precio_venta'] ?? 0);
            if ($pu <= 0) {
                continue;
            }
            $calcLinea = isset($lineasCalc[$idx]) && is_array($lineasCalc[$idx]) ? $lineasCalc[$idx] : null;
            $pct = $calcLinea !== null ? (float) ($calcLinea['descuento_porcentaje'] ?? 0) : 0.0;
            $descMonto = $calcLinea !== null
                ? (float) ($calcLinea['descuento_monto'] ?? 0)
                : 0.0;
            $precioFinal = $calcLinea !== null
                ? (float) ($calcLinea['subtotal_neto'] ?? max(0, $pu - $descMonto))
                : $pu;
            $out[] = [
                'descuento_porcentaje' => $pct,
                'precio_final' => round($precioFinal, 2),
                'descuento_monto' => round($descMonto, 2),
            ];
        }

        return $out;
    }

    /**
     * @param array<int, array<string, mixed>> $detalles
     * @return array{
     *   subtotal: float,
     *   subtotal_piezas: float,
     *   subtotal_insumos: float,
     *   descuento_monto: float,
     *   descuento_porcentaje: float,
     *   descuento_porcentaje_piezas: float,
     *   descuento_porcentaje_insumos: float,
     *   conteo_piezas: int,
     *   ticket_mixto: bool,
     *   lineas_calculadas: array<int, array<string, mixed>>,
     *   descuento_progreso: array<int, array<string, mixed>>,
     *   insumo_promo_progreso: array<int, array<string, mixed>>,
     *   descuento_detalle: array<int, string>
     * }
     */
    public function calcularTotalesPos(array $detalles, ?int $idCliente): array
    {
        $svcMayoreo = new DescuentoTiendaService();
        $cfgMayoreo = $svcMayoreo->obtenerConfigMayoreo();
        $pctInsumosGlobal = $this->obtenerDescuentoInsumosMostrador();

        $subtotalBruto = 0.0;
        $subtotalPiezasBruto = 0.0;
        $subtotalInsumosBruto = 0.0;
        $subtotalPlataLista = 0.0;
        $conteoPiezas = 0;
        $conteoPorMetal = [];
        $joyasMeta = [];

        foreach ($detalles as $idx => $linea) {
            if (!is_array($linea)) {
                continue;
            }
            $tipo = $this->resolverTipoLinea($linea);
            $cant = isset($linea['cantidad']) ? (float) $linea['cantidad'] : 1.0;
            if ($cant <= 0) {
                $cant = 1.0;
            }
            $pu = isset($linea['precio_unitario']) ? (float) $linea['precio_unitario'] : 0.0;
            if ($pu <= 0) {
                continue;
            }
            $importe = $pu * $cant;
            $subtotalBruto += $importe;

            if ($tipo === 'insumo') {
                $subtotalInsumosBruto += $importe;
                continue;
            }

            $subtotalPiezasBruto += $importe;
            $conteoPiezas += (int) round($cant);
            $idMetal = (int) ($linea['id_metal_FK'] ?? 0);
            if ($idMetal > 0) {
                $conteoPorMetal[$idMetal] = ($conteoPorMetal[$idMetal] ?? 0) + (int) round($cant);
            }
            $metal = $this->obtenerMetalPorId($idMetal);
            if ($metal !== null && !empty($metal['aplica_mayoreo']) && (int) $metal['aplica_mayoreo'] === 1) {
                $subtotalPlataLista += $importe;
            }
            $joyasMeta[] = [
                'index' => $idx,
                'linea' => $linea,
                'importe' => $importe,
                'id_metal' => $idMetal,
                'metal' => $metal,
            ];
        }

        $mayoreoActivo = $cfgMayoreo['umbral'] > 0
            && $subtotalPlataLista + 0.0001 >= $cfgMayoreo['umbral'];
        $pctMayoreo = $mayoreoActivo ? $this->acotarPorcentaje($cfgMayoreo['porcentaje']) : 0.0;

        $descuentoMonto = 0.0;
        $descuentoPiezasMonto = 0.0;
        $descuentoInsumosMonto = 0.0;
        $lineasCalculadas = [];
        $descuentoProgreso = [];
        $insumoPromoProgreso = [];
        $descuentoDetalle = [];
        $progresoMetalAgregado = [];

        foreach ($joyasMeta as $meta) {
            $linea = $meta['linea'];
            $importe = (float) $meta['importe'];
            $metal = $meta['metal'];
            $idMetal = (int) $meta['id_metal'];

            $pctLinea = $this->resolverPorcentajeJoyaLinea(
                $idCliente,
                $idMetal,
                $conteoPorMetal,
                $subtotalPlataLista,
                $mayoreoActivo,
                $pctMayoreo
            );

            $umbral = isset($metal['umbral_piezas_descuento']) && $metal['umbral_piezas_descuento'] !== null
                ? (int) $metal['umbral_piezas_descuento']
                : 0;
            $pctUmbral = isset($metal['descuento_umbral_pct']) && $metal['descuento_umbral_pct'] !== null
                ? (float) $metal['descuento_umbral_pct']
                : 0.0;
            $conteoMetal = $idMetal > 0 ? (int) ($conteoPorMetal[$idMetal] ?? 0) : 0;
            $umbralActivo = $umbral > 0 && $conteoMetal >= $umbral && $pctUmbral > 0;

            if ($umbral > 0 && $idMetal > 0 && !isset($progresoMetalAgregado[$idMetal])) {
                $faltan = max(0, $umbral - $conteoMetal);
                $descuentoProgreso[] = [
                    'id_metal' => $idMetal,
                    'nom_metal' => (string) ($metal['nom_metal'] ?? 'Metal'),
                    'conteo' => $conteoMetal,
                    'umbral' => $umbral,
                    'faltan' => $faltan,
                    'descuento_activo' => $umbralActivo,
                    'descuento_umbral_pct' => $pctUmbral,
                ];
                $progresoMetalAgregado[$idMetal] = true;
            }

            $pctLinea = $this->acotarPorcentaje($pctLinea);
            $descLinea = round($importe * ($pctLinea / 100), 2);
            $descuentoPiezasMonto += $descLinea;
            $descuentoMonto += $descLinea;

            $lineasCalculadas[(int) $meta['index']] = [
                'tipo_linea' => 'joya',
                'descuento_porcentaje' => $pctLinea,
                'descuento_monto' => $descLinea,
                'subtotal_neto' => round(max(0, $importe - $descLinea), 2),
            ];
        }

        foreach ($detalles as $idx => $linea) {
            if (!is_array($linea) || $this->resolverTipoLinea($linea) !== 'insumo') {
                continue;
            }
            $cant = isset($linea['cantidad']) ? (float) $linea['cantidad'] : 1.0;
            if ($cant <= 0) {
                $cant = 1.0;
            }
            $pu = isset($linea['precio_unitario']) ? (float) $linea['precio_unitario'] : 0.0;
            if ($pu <= 0) {
                continue;
            }
            $importe = $pu * $cant;

            $paga = isset($linea['promo_paga_unidades']) && $linea['promo_paga_unidades'] !== null && $linea['promo_paga_unidades'] !== ''
                ? (int) $linea['promo_paga_unidades']
                : 0;
            $lleva = isset($linea['promo_lleva_unidades']) && $linea['promo_lleva_unidades'] !== null && $linea['promo_lleva_unidades'] !== ''
                ? (int) $linea['promo_lleva_unidades']
                : 0;

            $descLinea = 0.0;
            $subtotalNeto = $importe;
            $pctLinea = 0.0;

            if ($paga > 0 && $lleva > 0) {
                $promo = $this->calcularPromoCantidadInsumo($cant, $pu, $paga, $lleva);
                $descLinea = $promo['descuento_monto'];
                $subtotalNeto = $promo['subtotal_neto'];
                $unidadesGratis = $promo['unidades_gratis'];
                $faltan = $this->faltanParaGratisInsumo($cant, $paga, $lleva);
                $insumoPromoProgreso[] = [
                    'index' => $idx,
                    'cantidad' => $cant,
                    'lleva' => $lleva,
                    'paga' => $paga,
                    'unidades_gratis_actuales' => $unidadesGratis,
                    'faltan_para_gratis' => $unidadesGratis > 0 && $faltan === 0 ? 0 : max(1, $faltan),
                ];
                if ($descLinea > 0) {
                    $descuentoDetalle[] = 'Insumo 5+1: $' . number_format($descLinea, 2, '.', '');
                }
            } else {
                $pctLinea = $pctInsumosGlobal;
                $descLinea = round($importe * ($pctLinea / 100), 2);
                $subtotalNeto = round(max(0, $importe - $descLinea), 2);
            }

            $descuentoInsumosMonto += $descLinea;
            $descuentoMonto += $descLinea;

            $lineasCalculadas[$idx] = [
                'tipo_linea' => 'insumo',
                'descuento_porcentaje' => $pctLinea,
                'descuento_monto' => $descLinea,
                'subtotal_neto' => $subtotalNeto,
            ];
        }

        $descuentoRatePiezas = $subtotalPiezasBruto > 0.00001
            ? ($descuentoPiezasMonto / $subtotalPiezasBruto) * 100
            : 0.0;
        $descuentoRateInsumos = $subtotalInsumosBruto > 0.00001
            ? ($descuentoInsumosMonto / $subtotalInsumosBruto) * 100
            : 0.0;
        $descuentoRateEfectivo = $subtotalBruto > 0.00001
            ? ($descuentoMonto / $subtotalBruto) * 100
            : 0.0;

        return [
            'subtotal' => round($subtotalBruto, 2),
            'subtotal_piezas' => round($subtotalPiezasBruto, 2),
            'subtotal_insumos' => round($subtotalInsumosBruto, 2),
            'descuento_monto' => round($descuentoMonto, 2),
            'descuento_porcentaje' => round($descuentoRateEfectivo, 2),
            'descuento_porcentaje_piezas' => round($descuentoRatePiezas, 2),
            'descuento_porcentaje_insumos' => round($descuentoRateInsumos, 2),
            'conteo_piezas' => $conteoPiezas,
            'ticket_mixto' => $subtotalPiezasBruto > 0.00001 && $subtotalInsumosBruto > 0.00001,
            'lineas_calculadas' => $lineasCalculadas,
            'descuento_progreso' => $descuentoProgreso,
            'insumo_promo_progreso' => $insumoPromoProgreso,
            'descuento_detalle' => $descuentoDetalle,
        ];
    }

    /**
     * @return array{unidades_gratis: int, unidades_pagadas: float, subtotal_neto: float, descuento_monto: float}
     */
    public function calcularPromoCantidadInsumo(float $cantidad, float $precioUnitario, int $paga, int $lleva): array
    {
        if ($cantidad <= 0 || $precioUnitario <= 0 || $paga <= 0 || $lleva <= 0) {
            $bruto = max(0, $cantidad) * max(0, $precioUnitario);

            return [
                'unidades_gratis' => 0,
                'unidades_pagadas' => $cantidad,
                'subtotal_neto' => round($bruto, 2),
                'descuento_monto' => 0.0,
            ];
        }

        if ($lleva > $paga) {
            // Paquete: lleva N paga M (ej. lleva 6 paga 5 = la sexta gratis).
            $sets = (int) floor($cantidad / $lleva);
            $gratis = $sets * ($lleva - $paga);
        } else {
            // Por umbral: cada N unidades pagadas, M gratis (ej. cada 5 cajas, 1 gratis → paga 5, lleva 1).
            $sets = (int) floor($cantidad / $paga);
            $gratis = $sets * $lleva;
        }

        $gratis = min($gratis, $cantidad);
        $pagadas = $cantidad - $gratis;
        $subtotal = $pagadas * $precioUnitario;
        $descuento = $gratis * $precioUnitario;

        return [
            'unidades_gratis' => (int) round($gratis),
            'unidades_pagadas' => $pagadas,
            'subtotal_neto' => round(max(0, $subtotal), 2),
            'descuento_monto' => round(max(0, $descuento), 2),
        ];
    }

    /**
     * Unidades faltantes en el ticket para la siguiente unidad gratis de la promo.
     */
    public function faltanParaGratisInsumo(float $cantidad, int $paga, int $lleva): int
    {
        if ($cantidad <= 0 || $paga <= 0 || $lleva <= 0) {
            return $lleva > $paga ? $lleva : $paga;
        }

        if ($lleva > $paga) {
            $resto = (int) fmod($cantidad, $lleva);
            if ($resto === 0.0 || $resto === 0) {
                return 0;
            }

            return (int) ($lleva - $resto);
        }

        $resto = (int) fmod($cantidad, $paga);
        if ($resto === 0.0 || $resto === 0) {
            return 0;
        }

        return (int) ($paga - $resto);
    }

    /**
     * Subtotal joyas a precio lista solo de metales con mayoreo habilitado.
     *
     * @param array<int, array<string, mixed>> $detalles
     */
    public function calcularSubtotalPlataListaPos(array $detalles): float
    {
        $sum = 0.0;
        foreach ($detalles as $linea) {
            if (!is_array($linea)) {
                continue;
            }
            if ($this->resolverTipoLinea($linea) === 'insumo') {
                continue;
            }
            $idMetal = (int) ($linea['id_metal_FK'] ?? 0);
            $metal = $this->obtenerMetalPorId($idMetal);
            if ($metal === null || empty($metal['aplica_mayoreo']) || (int) $metal['aplica_mayoreo'] !== 1) {
                continue;
            }
            $cant = isset($linea['cantidad']) ? (float) $linea['cantidad'] : 1.0;
            $pu = isset($linea['precio_unitario']) ? (float) $linea['precio_unitario'] : 0.0;
            if ($pu > 0 && $cant > 0) {
                $sum += $pu * $cant;
            }
        }

        return round($sum, 2);
    }

    /**
     * Subtotal plata (metales con mayoreo) desde venta_detalle pagada.
     */
    public function calcularSubtotalPlataListaVentaDetalle(PDO $db, int $idVenta): float
    {
        if ($idVenta <= 0) {
            return 0.0;
        }

        $cols = $this->columnasVentaDetalle($db);
        $filtroInsumo = isset($cols['tipo_linea'])
            ? " AND (vd.tipo_linea IS NULL OR LOWER(TRIM(vd.tipo_linea)) <> 'insumo')"
            : " AND (vd.id_insumo_FK IS NULL OR vd.id_insumo_FK = 0)";

        $colsMetal = $this->columnasMetalesTabla($db);
        if (empty($colsMetal['aplica_mayoreo'])) {
            return 0.0;
        }

        $stmt = $db->prepare(
            "SELECT COALESCE(vd.precio_unitario, 0) AS pu,
                    COALESCE(vd.cantidad, 1) AS cant
             FROM venta_detalle vd
             INNER JOIN piezas_stock ps ON ps.id_pieza_stock = vd.id_pieza_stock_FK
             INNER JOIN piezas p ON p.id_pieza = ps.id_pieza_FK
             INNER JOIN metales m ON m.id_metal = p.id_metal_FK AND m.aplica_mayoreo = 1
             WHERE vd.id_venta_FK = :id{$filtroInsumo}"
        );
        $stmt->bindValue(':id', $idVenta, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $sum = 0.0;
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $pu = (float) ($row['pu'] ?? 0);
            $cant = (float) ($row['cant'] ?? 1);
            if ($pu > 0 && $cant > 0) {
                $sum += $pu * $cant;
            }
        }

        return round($sum, 2);
    }

    /**
     * @return array<string, bool>
     */
    private function columnasVentaDetalle(PDO $db): array
    {
        static $cache = [];
        $key = spl_object_hash($db);
        if (isset($cache[$key])) {
            return $cache[$key];
        }

        $out = [];
        try {
            $stmt = $db->query('SHOW COLUMNS FROM venta_detalle');
            $cols = $stmt !== false ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
            if (is_array($cols)) {
                foreach ($cols as $col) {
                    $out[(string) $col] = true;
                }
            }
        } catch (Throwable $e) {
            error_log('ReglasDescuentoService::columnasVentaDetalle ' . $e->getMessage());
        }

        return $cache[$key] = $out;
    }

    /**
     *
     * @return array{porcentaje: float, origen: string, promocion: ?array}
     */
    public function resolverPorcentajeEfectivoPiezaOnline(
        int $idCliente,
        int $idPieza,
        int $idSubfamilia,
        int $idFamilia,
        int $idMetal,
        float $subtotalPlataListaTransaccion,
        int $conteoPiezasMetal
    ): array {
        $svc = new DescuentoTiendaService();
        $pctCliente = $svc->obtenerDescuentoCliente($idCliente) ?? 0.0;
        $cfgMayoreo = $svc->obtenerConfigMayoreo();

        $metal = $this->obtenerMetalPorId($idMetal);
        $pctMetal = $metal !== null
            ? (float) ($metal['descuento_mostrador_pct'] ?? 0)
            : $this->obtenerDescuentoGeneralFallback();

        $mayoreoActivo = $metal !== null
            && !empty($metal['aplica_mayoreo'])
            && (int) $metal['aplica_mayoreo'] === 1
            && $cfgMayoreo['umbral'] > 0
            && $subtotalPlataListaTransaccion + 0.0001 >= $cfgMayoreo['umbral'];
        $pctMayoreo = $mayoreoActivo ? $this->acotarPorcentaje($cfgMayoreo['porcentaje']) : 0.0;
        $conteoMap = $idMetal > 0 ? [$idMetal => max(1, $conteoPiezasMetal)] : [];
        $pct = $this->resolverPorcentajeJoyaLinea(
            $idCliente,
            $idMetal,
            $conteoMap,
            $subtotalPlataListaTransaccion,
            $mayoreoActivo,
            $pctMayoreo
        );

        $umbral = $metal !== null && isset($metal['umbral_piezas_descuento']) && $metal['umbral_piezas_descuento'] !== null
            ? (int) $metal['umbral_piezas_descuento']
            : 0;
        $pctUmbral = $metal !== null && isset($metal['descuento_umbral_pct']) && $metal['descuento_umbral_pct'] !== null
            ? (float) $metal['descuento_umbral_pct']
            : 0.0;

        $promo = (new PromocionTiendaResolver())->resolverParaPieza($idPieza, $idSubfamilia, $idFamilia, $idMetal);
        $pctPromo = $promo !== null ? (float) ($promo['porcentaje_descuento'] ?? 0) : 0.0;
        $pct = max($pct, $pctPromo);
        $pct = $this->acotarPorcentaje($pct);

        $pctClienteAplica = $this->descuentoClienteAplicaEnMetal($metal) ? $pctCliente : 0.0;
        $origen = 'ninguno';
        if ($pct > 0) {
            if ($pctPromo >= $pct - 0.0001 && $pctPromo >= $pctClienteAplica && $pctPromo >= $pctMetal) {
                $origen = 'promocion';
            } elseif ($mayoreoActivo && $pctMayoreo >= $pct - 0.0001) {
                $origen = 'mayoreo';
            } elseif ($pctUmbral > 0 && $umbral > 0 && $conteoPiezasMetal >= $umbral && $pctUmbral >= $pct - 0.0001) {
                $origen = 'umbral_piezas';
            } elseif ($pctClienteAplica >= $pct - 0.0001 && $pctClienteAplica >= $pctMetal) {
                $origen = 'cliente';
            } else {
                $origen = 'metal';
            }
        }

        return [
            'porcentaje' => $pct,
            'origen' => $origen,
            'promocion' => ($origen === 'promocion' && is_array($promo)) ? $promo : null,
        ];
    }

    /**
     * @param array<string, mixed> $linea
     */
    private function resolverTipoLinea(array $linea): string
    {
        $tipo = isset($linea['tipo_linea']) ? mb_strtolower(trim((string) $linea['tipo_linea'])) : '';
        if ($tipo === '') {
            return isset($linea['id_insumo_FK']) && (int) $linea['id_insumo_FK'] > 0 ? 'insumo' : 'joya';
        }

        return $tipo;
    }

    private function acotarPorcentaje(float $value): float
    {
        if ($value < 0) {
            return 0.0;
        }
        if ($value > 100) {
            return 100.0;
        }

        return $value;
    }
}
