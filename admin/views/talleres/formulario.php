<?php
$esEdicion = isset($taller) && !empty($taller);
$titulo = $esEdicion ? 'Editar Taller' : 'Nuevo Taller';
$accionForm = $esEdicion
    ? 'talleres.php?accion=actualizar&id=' . urlencode((string) $taller['id_taller'])
    : 'talleres.php?accion=crear';

$nombre = $_POST['nombre'] ?? ($esEdicion ? ($taller['nombre'] ?? '') : '');
$contacto = $_POST['contacto'] ?? ($esEdicion ? ($taller['contacto'] ?? '') : '');
$telefono = $_POST['telefono'] ?? ($esEdicion ? ($taller['telefono'] ?? '') : '');
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

    <?php if(!$esEdicion || !empty($taller)): ?>
        <form action="<?php echo htmlspecialchars($accionForm); ?>" method="POST" class="admin-form">
            <div class="form-row">
                <div class="form-group">
                    <label for="nombre"><i class="bi bi-tools"></i> Nombre del Taller:</label>
                    <input type="text" class="form-input" name="nombre" id="nombre" maxlength="100"
                           value="<?php echo htmlspecialchars($nombre); ?>" placeholder="Ej. Taller Orfebre Centro" required autofocus>
                </div>

                <div class="form-group">
                    <label for="contacto"><i class="bi bi-person"></i> Contacto:</label>
                    <input type="text" class="form-input" name="contacto" id="contacto" maxlength="100"
                           value="<?php echo htmlspecialchars($contacto); ?>" placeholder="Ej. Juan Perez">
                </div>

                <div class="form-group">
                    <label for="telefono"><i class="bi bi-telephone"></i> Teléfono:</label>
                    <input type="text" class="form-input" name="telefono" id="telefono" maxlength="20"
                           value="<?php echo htmlspecialchars($telefono); ?>" placeholder="Ej. 4611234567">
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-action-primary">
                    <i class="bi bi-check-lg"></i> <?php echo $esEdicion ? 'Guardar Cambios' : 'Guardar'; ?>
                </button>
                <a href="talleres.php?accion=leer" class="btn-action-secondary">
                    <i class="bi bi-x-lg"></i> Cancelar
                </a>
            </div>
        </form>
    <?php else: ?>
        <div class="alert-message error">
            <p><i class="bi bi-exclamation-triangle"></i> No se encontró el taller. <a href="talleres.php?accion=leer">Volver al listado</a></p>
        </div>
    <?php endif; ?>
</div>
