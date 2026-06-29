<div class="admin-modules">

    <?php if (isset($mensaje) && !empty($mensaje)): ?>
        <div class="alert-message info">
            <p><i class="bi bi-info-circle"></i> <?php echo htmlspecialchars($mensaje); ?></p>
        </div>
    <?php endif; ?>

    <div class="form-section admin-assign-panel">
        <h3><i class="bi bi-person-check"></i> Asignar Rol a Usuario</h3>
        <form action="usuario_rol.php?accion=asignar" method="POST" class="admin-form">
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
                    <small class="form-hint"><i class="bi bi-info-circle"></i> Solo se listan usuarios que son empleados activos.</small>
                </div>

                <div class="form-group">
                    <label for="id_rol_FK">Rol:</label>
                    <select class="form-input" name="id_rol_FK" id="id_rol_FK" required>
                        <option value="">-- Selecciona un rol --</option>
                        <?php foreach (($roles ?? []) as $rol): ?>
                            <option value="<?php echo (int) $rol['id_rol']; ?>">
                                <?php echo htmlspecialchars($rol['nombre_rol']); ?>
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
    $listSearchAction = 'usuario_rol.php';
    $listSearchHidden = ['accion' => 'leer'];
    $listSearchPlaceholder = 'Buscar por usuario, correo o rol...';
    require __DIR__ . '/../partials/list_search_bar.php';
    ?>
    </div>

    <?php if (!empty($asignaciones)): ?>
        <div class="admin-table-wrapper">
            <table id="tabla-usuario-rol" class="admin-table">
                <thead>
                    <tr>
                        <th class="col-usuario">Usuario</th>
                        <th class="col-correo">Correo</th>
                        <th class="related-col">Rol</th>
                        <th class="actions-col">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($asignaciones as $asignacion): ?>
                        <tr>
                            <td class="col-usuario"><?php echo htmlspecialchars($asignacion['nombre_usuario']); ?></td>
                            <td class="col-correo"><?php echo htmlspecialchars($asignacion['correo']); ?></td>
                            <td><?php echo htmlspecialchars($asignacion['nombre_rol']); ?></td>
                            <td class="actions-cell">
                                <a href="usuario_rol.php?accion=revocar&id_usuario=<?php echo (int) $asignacion['id_usuario_FK']; ?>&id_rol=<?php echo (int) $asignacion['id_rol_FK']; ?>"
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
            <p><i class="bi bi-info-circle"></i> No hay asignaciones de usuario-rol registradas.</p>
        </div>
    <?php endif; ?>
</div>

<script src="js/fk-autocomplete.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (!window.JoyeriaFkAutocomplete) return;
    JoyeriaFkAutocomplete.initSelectAutocomplete({ selectId: 'id_usuario_FK', allowEmpty: false, placeholder: 'Buscar empleado...' });
    JoyeriaFkAutocomplete.initSelectAutocomplete({ selectId: 'id_rol_FK', allowEmpty: false, placeholder: 'Buscar rol...' });
});
</script>
