<?php
/** @var array<int, array<string, mixed>> $insumos */
?>
<div class="admin-modules">
    <div class="alert-message info">
        <p><i class="bi bi-info-circle"></i>
            Las etiquetas imprimen el <strong>SKU</strong> del insumo (mismo codigo que escaneas en punto de venta).
            Requiere impresion de etiquetas habilitada en configuracion y el agente <code>print-agent-etiquetas</code> activo.
        </p>
    </div>

    <div class="module-actions-row">
        <div class="module-actions">
            <a href="insumos.php?accion=leer" class="btn-action-secondary">
                <i class="bi bi-arrow-left"></i> Volver a Insumos
            </a>
            <button type="button" class="btn-action-primary" id="btn-etiquetas-insumo-seleccionadas" disabled>
                <i class="bi bi-printer"></i> Encolar seleccionadas
            </button>
            <button type="button" class="btn-action-secondary" id="btn-etiquetas-insumo-todas">
                <i class="bi bi-printer-fill"></i> Encolar todas visibles
            </button>
        </div>
        <?php
        $listSearchAction = 'insumos.php';
        $listSearchHidden = ['accion' => 'etiquetas'];
        $listSearchPlaceholder = 'Buscar por nombre, SKU o categoria...';
        require __DIR__ . '/../partials/list_search_bar.php';
        ?>
    </div>

    <?php if (!empty($insumos)): ?>
        <div class="admin-table-wrapper">
            <table class="admin-table" id="tabla-etiquetas-insumo">
                <thead>
                    <tr>
                        <th style="width:40px;">
                            <input type="checkbox" id="etiquetas-insumo-check-all" title="Seleccionar todos">
                        </th>
                        <th class="id-col">ID</th>
                        <th>Nombre</th>
                        <th>Categoria</th>
                        <th>SKU (POS)</th>
                        <th>PVP</th>
                        <th>Stock total</th>
                        <th style="width:90px;">Copias</th>
                        <th class="actions-col">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($insumos as $row): ?>
                        <?php
                        $idInsumo = (int) $row['id_insumo'];
                        $sku = trim((string) ($row['sku_codigo'] ?? ''));
                        $sinSku = $sku === '';
                        ?>
                        <tr data-id-insumo="<?php echo $idInsumo; ?>">
                            <td>
                                <input type="checkbox" class="etiqueta-insumo-check" value="<?php echo $idInsumo; ?>"
                                    <?php echo $sinSku ? 'disabled title="Sin SKU"' : ''; ?>>
                            </td>
                            <td><strong>#<?php echo $idInsumo; ?></strong></td>
                            <td><?php echo htmlspecialchars((string) $row['nombre']); ?></td>
                            <td><?php echo htmlspecialchars((string) ($row['categoria'] ?? '')); ?></td>
                            <td>
                                <?php if ($sinSku): ?>
                                    <span class="text-muted">Sin SKU</span>
                                <?php else: ?>
                                    <code><?php echo htmlspecialchars($sku); ?></code>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $pvp = $row['precio_venta_sugerido'] ?? null;
                                echo $pvp !== null && $pvp !== ''
                                    ? '$' . htmlspecialchars(number_format((float) $pvp, 2, '.', ''))
                                    : '—';
                                ?>
                            </td>
                            <td><?php echo htmlspecialchars(number_format((float) ($row['stock_total'] ?? 0), 3, '.', '')); ?></td>
                            <td>
                                <input type="number" class="form-input etiqueta-insumo-copias" min="1" max="500" step="1"
                                       value="1" style="max-width:72px;"
                                       data-id-insumo="<?php echo $idInsumo; ?>"
                                       <?php echo $sinSku ? 'disabled' : ''; ?>>
                            </td>
                            <td class="actions-cell">
                                <?php if (!$sinSku): ?>
                                    <button type="button" class="btn-action-secondary"
                                            data-etiqueta-accion="insumo"
                                            data-id-insumo="<?php echo $idInsumo; ?>">
                                        <i class="bi bi-printer"></i> Etiqueta
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="alert-message info">
            <p><i class="bi bi-info-circle"></i> No hay insumos activos para imprimir.</p>
        </div>
    <?php endif; ?>
</div>

<script src="js/etiquetas-print.js"></script>
<script>
(function () {
    function recogerItemsSeleccionados() {
        var items = [];
        document.querySelectorAll('.etiqueta-insumo-check:checked').forEach(function (chk) {
            var id = parseInt(chk.value, 10);
            if (!id) return;
            var inp = document.querySelector('.etiqueta-insumo-copias[data-id-insumo="' + id + '"]');
            var copias = inp ? parseInt(inp.value, 10) : 1;
            if (!copias || copias < 1) copias = 1;
            items.push({ id_insumo: id, copias: copias });
        });
        return items;
    }

    function recogerTodosVisibles() {
        var items = [];
        document.querySelectorAll('.etiqueta-insumo-check:not(:disabled)').forEach(function (chk) {
            var id = parseInt(chk.value, 10);
            if (!id) return;
            var inp = document.querySelector('.etiqueta-insumo-copias[data-id-insumo="' + id + '"]');
            var copias = inp ? parseInt(inp.value, 10) : 1;
            if (!copias || copias < 1) copias = 1;
            items.push({ id_insumo: id, copias: copias });
        });
        return items;
    }

    function actualizarBtnSeleccionadas() {
        var btn = document.getElementById('btn-etiquetas-insumo-seleccionadas');
        if (!btn) return;
        var n = document.querySelectorAll('.etiqueta-insumo-check:checked').length;
        btn.disabled = n === 0;
    }

    var checkAll = document.getElementById('etiquetas-insumo-check-all');
    if (checkAll) {
        checkAll.addEventListener('change', function () {
            var on = checkAll.checked;
            document.querySelectorAll('.etiqueta-insumo-check:not(:disabled)').forEach(function (c) {
                c.checked = on;
            });
            actualizarBtnSeleccionadas();
        });
    }
    document.querySelectorAll('.etiqueta-insumo-check').forEach(function (c) {
        c.addEventListener('change', actualizarBtnSeleccionadas);
    });

    var btnSel = document.getElementById('btn-etiquetas-insumo-seleccionadas');
    if (btnSel && window.JoyeriaEtiquetasPrint) {
        btnSel.addEventListener('click', function () {
            var items = recogerItemsSeleccionados();
            if (!items.length) {
                alert('Selecciona al menos un insumo con SKU.');
                return;
            }
            window.JoyeriaEtiquetasPrint.encolarInsumosItems(items);
        });
    }

    var btnTodas = document.getElementById('btn-etiquetas-insumo-todas');
    if (btnTodas && window.JoyeriaEtiquetasPrint) {
        btnTodas.addEventListener('click', function () {
            var items = recogerTodosVisibles();
            if (!items.length) {
                alert('No hay insumos con SKU en esta lista.');
                return;
            }
            if (!confirm('Encolar etiquetas para ' + items.length + ' insumo(s) visibles?')) {
                return;
            }
            window.JoyeriaEtiquetasPrint.encolarInsumosItems(items);
        });
    }
})();
</script>
