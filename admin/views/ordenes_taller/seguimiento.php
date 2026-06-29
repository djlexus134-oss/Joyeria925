<?php
$estadoActual = (string) ($orden['estado'] ?? 'recibida');
$saldoPendiente = (float) ($orden['saldo_pendiente'] ?? 0);
$costoTotalOrden = (float) ($orden['costo_total'] ?? 0);
$pagos = $pagos ?? [];
$historial = $historial ?? [];

$transiciones = [
    'recibida' => ['en_taller' => 'Enviar a taller', 'cancelada' => 'Cancelar'],
    'en_taller' => ['lista' => 'Marcar como lista', 'cancelada' => 'Cancelar'],
    'lista' => ['entregada' => 'Entregar al cliente', 'en_taller' => 'Regresar a taller'],
];
$opcionesEstado = $transiciones[$estadoActual] ?? [];
$puedeAbonar = !in_array($estadoActual, ['cancelada'], true) && $saldoPendiente > 0.009;
$puedeCambiarEstado = !in_array($estadoActual, ['entregada', 'cancelada'], true);
?>

<div class="form-section" style="margin-top:24px;">
    <h3><i class="bi bi-activity"></i> Seguimiento y cobros</h3>

    <div class="form-row" style="margin-bottom:16px;">
        <div class="form-group">
            <label>Estado actual:</label>
            <p><strong><?php echo htmlspecialchars($app->etiquetaEstado($estadoActual)); ?></strong></p>
        </div>
        <div class="form-group">
            <label>Costo total:</label>
            <p><strong>$<?php echo htmlspecialchars(number_format($costoTotalOrden, 2)); ?></strong></p>
        </div>
        <div class="form-group">
            <label>Saldo pendiente:</label>
            <p><strong style="color:<?php echo $saldoPendiente > 0 ? '#c0392b' : '#27ae60'; ?>;">
                $<?php echo htmlspecialchars(number_format($saldoPendiente, 2)); ?>
            </strong></p>
        </div>
        <?php if ($saldoPendiente > 0.009 && $estadoActual === 'lista'): ?>
            <div class="form-group">
                <div class="alert-message warning" style="margin:0;">
                    <p><i class="bi bi-exclamation-triangle"></i> Hay saldo pendiente antes de entregar.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($puedeCambiarEstado && !empty($opcionesEstado)): ?>
        <div class="form-section" style="border:1px solid var(--border-color,#ddd);padding:16px;border-radius:8px;margin-bottom:16px;">
            <h4 style="margin:0 0 12px;"><i class="bi bi-arrow-repeat"></i> Cambiar estado</h4>
            <form action="ordenes_taller.php?accion=estado&id=<?php echo (int) $orden['id_orden_taller']; ?>" method="POST" class="admin-form">
                <div class="form-row">
                    <div class="form-group">
                        <label for="estado_nuevo">Nuevo estado:</label>
                        <select class="form-input" name="estado" id="estado_nuevo" required>
                            <option value="">-- Seleccione --</option>
                            <?php foreach ($opcionesEstado as $valor => $etiqueta): ?>
                                <option value="<?php echo htmlspecialchars($valor); ?>"><?php echo htmlspecialchars($etiqueta); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="flex:2;">
                        <label for="nota_estado">Nota (opcional):</label>
                        <input type="text" class="form-input" name="nota" id="nota_estado" maxlength="500" placeholder="Comentario del cambio de estado">
                    </div>
                </div>
                <button type="submit" class="btn-action-primary">
                    <i class="bi bi-check-lg"></i> Actualizar estado
                </button>
            </form>
        </div>
    <?php endif; ?>

    <?php if ($puedeAbonar): ?>
        <div class="form-section" style="border:1px dashed var(--border-color,#ddd);padding:16px;border-radius:8px;margin-bottom:16px;">
            <h4 style="margin:0 0 12px;"><i class="bi bi-wallet2"></i> Registrar abono</h4>
            <form action="ordenes_taller.php?accion=abono&id=<?php echo (int) $orden['id_orden_taller']; ?>" method="POST" class="admin-form">
                <div class="form-row">
                    <div class="form-group">
                        <label for="monto_abono">Monto:</label>
                        <input type="number" class="form-input" name="monto" id="monto_abono" step="0.01" min="0.01"
                               max="<?php echo htmlspecialchars(number_format($saldoPendiente, 2, '.', '')); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="id_forma_pago_abono">Forma de pago:</label>
                        <select class="form-input" name="id_forma_pago_FK" id="id_forma_pago_abono" required>
                            <option value="">-- Seleccione --</option>
                            <?php foreach (($catalogos['formasPago'] ?? []) as $fp): ?>
                                <option value="<?php echo (int) $fp['id_forma_pago']; ?>" <?php echo !empty($idFormaPagoDefault) && (int) $idFormaPagoDefault === (int) $fp['id_forma_pago'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars((string) $fp['forma_pago']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn-action-primary">
                    <i class="bi bi-cash-coin"></i> Registrar abono
                </button>
            </form>
        </div>
    <?php endif; ?>

    <h4><i class="bi bi-receipt"></i> Historial de cobros</h4>
    <?php if (!empty($pagos)): ?>
        <div class="admin-table-wrapper">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Monto</th>
                        <th>Forma de pago</th>
                        <th>Referencia</th>
                        <th>Usuario</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pagos as $pago): ?>
                        <tr>
                            <td><?php echo htmlspecialchars((string) $pago['fecha_registro']); ?></td>
                            <td>$<?php echo htmlspecialchars(number_format((float) $pago['monto'], 2)); ?></td>
                            <td><?php echo htmlspecialchars((string) $pago['forma_pago']); ?></td>
                            <td><?php echo htmlspecialchars((string) ($pago['referencia'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars((string) ($pago['usuario_nombre'] ?? '')); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="alert-message info"><p><i class="bi bi-info-circle"></i> Sin cobros registrados.</p></div>
    <?php endif; ?>

    <h4 style="margin-top:20px;"><i class="bi bi-clock-history"></i> Historial de estados</h4>
    <?php if (!empty($historial)): ?>
        <div class="admin-table-wrapper">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Estado</th>
                        <th>Nota</th>
                        <th>Usuario</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($historial as $h): ?>
                        <tr>
                            <td><?php echo htmlspecialchars((string) $h['fecha_registro']); ?></td>
                            <td><?php echo htmlspecialchars($app->etiquetaEstado((string) $h['estado'])); ?></td>
                            <td><?php echo htmlspecialchars((string) ($h['nota'] ?? '—')); ?></td>
                            <td><?php echo htmlspecialchars((string) ($h['usuario_nombre'] ?? '')); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="alert-message info"><p><i class="bi bi-info-circle"></i> Sin historial.</p></div>
    <?php endif; ?>
</div>
