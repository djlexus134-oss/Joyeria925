<?php
$esEdicion = isset($permiso) && !empty($permiso);
$titulo = $esEdicion ? 'Editar Permiso' : 'Nuevo Permiso';
$accionForm = $esEdicion
    ? 'permiso.php?accion=actualizar&id=' . urlencode((string) $permiso['id_permiso'])
    : 'permiso.php?accion=crear';
$nombrePermiso = $esEdicion ? $permiso['nombre_permiso'] : '';
$descripcionPermiso = $esEdicion ? $permiso['descripcion'] : '';
?>

<div class="form-section">
    <h3>
        <i class="bi <?php echo $esEdicion ? 'bi-pencil-square' : 'bi-plus-circle'; ?>"></i>
        <?php echo htmlspecialchars($titulo); ?>
    </h3>

    <?php if (!$esEdicion || !empty($permiso)): ?>
        <form action="<?php echo htmlspecialchars($accionForm); ?>" method="POST" class="admin-form">
            <div class="form-group">
                <label for="nombre_permiso">
                    <i class="bi bi-diagram-3"></i> Nombre del Permiso:
                </label>
                <input type="text"
                    class="form-input"
                    name="nombre_permiso"
                    id="nombre_permiso"
                    maxlength="50"
                    value="<?php echo htmlspecialchars($nombrePermiso); ?>"
                    placeholder="Ej. crear_venta, editar_cliente, leer_apartados..."
                    required
                    autofocus>
                <?php if ($esEdicion): ?>
                    <small class="form-hint"><i class="bi bi-info-circle"></i> Maximo 50 caracteres. ID: #<?php echo str_pad(htmlspecialchars($permiso['id_permiso']), 3, '0', STR_PAD_LEFT); ?></small>
                <?php else: ?>
                    <small class="form-hint"><i class="bi bi-info-circle"></i> Maximo 50 caracteres. Debe coincidir con la accion que representa el permiso.</small>
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
                    value="<?php echo htmlspecialchars($descripcionPermiso); ?>"
                    placeholder="Ingrese descripción del permiso (opcional)">
                <small class="form-hint"><i class="bi bi-info-circle"></i> Describe claramente lo que autoriza este permiso.</small>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-action-primary">
                    <i class="bi bi-check-lg"></i> <?php echo $esEdicion ? 'Guardar Cambios' : 'Guardar Permiso'; ?>
                </button>
                <a href="permiso.php?accion=leer" class="btn-action-secondary">
                    <i class="bi bi-x-lg"></i> Cancelar
                </a>
            </div>
        </form>
    <?php else: ?>
        <div class="alert-message error">
            <p><i class="bi bi-exclamation-triangle"></i> No se encontró el permiso. <a href="permiso.php?accion=leer">Volver al listado</a></p>
        </div>
    <?php endif; ?>
</div>