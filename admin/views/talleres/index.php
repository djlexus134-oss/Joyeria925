<div class="admin-modules">

    <?php if(isset($mensaje) && !empty($mensaje)): ?>
        <div class="alert-message success">
            <p><i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($mensaje); ?></p>
        </div>
    <?php endif; ?>

    <div class="module-actions-row">
    <div class="module-actions">
        <a href="talleres.php?accion=crear" class="btn-action-primary">
            <i class="bi bi-plus-lg"></i> Nuevo Taller
        </a>
    </div>
    <?php
    $listSearchAction = 'talleres.php';
    $listSearchHidden = ['accion' => 'leer'];
    $listSearchPlaceholder = 'Buscar por nombre, contacto o teléfono...';
    require __DIR__ . '/../partials/list_search_bar.php';
    ?>
    </div>

    <?php if(!empty($talleres)): ?>
        <div class="admin-table-wrapper">
            <table id="tabla-talleres" class="admin-table">
                <thead>
                    <tr>
                        <th class="id-col">ID</th>
                        <th class="name-col">Nombre</th>
                        <th class="related-col">Contacto</th>
                        <th class="related-col">Telefono</th>
                        <th class="actions-col">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($talleres as $taller): ?>
                        <tr>
                            <td><strong>#<?php echo str_pad(htmlspecialchars($taller['id_taller']), 3, '0', STR_PAD_LEFT); ?></strong></td>
                            <td><?php echo htmlspecialchars($taller['nombre']); ?></td>
                            <td><?php echo htmlspecialchars($taller['contacto'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($taller['telefono'] ?? 'N/A'); ?></td>
                            <td class="actions-cell">
                                <div class="actions-stack">
                                    <a href="talleres.php?accion=actualizar&id=<?php echo $taller['id_taller']; ?>" class="btn-action-secondary" title="Editar">
                                        <i class="bi bi-pencil"></i> Editar
                                    </a>
                                    <a href="talleres.php?accion=borrar&id=<?php echo $taller['id_taller']; ?>" class="btn-action-danger" title="Eliminar" onclick="return confirm('¿Estas seguro de dar de baja este taller?');">
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
                <p><i class="bi bi-info-circle"></i> No hay talleres registrados.</p>
            </div>
        </div>
    <?php endif; ?>
</div>
