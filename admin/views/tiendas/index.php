<div class="admin-modules">

    <?php if (isset($mensaje)): ?>
        <div class="alert-message success">
            <p><i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($mensaje); ?></p>
        </div>
    <?php endif; ?>

    <div class="module-actions-row">
    <div class="module-actions">
        <a href="tiendas.php?accion=crear" class="btn-action-primary">
            <i class="bi bi-plus-lg"></i> Nueva Tienda
        </a>
    </div>
    <?php
    $listSearchAction = 'tiendas.php';
    $listSearchHidden = ['accion' => 'leer'];
    $listSearchPlaceholder = 'Buscar por tienda, calle, colonia o CP...';
    require __DIR__ . '/../partials/list_search_bar.php';
    ?>
    </div>

    <?php if (!empty($tiendas)): ?>
        <div class="admin-table-wrapper">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th class="id-col">ID</th>
                        <th>Nombre</th>
                        <th>Dirección</th>
                        <th class="actions-col">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tiendas as $tienda): ?>
                        <tr>
                            <td><strong>#<?php echo str_pad((string) (int) $tienda['id_tienda'], 3, '0', STR_PAD_LEFT); ?></strong></td>
                            <td><?php echo htmlspecialchars((string) $tienda['nom_tienda']); ?></td>
                            <td>
                                <?php
                                $texto = (string) ($tienda['nom_calle'] ?? '') . ' #' . (string) ($tienda['num_exterior'] ?? '');
                                if (!empty($tienda['num_interior'])) {
                                    $texto .= ' Int. ' . (string) $tienda['num_interior'];
                                }
                                $texto .= ', ' . (string) ($tienda['nom_colonia'] ?? '') . ', CP ' . (string) ($tienda['codigo_postal'] ?? '');
                                echo htmlspecialchars($texto);
                                ?>
                            </td>
                            <td class="actions-cell">
                                <div class="actions-stack">
                                    <a href="tiendas.php?accion=actualizar&id=<?php echo (int) $tienda['id_tienda']; ?>" class="btn-action-secondary">
                                        <i class="bi bi-pencil"></i> Editar
                                    </a>
                                    <a href="tiendas.php?accion=borrar&id=<?php echo (int) $tienda['id_tienda']; ?>"
                                       class="btn-action-danger"
                                       onclick="return confirm('¿Deseas dar de baja esta tienda?');">
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
            <p><i class="bi bi-info-circle"></i> No hay tiendas registradas.</p>
        </div>
    <?php endif; ?>
</div>
