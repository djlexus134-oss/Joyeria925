<div class="admin-modules">

    <?php if(isset($mensaje) && !empty($mensaje)): ?>
        <div class="alert-message success">
            <p><i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($mensaje); ?></p>
        </div>
    <?php endif; ?>

    <div class="module-actions-row">
    <div class="module-actions">
        <a href="metales.php?accion=crear" class="btn-action-primary">
            <i class="bi bi-plus-lg"></i> Nuevo Metal
        </a>
    </div>
    <?php
    $listSearchAction = 'metales.php';
    $listSearchHidden = ['accion' => 'leer'];
    $listSearchPlaceholder = 'Buscar por nombre de metal...';
    require __DIR__ . '/../partials/list_search_bar.php';
    ?>
    </div>

    <?php if(!empty($metales)): ?>
        <div class="admin-table-wrapper">
            <table id="tabla-metales" class="admin-table">
                <thead>
                    <tr>
                        <th class="id-col">ID</th>
                        <th class="name-col">Metal</th>
                        <th class="related-col">Precio Tienda</th>
                        <th class="related-col">Precio Mercado</th>
                        <th class="actions-col">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($metales as $metal): ?>
                        <tr>
                            <td><strong>#<?php echo str_pad(htmlspecialchars($metal['id_metal']), 3, '0', STR_PAD_LEFT); ?></strong></td>
                            <td><?php echo htmlspecialchars($metal['nom_metal']); ?></td>
                            <td><?php echo $metal['precio_tienda'] === null ? '-' : '$' . number_format((float) $metal['precio_tienda'], 2, '.', ''); ?></td>
                            <td><?php echo $metal['precio_mercado'] === null ? '-' : '$' . number_format((float) $metal['precio_mercado'], 2, '.', ''); ?></td>
                            <td class="actions-cell">
                                <div class="actions-stack">
                                    <a href="metales.php?accion=actualizar&id=<?php echo $metal['id_metal']; ?>"
                                       class="btn-action-secondary" title="Editar metal">
                                        <i class="bi bi-pencil"></i> Editar
                                    </a>
                                    <a href="metales.php?accion=borrar&id=<?php echo $metal['id_metal']; ?>"
                                       class="btn-action-danger"
                                       title="Dar de baja metal"
                                       onclick="return confirm('¿Estas seguro de que deseas dar de baja este metal?');">
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
                <p><i class="bi bi-info-circle"></i> No hay metales registrados.</p>
            </div>
        </div>
    <?php endif; ?>
</div>
