<?php
$esEdicion = isset($rol) && !empty($rol);
$titulo = $esEdicion ? 'Editar Rol' : 'Nuevo Rol';
$accionForm = $esEdicion
    ? 'rol.php?accion=actualizar&id=' . urlencode((string) $rol['id_rol'])
    : 'rol.php?accion=crear';
$nombreRol = $esEdicion ? $rol['nombre_rol'] : '';
$descripcionRol = $esEdicion ? $rol['descripcion'] : '';
?>

<div class="form-section">
    <h3>
        <i class="bi <?php echo $esEdicion ? 'bi-pencil-square' : 'bi-plus-circle'; ?>"></i>
        <?php echo htmlspecialchars($titulo); ?>
    </h3>

    <?php if (!$esEdicion || !empty($rol)): ?>
        <form action="<?php echo htmlspecialchars($accionForm); ?>" method="POST" class="admin-form">
            <div class="form-group">
                <label for="nombre_rol">
                    <i class="bi bi-diagram-3"></i> Nombre del Rol:
                </label>
                <input type="text"
                    class="form-input"
                    name="nombre_rol"
                    id="nombre_rol"
                    maxlength="50"
                    value="<?php echo htmlspecialchars($nombreRol); ?>"
                    placeholder="Ej. Vendedor, Cliente, Administrador..."
                    required
                    autofocus>
                <?php if ($esEdicion): ?>
                    <small class="form-hint"><i class="bi bi-info-circle"></i> Maximo 50 caracteres. ID: #<?php echo str_pad(htmlspecialchars($rol['id_rol']), 3, '0', STR_PAD_LEFT); ?></small>
                <?php else: ?>
                    <small class="form-hint"><i class="bi bi-info-circle"></i> Maximo 50 caracteres. Debe coincidir con la funcionalidad general que representa el rol.</small>
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
                    value="<?php echo htmlspecialchars($descripcionRol); ?>"
                    placeholder="Ingrese descripción del rol (opcional)">
                <small class="form-hint"><i class="bi bi-info-circle"></i> Describe claramente lo que autoriza este rol.</small>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-action-primary">
                    <i class="bi bi-check-lg"></i> <?php echo $esEdicion ? 'Guardar Cambios' : 'Guardar Rol'; ?>
                </button>
                <a href="rol.php?accion=leer" class="btn-action-secondary">
                    <i class="bi bi-x-lg"></i> Cancelar
                </a>
            </div>
        </form>
    <?php else: ?>
        <div class="alert-message error">
            <p><i class="bi bi-exclamation-triangle"></i> No se encontró el rol. <a href="rol.php?accion=leer">Volver al listado</a></p>
        </div>
    <?php endif; ?>
</div>