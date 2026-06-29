<div class="admin-modules">

    <?php if (isset($mensaje) && trim(!empty($mensaje))): ?>
        <div class="alert-message success">
            <p><i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($mensaje); ?></p>
        </div>
    <?php endif; ?>

    <div class="module-actions-row">
    <div class="module-actions">
        <a href="rol.php?accion=crear" class="btn-action-primary">
            <i class="bi bi-plus-lg"></i> Nuevo Rol
        </a>
    </div>
    <?php
    $listSearchAction = 'rol.php';
    $listSearchHidden = ['accion' => 'leer'];
    $listSearchPlaceholder = 'Buscar por nombre o descripción...';
    require __DIR__ . '/../partials/list_search_bar.php';
    ?>
    </div>

    <?php if (!empty($roles)): ?>
        <div class="admin-table-wrapper">
            <table id="tabla-rol" class="admin-table">
                <thead>
                    <tr>
                        <th class="id-col">ID</th>
                        <th class="name-col">Nombre del Rol</th>
                        <th class="related-col">Descripción</th>
                        <th class="actions-col">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($roles as $rol): ?>
                        <tr>
                            <td><strong>#<?php echo str_pad(htmlspecialchars($rol['id_rol']), 3, '0', STR_PAD_LEFT); ?></strong></td>
                            <td><?php echo htmlspecialchars($rol['nombre_rol']); ?></td>
                            <td><span class="table-accent-text"><?php echo htmlspecialchars($rol['descripcion']); ?></span></td>
                            <td class="actions-cell">
                                <div class="actions-stack">
                                    <a href="rol.php?accion=actualizar&id=<?php echo $rol['id_rol']; ?>"
                                        class="btn-action-secondary" title="Editar rol">
                                        <i class="bi bi-pencil"></i> Editar
                                    </a>
                                    <a href="rol.php?accion=borrar&id=<?php echo $rol['id_rol']; ?>"
                                        class="btn-action-danger"
                                        title="Eliminar rol"
                                        onclick="return confirm('¿Estás seguro de que deseas eliminar este rol?');">
                                        <i class="bi bi-trash"></i> Eliminar
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="alert-message info">
            <div class="alert-content">
                <p><i class="bi bi-info-circle"></i> No hay roles registrados.</p>
            </div>
        </div>
    <?php endif; ?>
</div>