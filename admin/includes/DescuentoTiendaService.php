<?php
declare(strict_types=1);

require_once __DIR__ . '/../../sistema.class.php';
require_once __DIR__ . '/PromocionTiendaResolver.php';
require_once __DIR__ . '/ReglasDescuentoService.php';
require_once __DIR__ . '/../models/configuracion_general.php';
require_once __DIR__ . '/configuracion_plantilla_defaults.php';
require_once __DIR__ . '/../models/ventas.php';

/**
 * Descuento de cliente, promoción vigente y mayoreo por umbral (joyas).
 */
class DescuentoTiendaService extends Sistema
{
    /** @var array{umbral: float, porcentaje: float}|null */
    private static ?array $cacheMayoreo = null;

    /**
     * @return array{umbral: float, porcentaje: float}
     */
    public function obtenerConfigMayoreo(): array
    {
        if (self::$cacheMayoreo !== null) {
            return self::$cacheMayoreo;
        }

        $config = new ConfiguracionGeneral();
        $map = $config->leerPorClaves(['mayoreo_umbral_mxn', 'mayoreo_descuento_pct']);
        $defaults = configuracion_plantilla_defaults();

        $umbral = isset($map['mayoreo_umbral_mxn']) && $map['mayoreo_umbral_mxn'] !== null && $map['mayoreo_umbral_mxn'] !== ''
            ? (float) $map['mayoreo_umbral_mxn']
            : (float) ($defaults['mayoreo_umbral_mxn'] ?? 6000.0);
        $pct = isset($map['mayoreo_descuento_pct']) && $map['mayoreo_descuento_pct'] !== null && $map['mayoreo_descuento_pct'] !== ''
            ? (float) $map['mayoreo_descuento_pct']
            : (float) ($defaults['mayoreo_descuento_pct'] ?? 50.0);

        if ($umbral < 0) {
            $umbral = 0.0;
        }
        $pct = $this->acotarPorcentaje($pct);

        return self::$cacheMayoreo = [
            'umbral' => $umbral,
            'porcentaje' => $pct,
        ];
    }

    public static function limpiarCacheConfig(): void
    {
        self::$cacheMayoreo = null;
    }

    public function obtenerDescuentoCliente(int $idCliente): ?float
    {
        if ($idCliente <= 0) {
            return null;
        }

        return (new Ventas())->obtenerDescuentoClientePorcentaje($idCliente);
    }

    /**
     * Subtotal joyas a precio lista desde ítems de carrito (filas de listar).
     *
     * @param array<int, array<string, mixed>> $itemsCarrito
     */
    public function calcularSubtotalJoyasListaCarrito(array $itemsCarrito): float
    {
        $sum = 0.0;
        foreach ($itemsCarrito as $it) {
            if (!is_array($it)) {
                continue;
            }
            $lista = isset($it['precio_lista_snapshot']) && $it['precio_lista_snapshot'] !== null && $it['precio_lista_snapshot'] !== ''
                ? (float) $it['precio_lista_snapshot']
                : (float) ($it['precio_unitario_snapshot'] ?? 0);
            if ($lista > 0) {
                $sum += $lista;
            }
        }

        return round($sum, 2);
    }

    /**
     * Subtotal joyas a precio lista desde líneas de ticket POS (precio_unitario × cantidad).
     *
     * @param array<int, array<string, mixed>> $detalles
     */
    public function calcularSubtotalJoyasListaPos(array $detalles): float
    {
        $sum = 0.0;
        foreach ($detalles as $linea) {
            if (!is_array($linea)) {
                continue;
            }
            $tipo = isset($linea['tipo_linea']) ? mb_strtolower(trim((string) $linea['tipo_linea'])) : '';
            if ($tipo === '') {
                $tipo = isset($linea['id_insumo_FK']) && (int) $linea['id_insumo_FK'] > 0 ? 'insumo' : 'joya';
            }
            if ($tipo === 'insumo') {
                continue;
            }
            $cant = isset($linea['cantidad']) ? (float) $linea['cantidad'] : 1.0;
            if ($cant <= 0) {
                $cant = 1.0;
            }
            $pu = isset($linea['precio_unitario']) ? (float) $linea['precio_unitario'] : 0.0;
            if ($pu > 0) {
                $sum += $pu * $cant;
            }
        }

        return round($sum, 2);
    }

