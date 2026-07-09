<?php
require_once __DIR__ . '/../models/configuracion_general.php';
require_once __DIR__ . '/../models/ventas.php';
require_once __DIR__ . '/../models/devoluciones.php';
require_once __DIR__ . '/../models/apartado_gestion.php';
require_once __DIR__ . '/../models/ordenes_taller.php';
require_once __DIR__ . '/TicketEscPosBuilder.php';

class TicketService
{
    private ConfiguracionGeneral $config;
    private Ventas $ventas;

    public function __construct(?ConfiguracionGeneral $config = null, ?Ventas $ventas = null)
    {
        $this->config = $config ?? new ConfiguracionGeneral();
        $this->ventas = $ventas ?? new Ventas();
    }

    public function clavesConfig(): array
    {
        return [
            'ticket_nombre_comercial',
            'ticket_leyenda_folio',
            'ticket_horario',
            'ticket_mensaje_pie',
            'ticket_ancho_columnas',
            'ticket_margen_izquierdo',
            'ticket_feed_inicio_lineas',
            'ticket_mostrar_impuesto',
            'ticket_mostrar_empleado',
            'impresion_habilitada',
            'impresion_caja_token',
            'impresion_nombre_impresora',
            'impresion_id_tienda_caja',
        ];
    }

    public function leerConfigTicket(): array
    {
        return $this->config->leerConDefaults($this->clavesConfig());
    }

    public function impresionHabilitada(): bool
    {
        $map = $this->leerConfigTicket();

        return !empty($map['impresion_habilitada']);
    }

    /**
     * Codigo visible en ticket para una linea de venta (joya o insumo).
     */
    private function resolverCodigoItemTicket(array $ln): string
    {
        $tipo = (string) ($ln['tipo_linea'] ?? '');
        if ($tipo === 'insumo') {
            return trim((string) ($ln['insumo_codigo'] ?? ''));
        }

        $aux = trim((string) ($ln['pieza_codigo_auxiliar'] ?? ''));
        if ($aux !== '') {
            return $aux;
        }

        return trim((string) ($ln['pieza_codigo_barras'] ?? ''));
    }

    /**
     * Codigo visible en ticket para una pieza de apartado.
     */
    private function resolverCodigoApartadoDetalle(array $detalle): string
    {
        $aux = trim((string) ($detalle['codigo_auxiliar'] ?? ''));
        if ($aux !== '') {
            return $aux;
        }

        return trim((string) ($detalle['codigo_barras'] ?? ''));
    }

