<div class="admin-modules">

    <?php if (isset($mensaje) && trim(!empty($mensaje))): ?>
        <div class="alert-message success">
            <p><i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($mensaje); ?></p>
        </div>
    <?php endif; ?>

    <div class="module-actions-row">
    <div class="module-actions">
        <a href="permiso.php?accion=crear" class="btn-action-primary">
            <i class="bi bi-plus-lg"></i> Nuevo Permiso
        </a>
    </div>
    <?php
    $listSearchAction = 'permiso.php';
    $listSearchHidden = ['accion' => 'leer'];
    $listSearchPlaceholder = 'Buscar por nombre o descripción...';
    require __DIR__ . '/../partials/list_search_bar.php';
    ?>
    </div>

    <?php if (!empty($permisos)): ?>
        <div class="admin-table-wrapper">
            <table id="tabla-permiso" class="admin-table">
                <thead>
                    <tr>
                        <th class="id-col">ID</th>
                        <th class="name-col">Nombre del Permiso</th>
                        <th class="related-col">Descripción</th>
                        <th class="actions-col">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($permisos as $permiso): ?>
                        <tr>
                            <td><strong>#<?php echo str_pad(htmlspecialchars($permiso['id_permiso']), 3, '0', STR_PAD_LEFT); ?></strong></td>
                            <td><?php echo htmlspecialchars($permiso['nombre_permiso']); ?></td>
                            <td><span class="table-accent-text"><?php echo htmlspecialchars($permiso['descripcion']); ?></span></td>
                            <td class="actions-cell">
                                <div class="actions-stack">
                                    <a href="permiso.php?accion=actualizar&id=<?php echo $permiso['id_permiso']; ?>"
                                        class="btn-action-secondary" title="Editar permiso">
                                        <i class="bi bi-pencil"></i> Editar
                                    </a>
                                    <a href="permiso.php?accion=borrar&id=<?php echo $permiso['id_permiso']; ?>"
                                        class="btn-action-danger"
                                        title="Eliminar permiso"
                                        onclick="return confirm('¿Estás seguro de que deseas eliminar este permiso?');">
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
                <p><i class="bi bi-info-circle"></i> No hay permisos registrados.</p>
            </div>
        </div>
    <?php endif; ?>
</div>