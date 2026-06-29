<div class="admin-modules">

    <?php if (isset($mensaje) && !empty($mensaje)): ?>
        <div class="alert-message info">
            <p><i class="bi bi-info-circle"></i> <?php echo htmlspecialchars($mensaje); ?></p>
        </div>
    <?php endif; ?>

    <div class="form-section admin-assign-panel">
        <h3><i class="bi bi-bell"></i> Asignar Notificacion a Usuario</h3>
        <form action="usuario_notificacion.php?accion=asignar" method="POST" class="admin-form">
            <div class="form-row">
                <div class="form-group">
                    <label for="id_usuario_FK">Usuario:</label>
                    <select class="form-input" name="id_usuario_FK" id="id_usuario_FK" required>
                        <option value="">-- Selecciona un usuario --</option>
                        <?php foreach (($usuarios ?? []) as $usuario): ?>
                            <option value="<?php echo (int) $usuario['id_usuario']; ?>">
                                <?php echo htmlspecialchars($usuario['nombre_completo'] . ' (' . $usuario['correo'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="id_notificacion_FK">Notificacion:</label>
                    <select class="form-input" name="id_notificacion_FK" id="id_notificacion_FK" required>
                        <option value="">-- Selecciona una notificacion --</option>
                        <?php foreach (($notificaciones ?? []) as $notificacion): ?>
                            <?php $mensajeCorto = mb_strlen((string) $notificacion['mensaje']) > 80
                                ? mb_substr((string) $notificacion['mensaje'], 0, 80) . '...'
                                : (string) $notificacion['mensaje']; ?>
                            <option value="<?php echo (int) $notificacion['id_notificacion']; ?>">
                                <?php echo htmlspecialchars('#' . $notificacion['id_notificacion'] . ' - ' . $mensajeCorto); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-action-primary">
                    <i class="bi bi-plus-lg"></i> Asignar
                </button>
            </div>
        </form>
    </div>

    <div class="admin-list-toolbar">
    <?php
    $listSearchAction = 'usuario_notificacion.php';
    $listSearchHidden = ['accion' => 'leer'];
    $listSearchPlaceholder = 'Buscar por usuario, correo o mensaje...';
    require __DIR__ . '/../partials/list_search_bar.php';
    ?>
    </div>

    <?php if (!empty($asignaciones)): ?>
        <div class="admin-table-wrapper">
            <table id="tabla-usuario-notificacion" class="admin-table">
                <thead>
                    <tr>
                        <th class="col-usuario">Usuario</th>
                        <th class="col-correo">Correo</th>
                        <th class="col-mensaje">Notificacion</th>
                        <th class="col-flag">Leída</th>
                        <th class="col-fecha">Fecha</th>
                        <th class="actions-col">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($asignaciones as $asignacion): ?>
                        <tr>
                            <td class="col-usuario"><?php echo htmlspecialchars($asignacion['nombre_usuario']); ?></td>
                            <td class="col-correo"><?php echo htmlspecialchars($asignacion['correo']); ?></td>
                            <td class="col-mensaje"><?php echo htmlspecialchars($asignacion['mensaje']); ?></td>
                            <td class="col-flag"><?php echo ((int) $asignacion['leida'] === 1) ? 'Si' : 'No'; ?></td>
                            <td class="col-fecha"><?php echo htmlspecialchars((string) $asignacion['fecha_envio']); ?></td>
                            <td class="actions-cell">
                                <a href="usuario_notificacion.php?accion=desvincular&id_usuario=<?php echo (int) $asignacion['id_usuario_FK']; ?>&id_notificacion=<?php echo (int) $asignacion['id_notificacion_FK']; ?>"
                                   class="btn-action-danger"
                                   onclick="return confirm('Estas seguro de eliminar esta asignacion?');">
                                    <i class="bi bi-trash"></i> Eliminar
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="alert-message info">
            <p><i class="bi bi-info-circle"></i> No hay asignaciones de usuario-notificacion registradas.</p>
        </div>
    <?php endif; ?>
</div>
<script src="js/fk-autocomplete.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (!window.JoyeriaFkAutocomplete) return;
    JoyeriaFkAutocomplete.initSelectAutocomplete({ selectId: 'id_usuario_FK', allowEmpty: false, placeholder: 'Buscar usuario...' });
    JoyeriaFkAutocomplete.initSelectAutocomplete({ selectId: 'id_notificacion_FK', allowEmpty: false, placeholder: 'Buscar notificacion...' });
});
</script>
