<div class="admin-modules">
    <?php if(isset($mensaje) && !empty($mensaje)): ?>
        <div class="alert-message success">
            <p><i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($mensaje); ?></p>
        </div>
    <?php endif; ?>

    <div class="module-actions-row">
    <div class="module-actions">
        <a href="gastos_categoria.php?accion=crear" class="btn-action-primary">
            <i class="bi bi-plus-lg"></i> Nueva Categoria
        </a>
    </div>
    <?php
    $listSearchAction = 'gastos_categoria.php';
    $listSearchHidden = ['accion' => 'leer'];
    $listSearchPlaceholder = 'Buscar por nombre o descripción...';
    require __DIR__ . '/../partials/list_search_bar.php';
    ?>
    </div>

    <?php if(!empty($categorias)): ?>
        <div class="admin-table-wrapper">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Descripción</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categorias as $item): ?>
                        <tr>
                            <td>#<?php echo str_pad(htmlspecialchars($item['id_categoria_gasto']), 3, '0', STR_PAD_LEFT); ?></td>
                            <td><?php echo htmlspecialchars($item['nombre']); ?></td>
                            <td><?php echo htmlspecialchars($item['descripcion'] ?? 'N/A'); ?></td>
                            <td class="actions-cell">
                                <div class="actions-stack">
                                    <a href="gastos_categoria.php?accion=actualizar&id=<?php echo $item['id_categoria_gasto']; ?>" class="btn-action-secondary"><i class="bi bi-pencil"></i> Editar</a>
                                    <a href="gastos_categoria.php?accion=borrar&id=<?php echo $item['id_categoria_gasto']; ?>" class="btn-action-danger" onclick="return confirm('¿Dar de baja esta categoria?');"><i class="bi bi-trash"></i> Eliminar</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="alert-message info"><p><i class="bi bi-info-circle"></i> No hay categorias registradas.</p></div>
    <?php endif; ?>
</div>
