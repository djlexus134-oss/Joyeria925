<?php
$esEdicion = isset($formaPago) && !empty($formaPago);
$titulo = $esEdicion ? 'Editar Forma de Pago' : 'Nueva Forma de Pago';
$accionForm = $esEdicion
    ? 'forma_pago.php?accion=actualizar&id=' . urlencode((string) $formaPago['id_forma_pago'])
    : 'forma_pago.php?accion=crear';
$nombre = $_POST['forma_pago'] ?? ($esEdicion ? ($formaPago['forma_pago'] ?? '') : '');
$mostrarEsEfectivoCaja = $mostrarEsEfectivoCaja ?? false;
$mostrarClaveSat = $mostrarClaveSat ?? false;
$claveSat = $_POST['clave_sat'] ?? ($esEdicion ? ($formaPago['clave_sat'] ?? '') : '');
$esEfectivoVal = isset($_POST['es_efectivo']) ? (string) $_POST['es_efectivo'] : null;
if ($esEfectivoVal === null) {
    $esEfectivoChecked = $esEdicion && !empty($formaPago['es_efectivo']);
} else {
    $esEfectivoChecked = $esEfectivoVal === '1';
}
?>

<div class="form-section">
    <h3>
        <i class="bi <?php echo $esEdicion ? 'bi-pencil-square' : 'bi-plus-circle'; ?>"></i>
        <?php echo htmlspecialchars($titulo); ?>
    </h3>

    <?php if(isset($mensaje) && !empty($mensaje)): ?>
        <div class="alert-message info">
            <p><i class="bi bi-info-circle"></i> <?php echo htmlspecialchars($mensaje); ?></p>
        </div>
    <?php endif; ?>

    <?php if(!$esEdicion || !empty($formaPago)): ?>
        <form action="<?php echo htmlspecialchars($accionForm); ?>" method="POST" class="admin-form">
            <div class="form-group">
                <label for="forma_pago">
                    <i class="bi bi-credit-card"></i> Forma de Pago:
                </label>
                <input type="text" class="form-input" name="forma_pago" id="forma_pago" maxlength="40"
                       value="<?php echo htmlspecialchars($nombre); ?>" placeholder="Ej. Efectivo, Tarjeta, Transferencia" required autofocus>
                <small class="form-hint"><i class="bi bi-info-circle"></i> Maximo 40 caracteres.</small>
            </div>

            <?php if (!empty($mostrarClaveSat)): ?>
                <div class="form-group">
                    <label for="clave_sat">
                        <i class="bi bi-receipt-cutoff"></i> Clave SAT (c_FormaPago):
                    </label>
                    <input type="text" class="form-input" name="clave_sat" id="clave_sat" maxlength="2"
                           value="<?php echo htmlspecialchars((string) $claveSat); ?>" placeholder="01 efectivo, 04 tarjeta, 03 transferencia">
                    <small class="form-hint">Requerida para facturacion CFDI. Consulte el catalogo SAT c_FormaPago.</small>
                </div>
            <?php endif; ?>

            <?php if (!empty($mostrarEsEfectivoCaja)): ?>
                <div class="form-group">
                    <input type="hidden" name="es_efectivo" value="0">
                    <label class="form-group" style="display:flex; align-items:center; gap:0.5rem;">
                        <input type="checkbox" name="es_efectivo" id="es_efectivo" value="1" <?php echo $esEfectivoChecked ? 'checked' : ''; ?>>
                        <span><i class="bi bi-safe"></i> Cuenta como efectivo fisico en caja (cierre de caja)</span>
                    </label>
                </div>
            <?php endif; ?>

            <div class="form-actions">
                <button type="submit" class="btn-action-primary">
                    <i class="bi bi-check-lg"></i> <?php echo $esEdicion ? 'Guardar Cambios' : 'Guardar'; ?>
                </button>
                <a href="forma_pago.php?accion=leer" class="btn-action-secondary">
                    <i class="bi bi-x-lg"></i> Cancelar
                </a>
            </div>
        </form>
    <?php else: ?>
        <div class="alert-message error">
            <p><i class="bi bi-exclamation-triangle"></i> No se encontró la forma de pago. <a href="forma_pago.php?accion=leer">Volver al listado</a></p>
        </div>
    <?php endif; ?>
</div>
