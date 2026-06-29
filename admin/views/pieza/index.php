<?php
$piezaCanCreate = auth_can_module_action('pieza', 'CREAR');
$piezaCanUpdate = auth_can_module_action('pieza', 'ACTUALIZAR');
$piezaCanPhoto = auth_can_module_action('pieza', 'FOTO');
$piezaCanDelete = auth_can_module_action('pieza', 'BORRAR');
?>
<div class="admin-modules">

    <?php if (isset($mensaje) && !empty($mensaje)): ?>
        <div class="alert-message success">
            <p><i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($mensaje); ?></p>
        </div>
    <?php endif; ?>

    <?php if (!empty($stockIdsNuevos)): ?>
        <div class="alert-message info">
            <p style="margin:0 0 10px;">
                <i class="bi bi-printer"></i>
                Se generaron <?php echo count($stockIdsNuevos); ?> unidad(es) de stock.
                <?php if (!empty($idColaEtiquetasNueva)): ?>
                    Cola #<?php echo (int) $idColaEtiquetasNueva; ?> — la PC de etiquetas imprimira en breve.
                <?php endif; ?>
            </p>
            <button type="button" class="btn-action-secondary" id="btn-encolar-stock-nuevo"
                    data-ids-stock="<?php echo htmlspecialchars(implode(',', array_map('intval', $stockIdsNuevos)), ENT_QUOTES, 'UTF-8'); ?>">
                <i class="bi bi-send"></i> Encolar etiquetas de nuevo
            </button>
            <?php if (!empty($idPiezaRecienCreada)): ?>
                <a href="pieza.php?accion=stock&id_pieza=<?php echo (int) $idPiezaRecienCreada; ?>" class="btn-action-secondary">
                    <i class="bi bi-box-seam"></i> Ver stock de esta pieza
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="module-actions-row">
    <div class="module-actions">
        <?php if ($piezaCanCreate): ?>
            <a href="pieza.php?accion=crear" class="btn-action-primary">
                <i class="bi bi-plus-lg"></i> Nueva Pieza
            </a>
        <?php endif; ?>
        <a href="pieza.php?accion=stock" class="btn-action-secondary">
            <i class="bi bi-box-seam"></i> Stock de piezas
        </a>
    </div>
    <div class="module-search-bar module-search-bar-wide">
        <form class="module-search-form" method="get" action="pieza.php">
            <input type="hidden" name="accion" value="leer">
            <select class="form-input module-search-select" name="campo" aria-label="Atributo de busqueda">
                <option value="global" <?php echo ($campoBusquedaPieza ?? 'global') === 'global' ? 'selected' : ''; ?>>Todos</option>
                <option value="id" <?php echo ($campoBusquedaPieza ?? '') === 'id' ? 'selected' : ''; ?>>ID</option>
                <option value="descripcion" <?php echo ($campoBusquedaPieza ?? '') === 'descripcion' ? 'selected' : ''; ?>>Descripcion</option>
                <option value="subfamilia" <?php echo ($campoBusquedaPieza ?? '') === 'subfamilia' ? 'selected' : ''; ?>>Subfamilia</option>
                <option value="metal" <?php echo ($campoBusquedaPieza ?? '') === 'metal' ? 'selected' : ''; ?>>Metal</option>
                <option value="proveedor" <?php echo ($campoBusquedaPieza ?? '') === 'proveedor' ? 'selected' : ''; ?>>Proveedor</option>
                <option value="tienda" <?php echo ($campoBusquedaPieza ?? '') === 'tienda' ? 'selected' : ''; ?>>Tienda</option>
            </select>
            <input type="search" id="list-search-q" name="q" class="module-search-input"
                   value="<?php echo htmlspecialchars($busqueda ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                   placeholder="Buscar piezas..."
                   autocomplete="off">
            <button type="submit" class="btn-action-secondary module-search-submit">
                <i class="bi bi-search"></i> Buscar
            </button>
            <?php if (!empty($busqueda)): ?>
                <a href="pieza.php?accion=leer&campo=<?php echo urlencode((string) ($campoBusquedaPieza ?? 'global')); ?>" class="btn-action-secondary module-search-clear">Limpiar</a>
            <?php endif; ?>
        </form>
    </div>
    </div>

    <?php if (!empty($piezas)): ?>
        <div class="admin-table-wrapper">
            <table id="tabla-piezas" class="admin-table">
                <thead>
                    <tr>
                        <th class="id-col">ID</th>
                        <th class="name-col">Descripción</th>
                        <th class="related-col">Subfamilia</th>
                        <th class="related-col">Metal</th>
                        <th class="related-col">Peso (gr)</th>
                        <?php if ($piezaCanUpdate): ?>
                            <th class="related-col">Costo</th>
                            <th class="related-col">Aumento</th>
                        <?php endif; ?>
                        <th class="related-col">Precio venta</th>
                        <th class="related-col">Imagen</th>
                        <th class="actions-col">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($piezas as $pieza): ?>
                        <?php
                            $costoFila = (float) ($pieza['costo'] ?? 0);
                            $aumentoFila = ($pieza['aumento_pct'] !== null && $pieza['aumento_pct'] !== '') ? (float) $pieza['aumento_pct'] : 0;
                            $pvFila = round($costoFila * (1 + $aumentoFila / 100), 2);
                            if ($pvFila > 0) {
                                $pvFila = ceil($pvFila / 5) * 5;
                            }
                        ?>
                        <tr>
                            <td><strong>#<?php echo str_pad(htmlspecialchars($pieza['id_pieza']), 3, '0', STR_PAD_LEFT); ?></strong></td>
                            <td><?php echo htmlspecialchars($pieza['desc_pieza']); ?></td>
                            <td><?php echo htmlspecialchars($pieza['nom_sub_familia']); ?></td>
                            <td><?php echo htmlspecialchars($pieza['nom_metal']); ?></td>
                            <td><?php echo $pieza['peso_gr'] !== null && $pieza['peso_gr'] !== '' ? htmlspecialchars(number_format((float) $pieza['peso_gr'], 2, '.', '')) : '-'; ?></td>
                            <?php if ($piezaCanUpdate): ?>
                                <td>$<?php echo htmlspecialchars(number_format($costoFila, 2, '.', '')); ?></td>
                                <td><?php echo ($pieza['aumento_pct'] !== null && $pieza['aumento_pct'] !== '') ? htmlspecialchars(number_format($aumentoFila, 2, '.', '')) . '%' : '-'; ?></td>
                            <?php endif; ?>
                            <td>$<?php echo htmlspecialchars(number_format($pvFila, 2, '.', '')); ?></td>
                            <td>
                                <?php if (!empty($pieza['url_imagen'])): ?>
                                    <img src="<?php echo htmlspecialchars($pieza['url_imagen']); ?>" alt="Imagen pieza" style="width:48px;height:48px;object-fit:cover;border-radius:6px;">
                                <?php else: ?>
                                    <span>N/A</span>
                                <?php endif; ?>
                            </td>
                            <td class="actions-cell">
                                <div class="actions-stack">
                                    <?php if ($piezaCanUpdate): ?>
                                        <a href="pieza.php?accion=actualizar&id=<?php echo $pieza['id_pieza']; ?>" class="btn-action-secondary" title="Editar">
                                            <i class="bi bi-pencil"></i> Editar
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($piezaCanPhoto): ?>
                                        <a href="pieza.php?accion=gestionar_foto&id=<?php echo $pieza['id_pieza']; ?>" class="btn-action-secondary" title="Editar foto">
                                            <i class="bi bi-image"></i> Editar foto
                                        </a>
                                    <?php endif; ?>
                                    <a href="pieza.php?accion=stock&id_pieza=<?php echo $pieza['id_pieza']; ?>" class="btn-action-secondary" title="Ver stock de la pieza">
                                        <i class="bi bi-box-seam"></i> Ver stock
                                    </a>
                                    <?php if ($piezaCanDelete): ?>
                                        <a href="pieza.php?accion=borrar&id=<?php echo $pieza['id_pieza']; ?>" class="btn-action-danger" title="Borrar" onclick="return confirm('¿Estas seguro de dar de baja esta pieza?');">
                                            <i class="bi bi-trash"></i> Eliminar
                                        </a>
                                    <?php endif; ?>
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
                <p><i class="bi bi-info-circle"></i> No hay piezas registradas.</p>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php if (!empty($stockIdsNuevos) || !empty($idColaEtiquetasNueva)): ?>
<script src="js/etiquetas-print.js"></script>
<?php if (!empty($idColaEtiquetasNueva)): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.JoyeriaEtiquetasPrint) {
        JoyeriaEtiquetasPrint.pollEstadoCola(<?php echo (int) $idColaEtiquetasNueva; ?>);
    }
});
</script>
<?php endif; ?>
<?php endif; ?>
