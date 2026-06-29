<div class="admin-modules">

    <?php if (isset($mensaje)): ?>
        <div class="alert-message success">
            <p><i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($mensaje); ?></p>
        </div>
    <?php endif; ?>

    <div class="module-actions-row">
    <div class="module-actions">
        <a href="notificaciones.php?accion=crear" class="btn-action-primary">
            <i class="bi bi-plus-lg"></i> Nueva Notificación
        </a>
    </div>
    <?php
    $listSearchAction = 'notificaciones.php';
    $listSearchHidden = ['accion' => 'leer'];
    $listSearchPlaceholder = 'Buscar por mensaje o ID...';
    require __DIR__ . '/../partials/list_search_bar.php';
    ?>
    </div>

    <?php if (!empty($notificaciones)): ?>
        <div class="admin-table-wrapper">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th class="id-col">ID</th>
                        <th>Mensaje</th>
                        <th>Leída</th>
                        <th>Fecha envio</th>
                        <th class="actions-col">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($notificaciones as $notificacion): ?>
                        <tr>
                            <td><strong>#<?php echo str_pad((string) (int) $notificacion['id_notificacion'], 3, '0', STR_PAD_LEFT); ?></strong></td>
                            <td><?php echo htmlspecialchars((string) $notificacion['mensaje']); ?></td>
                            <td><?php echo ((int) $notificacion['leida'] === 1) ? 'Si' : 'No'; ?></td>
                            <td><?php echo htmlspecialchars((string) ($notificacion['fecha_envio'] ?? '')); ?></td>
                            <td class="actions-cell">
                                <div class="actions-stack">
                                    <a href="notificaciones.php?accion=actualizar&id=<?php echo (int) $notificacion['id_notificacion']; ?>" class="btn-action-secondary">
                                        <i class="bi bi-pencil"></i> Editar
                                    </a>
                                    <a href="notificaciones.php?accion=borrar&id=<?php echo (int) $notificacion['id_notificacion']; ?>"
                                       class="btn-action-danger"
                                       onclick="return confirm('¿Deseas eliminar esta notificacion?');">
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
            <p><i class="bi bi-info-circle"></i> No hay notificaciones registradas.</p>
        </div>
    <?php endif; ?>
</div>
