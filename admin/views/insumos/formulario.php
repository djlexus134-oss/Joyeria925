<?php
$esEdicion = isset($insumo) && !empty($insumo);
$titulo = $esEdicion ? 'Editar Insumo' : 'Nuevo Insumo';
$accionForm = $esEdicion
    ? 'insumos.php?accion=actualizar&id=' . urlencode((string) $insumo['id_insumo'])
    : 'insumos.php?accion=crear';

$nombre = $_POST['nombre'] ?? ($esEdicion ? ($insumo['nombre'] ?? '') : '');
$id_categoria_FK = $_POST['id_categoria_FK'] ?? ($esEdicion ? ($insumo['id_categoria_FK'] ?? '') : '');
$sku_codigo = $_POST['sku_codigo'] ?? ($esEdicion ? ($insumo['sku_codigo'] ?? '') : '');
$costo_referencia = $_POST['costo_referencia'] ?? ($esEdicion ? ($insumo['costo_referencia'] ?? '') : '');
$aumento_pct = $_POST['aumento_pct'] ?? ($esEdicion ? ($insumo['aumento_pct'] ?? '') : '');
$precio_venta_sugerido = $_POST['precio_venta_sugerido'] ?? ($esEdicion ? ($insumo['precio_venta_sugerido'] ?? '') : '');
$observaciones = $_POST['observaciones'] ?? ($esEdicion ? ($insumo['observaciones'] ?? '') : '');

$existenciasPorTienda = $existenciasPorTienda ?? [];
?>

