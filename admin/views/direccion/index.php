<div class="admin-modules">

    <?php if (isset($mensaje) && !empty($mensaje)): ?>
        <div class="alert-message success">
            <p><i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($mensaje); ?></p>
        </div>
    <?php endif; ?>

    <?php if (isset($error) && !empty($error)): ?>
        <div class="alert-message error">
            <p><i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?></p>
        </div>
    <?php endif; ?>

    <div class="module-actions" style="display:flex; gap:12px; flex-wrap:wrap;">
        <?php foreach ($entidades as $clave => $meta): ?>
            <a href="direccion.php?accion=leer&entidad=<?php echo urlencode($clave); ?>"
               class="btn-action-secondary <?php echo $clave === $entidad ? 'active-filter' : ''; ?>">
                <?php echo htmlspecialchars($meta['titulo']); ?>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="module-actions" style="margin-top: 12px;">
        <a href="direccion.php?accion=crear&entidad=<?php echo urlencode($entidad); ?>" class="btn-action-primary">
            <i class="bi bi-plus-lg"></i> Nuevo <?php echo htmlspecialchars($entidades[$entidad]['singular']); ?>
        </a>
    </div>

    <?php
    $listSearchAction = 'direccion.php';
    $listSearchHidden = ['accion' => 'leer', 'entidad' => $entidad];
    $listSearchPlaceholder = 'Buscar en esta lista...';
    require __DIR__ . '/../partials/list_search_bar.php';
    ?>

    <?php if (!empty($registros)): ?>
        <div class="admin-table-wrapper">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th class="id-col">ID</th>
                        <?php foreach ($columnas as $campo => $etiqueta): ?>
                            <th class="related-col"><?php echo htmlspecialchars($etiqueta); ?></th>
                        <?php endforeach; ?>
                        <th class="actions-col">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($registros as $fila): ?>
                        <tr>
                            <td>
                                <strong>#<?php echo str_pad((string) $fila[$entidades[$entidad]['id']], 3, '0', STR_PAD_LEFT); ?></strong>
                            </td>
                            <?php foreach ($columnas as $campo => $etiqueta): ?>
                                <td><?php echo htmlspecialchars((string) ($fila[$campo] ?? '')); ?></td>
                            <?php endforeach; ?>
                            <td class="actions-cell">
                                <div class="actions-stack">
                                    <a href="direccion.php?accion=actualizar&entidad=<?php echo urlencode($entidad); ?>&id=<?php echo intval($fila[$entidades[$entidad]['id']]); ?>"
                                       class="btn-action-secondary" title="Editar">
                                        <i class="bi bi-pencil"></i> Editar
                                    </a>
                                    <a href="direccion.php?accion=borrar&entidad=<?php echo urlencode($entidad); ?>&id=<?php echo intval($fila[$entidades[$entidad]['id']]); ?>"
                                       class="btn-action-danger"
                                       title="Eliminar"
                                       onclick="return confirm('¿Deseas eliminar este registro?');">
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
                <p><i class="bi bi-info-circle"></i> No hay registros de <?php echo htmlspecialchars(strtolower($entidades[$entidad]['titulo'])); ?>.</p>
            </div>
        </div>
    <?php endif; ?>
</div>
