<div class="admin-modules">
    <?php if(isset($mensaje) && !empty($mensaje)): ?>
        <div class="alert-message success"><p><i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($mensaje); ?></p></div>
    <?php endif; ?>

    <div class="module-actions-row">
    <div class="module-actions">
        <a href="impuestos.php?accion=crear" class="btn-action-primary"><i class="bi bi-plus-lg"></i> Nuevo Impuesto</a>
        <a href="impuestos_historico.php?accion=leer" class="btn-action-secondary"><i class="bi bi-clock-history"></i> Historico</a>
    </div>
    <?php
    $listSearchAction = 'impuestos.php';
    $listSearchHidden = ['accion' => 'leer'];
    $listSearchPlaceholder = 'Buscar por tipo o porcentaje...';
    require __DIR__ . '/../partials/list_search_bar.php';
    ?>
    </div>

    <?php if(!empty($impuestos)): ?>
        <div class="admin-table-wrapper">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tipo</th>
                        <th>Porcentaje</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($impuestos as $item): ?>
                        <tr>
                            <td>#<?php echo str_pad(htmlspecialchars($item['id_impuesto']), 3, '0', STR_PAD_LEFT); ?></td>
                            <td><?php echo htmlspecialchars($item['tipo_impuesto']); ?></td>
                            <td><?php echo htmlspecialchars($item['porcentaje'] ?? 'N/A'); ?>%</td>
                            <td class="actions-cell">
                                <div class="actions-stack">
                                    <a href="impuestos.php?accion=actualizar&id=<?php echo $item['id_impuesto']; ?>" class="btn-action-secondary"><i class="bi bi-pencil"></i> Editar</a>
                                    <a href="impuestos.php?accion=borrar&id=<?php echo $item['id_impuesto']; ?>" class="btn-action-danger" onclick="return confirm('¿Eliminar este impuesto?');"><i class="bi bi-trash"></i> Eliminar</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="alert-message info"><p><i class="bi bi-info-circle"></i> No hay impuestos registrados.</p></div>
    <?php endif; ?>
</div>
