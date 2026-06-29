<?php
/** @var array<string, mixed> $venta */
$idV = (int) ($venta['id_venta'] ?? 0);
$estadoPago = (string) ($venta['estado_pago'] ?? '');
$estadoEntrega = (string) ($venta['estado_entrega'] ?? '');
$nomTienda = (string) ($venta['nom_tienda'] ?? '—');
$clienteNombre = (string) ($venta['cliente_nombre'] ?? 'Cliente');
$clienteCorreo = (string) ($venta['cliente_correo'] ?? '');
$clienteTelefono = (string) ($venta['cliente_telefono'] ?? '');
$detalle = is_array($venta['detalle'] ?? null) ? $venta['detalle'] : [];
$totalVenta = (float) ($venta['total'] ?? 0);
$creditoAplicado = (float) ($venta['credito_aplicado'] ?? 0);
$montoCobrado = max(0.0, $totalVenta - $creditoAplicado);
$idPagoExterno = (string) ($venta['id_pago_externo'] ?? '');
?>

<div class="admin-modules">
    <section class="admin-card" style="margin-bottom:1rem;">
        <div class="form-actions" style="margin-bottom:1rem;">
            <a href="ventas_online.php" class="btn-action-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
        </div>

        <h3>Orden #<?php echo $idV; ?></h3>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;margin-top:1rem;">
            <div>
                <small class="form-hint">Cliente</small>
                <div><strong><?php echo htmlspecialchars($clienteNombre); ?></strong></div>
                <?php if ($clienteCorreo !== ''): ?><div><?php echo htmlspecialchars($clienteCorreo); ?></div><?php endif; ?>
                <?php if ($clienteTelefono !== ''): ?><div><?php echo htmlspecialchars($clienteTelefono); ?></div><?php endif; ?>
            </div>
            <div>
                <small class="form-hint">Sucursal de recogida</small>
                <div><strong><i class="bi bi-shop"></i> <?php echo htmlspecialchars($nomTienda); ?></strong></div>
            </div>
            <div>
                <small class="form-hint">Fecha</small>
                <div><?php echo htmlspecialchars((string) ($venta['fecha_venta'] ?? '')); ?></div>
            </div>
            <div>
                <small class="form-hint">Total mercancía</small>
                <div><strong>$<?php echo number_format($totalVenta, 2, '.', ','); ?> MXN</strong></div>
            </div>
            <?php if ($creditoAplicado > 0): ?>
            <div>
                <small class="form-hint">Crédito de tienda aplicado</small>
                <div class="text-success"><strong>−$<?php echo number_format($creditoAplicado, 2, '.', ','); ?> MXN</strong></div>
            </div>
            <div>
                <small class="form-hint">Monto cobrado</small>
                <div><strong>$<?php echo number_format($montoCobrado, 2, '.', ','); ?> MXN</strong></div>
            </div>
            <?php endif; ?>
            <div>
                <small class="form-hint">Referencia de pago</small>
                <div><code><?php echo htmlspecialchars($idPagoExterno !== '' ? $idPagoExterno : '—'); ?></code>
                <?php if ($idPagoExterno === 'credito_cliente'): ?>
                    <span class="text-muted small"> (crédito de tienda)</span>
                <?php endif; ?>
                </div>
            </div>
            <div>
                <small class="form-hint">Estado pago</small>
                <div><strong><?php echo htmlspecialchars($estadoPago); ?></strong></div>
            </div>
            <div>
                <small class="form-hint">Estado entrega</small>
                <div><strong><?php echo htmlspecialchars($estadoEntrega); ?></strong></div>
            </div>
            <?php if (!empty($venta['fecha_lista_recoger'])): ?>
            <div>
                <small class="form-hint">Lista para recoger desde</small>
                <div><?php echo htmlspecialchars((string) $venta['fecha_lista_recoger']); ?></div>
            </div>
            <?php endif; ?>
            <?php if (!empty($venta['fecha_entregada'])): ?>
            <div>
                <small class="form-hint">Entregada</small>
                <div><?php echo htmlspecialchars((string) $venta['fecha_entregada']); ?></div>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="admin-card" style="margin-bottom:1rem;">
        <h3>Piezas a apartar del anaquel</h3>
        <p class="form-hint">Reune estas piezas y marca el pedido como <strong>Lista para recoger</strong>.</p>
        <div class="admin-table-wrapper">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Descripción</th>
                        <th>Subfamilia</th>
                        <th>Metal</th>
                        <th>Sucursal</th>
                        <th class="text-right">Precio</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($detalle as $d): ?>
                        <tr>
                            <td><code><?php echo htmlspecialchars((string) ($d['codigo_auxiliar'] ?? '')); ?></code></td>
                            <td><?php echo htmlspecialchars((string) ($d['desc_pieza'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars((string) ($d['nom_sub_familia'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars((string) ($d['nom_metal'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars((string) ($d['nom_tienda'] ?? '')); ?></td>
                            <td class="text-right">$<?php echo number_format((float) ($d['precio_unitario'] ?? 0), 2, '.', ','); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="admin-card">
        <h3>Acciones</h3>
        <?php if ($estadoPago !== 'pagado'): ?>
            <p class="alert-message warning">Esta venta aún no está pagada. Espera a que Mercado Pago confirme el pago.</p>
        <?php else: ?>
            <div class="form-actions" style="gap:8px;flex-wrap:wrap;">
                <?php if ($estadoEntrega === 'pendiente'): ?>
                    <a class="btn-action-primary" href="ventas_online.php?accion=marcar_lista&id=<?php echo $idV; ?>"
                       onclick="return confirm('Confirmar que aparataste las piezas y avisamos al cliente?');">
                        <i class="bi bi-bell"></i> Marcar como lista para recoger
                    </a>
                <?php endif; ?>
                <?php if ($estadoEntrega === 'lista_recoger' || $estadoEntrega === 'pendiente'): ?>
                    <a class="btn-action-primary" href="ventas_online.php?accion=marcar_entregada&id=<?php echo $idV; ?>"
                       onclick="return confirm('Confirmar que entregaste las piezas al cliente?');">
                        <i class="bi bi-check2-circle"></i> Marcar como entregada
                    </a>
                <?php endif; ?>
                <?php if ($estadoEntrega === 'entregada'): ?>
                    <p class="alert-message info" style="margin:0;">Esta venta ya fue entregada.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </section>
</div>
