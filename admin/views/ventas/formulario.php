<?php
$esEdicion = isset($venta) && !empty($venta);
$titulo = $esEdicion ? 'Editar Venta' : 'Nueva Venta';
$accionForm = $esEdicion
    ? 'ventas.php?accion=actualizar&id=' . urlencode((string) $venta['id_venta'])
    : 'ventas.php?accion=crear';

$id_cliente_FK = $_POST['id_cliente_FK'] ?? ($esEdicion ? ($venta['id_cliente_FK'] ?? '') : '');
$id_empleado_FK = $_POST['id_empleado_FK'] ?? ($esEdicion ? ($venta['id_empleado_FK'] ?? '') : '');
$id_impuesto_FK = $_POST['id_impuesto_FK'] ?? ($esEdicion ? ($venta['id_impuesto_FK'] ?? '') : (!empty($idImpuestoDefault) ? (string) (int) $idImpuestoDefault : ''));
$id_apartado_FK = $_POST['id_apartado_FK'] ?? ($esEdicion ? ($venta['id_apartado_FK'] ?? '') : '');
$total = $_POST['total'] ?? ($esEdicion ? ($venta['total'] ?? '') : '');
$impuesto_porcentaje = $_POST['impuesto_porcentaje'] ?? ($esEdicion ? ($venta['impuesto_porcentaje'] ?? '') : '');
$impuesto_monto = $_POST['impuesto_monto'] ?? ($esEdicion ? ($venta['impuesto_monto'] ?? '') : '');
$estado = $_POST['estado'] ?? ($esEdicion ? ($venta['estado'] ?? 'completada') : 'completada');
?>

<div class="form-section">
    <h3><i class="bi <?php echo $esEdicion ? 'bi-pencil-square' : 'bi-plus-circle'; ?>"></i> <?php echo htmlspecialchars($titulo); ?></h3>

    <?php if (isset($mensaje) && !empty($mensaje)): ?>
        <div class="alert-message info"><p><i class="bi bi-info-circle"></i> <?php echo htmlspecialchars($mensaje); ?></p></div>
    <?php endif; ?>

    <?php if (!$esEdicion || !empty($venta)): ?>
        <form action="<?php echo htmlspecialchars($accionForm); ?>" method="POST" class="admin-form">
            <div class="form-row">
                <div class="form-group">
                    <label for="id_cliente_FK"><i class="bi bi-person"></i> Cliente:</label>
                    <select class="form-input" name="id_cliente_FK" id="id_cliente_FK" required>
                        <?php
                        $clientes = $catalogos['clientes'] ?? [];
                        $selectedId = $id_cliente_FK;
                        $emptyLabel = '-- Selecciona cliente --';
                        $emptyValue = '';
                        require __DIR__ . '/../partials/cliente_select_options.php';
                        ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="id_empleado_FK"><i class="bi bi-person-badge"></i> Empleado:</label>
                    <select class="form-input" name="id_empleado_FK" id="id_empleado_FK" required>
                        <option value="">-- Selecciona empleado --</option>
                        <?php foreach (($catalogos['empleados'] ?? []) as $empleado): ?>
                            <option value="<?php echo (int) $empleado['id_empleado']; ?>" <?php echo ((string) $id_empleado_FK === (string) $empleado['id_empleado']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars((string) $empleado['nombre_completo']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="id_impuesto_FK"><i class="bi bi-percent"></i> Impuesto:</label>
                    <select class="form-input" name="id_impuesto_FK" id="id_impuesto_FK" required>
                        <option value="">-- Selecciona impuesto --</option>
                        <?php foreach (($catalogos['impuestos'] ?? []) as $impuesto): ?>
                            <option value="<?php echo (int) $impuesto['id_impuesto']; ?>" <?php echo ((string) $id_impuesto_FK === (string) $impuesto['id_impuesto']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars((string) $impuesto['tipo_impuesto']); ?> (<?php echo htmlspecialchars((string) $impuesto['porcentaje']); ?>%)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="id_apartado_FK"><i class="bi bi-receipt"></i> Apartado (opcional):</label>
                    <select class="form-input" name="id_apartado_FK" id="id_apartado_FK">
                        <option value="">-- Sin apartado --</option>
                        <?php foreach (($catalogos['apartados'] ?? []) as $apartado): ?>
                            <option value="<?php echo (int) $apartado['id_apartado']; ?>" <?php echo ((string) $id_apartado_FK === (string) $apartado['id_apartado']) ? 'selected' : ''; ?>>
                                #<?php echo (int) $apartado['id_apartado']; ?> - <?php echo htmlspecialchars((string) $apartado['cliente_nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="total"><i class="bi bi-currency-dollar"></i> Total:</label>
                    <input type="number" class="form-input" name="total" id="total" step="0.01" min="0.01" value="<?php echo htmlspecialchars((string) $total); ?>" required autofocus>
                </div>

                <div class="form-group">
                    <label for="impuesto_porcentaje"><i class="bi bi-percent"></i> Porcentaje de impuesto:</label>
                    <input type="number" class="form-input" name="impuesto_porcentaje" id="impuesto_porcentaje" step="0.01" min="0" max="100" value="<?php echo htmlspecialchars((string) $impuesto_porcentaje); ?>" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="impuesto_monto"><i class="bi bi-cash"></i> Monto de impuesto:</label>
                    <input type="number" class="form-input" name="impuesto_monto" id="impuesto_monto" step="0.01" min="0" value="<?php echo htmlspecialchars((string) $impuesto_monto); ?>" required>
                </div>

                <div class="form-group">
                    <label for="estado"><i class="bi bi-flag"></i> Estado:</label>
                    <select class="form-input" name="estado" id="estado" required>
                        <option value="completada" <?php echo $estado === 'completada' ? 'selected' : ''; ?>>Completada</option>
                        <option value="cancelada" <?php echo $estado === 'cancelada' ? 'selected' : ''; ?>>Cancelada</option>
                        <option value="devuelta" <?php echo $estado === 'devuelta' ? 'selected' : ''; ?>>Devuelta</option>
                    </select>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-action-primary"><i class="bi bi-check-lg"></i> Guardar</button>
                <a href="ventas.php?accion=leer" class="btn-action-secondary"><i class="bi bi-x-lg"></i> Cancelar</a>
            </div>
        </form>
    <?php else: ?>
        <div class="alert-message error">
            <p><i class="bi bi-exclamation-triangle"></i> No se encontró la venta. <a href="ventas.php?accion=leer">Volver al listado</a></p>
        </div>
    <?php endif; ?>
</div>
<script src="js/fk-autocomplete.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (!window.JoyeriaFkAutocomplete) return;
    JoyeriaFkAutocomplete.initSelectAutocomplete({ selectId: 'id_cliente_FK', allowEmpty: false, placeholder: 'Nombre, apellido, correo o teléfono...' });
    JoyeriaFkAutocomplete.initSelectAutocomplete({ selectId: 'id_empleado_FK', allowEmpty: false, placeholder: 'Buscar empleado...' });
    JoyeriaFkAutocomplete.initSelectAutocomplete({ selectId: 'id_impuesto_FK', allowEmpty: false, placeholder: 'Buscar impuesto...' });
    JoyeriaFkAutocomplete.initSelectAutocomplete({ selectId: 'id_apartado_FK', allowEmpty: true, placeholder: 'Buscar apartado...' });
});
</script>
