<div class="admin-modules">
    <?php if (isset($mensaje) && !empty($mensaje)): ?>
        <div class="alert-message success">
            <p><i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($mensaje); ?></p>
        </div>
    <?php endif; ?>

    <div class="module-actions-row">
        <div class="module-actions">
            <a href="variantes.php?accion=crear" class="btn-action-primary">
                <i class="bi bi-plus-lg"></i> Nuevo tipo de variante
            </a>
        </div>
        <?php
        $listSearchAction = 'variantes.php';
        $listSearchHidden = ['accion' => 'leer'];
        $listSearchPlaceholder = 'Buscar por nombre o identificador...';
        require __DIR__ . '/../partials/list_search_bar.php';
        ?>
    </div>

    <?php if (!empty($tipos)): ?>
        <div class="admin-table-wrapper">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Identificador</th>
                        <th>Es talla</th>
                        <th>Valores</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tipos as $item): ?>
                        <tr>
                            <td>#<?php echo str_pad((string) $item['id_variante_tipo'], 3, '0', STR_PAD_LEFT); ?></td>
                            <td><?php echo htmlspecialchars((string) $item['nombre']); ?></td>
                            <td><code><?php echo htmlspecialchars((string) $item['slug']); ?></code></td>
                            <td><?php echo (int) ($item['es_talla'] ?? 0) === 1 ? 'Si' : 'No'; ?></td>
                            <td><?php echo (int) ($item['total_valores'] ?? 0); ?></td>
                            <td class="actions-cell">
                                <div class="actions-stack">
                                    <a href="variantes.php?accion=actualizar&id=<?php echo (int) $item['id_variante_tipo']; ?>" class="btn-action-secondary">
                                        <i class="bi bi-pencil"></i> Editar
                                    </a>
                                    <a href="variantes.php?accion=borrar&id=<?php echo (int) $item['id_variante_tipo']; ?>" class="btn-action-danger" onclick="return confirm('¿Dar de baja este tipo de variante?');">
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
            <p><i class="bi bi-info-circle"></i> No hay tipos de variante registrados.</p>
        </div>
    <?php endif; ?>
</div>
