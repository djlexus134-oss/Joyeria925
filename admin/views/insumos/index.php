<div class="admin-modules">
    <?php if (isset($mensaje) && !empty($mensaje)): ?>
        <div class="alert-message success">
            <p><i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($mensaje); ?></p>
        </div>
    <?php endif; ?>

    <div class="module-actions-row">
    <div class="module-actions">
        <a href="insumos.php?accion=crear" class="btn-action-primary">
            <i class="bi bi-plus-lg"></i> Nuevo Insumo
        </a>
        <a href="insumos.php?accion=etiquetas" class="btn-action-secondary">
            <i class="bi bi-printer"></i> Imprimir Etiquetas
        </a>
    </div>
    <?php
    $listSearchAction = 'insumos.php';
    $listSearchHidden = ['accion' => 'leer'];
    $listSearchPlaceholder = 'Buscar por nombre, SKU o categoria...';
    require __DIR__ . '/../partials/list_search_bar.php';
    ?>
    </div>

    <?php if (!empty($insumos)): ?>
        <div class="admin-table-wrapper">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th class="id-col">ID</th>
                        <th>Nombre</th>
                        <th>Categoria</th>
                        <th>SKU</th>
                        <th>PVP sugerido</th>
                        <th>Stock total</th>
                        <th class="actions-col">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($insumos as $row): ?>
                        <tr>
                            <td><strong>#<?php echo (int) $row['id_insumo']; ?></strong></td>
                            <td><?php echo htmlspecialchars((string) $row['nombre']); ?></td>
                            <td><?php echo htmlspecialchars((string) ($row['categoria'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars((string) ($row['sku_codigo'] ?? '')); ?></td>
                            <td>
                                <?php
                                $pvp = $row['precio_venta_sugerido'] ?? null;
                                echo $pvp !== null && $pvp !== '' ? '$' . htmlspecialchars(number_format((float) $pvp, 2, '.', '')) : '—';
                                ?>
                            </td>
                            <td><?php echo htmlspecialchars(number_format((float) ($row['stock_total'] ?? 0), 3, '.', '')); ?></td>
                            <td class="actions-cell">
                                <div class="actions-stack">
                                    <?php if (trim((string) ($row['sku_codigo'] ?? '')) !== ''): ?>
                                        <button type="button" class="btn-action-secondary"
                                                data-etiqueta-accion="insumo"
                                                data-id-insumo="<?php echo (int) $row['id_insumo']; ?>">
                                            <i class="bi bi-printer"></i> Etiqueta
                                        </button>
                                    <?php endif; ?>
                                    <a href="insumos.php?accion=actualizar&id=<?php echo (int) $row['id_insumo']; ?>" class="btn-action-secondary">
                                        <i class="bi bi-pencil"></i> Editar
                                    </a>
                                    <a href="insumos.php?accion=borrar&id=<?php echo (int) $row['id_insumo']; ?>" class="btn-action-danger" onclick="return confirm('¿Dar de baja este insumo?');">
                                        <i class="bi bi-trash"></i> Baja
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
                <p><i class="bi bi-info-circle"></i> No hay insumos registrados.</p>
            </div>
        </div>
    <?php endif; ?>
</div>
<script src="js/etiquetas-print.js"></script>
