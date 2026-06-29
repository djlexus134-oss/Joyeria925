<div class="admin-modules">

    <?php if (isset($mensaje) && $mensaje !== ''): ?>
        <div class="alert-message success">
            <p><i class="bi bi-check-circle"></i> <?php echo htmlspecialchars((string) $mensaje); ?></p>
        </div>
    <?php endif; ?>

    <div class="module-actions-row">
        <div class="module-actions">
            <a href="promociones_banner.php?accion=crear" class="btn-action-primary">
                <i class="bi bi-plus-lg"></i> Nuevo banner
            </a>
        </div>
        <?php
        $listSearchAction = 'promociones_banner.php';
        $listSearchHidden = ['accion' => 'leer'];
        $listSearchPlaceholder = 'Titulo, texto, eyebrow...';
        require __DIR__ . '/../partials/list_search_bar.php';
        ?>
    </div>

    <?php if (!empty($banners)): ?>
        <div class="admin-table-wrapper">
            <table id="tabla-promociones-banner" class="admin-table">
                <thead>
                    <tr>
                        <th class="id-col">ID</th>
                        <th>Orden</th>
                        <th class="col-titulo">Titulo</th>
                        <th class="col-flag">Visitante</th>
                        <th class="col-flag">Cliente</th>
                        <th class="col-flag">Ticker</th>
                        <th class="col-flag">Barra inf.</th>
                        <th class="col-variante">Estilo CSS</th>
                        <th class="col-imagen">Imagen</th>
                        <th>Estado</th>
                        <th class="actions-col">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($banners as $b): ?>
                        <tr>
                            <td>#<?php echo (int) ($b['id_promocion_banner'] ?? 0); ?></td>
                            <td><?php echo (int) ($b['orden'] ?? 0); ?></td>
                            <td class="col-titulo"><?php echo htmlspecialchars((string) ($b['titulo'] ?? '')); ?></td>
                            <td class="col-flag"><?php echo (int) ($b['visible_visitante'] ?? 0) === 1 ? 'Si' : 'No'; ?></td>
                            <td class="col-flag"><?php echo (int) ($b['visible_cliente'] ?? 0) === 1 ? 'Si' : 'No'; ?></td>
                            <td class="col-flag"><?php echo (int) ($b['visible_ticker'] ?? 0) === 1 ? 'Si' : 'No'; ?></td>
                            <td class="col-flag"><?php echo (int) ($b['visible_barra_inferior'] ?? 0) === 1 ? 'Si' : 'No'; ?></td>
                            <td class="col-variante"><small><?php echo htmlspecialchars((string) ($b['variante'] ?? '')); ?></small></td>
                            <td class="col-imagen"><small><?php echo htmlspecialchars((string) ($b['fuente_imagen'] ?? '')); ?></small></td>
                            <td>
                                <span class="badge badge-<?php echo (int) ($b['activo'] ?? 0) === 1 ? 'success' : 'danger'; ?>">
                                    <?php echo (int) ($b['activo'] ?? 0) === 1 ? 'Activo' : 'Inactivo'; ?>
                                </span>
                            </td>
                            <td class="actions-cell">
                                <div class="actions-stack">
                                    <a href="promociones_banner.php?accion=actualizar&id=<?php echo (int) $b['id_promocion_banner']; ?>" class="btn-action-secondary">
                                        <i class="bi bi-pencil"></i> Editar
                                    </a>
                                    <a href="promociones_banner.php?accion=borrar&id=<?php echo (int) $b['id_promocion_banner']; ?>"
                                       class="btn-action-danger"
                                       onclick="return confirm('¿Desactivar este banner?');">
                                        <i class="bi bi-trash"></i> Desactivar
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="admin-empty-msg">Sin banners configurados.</p>
    <?php endif; ?>
</div>