<div class="form-section">
    <h3>
        <i class="bi <?php echo $esEdicion ? 'bi-pencil-square' : 'bi-plus-circle'; ?>"></i>
        <?php echo htmlspecialchars($titulo); ?>
    </h3>

    <?php if (isset($mensaje) && !empty($mensaje)): ?>
        <div class="alert-message info">
            <p><i class="bi bi-info-circle"></i> <?php echo htmlspecialchars($mensaje); ?></p>
        </div>
    <?php endif; ?>

    <?php if (!$esEdicion || !empty($insumo)): ?>
        <form action="<?php echo htmlspecialchars($accionForm); ?>" method="POST" class="admin-form" id="form-insumo">
            <?php if ($esEdicion): ?>
                <input type="hidden" name="existencias_sincronizar" value="1">
            <?php endif; ?>
            <fieldset class="form-fieldset">
                <legend><i class="bi bi-droplet-half"></i> Datos generales</legend>
                <div class="form-row">
                    <div class="form-group">
                        <label for="nombre"><i class="bi bi-card-text"></i> Nombre:<span class="required">*</span></label>
                        <input type="text" class="form-input" name="nombre" id="nombre" maxlength="150"
                               value="<?php echo htmlspecialchars((string) $nombre); ?>" required autofocus>
                    </div>
                    <div class="form-group">
                        <label for="id_categoria_FK"><i class="bi bi-tag"></i> Categoria:</label>
                        <div style="display:flex;gap:10px;align-items:flex-end;">
                            <div style="flex:1 1 auto;min-width:220px;">
                                <select class="form-input" name="id_categoria_FK" id="id_categoria_FK">
                                    <option value="">-- Sin categoria --</option>
                                    <?php foreach (($catalogos['categorias'] ?? []) as $cat): ?>
                                        <option value="<?php echo (int) $cat['id_categoria']; ?>"
                                            <?php echo ((string) $id_categoria_FK === (string) $cat['id_categoria']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars((string) $cat['nombre']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="button" class="btn-action-secondary" id="btn-nueva-categoria" style="white-space:nowrap;">
                                <i class="bi bi-plus-circle"></i> Nueva
                            </button>
                        </div>
                        <small class="form-hint"><i class="bi bi-info-circle"></i> Escribe para buscar; si no existe, crea una nueva.</small>
                    </div>
                </div>

                <div class="form-row">
                    <?php if ($esEdicion): ?>
                        <div class="form-group">
                            <label for="sku_codigo"><i class="bi bi-upc-scan"></i> SKU (POS):</label>
                            <input type="text" class="form-input" id="sku_codigo" maxlength="50"
                                   value="<?php echo htmlspecialchars((string) $sku_codigo); ?>"
                                   readonly disabled>
                            <small class="form-hint"><i class="bi bi-info-circle"></i> Codigo generado automaticamente al crear el insumo.</small>
                        </div>
                    <?php else: ?>
                        <div class="form-group">
                            <label><i class="bi bi-upc-scan"></i> SKU (POS):</label>
                            <p class="form-hint" style="margin:0;padding:10px 0;">
                                <i class="bi bi-info-circle"></i> Se generara automaticamente al guardar (formato <code>ID/1</code>, ej. <code>42/1</code>).
                            </p>
                        </div>
                    <?php endif; ?>
                    <div class="form-group">
                        <label for="costo_referencia"><i class="bi bi-currency-dollar"></i> Costo referencia:</label>
                        <input type="number" class="form-input" name="costo_referencia" id="costo_referencia" step="0.01" min="0"
                               value="<?php echo htmlspecialchars((string) $costo_referencia); ?>">
                    </div>
                    <div class="form-group">
                        <label for="aumento_pct"><i class="bi bi-graph-up-arrow"></i> Aumento (%):</label>
                        <input type="number" class="form-input" name="aumento_pct" id="aumento_pct" step="1" min="0"
                               value="<?php echo htmlspecialchars((string) $aumento_pct); ?>"
                               placeholder="Ej. 80">
                        <small class="form-hint"><i class="bi bi-info-circle"></i> Se redondea a múltiplos de 5.</small>
                    </div>
                    <div class="form-group">
                        <label for="precio_venta_sugerido"><i class="bi bi-cash-coin"></i> PVP sugerido:</label>
                        <input type="number" class="form-input" name="precio_venta_sugerido" id="precio_venta_sugerido" step="0.01" min="0"
                               value="<?php echo htmlspecialchars((string) $precio_venta_sugerido); ?>">
                        <small class="form-hint"><i class="bi bi-info-circle"></i> Si hay costo + aumento, se calcula y se redondea a múltiplos de 5.</small>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group" style="flex: 1 1 100%;">
                        <label for="observaciones"><i class="bi bi-chat-left-text"></i> Observaciones:</label>
                        <textarea class="form-input" name="observaciones" id="observaciones" maxlength="500" rows="3"><?php echo htmlspecialchars((string) $observaciones); ?></textarea>
                    </div>
                </div>
            </fieldset>

            <fieldset class="form-fieldset">
                <legend><i class="bi bi-shop"></i> Existencia por tienda</legend>
                <p class="form-hint">Agrega tiendas según necesites. Deja en blanco la cantidad si no quieres modificar esa tienda.
                    <?php if ($esEdicion): ?> Al guardar, si quitas una fila de tienda, se elimina su registro de existencia en base de datos.<?php endif; ?>
                </p>

                <div id="existencias-container" style="display:flex;flex-direction:column;gap:10px;"></div>

                <div class="form-actions" style="justify-content:flex-start;margin-top:12px;">
                    <button type="button" class="btn-action-secondary" id="btn-agregar-existencia">
                        <i class="bi bi-plus-lg"></i> Agregar tienda
                    </button>
                </div>
            </fieldset>

            <div class="form-actions">
                <button type="submit" class="btn-action-primary"><?php echo $esEdicion ? 'Guardar' : 'Crear'; ?></button>
                <a href="insumos.php?accion=leer" class="btn-action-secondary">Volver</a>
            </div>
        </form>
    <?php else: ?>
        <div class="alert-message info"><p>Insumo no encontrado.</p></div>
        <a href="insumos.php?accion=leer" class="btn-action-secondary">Volver</a>
    <?php endif; ?>
</div>

<?php
$tiendasJs = $catalogos['tiendas'] ?? [];
$existMap = [];
if (isset($_POST['existencia']) && is_array($_POST['existencia'])) {
    foreach ($_POST['existencia'] as $k => $v) {
        $tid = (int) $k;
        $existMap[$tid] = (string) $v;
    }
} else {
    foreach (($existenciasPorTienda ?? []) as $k => $v) {
        $tid = (int) $k;
        $existMap[$tid] = (string) $v;
    }
}
?>

<script src="js/fk-autocomplete.js"></script>

<script>
    (function () {
        var tiendas = <?php echo json_encode(array_values($tiendasJs), JSON_UNESCAPED_UNICODE); ?>;
        var existenciasIniciales = <?php echo json_encode($existMap, JSON_UNESCAPED_UNICODE); ?>;
        var existenciaRowSeq = 0;

        var container = document.getElementById('existencias-container');
        var btnAdd = document.getElementById('btn-agregar-existencia');
        var categoriaSelect = document.getElementById('id_categoria_FK');
        var btnNuevaCategoria = document.getElementById('btn-nueva-categoria');

        var costoInput = document.getElementById('costo_referencia');
        var aumentoInput = document.getElementById('aumento_pct');
        var pvpInput = document.getElementById('precio_venta_sugerido');

        if (!container) { return; }

        function el(tag, attrs) {
            var n = document.createElement(tag);
            if (attrs) {
                Object.keys(attrs).forEach(function (k) {
                    if (k === 'text') { n.textContent = attrs[k]; return; }
                    n.setAttribute(k, attrs[k]);
                });
            }
            return n;
        }

        function crearFilaExistencia(preselectId, cantidad) {
            var row = el('div', { class: 'form-row', 'data-row': '1' });
            row.style.display = 'grid';
            row.style.gridTemplateColumns = '1fr 180px 40px';
            row.style.gap = '10px';
            row.style.alignItems = 'end';

            var fgTienda = el('div', { class: 'form-group' });
            var labelT = el('label', { text: 'Tienda:' });
            var select = el('select', { class: 'form-input' });
            existenciaRowSeq += 1;
            select.id = 'existencia_tienda_' + existenciaRowSeq;
            select.appendChild(el('option', { value: '', text: '-- Selecciona tienda --' }));
            tiendas.forEach(function (t) {
                var opt = el('option', { value: String(t.id_tienda), text: t.nom_tienda });
                select.appendChild(opt);
            });
            fgTienda.appendChild(labelT);
            fgTienda.appendChild(select);

            var fgCant = el('div', { class: 'form-group' });
            var labelC = el('label', { text: 'Cantidad:' });
            var input = el('input', { class: 'form-input', type: 'number', step: '0.001', min: '0', value: (cantidad || '') });
            fgCant.appendChild(labelC);
            fgCant.appendChild(input);

            var btnDel = el('button', { type: 'button', class: 'btn-remove-existencia', title: 'Quitar tienda' });
            btnDel.innerHTML = '<i class="bi bi-x-lg"></i>';
            btnDel.addEventListener('click', function () {
                row.remove();
            });

            function tiendaYaSeleccionada(idTienda) {
                if (!idTienda) {
                    return false;
                }
                var rows = container.querySelectorAll('[data-row="1"]');
                for (var i = 0; i < rows.length; i++) {
                    var other = rows[i];
                    if (other === row) {
                        continue;
                    }
                    var sel = other.querySelector('select');
                    if (!sel) {
                        continue;
                    }
                    if (String(sel.value || '') === String(idTienda)) {
                        return true;
                    }
                }
                return false;
            }

            function syncName() {
                var v = select.value ? String(select.value) : '';
                if (!v) {
                    input.name = '';
                    return;
                }
                if (tiendaYaSeleccionada(v)) {
                    alert('Esa tienda ya esta agregada en otra fila.');
                    select.value = '';
                    v = '';
                }
                if (!v) {
                    input.name = '';
                    return;
                }
                input.name = 'existencia[' + v + ']';
            }
            select.addEventListener('change', syncName);
            syncName();

            row.appendChild(fgTienda);
            row.appendChild(fgCant);
            row.appendChild(btnDel);

            if (window.JoyeriaFkAutocomplete) {
                var auto = JoyeriaFkAutocomplete.initSelectAutocomplete({
                    selectId: select.id,
                    allowEmpty: true,
                    placeholder: 'Buscar tienda...'
                });
                if (preselectId) {
                    select.value = String(preselectId);
                    if (auto) {
                        auto.refresh();
                    }
                    syncName();
                }
            } else if (preselectId) {
                select.value = String(preselectId);
                syncName();
            }

            return row;
        }

        // Inicial: si hay existencias ya guardadas, precargar; si no, arrancar con una fila vacía.
        var any = false;
        Object.keys(existenciasIniciales || {}).forEach(function (k) {
            var tid = parseInt(k, 10);
            if (!isFinite(tid) || tid <= 0) { return; }
            any = true;
            container.appendChild(crearFilaExistencia(tid, existenciasIniciales[k]));
        });
        if (!any) {
            container.appendChild(crearFilaExistencia('', ''));
        }

        if (btnAdd) {
            btnAdd.addEventListener('click', function () {
                container.appendChild(crearFilaExistencia('', ''));
            });
        }

        var formInsumo = document.getElementById('form-insumo');
        if (formInsumo) {
            formInsumo.addEventListener('submit', function (e) {
                var seen = {};
                var rows = container.querySelectorAll('[data-row="1"]');
                for (var r = 0; r < rows.length; r++) {
                    var sel = rows[r].querySelector('select');
                    if (!sel) {
                        continue;
                    }
                    var tid = String(sel.value || '').trim();
                    if (!tid) {
                        continue;
                    }
                    if (seen[tid]) {
                        e.preventDefault();
                        alert('Hay tiendas repetidas en existencia. Corrige las filas antes de guardar.');
                        return false;
                    }
                    seen[tid] = true;
                }
            });
        }

        // Categoria: autocompletable (remote) + modal para crear.
        var categoriaAuto = null;
        if (categoriaSelect && window.JoyeriaFkAutocomplete) {
            categoriaAuto = JoyeriaFkAutocomplete.initSelectAutocomplete({
                selectId: 'id_categoria_FK',
                allowEmpty: true,
                placeholder: 'Buscar categoria...'
            });
        }

        function abrirNuevaCategoria() {
            var nombre = prompt('Nombre de la nueva categoria:');
            if (!nombre) { return; }
            fetch('api/insumo_categorias.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ nombre: nombre })
            })
                .then(function (r) { return r.json(); })
                .then(function (json) {
                    if (!json || json.success !== true || !json.data) {
                        alert('No se pudo crear la categoria.');
                        return;
                    }
                    var opt = document.createElement('option');
                    opt.value = String(json.data.id_categoria);
                    opt.textContent = String(json.data.nombre || nombre);
                    categoriaSelect.appendChild(opt);
                    categoriaSelect.value = String(json.data.id_categoria);
                    if (categoriaAuto) {
                        categoriaAuto.refresh();
                    }
                })
                .catch(function () {
                    alert('No se pudo crear la categoria.');
                });
        }

        if (btnNuevaCategoria) {
            btnNuevaCategoria.addEventListener('click', abrirNuevaCategoria);
        }

        // Aumento%: redondear a múltiplos de 5 y recalcular PVP (redondeado a múltiplos de 5 hacia arriba).
        function redondear5(n) {
            return Math.round(n / 5) * 5;
        }
        function ceil5(n) {
            return Math.ceil(n / 5) * 5;
        }
        function fmt2(n) {
            return (Math.round(n * 100) / 100).toFixed(2);
        }
        function recalcularPvp() {
            if (!costoInput || !aumentoInput || !pvpInput) { return; }
            var costo = parseFloat(costoInput.value);
            var aum = parseFloat(aumentoInput.value);
            if (!isFinite(costo) || costo < 0) { return; }
            if (!isFinite(aum) || aum < 0) { aum = 0; }
            var pv = costo * (1 + aum / 100);
            if (pv > 0) {
                pv = ceil5(pv);
            }
            pvpInput.value = fmt2(pv);
        }
        if (aumentoInput) {
            aumentoInput.addEventListener('blur', function () {
                var v = parseFloat(aumentoInput.value);
                if (!isFinite(v) || v < 0) { v = 0; }
                aumentoInput.value = String(redondear5(v));
                recalcularPvp();
            });
            ['input', 'change'].forEach(function (evt) {
                aumentoInput.addEventListener(evt, recalcularPvp);
            });
        }
        if (costoInput) {
            ['input', 'change'].forEach(function (evt) {
                costoInput.addEventListener(evt, recalcularPvp);
            });
        }
    })();
</script>
