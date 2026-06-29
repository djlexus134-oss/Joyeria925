<?php
/** @var array $factura */
/** @var string|null $mensaje */
/** @var string $mensajeTipo */
$idFactura = (int) $factura['id_factura'];
?>
<div class="module-actions">
    <a href="facturas.php?accion=leer" class="btn-action-secondary"><i class="bi bi-arrow-left"></i> Listado</a>
    <a href="ventas.php?accion=ver&id=<?php echo (int) $factura['id_venta_FK']; ?>" class="btn-action-secondary">Venta #<?php echo (int) $factura['id_venta_FK']; ?></a>
</div>

<?php if (!empty($mensaje)): ?>
    <div class="alert-message <?php echo htmlspecialchars($mensajeTipo ?? 'info'); ?>">
        <p><?php echo htmlspecialchars($mensaje); ?></p>
    </div>
<?php endif; ?>

<div class="form-section" style="margin-top:1rem;">
    <h3><i class="bi bi-receipt-cutoff"></i> Factura #<?php echo $idFactura; ?></h3>
    <div class="form-row">
        <p><strong>Estado:</strong> <?php echo htmlspecialchars((string) ($factura['estado'] ?? '')); ?></p>
        <p><strong>Serie-Folio:</strong> <?php echo htmlspecialchars(trim((string) ($factura['serie'] ?? '') . '-' . (string) ($factura['folio'] ?? ''))); ?></p>
        <p><strong>UUID:</strong> <?php echo htmlspecialchars((string) ($factura['uuid'] ?? '—')); ?></p>
        <p><strong>Total:</strong> $<?php echo number_format((float) ($factura['total'] ?? 0), 2); ?></p>
        <p><strong>RFC receptor:</strong> <?php echo htmlspecialchars((string) ($factura['rfc_receptor'] ?? '')); ?></p>
        <p><strong>Envio correo:</strong> <?php echo htmlspecialchars((string) ($factura['envio_correo_estado'] ?? '')); ?></p>
        <p><strong>Envio WhatsApp:</strong> <?php echo htmlspecialchars((string) ($factura['envio_whatsapp_estado'] ?? '')); ?></p>
        <?php if (!empty($factura['error_timbrado'])): ?>
            <p><strong>Error:</strong> <span class="text-danger"><?php echo htmlspecialchars((string) $factura['error_timbrado']); ?></span></p>
        <?php endif; ?>
    </div>

    <div class="form-actions" style="margin-top:1rem;">
        <?php if (($factura['estado'] ?? '') === 'emitida'): ?>
            <a href="api/factura_descarga.php?tipo=pdf&id=<?php echo $idFactura; ?>" class="btn-action-primary" target="_blank"><i class="bi bi-file-pdf"></i> PDF</a>
            <a href="api/factura_descarga.php?tipo=xml&id=<?php echo $idFactura; ?>" class="btn-action-secondary" target="_blank"><i class="bi bi-file-code"></i> XML</a>
            <a href="facturas.php?accion=reenviar&id=<?php echo $idFactura; ?>" class="btn-action-secondary"><i class="bi bi-send"></i> Reenviar al cliente</a>
            <a href="facturas.php?accion=cancelar&id=<?php echo $idFactura; ?>" class="btn-action-danger" onclick="return confirm('¿Cancelar esta factura en el PAC?');"><i class="bi bi-x-circle"></i> Cancelar</a>
        <?php elseif (($factura['estado'] ?? '') === 'error'): ?>
            <a href="facturas.php?accion=emitir&id_venta=<?php echo (int) $factura['id_venta_FK']; ?>" class="btn-action-primary"><i class="bi bi-arrow-repeat"></i> Reintentar timbrado</a>
        <?php endif; ?>
    </div>
</div>