    /**
     * Subtotal joyas a precio lista desde venta_detalle (precio_unitario × cantidad).
     */
    public function calcularSubtotalJoyasListaVentaDetalle(PDO $db, int $idVenta): float
    {
        if ($idVenta <= 0) {
            return 0.0;
        }

        $cols = $this->columnasVentaDetalle($db);
        $filtroInsumo = isset($cols['tipo_linea'])
            ? " AND (tipo_linea IS NULL OR LOWER(TRIM(tipo_linea)) <> 'insumo')"
            : " AND (id_insumo_FK IS NULL OR id_insumo_FK = 0)";

        $stmt = $db->prepare(
            "SELECT COALESCE(precio_unitario, 0) AS pu,
                    COALESCE(cantidad, 1) AS cant
             FROM venta_detalle
             WHERE id_venta_FK = :id{$filtroInsumo}"
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
            if ($cant <= 0) {
                $cant = 1.0;
            }
            if ($pu > 0) {
                $sum += $pu * $cant;
            }
        }

        return round($sum, 2);
    }

    /**
     * % mayoreo si el subtotal joyas lista de la transacción alcanza el umbral.
     */
    public function porcentajeMayoreoTransaccion(float $subtotalJoyasLista): float
    {
        $cfg = $this->obtenerConfigMayoreo();
        if ($cfg['umbral'] <= 0 || $subtotalJoyasLista + 0.0001 < $cfg['umbral']) {
            return 0.0;
        }

        return $cfg['porcentaje'];
    }

    /**
     * Mejor % para una joya: cliente, promoción por pieza, mayoreo del ticket.
     *
     * @return array{porcentaje: float, origen: string, promocion: ?array}
     */
    public function resolverPorcentajeEfectivoPieza(
        int $idCliente,
        int $idPieza,
        int $idSubfamilia,
        int $idFamilia,
        float $subtotalJoyasListaTransaccion,
        int $idMetal = 0,
        int $conteoPiezasMetal = 0,
        ?float $subtotalPlataListaTransaccion = null
    ): array {
        $subPlata = $subtotalPlataListaTransaccion ?? $subtotalJoyasListaTransaccion;

        return (new ReglasDescuentoService())->resolverPorcentajeEfectivoPiezaOnline(
            $idCliente,
            $idPieza,
            $idSubfamilia,
            $idFamilia,
            $idMetal,
            $subPlata,
            $conteoPiezasMetal > 0 ? $conteoPiezasMetal : 1
        );
    }

