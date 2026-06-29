<?php
/** @var array $venta */
$detalle = isset($venta['detalle']) && is_array($venta['detalle']) ? $venta['detalle'] : [];
$pagos = isset($venta['pagos']) && is_array($venta['pagos']) ? $venta['pagos'] : [];
$devoluciones = isset($venta['devoluciones']) && is_array($venta['devoluciones']) ? $venta['devoluciones'] : [];
$canjeCreditos = isset($venta['canje_creditos']) && is_array($venta['canje_creditos']) ? $venta['canje_creditos'] : [];
$totalCanjeAplicado = 0.0;
foreach ($canjeCreditos as $ccRow) {
    if (is_array($ccRow)) {
        $totalCanjeAplicado += (float) ($ccRow['monto_credito'] ?? 0);
    }
}
$idApartadoVenta = isset($venta['id_apartado_FK']) ? (int) $venta['id_apartado_FK'] : 0;
$facturaCfdi = isset($facturaCfdi) && is_array($facturaCfdi) ? $facturaCfdi : null;
$facturacionHabilitada = !empty($facturacionHabilitada);
$idVenta = (int) $venta['id_venta'];
$subtotalMercancia = 0.0;
foreach ($detalle as $lnRes) {
    if (!is_array($lnRes) || (int) ($lnRes['anulada'] ?? 0) === 1) {
        continue;
    }
    $subtotalMercancia += (float) ($lnRes['subtotal'] ?? 0);
}
$totalCobrado = (float) ($venta['total'] ?? 0);
$impuestoMontoRes = (float) ($venta['impuesto_monto'] ?? 0);
$impuestoPctRes = (float) ($venta['impuesto_porcentaje'] ?? 0);
$descuentoCliente = max(0.0, round($subtotalMercancia + $impuestoMontoRes - $totalCanjeAplicado - $totalCobrado, 2));
?>

