<?php
$esEdicion = isset($notificacion) && !empty($notificacion);
$titulo = $esEdicion ? 'Editar Notificación' : 'Nueva Notificación';
$accionForm = $esEdicion
    ? 'notificaciones.php?accion=actualizar&id=' . urlencode((string) $notificacion['id_notificacion'])
    : 'notificaciones.php?accion=crear';

$mensajeNotificacion = $_POST['mensaje'] ?? ($esEdicion ? ($notificacion['mensaje'] ?? '') : '');
$leida = $_POST['leida'] ?? ($esEdicion ? (string) ((int) ($notificacion['leida'] ?? 0)) : '0');
?>

<div class="form-section">
    <h3>
        <i class="bi <?php echo $esEdicion ? 'bi-pencil-square' : 'bi-plus-circle'; ?>"></i>
        <?php echo htmlspecialchars($titulo); ?>
    </h3>

    <?php if (isset($mensaje) && !empty($mensaje)): ?>
        <div class="alert-message info">
            <p><i class="bi bi-info-circle"></i> <?php echo htmlspecialchars($mensaje); ?></p>
        </div>
    <?php endif; ?>

    <?php if (!$esEdicion || !empty($notificacion)): ?>
        <form action="<?php echo htmlspecialchars($accionForm); ?>" method="POST" class="admin-form">
            <div class="form-group">
                <label for="mensaje"><i class="bi bi-chat-left-text"></i> Mensaje:</label>
                <textarea class="form-input" name="mensaje" id="mensaje" rows="4" required><?php echo htmlspecialchars($mensajeNotificacion); ?></textarea>
            </div>

            <div class="form-group">
                <label for="leida"><i class="bi bi-check2-square"></i> Estado:</label>
                <select class="form-input" name="leida" id="leida">
                    <option value="0" <?php echo $leida === '0' ? 'selected' : ''; ?>>No leída</option>
                    <option value="1" <?php echo $leida === '1' ? 'selected' : ''; ?>>Leída</option>
                </select>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-action-primary">
                    <i class="bi bi-check-lg"></i> <?php echo $esEdicion ? 'Guardar Cambios' : 'Guardar'; ?>
                </button>
                <a href="notificaciones.php?accion=leer" class="btn-action-secondary">
                    <i class="bi bi-x-lg"></i> Cancelar
                </a>
            </div>
        </form>
    <?php else: ?>
        <div class="alert-message error">
            <p><i class="bi bi-exclamation-triangle"></i> No se encontró la notificación. <a href="notificaciones.php?accion=leer">Volver al listado</a></p>
        </div>
    <?php endif; ?>
</div>