    /**
     * @return array{
     *   precio_lista: float,
     *   precio_final: float,
     *   descuento_monto: float,
     *   porcentaje: float,
     *   tiene_promocion: bool,
     *   promocion: ?array,
     *   descuento_origen: string
     * }
     */
    public function calcularPreciosPieza(
        array $pieza,
        float $precioLista,
        int $idCliente = 0,
        ?float $subtotalJoyasListaTransaccion = null,
        int $conteoPiezasMetal = 0,
        ?float $subtotalPlataListaTransaccion = null
    ): array {
        $precioLista = max(0.0, round($precioLista, 2));
        $idPieza = (int) ($pieza['id_pieza'] ?? 0);
        $idSub = (int) ($pieza['id_sub_familia'] ?? $pieza['id_subfamilia_FK'] ?? $pieza['id_sub_familia_FK'] ?? 0);
        $idFam = (int) ($pieza['id_familia'] ?? $pieza['id_familia_FK'] ?? 0);
        $idMetal = (int) ($pieza['id_metal_FK'] ?? $pieza['id_metal'] ?? 0);
        $reglas = new ReglasDescuentoService();

        if ($idCliente <= 0) {
            $metal = $reglas->obtenerMetalPorId($idMetal);
            $pctMetal = $metal !== null
                ? (float) ($metal['descuento_mostrador_pct'] ?? 0)
                : $reglas->obtenerDescuentoGeneralFallback();
            $promo = (new PromocionTiendaResolver())->resolverParaPieza($idPieza, $idSub, $idFam, $idMetal);
            $pctPromo = $promo !== null ? (float) ($promo['porcentaje_descuento'] ?? 0) : 0.0;
            $pct = $this->acotarPorcentaje(max($pctMetal, $pctPromo));
            if ($pct <= 0) {
                return [
                    'precio_lista' => $precioLista,
                    'precio_final' => $precioLista,
                    'descuento_monto' => 0.0,
                    'porcentaje' => 0.0,
                    'tiene_promocion' => false,
                    'promocion' => null,
                    'descuento_origen' => 'ninguno',
                ];
            }
            $precios = (new PromocionTiendaResolver())->calcularPrecios($precioLista, $pct);
            $origen = $pctPromo >= $pct - 0.0001 && $pctPromo >= $pctMetal ? 'promocion' : 'metal';

            return [
                'precio_lista' => $precios['precio_lista'],
                'precio_final' => $precios['precio_final'],
                'descuento_monto' => $precios['descuento_monto'],
                'porcentaje' => $precios['porcentaje'],
                'tiene_promocion' => $precios['descuento_monto'] > 0,
                'promocion' => $origen === 'promocion' ? $promo : null,
                'descuento_origen' => $origen,
            ];
        }

        $subTx = $subtotalJoyasListaTransaccion ?? $precioLista;
        $subPlata = $subtotalPlataListaTransaccion ?? $subTx;
        $conteoMetal = $conteoPiezasMetal > 0 ? $conteoPiezasMetal : 1;
        $resPct = $this->resolverPorcentajeEfectivoPieza(
            $idCliente,
            $idPieza,
            $idSub,
            $idFam,
            $subTx,
            $idMetal,
            $conteoMetal,
            $subPlata
        );
        $precios = (new PromocionTiendaResolver())->calcularPrecios($precioLista, $resPct['porcentaje']);

        return [
            'precio_lista' => $precios['precio_lista'],
            'precio_final' => $precios['precio_final'],
            'descuento_monto' => $precios['descuento_monto'],
            'porcentaje' => $precios['porcentaje'],
            'tiene_promocion' => $precios['descuento_monto'] > 0,
            'promocion' => $resPct['promocion'],
            'descuento_origen' => (string) $resPct['origen'],
        ];
    }

    /**
     * Tras venta pagada: eleva descuento_porcentaje del cliente si califica mayoreo.
     */
    public function persistirDescuentoMayoreoSiCalifica(int $idCliente, float $subtotalJoyasLista): bool
    {
        if ($idCliente <= 0) {
            return false;
        }

        $cfg = $this->obtenerConfigMayoreo();
        if ($cfg['umbral'] <= 0 || $subtotalJoyasLista + 0.0001 < $cfg['umbral']) {
            return false;
        }

        $pctObjetivo = $this->acotarPorcentaje($cfg['porcentaje']);
        $actual = $this->obtenerDescuentoCliente($idCliente);
        $actualNum = $actual !== null ? (float) $actual : 0.0;
        if ($actualNum >= $pctObjetivo - 0.0001) {
            return false;
        }

        $stmt = $this->getDb()->prepare(
            'UPDATE clientes SET descuento_porcentaje = :pct WHERE id_cliente = :id AND activo = 1'
        );
        $stmt->bindValue(':pct', number_format($pctObjetivo, 2, '.', ''), PDO::PARAM_STR);
        $stmt->bindValue(':id', $idCliente, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    /**
     * % joyas para POS: base cliente/general + mayoreo del ticket si aplica.
     */
    public function resolverDescuentoPorcentajeJoyasPos(?int $idCliente, float $subtotalJoyasLista): float
    {
        $ventas = new Ventas();
        $rateBase = $ventas->resolverDescuentoPorcentajeLinea('joya', $idCliente);
        $rateMayoreo = $this->porcentajeMayoreoTransaccion($subtotalJoyasLista);

        return $this->acotarPorcentaje(max($rateBase, $rateMayoreo));
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
            error_log('DescuentoTiendaService::columnasVentaDetalle ' . $e->getMessage());
        }

        return $cache[$key] = $out;
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