    public function construirDesdeVenta(int $idVenta): array
    {
        $venta = $this->ventas->leerUno($idVenta);
        if (!is_array($venta)) {
            throw new InvalidArgumentException('Venta no encontrada.');
        }

        $cfg = $this->leerConfigTicket();
        $detalle = isset($venta['detalle']) && is_array($venta['detalle']) ? $venta['detalle'] : [];
        $pagos = $this->ventas->leerPagosVenta($idVenta);

        $subtotal = 0.0;
        $subtotalLista = 0.0;
        $conteoPiezas = 0;
        $lineas = [];
        $descuentoDesgloseMap = [];
        foreach ($detalle as $ln) {
            if (!is_array($ln)) {
                continue;
            }
            if ((int) ($ln['anulada'] ?? 0) === 1) {
                continue;
            }
            $cantidad = (float) ($ln['cantidad'] ?? 0);
            if ($cantidad <= 0) {
                $cantidad = 1.0;
            }
            $precio = (float) ($ln['precio_unitario'] ?? 0);
            $subtotalLinea = isset($ln['subtotal']) ? (float) $ln['subtotal'] : ($cantidad * $precio);
            $subtotalListaLinea = $precio > 0 ? $precio * $cantidad : $subtotalLinea;
            $descuentoLinea = isset($ln['descuento_aplicado']) && is_numeric($ln['descuento_aplicado'])
                ? (float) $ln['descuento_aplicado']
                : max(0.0, round($subtotalListaLinea - $subtotalLinea, 2));
            $descuentoPct = $subtotalListaLinea > 0.00001
                ? round(($descuentoLinea / $subtotalListaLinea) * 100, 2)
                : 0.0;

            $subtotal += $subtotalLinea;
            $subtotalLista += $subtotalListaLinea;
            if (($ln['tipo_linea'] ?? '') === 'joya' && $cantidad > 0) {
                $conteoPiezas += (int) round($cantidad);
            }

            if ($descuentoLinea > 0.009) {
                $tipoLinea = (string) ($ln['tipo_linea'] ?? '');
                if ($tipoLinea === 'insumo') {
                    $claveDesglose = 'Insumos';
                } else {
                    $nomMetal = trim((string) ($ln['pieza_metal_nombre'] ?? ''));
                    $claveDesglose = $nomMetal !== '' ? $nomMetal : 'Joyas';
                }
                $descuentoDesgloseMap[$claveDesglose] = ($descuentoDesgloseMap[$claveDesglose] ?? 0.0) + $descuentoLinea;
            }

            $lineas[] = [
                'descripcion' => (string) ($ln['nombre_item'] ?? $ln['descripcion'] ?? 'Articulo'),
                'codigo' => $this->resolverCodigoItemTicket($ln),
                'cantidad' => $cantidad,
                'precio_unitario' => $precio,
                'subtotal' => $subtotalLinea,
                'subtotal_lista' => round($subtotalListaLinea, 2),
                'descuento_monto' => round($descuentoLinea, 2),
                'descuento_porcentaje' => $descuentoPct,
            ];
        }

        $descuentoDesglose = [];
        foreach ($descuentoDesgloseMap as $etiqueta => $monto) {
            if ($monto <= 0.009) {
                continue;
            }
            $descuentoDesglose[] = [
                'etiqueta' => 'Desc. ' . $etiqueta,
                'monto' => round((float) $monto, 2),
            ];
        }

        $descuentoPct = (float) ($venta['descuento_porcentaje_aplicado'] ?? 0);
        $total = (float) ($venta['total'] ?? 0);
        $impuestoMonto = (float) ($venta['impuesto_monto'] ?? 0);
        $descuentoTotal = max(0, round($subtotalLista - $subtotal, 2));

        $montoCanje = 0.0;
        $devCanje = new Devoluciones();
        foreach ($devCanje->listarCanjeEnVentaDestino($idVenta) as $cr) {
            if (is_array($cr)) {
                $montoCanje += (float) ($cr['monto_credito'] ?? 0);
            }
        }
        $montoCanje = round($montoCanje, 2);

        $descuentoMonto = max(0, round($descuentoTotal - $montoCanje, 2));
        if ($descuentoMonto <= 0 && $descuentoPct > 0 && $montoCanje <= 0.009 && $subtotalLista > 0) {
            $descuentoMonto = round($subtotalLista * ($descuentoPct / 100), 2);
        }

        $ancho = (int) ($cfg['ticket_ancho_columnas'] ?? 38);
        if ($ancho <= 0) {
            $ancho = 38;
        }
        $margen = (int) ($cfg['ticket_margen_izquierdo'] ?? 40);
        if ($margen < 0) {
            $margen = 0;
        }
        $feedInicio = (int) ($cfg['ticket_feed_inicio_lineas'] ?? 1);
        if ($feedInicio < 0) {
            $feedInicio = 0;
        }

        return [
            'id_venta' => (int) $venta['id_venta'],
            'nombre_comercial' => (string) ($cfg['ticket_nombre_comercial'] ?? ''),
            'leyenda_folio' => (string) ($cfg['ticket_leyenda_folio'] ?? 'Folio'),
            'horario' => (string) ($cfg['ticket_horario'] ?? ''),
            'mensaje_pie' => (string) ($cfg['ticket_mensaje_pie'] ?? ''),
            'mostrar_impuesto' => !empty($cfg['ticket_mostrar_impuesto']),
            'mostrar_empleado' => !empty($cfg['ticket_mostrar_empleado']),
            'ancho_columnas' => $ancho,
            'margen_izquierdo' => $margen,
            'feed_inicio_lineas' => $feedInicio,
            'fecha_venta' => (string) ($venta['fecha_venta'] ?? ''),
            'empleado_numero' => $this->formatearNumeroEmpleado((int) ($venta['id_empleado_FK'] ?? 0)),
            'cliente_nombre' => (string) ($venta['cliente_nombre'] ?? ''),
            'subtotal_lista' => round($subtotalLista, 2),
            'subtotal' => round($subtotal, 2),
            'descuento_monto' => round($descuentoMonto, 2),
            'descuento_desglose' => $descuentoDesglose,
            'monto_canje' => $montoCanje,
            'impuesto_porcentaje' => (float) ($venta['impuesto_porcentaje'] ?? 0),
            'impuesto_monto' => (float) ($venta['impuesto_monto'] ?? 0),
            'total' => (float) ($venta['total'] ?? 0),
            'conteo_piezas' => $conteoPiezas,
            'lineas' => $lineas,
            'pagos' => $pagos,
        ];
    }

