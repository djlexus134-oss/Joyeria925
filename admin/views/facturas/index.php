<?php
/** @var array $facturas */
/** @var string|null $mensaje */
/** @var string $mensajeTipo */
?>
<div class="module-actions">
    <a href="ventas.php?accion=leer" class="btn-action-secondary"><i class="bi bi-arrow-left"></i> Ventas</a>
</div>

<?php if (!empty($mensaje)): ?>
    <div class="alert-message <?php echo htmlspecialchars($mensajeTipo ?? 'info'); ?>">
        <p><?php echo htmlspecialchars($mensaje); ?></p>
    </div>
<?php endif; ?>

<div class="form-section" style="margin-top:1rem;">
    <h3><i class="bi bi-receipt-cutoff"></i> Facturas emitidas</h3>
    <div class="admin-table-wrapper">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Venta</th>
                    <th>Cliente</th>
                    <th>Serie-Folio</th>
                    <th>UUID</th>
                    <th>Total</th>
                    <th>Estado</th>
                    <th>Correo</th>
                    <th>WhatsApp</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($facturas)): ?>
                    <tr><td colspan="10">Sin facturas registradas.</td></tr>
                <?php else: ?>
                    <?php foreach ($facturas as $f): ?>
                        <tr>
                            <td><?php echo (int) $f['id_factura']; ?></td>
                            <td>#<?php echo (int) $f['id_venta_FK']; ?></td>
                            <td><?php echo htmlspecialchars((string) ($f['cliente_nombre'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars(trim((string) ($f['serie'] ?? '') . '-' . (string) ($f['folio'] ?? ''))); ?></td>
                            <td style="font-size:0.85em;"><?php echo htmlspecialchars((string) ($f['uuid'] ?? '—')); ?></td>
                            <td>$<?php echo number_format((float) ($f['total'] ?? 0), 2); ?></td>
                            <td><?php echo htmlspecialchars((string) ($f['estado'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars((string) ($f['envio_correo_estado'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars((string) ($f['envio_whatsapp_estado'] ?? '')); ?></td>
                            <td>
                                <a class="btn-action-secondary" href="facturas.php?accion=ver&id=<?php echo (int) $f['id_factura']; ?>">Ver</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
