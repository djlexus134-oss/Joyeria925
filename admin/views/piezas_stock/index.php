<?php
$stockBaseScript = $stockBaseScript ?? 'pieza.php';
$stockAccionLeer = $stockAccionLeer ?? 'stock';
$stockAccionCrear = $stockAccionCrear ?? 'stock_crear';
$stockAccionActualizar = $stockAccionActualizar ?? 'stock_actualizar';
$stockAccionBorrar = $stockAccionBorrar ?? 'stock_borrar';
$stockIdPiezaQuery = (isset($idPiezaFiltro) && (int) $idPiezaFiltro > 0) ? '&id_pieza=' . (int) $idPiezaFiltro : '';
?>
<div class="admin-modules">

    <?php if (isset($mensaje)): ?>
        <div class="alert-message success">
            <p><i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($mensaje); ?></p>
        </div>
    <?php endif; ?>

    <div class="module-actions-row">
    <div class="module-actions-stack">
    <div class="module-actions">
        <a href="<?php echo htmlspecialchars($stockBaseScript); ?>?accion=<?php echo htmlspecialchars($stockAccionCrear); ?><?php echo $stockIdPiezaQuery; ?>" class="btn-action-primary">
            <i class="bi bi-plus-lg"></i> Nuevo Stock
        </a>
        <a href="pieza.php?accion=leer" class="btn-action-secondary">
            <i class="bi bi-arrow-left"></i> Volver a Piezas
        </a>
        <?php
        $__encolarTituloPieza = 'Pieza';
        if (!empty($piezasStock) && isset($piezasStock[0]['desc_pieza'])) {
            $__encolarTituloPieza = (string) $piezasStock[0]['desc_pieza'];
        }
        ?>
        <?php if (isset($idPiezaFiltro) && (int) $idPiezaFiltro > 0): ?>
            <button type="button" class="btn-action-secondary"
                    data-etiqueta-accion="rango"
                    data-id-pieza="<?php echo (int) $idPiezaFiltro; ?>"
                    data-titulo-pieza="<?php echo htmlspecialchars($__encolarTituloPieza, ENT_QUOTES, 'UTF-8'); ?>">
                <i class="bi bi-printer"></i> Encolar rango
            </button>
        <?php endif; ?>
    </div>
        <?php if (isset($idPiezaFiltro) && (int) $idPiezaFiltro > 0): ?>
            <div id="panel-etiquetas-rango-stock" class="piezas-stock-rango-panel" style="display:none;">
                <p class="piezas-stock-rango-hint">
                    Pieza: <strong id="etiquetas-rango-stock-titulo">—</strong><br>
                    Usa el número después de la barra en el codigo auxiliar (ej. en <code>42/5</code> el rango es <code>5</code>).
                </p>
                <input type="hidden" id="etiquetas-rango-stock-id-pieza" value="">
                <div class="form-row">
                    <div class="form-group">
                        <label for="etiquetas-rango-stock-desde">Desde</label>
                        <input type="number" class="form-input" id="etiquetas-rango-stock-desde" min="1" step="1" value="1">
                    </div>
                    <div class="form-group">
                        <label for="etiquetas-rango-stock-hasta">Hasta</label>
                        <input type="number" class="form-input" id="etiquetas-rango-stock-hasta" min="1" step="1" value="1">
                    </div>
                </div>
                <div class="form-actions" style="margin-top:0;">
                    <button type="button" class="btn-action-primary" id="btn-etiquetas-rango-stock-confirmar">
                        <i class="bi bi-send"></i> Encolar
                    </button>
                    <button type="button" class="btn-action-secondary" id="btn-etiquetas-rango-stock-cerrar">
                        Cancelar
                    </button>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <?php
    $listSearchAction = $stockBaseScript;
    $listSearchHidden = ['accion' => $stockAccionLeer];
    if (isset($idPiezaFiltro) && (int) $idPiezaFiltro > 0) {
        $listSearchHidden['id_pieza'] = (string) (int) $idPiezaFiltro;
    }
    $listSearchPlaceholder = 'Buscar por codigo, pieza, estado o proveedor...';
    require __DIR__ . '/../partials/list_search_bar.php';
    ?>
    </div>

    <?php if (!empty($piezasStock)): ?>
        <div class="admin-table-wrapper">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th class="id-col">ID</th>
                        <th>Pieza</th>
                        <th>Talla/Color</th>
                        <th>Código Auxiliar</th>
                        <th>Precio Venta</th>
                        <th>Estado</th>
                        <th>Tipo Código</th>
                        <th class="actions-col">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($piezasStock as $stock): ?>
                        <tr>
                            <td><strong>#<?php echo str_pad((string) (int) $stock['id_pieza_stock'], 4, '0', STR_PAD_LEFT); ?></strong></td>
                            <td><?php echo htmlspecialchars((string) $stock['desc_pieza']); ?></td>
                            <td>
                                <?php
                                require_once __DIR__ . '/../../../includes/variantes_stock_helpers.php';
                                $textoVar = joyeria_texto_variante_stock($stock);
                                if ($textoVar !== ''):
                                ?>
                                    <span class="badge badge-info"><?php echo htmlspecialchars($textoVar); ?></span>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td><code><?php echo htmlspecialchars((string) $stock['codigo_auxiliar']); ?></code></td>
                            <td>$<?php echo number_format((float) $stock['precio_venta'], 2, '.', ''); ?></td>
                            <td>
                                <span class="badge badge-<?php echo match($stock['estado']) {
                                    'disponible' => 'success',
                                    'vendida' => 'info',
                                    'apartada' => 'warning',
                                    'defectuosa' => 'danger',
                                    'reparacion' => 'warning',
                                    default => 'secondary'
                                }; ?>">
                                    <?php echo htmlspecialchars((string) $stock['estado']); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars((string) $stock['tipo_codigo']); ?></td>
                            <td class="actions-cell">
                                <div class="actions-stack">
                                    <button type="button" class="btn-action-secondary"
                                            data-etiqueta-accion="una"
                                            data-id-stock="<?php echo (int) $stock['id_pieza_stock']; ?>">
                                        <i class="bi bi-printer"></i> Etiqueta
                                    </button>
                                    <a href="<?php echo htmlspecialchars($stockBaseScript); ?>?accion=<?php echo htmlspecialchars($stockAccionActualizar); ?>&id=<?php echo (int) $stock['id_pieza_stock']; ?><?php echo $stockIdPiezaQuery; ?>" class="btn-action-secondary">
                                        <i class="bi bi-pencil"></i> Editar
                                    </a>
                                    <a href="<?php echo htmlspecialchars($stockBaseScript); ?>?accion=<?php echo htmlspecialchars($stockAccionBorrar); ?>&id=<?php echo (int) $stock['id_pieza_stock']; ?><?php echo $stockIdPiezaQuery; ?>"
                                       class="btn-action-danger"
                                       onclick="return confirm('¿Deseas dar de baja este stock?');">
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
            <p><i class="bi bi-info-circle"></i> No hay piezas de stock registradas.</p>
        </div>
    <?php endif; ?>
</div>

<script src="js/etiquetas-print.js"></script>

