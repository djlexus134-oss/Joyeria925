<div class="admin-modules">

    <?php if (isset($mensaje) && !empty($mensaje)): ?>
        <div class="alert-message info">
            <p><i class="bi bi-info-circle"></i> <?php echo htmlspecialchars($mensaje); ?></p>
        </div>
    <?php endif; ?>

    <div class="form-section" style="margin-bottom: 20px;">
        <h3><i class="bi bi-shield-check"></i> Asignar Permiso a Rol</h3>
        <form action="rol_permiso.php?accion=asignar" method="POST" class="admin-form">
            <div class="form-row">
                <div class="form-group">
                    <label for="id_rol_FK">Rol:</label>
                    <select class="form-input" name="id_rol_FK" id="id_rol_FK" required>
                        <option value="">-- Selecciona un rol --</option>
                        <?php foreach (($roles ?? []) as $rol): ?>
                            <option value="<?php echo (int) $rol['id_rol']; ?>">
                                <?php echo htmlspecialchars((string) $rol['nombre_rol']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="id_permiso_FK">Permiso:</label>
                    <select class="form-input" name="id_permiso_FK" id="id_permiso_FK" required>
                        <option value="">-- Selecciona un permiso --</option>
                        <?php foreach (($permisos ?? []) as $permiso): ?>
                            <option value="<?php echo (int) $permiso['id_permiso']; ?>">
                                <?php echo htmlspecialchars((string) $permiso['nombre_permiso']); ?>
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

    <?php
    $listSearchAction = 'rol_permiso.php';
    $listSearchHidden = ['accion' => 'leer'];
    $listSearchPlaceholder = 'Buscar por rol o permiso...';
    require __DIR__ . '/../partials/list_search_bar.php';
    ?>

    <?php if (!empty($asignaciones)): ?>
        <div class="admin-table-wrapper">
            <table id="tabla-rol-permiso" class="admin-table">
                <thead>
                    <tr>
                        <th class="related-col">Rol</th>
                        <th class="related-col">Permiso</th>
                        <th class="related-col">Descripción</th>
                        <th class="actions-col">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($asignaciones as $asignacion): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($asignacion['nombre_rol']); ?></td>
                            <td><?php echo htmlspecialchars($asignacion['nombre_permiso']); ?></td>
                            <td><?php echo htmlspecialchars($asignacion['descripcion'] ?? 'N/A'); ?></td>
                            <td class="actions-cell">
                                <a href="rol_permiso.php?accion=revocar&id_rol=<?php echo (int) $asignacion['id_rol_FK']; ?>&id_permiso=<?php echo (int) $asignacion['id_permiso_FK']; ?>"
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
            <p><i class="bi bi-info-circle"></i> No hay asignaciones de rol-permiso registradas.</p>
        </div>
    <?php endif; ?>
</div>

<script src="js/fk-autocomplete.js"></script>
<script>
    (function () {
        if (!window.JoyeriaFkAutocomplete) {
            return;
        }
        JoyeriaFkAutocomplete.initSelectAutocomplete({ selectId: 'id_rol_FK', allowEmpty: false, placeholder: 'Buscar rol...' });
        JoyeriaFkAutocomplete.initSelectAutocomplete({ selectId: 'id_permiso_FK', allowEmpty: false, placeholder: 'Buscar permiso...' });
    })();
</script>
