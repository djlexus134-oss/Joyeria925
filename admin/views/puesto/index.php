<div class="admin-modules">

    <?php if (isset($mensaje) && trim(!empty($mensaje))): ?>
        <div class="alert-message success">
            <p><i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($mensaje); ?></p>
        </div>
    <?php endif; ?>

    <div class="module-actions-row">
    <div class="module-actions">
        <a href="puesto.php?accion=crear" class="btn-action-primary">
            <i class="bi bi-plus-lg"></i> Nuevo Puesto
        </a>
    </div>
    <?php
    $listSearchAction = 'puesto.php';
    $listSearchHidden = ['accion' => 'leer'];
    $listSearchPlaceholder = 'Buscar por nombre o descripción...';
    require __DIR__ . '/../partials/list_search_bar.php';
    ?>
    </div>

    <?php if (!empty($puestos)): ?>
        <div class="admin-table-wrapper">
            <table id="tabla-puesto" class="admin-table">
                <thead>
                    <tr>
                        <th class="id-col">ID</th>
                        <th class="name-col">Nombre del Puesto</th>
                        <th class="related-col">Descripción</th>
                        <th class="actions-col">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($puestos as $puesto): ?>
                        <tr>
                            <td><strong>#<?php echo str_pad(htmlspecialchars($puesto['id_puesto']), 3, '0', STR_PAD_LEFT); ?></strong></td>
                            <td><?php echo htmlspecialchars($puesto['nombre_puesto']); ?></td>
                            <td><span class="table-accent-text"><?php echo htmlspecialchars($puesto['descripcion']); ?></span></td>
                            <td class="actions-cell">
                                <div class="actions-stack">
                                    <a href="puesto.php?accion=actualizar&id=<?php echo $puesto['id_puesto']; ?>"
                                        class="btn-action-secondary" title="Editar puesto">
                                        <i class="bi bi-pencil"></i> Editar
                                    </a>
                                    <a href="puesto.php?accion=borrar&id=<?php echo $puesto['id_puesto']; ?>"
                                        class="btn-action-danger"
                                        title="Eliminar puesto"
                                        onclick="return confirm('¿Estás seguro de que deseas eliminar este puesto?');">
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
                <p><i class="bi bi-info-circle"></i> No hay puestos registrados.</p>
            </div>
        </div>
    <?php endif; ?>
</div>