<div class="admin-modules">
    <div class="module-actions">
        <a href="ventas.php?accion=leer" class="btn-action-secondary">
            <i class="bi bi-arrow-left"></i> Volver al listado
        </a>
        <button type="button" class="btn-action-primary" id="btn_reimprimir_ticket" data-id-venta="<?php echo (int) $venta['id_venta']; ?>">
            <i class="bi bi-printer"></i> <?php echo $idApartadoVenta > 0 ? 'Reimprimir ticket (liquidacion apartado)' : 'Reimprimir ticket'; ?>
        </button>
        <?php if ($idApartadoVenta > 0): ?>
            <button type="button" class="btn-action-secondary" id="btn_reimprimir_ticket_venta" data-id-venta="<?php echo (int) $venta['id_venta']; ?>">
                <i class="bi bi-receipt"></i> Reimprimir ticket de venta (POS)
            </button>
        <?php endif; ?>
    </div>

    <div class="alert-message info" style="margin-top:1rem;">
        <p><i class="bi bi-info-circle"></i> Las devoluciones con reembolso en efectivo se registran en <strong>Punto de Venta</strong> (sección Devoluciones). El canje por cambio de pieza descuenta el crédito en la venta nueva y liquida la venta origen sin salida de efectivo.</p>
    </div>

    <div class="form-section" style="margin-top: 1rem;">
        <h3><i class="bi bi-receipt"></i> Venta #<?php echo (int) $venta['id_venta']; ?></h3>
        <div class="form-row">
            <p><strong>Cliente:</strong> <?php echo htmlspecialchars((string) ($venta['cliente_nombre'] ?? '')); ?></p>
            <p><strong>Empleado:</strong> <?php echo htmlspecialchars((string) ($venta['empleado_nombre'] ?? '')); ?></p>
            <p><strong>Fecha:</strong> <?php echo htmlspecialchars((string) ($venta['fecha_venta'] ?? '')); ?></p>
            <p><strong>Estado:</strong> <?php echo htmlspecialchars((string) ($venta['estado'] ?? '')); ?></p>
            <?php if (!empty($idApartadoVenta)): ?>
                <p><strong>Apartado liquidado:</strong> #<?php echo (int) $idApartadoVenta; ?>
                    <span class="text-muted">— la reimpresión principal usa el ticket termico de <strong>liquidación de apartado</strong>.</span>
                </p>
            <?php endif; ?>
        </div>

        <h4 style="margin-top:1rem;"><i class="bi bi-cash-coin"></i> Resumen financiero</h4>
        <div class="form-row">
            <p><strong>Subtotal mercancía:</strong> $<?php echo htmlspecialchars(number_format($subtotalMercancia, 2, '.', '')); ?></p>
            <?php if ($descuentoCliente > 0.009): ?>
                <p><strong>Descuento cliente:</strong> −$<?php echo htmlspecialchars(number_format($descuentoCliente, 2, '.', '')); ?></p>
            <?php endif; ?>
            <?php if ($totalCanjeAplicado > 0.009): ?>
                <p><strong>Crédito por canje:</strong> −$<?php echo htmlspecialchars(number_format($totalCanjeAplicado, 2, '.', '')); ?>
                    <span class="text-muted">(<?php echo count($canjeCreditos); ?> pieza<?php echo count($canjeCreditos) === 1 ? '' : 's'; ?>)</span>
                </p>
            <?php endif; ?>
            <p><strong>Impuesto:</strong> <?php echo htmlspecialchars(number_format($impuestoPctRes, 2, '.', '')); ?>% —
                $<?php echo htmlspecialchars(number_format($impuestoMontoRes, 2, '.', '')); ?></p>
            <p><strong>Total cobrado:</strong> $<?php echo htmlspecialchars(number_format($totalCobrado, 2, '.', '')); ?></p>
        </div>
        <?php if ($totalCanjeAplicado > 0.009): ?>
            <p class="text-muted" style="margin-top:0;font-size:0.95rem;">
                <i class="bi bi-info-circle"></i> El total cobrado es la parte pagada en caja; el canje no genera movimiento de efectivo en esta venta.
            </p>
        <?php endif; ?>

        <?php if (!empty($pagos)): ?>
            <h4 style="margin-top:1rem;"><i class="bi bi-credit-card"></i> Pagos (incluye reembolsos negativos)</h4>
            <div class="admin-table-wrapper">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Forma de pago</th>
                            <th class="text-right">Monto</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pagos as $pg): ?>
                            <tr>
                                <td><?php echo htmlspecialchars((string) ($pg['forma_pago'] ?? '')); ?></td>
                                <td class="text-right">$<?php echo htmlspecialchars(number_format((float) ($pg['monto'] ?? 0), 2, '.', '')); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php if (!empty($canjeCreditos)): ?>
            <h4 style="margin-top:1rem;"><i class="bi bi-arrow-left-right"></i> Piezas devueltas en esta compra (canje)</h4>
            <p class="text-muted" style="margin-top:0;font-size:0.95rem;">
                Estas piezas se devolvieron de ventas anteriores y su valor se aplicó como descuento en esta venta.
            </p>
            <div class="admin-table-wrapper">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Venta origen</th>
                            <th>Stock #</th>
                            <th>Código</th>
                            <th>Descripción</th>
                            <th class="text-right">Crédito aplicado</th>
                            <th>Motivo</th>
                            <th>Fecha canje</th>
                            <th>Empleado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($canjeCreditos as $cc): ?>
                            <?php
                            $idOrig = (int) ($cc['id_venta_origen'] ?? 0);
                            $codPieza = trim((string) ($cc['pieza_codigo'] ?? ''));
                            ?>
                            <tr>
                                <td>
                                    <?php if ($idOrig > 0): ?>
                                        <a href="ventas.php?accion=ver&amp;id=<?php echo $idOrig; ?>">#<?php echo $idOrig; ?></a>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td><?php echo (int) ($cc['id_pieza_stock_FK'] ?? 0); ?></td>
                                <td><?php echo htmlspecialchars($codPieza !== '' ? $codPieza : '—'); ?></td>
                                <td><?php echo htmlspecialchars((string) ($cc['pieza_descripcion'] ?? '—')); ?></td>
                                <td class="text-right">$<?php echo htmlspecialchars(number_format((float) ($cc['monto_credito'] ?? 0), 2, '.', '')); ?></td>
                                <td><?php echo nl2br(htmlspecialchars((string) ($cc['motivo'] ?? ''), ENT_QUOTES, 'UTF-8')); ?></td>
                                <td><?php echo htmlspecialchars((string) ($cc['fecha_devolucion'] ?? '')); ?></td>
                                <td><?php echo htmlspecialchars((string) ($cc['empleado_nombre'] ?? '')); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php if (!empty($detalle)): ?>
            <h4 style="margin-top:1rem;"><i class="bi bi-box-seam"></i> Detalle</h4>
            <div class="admin-table-wrapper">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Tipo</th>
                            <th>Cod. auxiliar</th>
                            <th>Descripción</th>
                            <th>Estado pieza</th>
                            <th>Anulada</th>
                            <th>Tienda (insumo)</th>
                            <th class="text-right">Cantidad</th>
                            <th class="text-right">P. unitario</th>
                            <th class="text-right">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($detalle as $ln): ?>
                            <?php
                            $anulada = isset($ln['anulada']) ? (int) $ln['anulada'] : 0;
                            $esJoya = ($ln['tipo_linea'] ?? '') === 'joya';
                            $codAux = trim((string) ($ln['pieza_codigo_auxiliar'] ?? ''));
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars((string) ($ln['tipo_linea'] ?? '')); ?></td>
                                <td><?php echo $esJoya ? htmlspecialchars($codAux !== '' ? $codAux : '—') : '—'; ?></td>
                                <td><?php echo htmlspecialchars((string) ($ln['nombre_item'] ?? '—')); ?></td>
                                <td><?php echo htmlspecialchars((string) ($ln['estado_pieza'] ?? '—')); ?></td>
                                <td><?php echo $anulada ? 'Si' : 'No'; ?></td>
                                <td>
                                    <?php
                                    echo (isset($ln['id_tienda_FK']) && (int) $ln['id_tienda_FK'] > 0)
                                        ? '#' . (int) $ln['id_tienda_FK']
                                        : '—';
                                    ?>
                                </td>
                                <td class="text-right"><?php echo htmlspecialchars((string) ($ln['cantidad'] ?? '')); ?></td>
                                <td class="text-right">
                                    $<?php echo htmlspecialchars(number_format((float) ($ln['precio_unitario'] ?? 0), 2, '.', '')); ?>
                                </td>
                                <td class="text-right">
                                    $<?php echo htmlspecialchars(number_format((float) ($ln['subtotal'] ?? 0), 2, '.', '')); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert-message info">
                <p><i class="bi bi-info-circle"></i> Esta venta no tiene líneas de detalle registradas.</p>
            </div>
        <?php endif; ?>

        <h4 style="margin-top:1.5rem;"><i class="bi bi-receipt-cutoff"></i> Factura CFDI</h4>
        <?php if (!$facturacionHabilitada): ?>
            <div class="alert-message info">
                <p><i class="bi bi-info-circle"></i> La facturacion CFDI esta deshabilitada. Active <strong>facturacion_habilitada</strong> en Configuracion del sistema.</p>
            </div>
        <?php elseif ($facturaCfdi): ?>
            <?php $estadoCfdi = (string) ($facturaCfdi['estado'] ?? ''); ?>
            <div class="form-row">
                <p><strong>Estado:</strong> <?php echo htmlspecialchars($estadoCfdi); ?></p>
                <?php if ($estadoCfdi === 'emitida'): ?>
                    <p><strong>UUID:</strong> <?php echo htmlspecialchars((string) ($facturaCfdi['uuid'] ?? '—')); ?></p>
                    <p><strong>Serie-Folio:</strong> <?php echo htmlspecialchars(trim((string) ($facturaCfdi['serie'] ?? '') . '-' . (string) ($facturaCfdi['folio'] ?? ''))); ?></p>
                    <p><strong>Envio correo:</strong> <?php echo htmlspecialchars((string) ($facturaCfdi['envio_correo_estado'] ?? '')); ?></p>
                    <p><strong>Envio WhatsApp:</strong> <?php echo htmlspecialchars((string) ($facturaCfdi['envio_whatsapp_estado'] ?? '')); ?></p>
                <?php elseif (!empty($facturaCfdi['error_timbrado'])): ?>
                    <p><strong>Error timbrado:</strong> <span class="text-danger"><?php echo htmlspecialchars((string) $facturaCfdi['error_timbrado']); ?></span></p>
                <?php endif; ?>
            </div>
            <div class="form-actions" style="margin-top:0.5rem;">
                <a href="facturas.php?accion=ver&id=<?php echo (int) $facturaCfdi['id_factura']; ?>" class="btn-action-secondary">Ver detalle factura</a>
                <?php if ($estadoCfdi === 'emitida'): ?>
                    <a href="api/factura_descarga.php?tipo=pdf&id=<?php echo (int) $facturaCfdi['id_factura']; ?>" class="btn-action-primary" target="_blank"><i class="bi bi-file-pdf"></i> PDF</a>
                    <a href="api/factura_descarga.php?tipo=xml&id=<?php echo (int) $facturaCfdi['id_factura']; ?>" class="btn-action-secondary" target="_blank"><i class="bi bi-file-code"></i> XML</a>
                    <a href="facturas.php?accion=reenviar&id=<?php echo (int) $facturaCfdi['id_factura']; ?>" class="btn-action-secondary"><i class="bi bi-send"></i> Reenviar al cliente</a>
                <?php elseif ($estadoCfdi === 'error'): ?>
                    <a href="facturas.php?accion=emitir&id_venta=<?php echo $idVenta; ?>" class="btn-action-primary"><i class="bi bi-arrow-repeat"></i> Reintentar timbrado</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="alert-message info">
                <p><i class="bi bi-info-circle"></i> No hay factura registrada para esta venta.</p>
            </div>
            <div class="form-actions" style="margin-top:0.5rem;">
                <a href="facturas.php?accion=emitir&id_venta=<?php echo $idVenta; ?>" class="btn-action-primary"><i class="bi bi-receipt-cutoff"></i> Emitir factura</a>
            </div>
        <?php endif; ?>

        <?php if (!empty($devoluciones)): ?>
            <h4 style="margin-top:1rem;"><i class="bi bi-arrow-counterclockwise"></i> Devoluciones de esta venta (origen)</h4>
            <div class="admin-table-wrapper">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Stock #</th>
                            <th>Código</th>
                            <th>Descripción</th>
                            <th class="text-right">Monto</th>
                            <th>Forma pago</th>
                            <th>Canje en venta</th>
                            <th>Motivo</th>
                            <th>Empleado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($devoluciones as $d): ?>
                            <?php
                            $idDestCanje = (int) ($d['id_venta_destino_canje_FK'] ?? 0);
                            $codDev = trim((string) ($d['pieza_codigo'] ?? ''));
                            $esCanjeInterno = $idDestCanje > 0 || empty($d['id_forma_pago_FK']);
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars((string) ($d['fecha_devolucion'] ?? '')); ?></td>
                                <td><?php echo (int) ($d['id_pieza_stock_FK'] ?? 0); ?></td>
                                <td><?php echo htmlspecialchars($codDev !== '' ? $codDev : '—'); ?></td>
                                <td><?php echo htmlspecialchars((string) ($d['pieza_descripcion'] ?? '—')); ?></td>
                                <td class="text-right">$<?php echo htmlspecialchars(number_format((float) ($d['monto_reembolso'] ?? 0), 2, '.', '')); ?></td>
                                <td><?php echo htmlspecialchars($esCanjeInterno ? 'Canje interno (sin efectivo)' : (string) ($d['forma_pago'] ?? '—')); ?></td>
                                <td>
                                    <?php if ($idDestCanje > 0): ?>
                                        <a href="ventas.php?accion=ver&amp;id=<?php echo $idDestCanje; ?>">#<?php echo $idDestCanje; ?></a>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td><?php echo nl2br(htmlspecialchars((string) ($d['motivo'] ?? ''), ENT_QUOTES, 'UTF-8')); ?></td>
                                <td><?php echo htmlspecialchars((string) ($d['empleado_nombre'] ?? '')); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
(function () {
    function encolarTicket(btn, idVenta, forzarVenta) {
        if (!idVenta) return;
        if (btn) btn.disabled = true;
        var payload = { id_venta: idVenta };
        if (forzarVenta) {
            payload.tipo_ticket = 'venta';
        }
        fetch('api/impresion.php?accion=encolar', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res || !res.success) {
                    throw new Error((res && res.error) || 'No se pudo encolar el ticket.');
                }
                alert(res.message || 'Ticket encolado para reimpresión.');
            })
            .catch(function (err) {
                alert(err.message || 'Error al reimprimir ticket.');
            })
            .finally(function () {
                if (btn) btn.disabled = false;
            });
    }
    var btn = document.getElementById('btn_reimprimir_ticket');
    if (btn) {
        btn.addEventListener('click', function () {
            encolarTicket(btn, parseInt(btn.getAttribute('data-id-venta') || '0', 10), false);
        });
    }
    var btnV = document.getElementById('btn_reimprimir_ticket_venta');
    if (btnV) {
        btnV.addEventListener('click', function () {
            encolarTicket(btnV, parseInt(btnV.getAttribute('data-id-venta') || '0', 10), true);
        });
    }
})();
</script>
