<div class="admin-modules">
    <?php if(isset($mensaje) && !empty($mensaje)): ?>
        <div class="alert-message success"><p><i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($mensaje); ?></p></div>
    <?php endif; ?>

    <div class="module-actions-row">
    <div class="module-actions">
        <a href="configuracion_general.php" class="btn-action-secondary"><i class="bi bi-sliders"></i> Panel de configuracion</a>
        <a href="configuracion_general.php?accion=crear" class="btn-action-primary"><i class="bi bi-plus-lg"></i> Nueva clave</a>
    </div>
    <?php
    $listSearchAction = 'configuracion_general.php';
    $listSearchHidden = ['accion' => 'avanzado'];
    $listSearchPlaceholder = 'Buscar por clave, valor o tipo...';
    require __DIR__ . '/../partials/list_search_bar.php';
    ?>
    </div>

    <?php if(!empty($configuraciones)): ?>
        <div class="admin-table-wrapper">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Clave</th>
                        <th>Valor</th>
                        <th>Tipo</th>
                        <th>Fecha Actualizacion</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($configuraciones as $item): ?>
                        <tr>
                            <td>#<?php echo str_pad(htmlspecialchars($item['id_configuracion_global']), 3, '0', STR_PAD_LEFT); ?></td>
                            <td><?php echo htmlspecialchars($item['clave'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($item['valor']); ?></td>
                            <td><?php echo htmlspecialchars($item['tipo']); ?></td>
                            <td><?php echo htmlspecialchars($item['fecha_actualizacion'] ?? 'N/A'); ?></td>
                            <td class="actions-cell">
                                <div class="actions-stack">
                                    <a href="configuracion_general.php?accion=actualizar&amp;id=<?php echo $item['id_configuracion_global']; ?>" class="btn-action-secondary"><i class="bi bi-pencil"></i> Editar</a>
                                    <a href="configuracion_general.php?accion=borrar&amp;id=<?php echo $item['id_configuracion_global']; ?>" class="btn-action-danger" onclick="return confirm('¿Eliminar esta configuracion?');"><i class="bi bi-trash"></i> Eliminar</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="alert-message info"><p><i class="bi bi-info-circle"></i> No hay configuraciones registradas.</p></div>
    <?php endif; ?>
</div>
