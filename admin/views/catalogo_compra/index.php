<div class="admin-modules">

    <?php if (isset($mensaje)): ?>
        <div class="alert-message success">
            <p><i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($mensaje); ?></p>
        </div>
    <?php endif; ?>

    <div class="module-actions-row">
    <div class="module-actions">
        <a href="catalogo_compra.php?accion=crear" class="btn-action-primary">
            <i class="bi bi-plus-lg"></i> Nuevo artículo
        </a>
    </div>
    <?php
    $listSearchAction = 'catalogo_compra.php';
    $listSearchHidden = ['accion' => 'leer'];
    $listSearchPlaceholder = 'Buscar por descripción, tipo, familia, subfamilia o metal...';
    require __DIR__ . '/../partials/list_search_bar.php';
    ?>
    </div>

    <?php if (!empty($articulos)): ?>
        <div class="admin-table-wrapper">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th class="id-col">ID</th>
                        <th>Tipo</th>
                        <th>Descripción</th>
                        <th>Familia</th>
                        <th>Subfamilia</th>
                        <th>Metal</th>
                        <th class="actions-col">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($articulos as $articulo): ?>
                        <tr>
                            <td><strong>#<?php echo str_pad((string) (int) $articulo['id_articulo_compra'], 3, '0', STR_PAD_LEFT); ?></strong></td>
                            <td><?php echo htmlspecialchars((string) $articulo['tipo']); ?></td>
                            <td><?php echo htmlspecialchars((string) $articulo['descripcion']); ?></td>
                            <td><?php echo htmlspecialchars((string) ($articulo['nom_familia'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars((string) ($articulo['nom_sub_familia'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars((string) ($articulo['nom_metal'] ?? '')); ?></td>
                            <td class="actions-cell">
                                <div class="actions-stack">
                                    <a href="catalogo_compra.php?accion=actualizar&id=<?php echo (int) $articulo['id_articulo_compra']; ?>" class="btn-action-secondary">
                                        <i class="bi bi-pencil"></i> Editar
                                    </a>
                                    <a href="catalogo_compra.php?accion=borrar&id=<?php echo (int) $articulo['id_articulo_compra']; ?>"
                                       class="btn-action-danger"
                                       onclick="return confirm('¿Deseas dar de baja este artículo?');">
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
            <p><i class="bi bi-info-circle"></i> No hay artículos de catálogo registrados.</p>
        </div>
    <?php endif; ?>
</div>
