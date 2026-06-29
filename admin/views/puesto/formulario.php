<?php
$esEdicion = isset($puesto) && !empty($puesto);
$titulo = $esEdicion ? 'Editar Puesto' : 'Nuevo Puesto';
$accionForm = $esEdicion
    ? 'puesto.php?accion=actualizar&id=' . urlencode((string) $puesto['id_puesto'])
    : 'puesto.php?accion=crear';
$nombrePuesto = $esEdicion ? $puesto['nombre_puesto'] : '';
$descripcionPuesto = $esEdicion ? $puesto['descripcion'] : '';
?>

<div class="form-section">
    <h3>
        <i class="bi <?php echo $esEdicion ? 'bi-pencil-square' : 'bi-plus-circle'; ?>"></i>
        <?php echo htmlspecialchars($titulo); ?>
    </h3>

    <?php if (!$esEdicion || !empty($puesto)): ?>
        <form action="<?php echo htmlspecialchars($accionForm); ?>" method="POST" class="admin-form">
            <div class="form-group">
                <label for="nombre_puesto">
                    <i class="bi bi-diagram-3"></i> Nombre del Puesto:
                </label>
                <input type="text"
                    class="form-input"
                    name="nombre_puesto"
                    id="nombre_puesto"
                    maxlength="50"
                    value="<?php echo htmlspecialchars($nombrePuesto); ?>"
                    placeholder="Ej. Vendedor, Administrador, Gerente..."
                    required
                    autofocus>
                <?php if ($esEdicion): ?>
                    <small class="form-hint"><i class="bi bi-info-circle"></i> Maximo 50 caracteres. ID: #<?php echo str_pad(htmlspecialchars($puesto['id_puesto']), 3, '0', STR_PAD_LEFT); ?></small>
                <?php else: ?>
                    <small class="form-hint"><i class="bi bi-info-circle"></i> Maximo 50 caracteres. Debe coincidir con la funcionalidad general que representa el puesto.</small>
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label for="descripcion">
                    <i class="bi bi-diagram-3"></i> Descripción:
                </label>
                <input type="text"
                    class="form-input"
                    name="descripcion"
                    id="descripcion"
                    maxlength="255"
                    value="<?php echo htmlspecialchars($descripcionPuesto); ?>"
                    placeholder="Ingrese descripción del puesto (opcional)">
                <small class="form-hint"><i class="bi bi-info-circle"></i> Describe claramente lo que autoriza este puesto.</small>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-action-primary">
                    <i class="bi bi-check-lg"></i> <?php echo $esEdicion ? 'Guardar Cambios' : 'Guardar Puesto'; ?>
                </button>
                <a href="puesto.php?accion=leer" class="btn-action-secondary">
                    <i class="bi bi-x-lg"></i> Cancelar
                </a>
            </div>
        </form>
    <?php else: ?>
        <div class="alert-message error">
            <p><i class="bi bi-exclamation-triangle"></i> No se encontró el puesto. <a href="puesto.php?accion=leer">Volver al listado</a></p>
        </div>
    <?php endif; ?>
</div>