    public function construirEscPosBase64(int $idVenta): string
    {
        $ticket = $this->construirDesdeVenta($idVenta);
        $builder = new TicketEscPosBuilder(
            (int) ($ticket['ancho_columnas'] ?? 38),
            (int) ($ticket['margen_izquierdo'] ?? 40)
        );

        return $builder->toBase64($ticket);
    }

    private function formatearNumeroEmpleado(int $idEmpleado): string
    {
        if ($idEmpleado <= 0) {
            return '';
        }

        return '#' . str_pad((string) $idEmpleado, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Construye datos de ticket termico para apartado (misma API de impresora que ventas).
     *
     * @param 'alta'|'abono'|'liquidacion' $modo
     *
     * @return array<string, mixed>
     */
    public function construirDesdeApartado(int $idApartado, string $modo): array
    {
        $modos = ['alta', 'abono', 'liquidacion'];
        if (!in_array($modo, $modos, true)) {
            $modo = 'abono';
        }

        $ag = new ApartadoGestion();
        $full = $ag->leerApartadoCompleto($idApartado);

        $cfg = $this->leerConfigTicket();
        $ancho = (int) ($cfg['ticket_ancho_columnas'] ?? 38);
        if ($ancho <= 0) {
            $ancho = 38;
        }
        $margen = (int) ($cfg['ticket_margen_izquierdo'] ?? 40);
        if ($margen < 0) {
            $margen = 0;
        }
        $feedInicio = (int) ($cfg['ticket_feed_inicio_lineas'] ?? 1);
        if ($feedInicio < 0) {
            $feedInicio = 0;
        }

        $subtotal = 0.0;
        $subtotalLista = 0.0;
        $conteoPiezas = 0;
        $lineas = [];
        $descuentoDesgloseMap = [];
        foreach ($full['detalles'] ?? [] as $d) {
            if (!is_array($d)) {
                continue;
            }
            $pr = (float) ($d['precio_apartado'] ?? 0);
            $lista = (float) ($d['precio_venta'] ?? $pr);
            $descLinea = max(0.0, round($lista - $pr, 2));
            $descPct = $lista > 0.00001 ? round(($descLinea / $lista) * 100, 2) : 0.0;
            $subtotal += $pr;
            $subtotalLista += $lista;
            $conteoPiezas++;

            if ($descLinea > 0.009) {
                $nomMetal = trim((string) ($d['metal_nombre'] ?? ''));
                $claveDesglose = $nomMetal !== '' ? $nomMetal : 'Joyas';
                $descuentoDesgloseMap[$claveDesglose] = ($descuentoDesgloseMap[$claveDesglose] ?? 0.0) + $descLinea;
            }

            $lineas[] = [
                'descripcion' => (string) ($d['desc_pieza'] ?? 'Pieza'),
                'codigo' => $this->resolverCodigoApartadoDetalle($d),
                'cantidad' => 1.0,
                'precio_unitario' => $lista > 0 ? $lista : $pr,
                'subtotal' => $pr,
                'subtotal_lista' => round($lista, 2),
                'descuento_monto' => round($descLinea, 2),
                'descuento_porcentaje' => $descPct,
            ];
        }

        $descuentoDesglose = [];
        foreach ($descuentoDesgloseMap as $etiqueta => $monto) {
            if ($monto <= 0.009) {
                continue;
            }
            $descuentoDesglose[] = [
                'etiqueta' => 'Desc. ' . $etiqueta,
                'monto' => round((float) $monto, 2),
            ];
        }
        $descuentoMonto = max(0.0, round($subtotalLista - $subtotal, 2));

        $pagosDisplay = [];
        foreach ($full['pagos'] ?? [] as $p) {
            if (!is_array($p)) {
                continue;
            }
            if (($p['estado'] ?? '') !== 'registrado') {
                continue;
            }
            $forma = trim((string) ($p['forma_pago'] ?? 'Pago'));
            $to = (string) ($p['tipo_origen'] ?? '');
            if ($to === 'credito_por_cambio') {
                $forma .= ' (credito cambio)';
            }
            $pagosDisplay[] = [
                'forma_pago' => $forma,
                'monto' => (float) ($p['monto'] ?? 0),
            ];
        }

        $totalApr = (float) ($full['total_apartado'] ?? 0);
        $impMonto = (float) ($full['impuesto_monto'] ?? 0);
        $saldo = (float) ($full['saldo_pendiente'] ?? 0);

        $titulos = [
            'alta' => 'ALTA DE APARTADO',
            'abono' => 'ABONO APARTADO',
            'liquidacion' => 'LIQUIDACION APARTADO',
        ];
        $documentoTitulo = $titulos[$modo] ?? 'APARTADO';

        $idEmp = (int) ($full['id_empleado_FK'] ?? 0);

        $fecha = (string) ($full['fecha_apartado'] ?? '');
        $estadoApr = (string) ($full['estado'] ?? '');
        $banner = '';
        if ($modo === 'liquidacion' || $saldo <= 0.02 || $estadoApr === 'liquidado') {
            $banner = '*** LIQUIDADO ***';
        }

        return [
            'documento_titulo' => $documentoTitulo,
            'nombre_comercial' => (string) ($cfg['ticket_nombre_comercial'] ?? ''),
            'leyenda_folio' => 'Apartado',
            'horario' => (string) ($cfg['ticket_horario'] ?? ''),
            'mensaje_pie' => (string) ($cfg['ticket_mensaje_pie'] ?? ''),
            'mostrar_impuesto' => $impMonto > 0.009,
            'mostrar_empleado' => !empty($cfg['ticket_mostrar_empleado']),
            'ancho_columnas' => $ancho,
            'margen_izquierdo' => $margen,
            'feed_inicio_lineas' => $feedInicio,
            'id_venta' => $idApartado,
            'fecha_venta' => $fecha,
            'empleado_numero' => $this->formatearNumeroEmpleado($idEmp),
            'cliente_nombre' => (string) ($full['cliente_nombre'] ?? ''),
            'subtotal_lista' => round($subtotalLista, 2),
            'subtotal' => round($subtotal, 2),
            'descuento_monto' => round($descuentoMonto, 2),
            'descuento_desglose' => $descuentoDesglose,
            'impuesto_porcentaje' => 0.0,
            'impuesto_monto' => $impMonto,
            'total' => $totalApr,
            'conteo_piezas' => $conteoPiezas,
            'lineas' => $lineas,
            'pagos' => $pagosDisplay,
            'pagos_seccion_titulo' => 'Abonos y pagos:',
            'saldo_pendiente' => round($saldo, 2),
            'apartado_estado_banner' => $banner,
        ];
    }

    public function construirEscPosApartadoBase64(int $idApartado, string $modo): string
    {
        $ticket = $this->construirDesdeApartado($idApartado, $modo);
        $builder = new TicketEscPosBuilder(
            (int) ($ticket['ancho_columnas'] ?? 38),
            (int) ($ticket['margen_izquierdo'] ?? 40)
        );

        return $builder->toBase64($ticket);
    }

    /**
     * @return array<string, mixed>
     */
    public function construirDesdeOrdenTaller(int $idOrdenTaller): array
    {
        if ($idOrdenTaller <= 0) {
            throw new InvalidArgumentException('Orden de taller no especificada.');
        }

        $ot = new OrdenesTaller();
        $orden = $ot->leerUno($idOrdenTaller);
        if (!$orden) {
            throw new InvalidArgumentException('La orden de taller no existe.');
        }

        $pagosRaw = $ot->leerPagos($idOrdenTaller);
        $cfg = $this->leerConfigTicket();
        $ancho = (int) ($cfg['ticket_ancho_columnas'] ?? 38);
        if ($ancho <= 0) {
            $ancho = 38;
        }
        $margen = (int) ($cfg['ticket_margen_izquierdo'] ?? 40);
        if ($margen < 0) {
            $margen = 0;
        }
        $feedInicio = (int) ($cfg['ticket_feed_inicio_lineas'] ?? 1);
        if ($feedInicio < 0) {
            $feedInicio = 0;
        }

        $infoLineas = [];
        $estado = $ot->etiquetaEstado((string) ($orden['estado'] ?? ''));
        $infoLineas[] = 'Estado: ' . $estado;
        $infoLineas[] = 'Tipo: ' . ucfirst((string) ($orden['tipo'] ?? 'reparacion'));
        $piezaDesc = trim((string) ($orden['pieza_descripcion'] ?? ''));
        if ($piezaDesc !== '') {
            $infoLineas[] = 'Pieza: ' . $piezaDesc;
        }
        $codigo = trim((string) ($orden['codigo_auxiliar'] ?? ''));
        if ($codigo === '') {
            $codigo = trim((string) ($orden['codigo_barras'] ?? ''));
        }
        if ($codigo !== '') {
            $infoLineas[] = 'Codigo: ' . $codigo;
        }
        $origen = (string) ($orden['origen'] ?? '') === 'inventario' ? 'Inventario' : 'Cliente';
        $infoLineas[] = 'Origen: ' . $origen;
        $tallerNombre = trim((string) ($orden['taller_nombre'] ?? ''));
        if ($tallerNombre !== '') {
            $telTaller = trim((string) ($orden['taller_telefono'] ?? ''));
            $infoLineas[] = 'Taller: ' . $tallerNombre . ($telTaller !== '' ? ' - ' . $telTaller : '');
        }
        if (!empty($orden['fecha_compromiso'])) {
            $infoLineas[] = 'Compromiso: ' . (string) $orden['fecha_compromiso'];
        }
        $trabajo = trim((string) ($orden['descripcion_problema'] ?? ''));
        if ($trabajo !== '') {
            $infoLineas[] = 'Trabajo: ' . $trabajo;
        }
        $obs = trim((string) ($orden['observaciones'] ?? ''));
        if ($obs !== '') {
            $infoLineas[] = 'Obs: ' . $obs;
        }

        $pagosDisplay = [];
        $totalAbonado = 0.0;
        foreach ($pagosRaw as $pago) {
            if (!is_array($pago)) {
                continue;
            }
            if (($pago['estado'] ?? '') !== 'registrado') {
                continue;
            }
            $monto = (float) ($pago['monto'] ?? 0);
            $totalAbonado += $monto;
            $pagosDisplay[] = [
                'forma_pago' => trim((string) ($pago['forma_pago'] ?? 'Pago')),
                'monto' => $monto,
            ];
        }

        $costoTotal = (float) ($orden['costo_total'] ?? 0);
        $saldo = (float) ($orden['saldo_pendiente'] ?? 0);
        $clienteNombre = trim((string) ($orden['cliente_nombre'] ?? ''));
        $telCliente = trim((string) ($orden['cliente_telefono'] ?? ''));
        if ($clienteNombre !== '' && $telCliente !== '') {
            $clienteNombre .= ' - ' . $telCliente;
        }

        return [
            'documento_titulo' => 'ORDEN DE TALLER',
            'nombre_comercial' => (string) ($cfg['ticket_nombre_comercial'] ?? ''),
            'leyenda_folio' => 'Folio',
            'folio_display' => (string) ($orden['folio'] ?? ('OT-' . $idOrdenTaller)),
            'horario' => (string) ($cfg['ticket_horario'] ?? ''),
            'mensaje_pie' => (string) ($cfg['ticket_mensaje_pie'] ?? ''),
            'mostrar_impuesto' => false,
            'mostrar_empleado' => false,
            'ancho_columnas' => $ancho,
            'margen_izquierdo' => $margen,
            'feed_inicio_lineas' => $feedInicio,
            'id_venta' => $idOrdenTaller,
            'fecha_venta' => (string) ($orden['fecha_registro'] ?? ''),
            'cliente_nombre' => $clienteNombre !== '' ? $clienteNombre : 'N/A',
            'info_lineas' => $infoLineas,
            'subtotal_lista' => round($costoTotal, 2),
            'subtotal' => round($costoTotal, 2),
            'descuento_monto' => 0.0,
            'descuento_desglose' => [],
            'impuesto_porcentaje' => 0.0,
            'impuesto_monto' => 0.0,
            'total' => round($costoTotal, 2),
            'lineas' => [],
            'pagos' => $pagosDisplay,
            'pagos_seccion_titulo' => 'Cobros:',
            'saldo_pendiente' => round($saldo, 2),
        ];
    }

    public function construirEscPosOrdenTallerBase64(int $idOrdenTaller): string
    {
        $ticket = $this->construirDesdeOrdenTaller($idOrdenTaller);
        $builder = new TicketEscPosBuilder(
            (int) ($ticket['ancho_columnas'] ?? 38),
            (int) ($ticket['margen_izquierdo'] ?? 40)
        );

        return $builder->toBase64($ticket);
    }

    public function renderVistaPreviaHtml(array $ticket): string
    {
        $width = (int) ($ticket['ancho_columnas'] ?? 42);
        $chars = max(24, min(48, $width));
        $esc = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $money = static fn ($v) => '$' . number_format((float) $v, 2, '.', '');

        ob_start();
        ?>
        <div class="ticket-preview" style="max-width:300px;margin:0 auto;padding:12px 12px 12px 20px;background:#fff;color:#111;">
            <div style="text-align:center;font-weight:bold;"><?php echo $esc($ticket['nombre_comercial'] ?? ''); ?></div>
            <div style="text-align:center;"><?php echo $esc($ticket['horario'] ?? ''); ?></div>
            <div style="text-align:center;"><?php echo str_repeat('-', $chars); ?></div>
            <div style="text-align:center;"><?php echo $esc($ticket['leyenda_folio'] ?? 'Folio'); ?></div>
            <div style="text-align:center;font-weight:bold;font-size:16px;">#<?php echo (int) ($ticket['id_venta'] ?? 0); ?></div>
            <?php if (!empty($ticket['mostrar_empleado']) && !empty($ticket['empleado_numero'])): ?>
                <div>Atendio: <?php echo $esc($ticket['empleado_numero']); ?></div>
            <?php endif; ?>
            <div><?php echo str_repeat('-', $chars); ?></div>
            <?php foreach ($ticket['lineas'] ?? [] as $linea): ?>
                <?php if (!empty($linea['codigo'])): ?>
                    <div style="font-size:12px;color:#444;">Cod: <?php echo $esc($linea['codigo']); ?></div>
                <?php endif; ?>
                <div><?php echo $esc($linea['descripcion'] ?? ''); ?></div>
                <div style="display:flex;justify-content:space-between;">
                    <span><?php echo $esc(($linea['cantidad'] ?? 1) . ' x ' . $money($linea['precio_unitario'] ?? 0)); ?></span>
                    <span><?php echo $esc($money($linea['subtotal'] ?? 0)); ?></span>
                </div>
                <?php if (!empty($linea['descuento_monto']) && (float) $linea['descuento_monto'] > 0.009): ?>
                    <div style="font-size:12px;color:#444;">
                        Desc <?php echo $esc(number_format((float) ($linea['descuento_porcentaje'] ?? 0), 2, '.', '')); ?>%
                        (-<?php echo $esc($money($linea['descuento_monto'])); ?>)
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
            <?php if (array_key_exists('conteo_piezas', $ticket)): ?>
                <div style="display:flex;justify-content:space-between;font-weight:bold;">
                    <span><?php echo $esc($ticket['leyenda_conteo_piezas'] ?? 'Piezas en esta compra'); ?></span>
                    <span><?php echo (int) ($ticket['conteo_piezas'] ?? 0); ?></span>
                </div>
            <?php endif; ?>
            <div><?php echo str_repeat('-', $chars); ?></div>
            <?php if (!empty($ticket['subtotal_lista']) && (float) $ticket['subtotal_lista'] > (float) ($ticket['subtotal'] ?? 0) + 0.009): ?>
                <div style="display:flex;justify-content:space-between;"><span>Subtotal lista</span><span><?php echo $esc($money($ticket['subtotal_lista'])); ?></span></div>
            <?php endif; ?>
            <?php foreach ($ticket['descuento_desglose'] ?? [] as $itemDesc): ?>
                <?php if (!is_array($itemDesc) || (float) ($itemDesc['monto'] ?? 0) <= 0.009) { continue; } ?>
                <div style="display:flex;justify-content:space-between;">
                    <span><?php echo $esc($itemDesc['etiqueta'] ?? 'Descuento'); ?></span>
                    <span>-<?php echo $esc($money($itemDesc['monto'])); ?></span>
                </div>
            <?php endforeach; ?>
            <div style="display:flex;justify-content:space-between;"><span>Subtotal</span><span><?php echo $esc($money($ticket['subtotal'] ?? 0)); ?></span></div>
            <?php if (!empty($ticket['mostrar_impuesto'])): ?>
                <div style="display:flex;justify-content:space-between;"><span>Impuesto</span><span><?php echo $esc($money($ticket['impuesto_monto'] ?? 0)); ?></span></div>
            <?php endif; ?>
            <div style="display:flex;justify-content:space-between;font-weight:bold;"><span>TOTAL</span><span><?php echo $esc($money($ticket['total'] ?? 0)); ?></span></div>
            <?php if (!empty($ticket['mensaje_pie'])): ?>
                <div style="text-align:center;margin-top:8px;"><?php echo $esc($ticket['mensaje_pie']); ?></div>
            <?php endif; ?>
        </div>
        <?php

        return (string) ob_get_clean();
    }
}
