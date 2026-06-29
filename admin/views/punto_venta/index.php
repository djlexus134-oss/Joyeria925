<?php
$clientes = $catalogos['clientes'] ?? [];
$impuestos = $catalogos['impuestos'] ?? [];
$formasPago = $formasPago ?? [];
$estadoPos = $estado ?? [];
$detalles = isset($estadoPos['detalles']) && is_array($estadoPos['detalles']) ? $estadoPos['detalles'] : [];
?>

<div class="form-section">
    <h3><i class="bi bi-shop"></i> Ticket actual</h3>
    <div id="pos-pausadas-wrap" class="alert-message info" style="display:none;margin-bottom:0.75rem;">
        <p style="margin:0 0 0.5rem 0;">
            <i class="bi bi-pause-circle"></i>
            Ventas en espera (<span id="pos-pausadas-count">0</span>)
        </p>
        <p class="text-muted" style="margin:0 0 0.5rem 0;font-size:0.9rem;">
            Retoma una venta guardada o eliminala si ya no la necesitas.
        </p>
        <div id="pos-pausadas-list"></div>
    </div>
    <div id="pos_impresion_status" class="alert-message info" style="display:none;margin-bottom:0.75rem;"><p><i class="bi bi-printer"></i> <span id="pos_impresion_status_text"></span></p></div>

    <div class="form-row">
        <div class="form-group">
            <label for="id_cliente_FK"><i class="bi bi-person"></i> Cliente (opcional):</label>
            <select class="form-input" id="id_cliente_FK" name="id_cliente_FK">
                <option value="">Público general</option>
                <?php
                $selectedId = $estadoPos['id_cliente'] ?? '';
                $includeEmpty = false;
                require __DIR__ . '/../partials/cliente_select_options.php';
                ?>
            </select>
            <div id="pos_credito_cliente_box" class="alert-message info" style="display:none;margin-top:0.5rem;padding:0.5rem 0.75rem;">
                <p style="margin:0;">
                    <i class="bi bi-wallet2"></i>
                    Crédito a favor disponible:
                    <strong>$<span id="pos_credito_cliente_saldo">0.00</span></strong>
                </p>
            </div>
        </div>
        <div class="form-group">
            <label for="id_impuesto_FK"><i class="bi bi-percent"></i> Impuesto:</label>
            <select class="form-input" id="id_impuesto_FK" name="id_impuesto_FK" required>
                <?php
                $idImpuestoSesion = isset($estadoPos['id_impuesto']) ? (string) $estadoPos['id_impuesto'] : '';
                foreach ($impuestos as $impuesto):
                    $idImpOpt = (int) $impuesto['id_impuesto'];
                    $selImpuestoPos = ($idImpuestoSesion !== '' && $idImpuestoSesion === (string) $idImpOpt)
                        || ($idImpuestoSesion === '' && !empty($idImpuestoDefault) && (int) $idImpuestoDefault === $idImpOpt);
                ?>
                    <option value="<?php echo $idImpOpt; ?>" <?php echo $selImpuestoPos ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars((string) $impuesto['tipo_impuesto']); ?> (<?php echo htmlspecialchars((string) $impuesto['porcentaje']); ?>%)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="id_tienda_FK"><i class="bi bi-shop-window"></i> Tienda (para insumos):</label>
            <select class="form-input" id="id_tienda_FK" name="id_tienda_FK">
                <option value="">Auto</option>
                <?php foreach (($tiendas ?? []) as $tienda): ?>
                    <option value="<?php echo (int) $tienda['id_tienda']; ?>" <?php echo ((string) ($estadoPos['id_tienda'] ?? '') === (string) $tienda['id_tienda']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars((string) $tienda['nom_tienda']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label><i class="bi bi-person-badge"></i> Empleado en sesión:</label>
            <input type="text" class="form-input" value="#<?php echo (int) $idEmpleadoSesion; ?>" disabled>
        </div>
    </div>

    <div class="form-row">
        <div class="form-group" style="flex: 1 1 70%;">
            <label for="codigo_busqueda"><i class="bi bi-upc-scan"></i> Código (barras/auxiliar/SKU):</label>
            <div style="display:flex; gap:8px; align-items:stretch;">
                <input type="text" id="codigo_busqueda" class="form-input joyeria-barcode-input" style="flex:1 1 auto;" placeholder="Escanea o escribe (ej. 28488/97) y Agregar" autofocus autocomplete="off">
                <button type="button" class="btn-action-secondary" id="btn_escanear_camara" title="Escanear con cámara" style="white-space:nowrap; min-width:48px;">
                    <i class="bi bi-camera"></i><span class="pos-scan-label"> Escanear</span>
                </button>
            </div>
        </div>
        <div class="form-group" style="display:flex; gap:8px; align-items:flex-end;">
            <button type="button" class="btn-action-primary" id="btn_agregar"><i class="bi bi-plus-lg"></i> Agregar</button>
            <button type="button" class="btn-action-danger" id="btn_limpiar" title="Descartar el ticket actual sin guardarlo (F2)">
                <i class="bi bi-trash"></i> Descartar
            </button>
        </div>
    </div>

    <div id="pos-mensaje"></div>

    <div class="admin-table-wrapper">
        <table class="admin-table" id="tabla-pos">
            <thead>
                <tr>
                    <th>Tipo</th>
                    <th>Código</th>
                    <th>Descripción</th>
                    <th>Cantidad</th>
                    <th>Precio unitario</th>
                    <th>Subtotal</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody id="tabla-pos-body">
                <?php if (!empty($detalles)): ?>
                    <?php foreach ($detalles as $idx => $linea): ?>
                        <?php
                        $cantidad = isset($linea['cantidad']) ? (float) $linea['cantidad'] : 0.0;
                        $precio = isset($linea['precio_unitario']) ? (float) $linea['precio_unitario'] : 0.0;
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars((string) ($linea['tipo_linea'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars((string) ($linea['codigo'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars((string) ($linea['descripcion'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars(number_format($cantidad, 3, '.', '')); ?></td>
                            <td>$<?php echo htmlspecialchars(number_format($precio, 2, '.', '')); ?></td>
                            <td>$<?php echo htmlspecialchars(number_format($cantidad * $precio, 2, '.', '')); ?></td>
                            <td>
                                <button type="button" class="btn-action-danger btn-eliminar-item" data-index="<?php echo (int) $idx; ?>">
                                    <i class="bi bi-trash"></i> Quitar
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="7">No hay productos agregados al ticket.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div id="pos-creditos-canje-wrap" class="form-section" style="margin-top:0.75rem;">
        <h4 style="margin:0 0 0.5rem 0; font-size:1rem;"><i class="bi bi-arrow-left-right"></i> Credito por devolucion (canje)</h4>
        <div id="pos-creditos-canje-inner" class="text-muted" style="font-size:0.95rem;">Sin creditos en este ticket. Arma la lista en Devoluciones abajo y pulsa «Aplicar créditos al ticket».</div>
    </div>

    <div class="form-row">
        <div class="form-group">
            <label>Conteo de piezas:</label>
            <input type="text" class="form-input" id="conteo_piezas" value="<?php echo htmlspecialchars((string) ($totalesIniciales['conteo_piezas'] ?? '0')); ?>" readonly>
        </div>
        <div class="form-group">
            <label>Subtotal:</label>
            <input type="text" class="form-input" id="subtotal" value="$<?php echo htmlspecialchars((string) ($totalesIniciales['subtotal'] ?? '0.00')); ?>" readonly>
        </div>
        <div class="form-group">
            <label>Descuento aplicado:</label>
            <input type="text" class="form-input" id="descuento" value="<?php echo htmlspecialchars((string) ($totalesIniciales['descuento_porcentaje'] ?? '0.00')); ?>% ($<?php echo htmlspecialchars((string) ($totalesIniciales['descuento_monto'] ?? '0.00')); ?>)" readonly>
        </div>
        <div class="form-group" id="pos_monto_credito_canje_wrap" style="<?php
            $mcIni = isset($totalesIniciales['monto_credito_canje']) ? (float) $totalesIniciales['monto_credito_canje'] : 0.0;
            echo $mcIni > 0.0001 ? '' : 'display:none;';
        ?>">
            <label>Descuento por canje:</label>
            <input type="text" class="form-input" id="pos_monto_credito_canje" value="$<?php echo htmlspecialchars((string) ($totalesIniciales['monto_credito_canje'] ?? '0.00')); ?>" readonly>
        </div>
        <div class="form-group">
            <label>Impuesto:</label>
            <input type="text" class="form-input" id="impuesto" value="<?php echo htmlspecialchars((string) ($totalesIniciales['impuesto_porcentaje'] ?? '0.00')); ?>% ($<?php echo htmlspecialchars((string) ($totalesIniciales['impuesto_monto'] ?? '0.00')); ?>)" readonly>
        </div>
        <div class="form-group">
            <label>Total:</label>
            <input type="text" class="form-input" id="total" value="$<?php echo htmlspecialchars((string) ($totalesIniciales['total'] ?? '0.00')); ?>" readonly>
        </div>
    </div>

    <fieldset class="form-fieldset">
        <legend><i class="bi bi-credit-card"></i> Pagos</legend>
        <div id="pagos-container" style="display:flex;flex-direction:column;gap:10px;"></div>
        <div class="form-actions" style="justify-content:flex-start;margin-top:12px;flex-wrap:wrap;gap:8px;">
            <button type="button" class="btn-action-secondary" id="btn_agregar_pago"><i class="bi bi-plus-lg"></i> Agregar pago</button>
            <button type="button" class="btn-action-secondary" id="btn_mostrar_qr_spei" style="display:none;" disabled title="Sin monto a depositar">
                <i class="bi bi-qr-code"></i> Mostrar QR transferencia
            </button>
        </div>
    </fieldset>

    <dialog id="modal-spei-deposito" class="pos-spei-modal">
        <div class="pos-spei-modal-inner">
            <h3 style="margin-top:0;"><i class="bi bi-bank"></i> Datos para transferencia SPEI</h3>
            <p class="text-muted pos-spei-modal-hint">Escanea el codigo o copia la CLABE en tu app bancaria.</p>
            <div id="spei-qr-canvas-wrap" class="pos-spei-qr-wrap">
                <canvas id="spei-qr-canvas" width="220" height="220" aria-label="Codigo QR de transferencia"></canvas>
            </div>
            <div id="spei-datos-resumen" class="pos-spei-datos"></div>
            <div class="form-actions pos-spei-modal-actions">
                <button type="button" class="btn-action-secondary" id="btn-spei-copiar-clabe"><i class="bi bi-clipboard"></i> Copiar CLABE</button>
                <button type="button" class="btn-action-secondary" id="btn-spei-copiar-todo"><i class="bi bi-clipboard-check"></i> Copiar todo</button>
                <button type="button" class="btn-action-primary" id="btn-spei-cerrar">Cerrar</button>
            </div>
        </div>
    </dialog>

    <div class="form-actions" style="flex-wrap:wrap; gap:8px;">
        <button type="button" class="btn-action-primary" id="btn_confirmar"><i class="bi bi-check-lg"></i> Confirmar venta</button>
        <button type="button" class="btn-action-secondary" id="btn_pausar_y_nueva" title="Guardar este ticket en espera y abrir uno nuevo (F3)">
            <i class="bi bi-pause-circle"></i> Pausar y nueva
        </button>
        <button type="button" class="btn-action-danger" id="btn_nueva_venta" title="Descartar ticket actual (F2)">
            <i class="bi bi-trash"></i> Descartar ticket
        </button>
    </div>
</div>

<?php if (!empty($mostrarPanelDevoluciones)): ?>
    <?php require __DIR__ . '/devoluciones_panel.php'; ?>
<?php endif; ?>

<script src="js/pos-scan-feedback.js"></script>
<script src="js/pos-stock-alert.js"></script>
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.4/build/qrcode.min.js"></script>
<script src="js/pos-barcode-scanner.js"></script>
<script src="js/pos-spei-qr.js"></script>
<script src="js/fk-autocomplete.js"></script>
<script>
(function () {
    function qs(id) { return document.getElementById(id); }

    function formatDescuentoPosTotales(totales) {
        if (!totales) {
            return '0.00% ($0.00)';
        }
        var monto = totales.descuento_monto || '0.00';
        if (totales.ticket_mixto) {
            var pctPiezas = totales.descuento_porcentaje_piezas || '0.00';
            var pctInsumos = totales.descuento_porcentaje_insumos || '0.00';
            return 'Piezas: ' + pctPiezas + '% · Insumos: ' + pctInsumos + '% ($' + monto + ')';
        }
        var pct = totales.descuento_porcentaje_piezas != null && parseFloat(totales.subtotal_insumos || 0) > 0.0001
            && parseFloat(totales.subtotal_piezas || 0) <= 0.0001
            ? (totales.descuento_porcentaje_insumos || totales.descuento_porcentaje)
            : (totales.descuento_porcentaje_piezas != null ? totales.descuento_porcentaje_piezas : totales.descuento_porcentaje);
        return pct + '% ($' + monto + ')';
    }
    var selCliente = qs('id_cliente_FK');
    var selImpuesto = qs('id_impuesto_FK');
    var selTienda = qs('id_tienda_FK');
    var txtCodigo = qs('codigo_busqueda');
    var tablaBody = qs('tabla-pos-body');
    var mensajeWrap = qs('pos-mensaje');
    var pagosContainer = qs('pagos-container');
    var formasPago = <?php echo json_encode($formasPago, JSON_UNESCAPED_UNICODE); ?>;
    var idFormaPagoCfg = <?php echo json_encode($idFormaPagoDefault ?? null, JSON_UNESCAPED_UNICODE); ?>;
    var idImpuestoCfg = <?php echo json_encode($idImpuestoDefault ?? null, JSON_UNESCAPED_UNICODE); ?>;
    var datosDepositoSpei = <?php echo json_encode($datosDepositoSpei ?? ['habilitado' => false], JSON_UNESCAPED_UNICODE); ?>;
    var catalogoImpuestosPos = <?php echo json_encode($impuestos, JSON_UNESCAPED_UNICODE); ?>;
    var FORMA_PAGO_CREDITO_CLIENTE_LABEL = 'Crédito a favor cliente';
    var posCreditoClienteSaldo = 0;

    function obtenerIdFormaPagoCreditoCliente() {
        for (var i = 0; i < (formasPago || []).length; i++) {
            if (String(formasPago[i].forma_pago || '') === FORMA_PAGO_CREDITO_CLIENTE_LABEL) {
                return String(formasPago[i].id_forma_pago);
            }
        }
        return '';
    }

    function refrescarCreditoCliente() {
        var idCli = (selCliente.value || '').trim();
        var box = qs('pos_credito_cliente_box');
        var lbl = qs('pos_credito_cliente_saldo');
        posCreditoClienteSaldo = 0;
        if (!idCli) {
            if (box) box.style.display = 'none';
            sincronizarTopesCreditoClienteEnPagos();
            return Promise.resolve(0);
        }
        return fetch('api/clientes_creditos.php?id_cliente=' + encodeURIComponent(idCli) + '&estado=disponible', {
            credentials: 'same-origin'
        })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res && res.success && res.data) {
                    var saldo = parseFloat(res.data.total_disponible || 0);
                    posCreditoClienteSaldo = isFinite(saldo) ? saldo : 0;
                }
                if (box && lbl) {
                    if (posCreditoClienteSaldo > 0.0001) {
                        box.style.display = '';
                        lbl.textContent = posCreditoClienteSaldo.toFixed(2);
                    } else {
                        box.style.display = 'none';
                    }
                }
                sincronizarTopesCreditoClienteEnPagos();
                return posCreditoClienteSaldo;
            })
            .catch(function () {
                if (box) box.style.display = 'none';
                sincronizarTopesCreditoClienteEnPagos();
                return 0;
            });
    }

    function sumarMontosCreditoClienteExcepto(filaExcluida) {
        var idCC = obtenerIdFormaPagoCreditoCliente();
        if (!idCC) return 0;
        var rows = pagosContainer.querySelectorAll('.form-row');
        var s = 0;
        for (var i = 0; i < rows.length; i++) {
            if (filaExcluida && rows[i] === filaExcluida) continue;
            var sel = rows[i].querySelector('.pos-forma-pago');
            var mon = rows[i].querySelector('.pos-monto-pago');
            if (sel && mon && String(sel.value) === String(idCC)) {
                var v = parseFloat(mon.value || '0');
                if (isFinite(v) && v > 0) s += v;
            }
        }
        return s;
    }

    function sincronizarTopesCreditoClienteEnPagos() {
        var idCC = obtenerIdFormaPagoCreditoCliente();
        if (!idCC) return;
        var rows = pagosContainer.querySelectorAll('.form-row');
        for (var i = 0; i < rows.length; i++) {
            var sel = rows[i].querySelector('.pos-forma-pago');
            var mon = rows[i].querySelector('.pos-monto-pago');
            if (sel && mon && String(sel.value) === String(idCC)) {
                var usadoEnOtros = sumarMontosCreditoClienteExcepto(rows[i]);
                var tope = Math.max(0, posCreditoClienteSaldo - usadoEnOtros);
                mon.max = tope.toFixed(2);
                var v = parseFloat(mon.value || '0');
                if (!isFinite(v) || v < 0) v = 0;
                if (v > tope) {
                    mon.value = tope.toFixed(2);
                }
            }
        }
    }

    if (window.JoyeriaFkAutocomplete) {
        JoyeriaFkAutocomplete.initSelectAutocomplete({ selectId: 'id_cliente_FK', allowEmpty: true, placeholder: 'Nombre, apellido, correo o teléfono...' });
        JoyeriaFkAutocomplete.initSelectAutocomplete({ selectId: 'id_impuesto_FK', allowEmpty: false, placeholder: 'Buscar impuesto...' });
        JoyeriaFkAutocomplete.initSelectAutocomplete({ selectId: 'id_tienda_FK', allowEmpty: true, placeholder: 'Buscar tienda...' });
    }

    function showMessage(text, type) {
        mensajeWrap.innerHTML = '<div class="alert-message ' + (type || 'info') + '"><p>' + text + '</p></div>';
    }

    function showPosError(err, fallbackMsg) {
        var msg = (err && err.message) ? err.message : (fallbackMsg || 'Error');
        if (window.JoyeriaPosStockAlert && JoyeriaPosStockAlert.showIfStockError(err, { focusCodigo: true })) {
            return;
        }
        showMessage(msg, 'error');
    }

    function escHtmlPos(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function payloadConMetaYPagos(extra) {
        var base = {
            id_cliente_FK: selCliente ? (selCliente.value || '') : '',
            id_impuesto_FK: selImpuesto ? (selImpuesto.value || '') : '',
            id_tienda_FK: selTienda ? (selTienda.value || '') : '',
            pagos: JSON.stringify(obtenerPagosPayload())
        };
        if (!extra) {
            return base;
        }
        Object.keys(extra).forEach(function (k) {
            base[k] = extra[k];
        });
        return base;
    }

    function restaurarPagosBorrador(pagos) {
        pagosContainer.innerHTML = '';
        if (!pagos || !pagos.length) {
            pagosContainer.appendChild(crearFilaPago(true));
            return;
        }
        pagos.forEach(function (p, idx) {
            var row = crearFilaPago(idx === 0);
            var sel = row.querySelector('.pos-forma-pago');
            var mon = row.querySelector('.pos-monto-pago');
            if (sel && p.id_forma_pago_FK) {
                sel.value = String(p.id_forma_pago_FK);
            }
            if (mon && p.monto !== undefined && p.monto !== null) {
                mon.value = String(p.monto);
            }
            pagosContainer.appendChild(row);
        });
        sincronizarTopesCreditoClienteEnPagos();
    }

    function renderPausadasLista(pausadas) {
        var wrap = qs('pos-pausadas-wrap');
        var list = qs('pos-pausadas-list');
        var badge = qs('pos-pausadas-count');
        if (!wrap || !list) {
            return;
        }
        var items = Array.isArray(pausadas) ? pausadas : [];
        if (badge) {
            badge.textContent = String(items.length);
        }
        if (items.length === 0) {
            wrap.style.display = 'none';
            list.innerHTML = '';
            return;
        }
        wrap.style.display = '';
        var html = '<ul style="list-style:none;padding:0;margin:0;">';
        items.forEach(function (p) {
            var etiqueta = escHtmlPos(p.etiqueta || 'Venta en espera');
            var total = escHtmlPos(p.total || '0.00');
            var piezas = parseInt(p.conteo_piezas || 0, 10);
            var id = escHtmlPos(p.id || '');
            html += '<li style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;justify-content:space-between;padding:8px 0;border-bottom:1px solid rgba(0,0,0,.08);">'
                + '<div><strong>' + etiqueta + '</strong><br><small class="text-muted">'
                + piezas + ' pieza(s) · Total $' + total + '</small></div>'
                + '<div style="display:flex;gap:6px;flex-wrap:wrap;">'
                + '<button type="button" class="btn-action-primary btn-pos-retomar" data-id="' + id + '"><i class="bi bi-play-circle"></i> Retomar</button>'
                + '<button type="button" class="btn-action-danger btn-pos-eliminar-pausada" data-id="' + id + '"><i class="bi bi-trash"></i></button>'
                + '</div></li>';
        });
        html += '</ul>';
        list.innerHTML = html;
    }

    function obtenerFormaEfectivoId() {
        for (var i = 0; i < (formasPago || []).length; i++) {
            var nombre = String(formasPago[i].forma_pago || '').toLowerCase();
            if (nombre.indexOf('efectivo') !== -1) {
                return String(formasPago[i].id_forma_pago);
            }
        }
        return '';
    }

    function obtenerIdFormaPagoPredeterminada() {
        if (idFormaPagoCfg !== null && idFormaPagoCfg !== undefined && idFormaPagoCfg !== '') {
            var sid = String(idFormaPagoCfg);
            for (var j = 0; j < (formasPago || []).length; j++) {
                if (String(formasPago[j].id_forma_pago) === sid) {
                    return sid;
                }
            }
        }
        return obtenerFormaEfectivoId();
    }

    function obtenerIdImpuestoPredeterminado() {
        if (idImpuestoCfg !== null && idImpuestoCfg !== undefined && idImpuestoCfg !== '') {
            var sid = String(idImpuestoCfg);
            for (var k = 0; k < (catalogoImpuestosPos || []).length; k++) {
                if (String(catalogoImpuestosPos[k].id_impuesto) === sid) {
                    return sid;
                }
            }
        }
        if ((catalogoImpuestosPos || []).length > 0) {
            return String(catalogoImpuestosPos[0].id_impuesto);
        }
        return '';
    }

    function aplicarImpuestoPredeterminadoSiFalta() {
        if (!selImpuesto) return;
        var idPred = obtenerIdImpuestoPredeterminado();
        if (idPred && !(selImpuesto.value || '').trim()) {
            selImpuesto.value = idPred;
        }
    }

    function syncFkDisplay(selectId) {
        var sel = qs(selectId);
        if (!sel) return;
        var display = qs(selectId + '_display');
        if (!display) return;
        var opt = sel.options[sel.selectedIndex];
        display.value = (opt && opt.value !== '') ? String(opt.textContent || '').trim() : '';
    }

    function aplicarMetadatosDesdeEstado(estado) {
        if (!estado) return;
        if (selCliente) {
            var idCli = estado.id_cliente;
            selCliente.value = (idCli !== null && idCli !== undefined && String(idCli) !== '')
                ? String(idCli)
                : '';
            syncFkDisplay('id_cliente_FK');
        }
        if (selTienda) {
            var idTienda = estado.id_tienda;
            selTienda.value = (idTienda !== null && idTienda !== undefined && String(idTienda) !== '')
                ? String(idTienda)
                : '';
            syncFkDisplay('id_tienda_FK');
        }
        if (selImpuesto) {
            var idImp = estado.id_impuesto;
            if (idImp !== null && idImp !== undefined && String(idImp) !== '') {
                selImpuesto.value = String(idImp);
            } else {
                aplicarImpuestoPredeterminadoSiFalta();
            }
            syncFkDisplay('id_impuesto_FK');
        }
    }

    /** Limpia campos de captura que no vienen del estado de sesion (nuevo ticket). */
    function limpiarExtrasTicketPos() {
        if (txtCodigo) {
            txtCodigo.value = '';
        }
        posCreditoClienteSaldo = 0;
        var boxCred = qs('pos_credito_cliente_box');
        if (boxCred) {
            boxCred.style.display = 'none';
        }
        if (typeof window.joyeriaPosLimpiarDevolucionesUi === 'function') {
            window.joyeriaPosLimpiarDevolucionesUi(true);
        }
    }

    function esTicketVacuo(estado) {
        if (!estado) return true;
        var detalles = Array.isArray(estado.detalles) ? estado.detalles : [];
        var creditos = Array.isArray(estado.creditos_canje) ? estado.creditos_canje : [];
        return detalles.length === 0 && creditos.length === 0;
    }

    function ticketTieneContenidoEnPantalla() {
        if (!tablaBody) return false;
        var filas = tablaBody.querySelectorAll('tr');
        if (filas.length === 0) {
            return false;
        }
        if (filas.length === 1) {
            var txt = (filas[0].textContent || '').trim();
            if (txt.indexOf('No hay productos') !== -1) {
                return false;
            }
        }
        return true;
    }

    function ticketTieneCreditosCanjeEnPantalla() {
        var credInner = qs('pos-creditos-canje-inner');
        if (!credInner) {
            return false;
        }
        return !!credInner.querySelector('.btn-pos-quitar-credito');
    }

    function ticketRequiereConfirmacionParaNuevaVenta() {
        if (ticketTieneContenidoEnPantalla() || ticketTieneCreditosCanjeEnPantalla()) {
            return true;
        }
        if (selCliente && (selCliente.value || '').trim() !== '') {
            return true;
        }
        if (obtenerTotalTicketNumerico() > 0.02) {
            return true;
        }
        var filasPago = pagosContainer.querySelectorAll('.form-row');
        for (var i = 0; i < filasPago.length; i++) {
            var mon = filasPago[i].querySelector('.pos-monto-pago');
            var v = mon ? parseFloat(mon.value || '0') : 0;
            if (isFinite(v) && v > 0.009) {
                return true;
            }
        }
        return false;
    }

    /** Deja la pantalla lista para capturar otra venta (ticket vacío en servidor). */
    function prepararUiTicketNuevo(res, opts) {
        opts = opts || {};
        actualizarVista(res);
        if (!Object.prototype.hasOwnProperty.call(res || {}, 'pagos_borrador')) {
            pagosContainer.innerHTML = '';
            pagosContainer.appendChild(crearFilaPago(true));
        }
        if (opts.limpiarMensaje) {
            mensajeWrap.innerHTML = '';
        }
        if (txtCodigo) {
            txtCodigo.value = '';
            txtCodigo.focus();
        }
        if (window.PosSpeiQr && typeof window.PosSpeiQr.actualizarEstadoBoton === 'function') {
            window.PosSpeiQr.actualizarEstadoBoton();
        }
    }

    function ejecutarNuevaVenta() {
        if (ticketRequiereConfirmacionParaNuevaVenta()) {
            if (!window.confirm(
                '¿Descartar el ticket actual?\n\n'
                + 'No se guardará en espera (productos, cliente, pagos o créditos de canje).'
            )) {
                return Promise.resolve(false);
            }
        }
        return postAction('limpiar', {})
            .then(function (res) {
                if (!res.ok) {
                    throw new Error(res.mensaje || 'No se pudo descartar el ticket.');
                }
                prepararUiTicketNuevo(res, { limpiarMensaje: true });
                showMessage('Ticket descartado. Listo para una venta nueva.', 'success');
                return true;
            })
            .catch(function (err) {
                showMessage(err.message || 'Error al descartar el ticket.', 'error');
                return false;
            });
    }

    function ejecutarPausarYNueva() {
        if (!ticketRequiereConfirmacionParaNuevaVenta()) {
            showMessage('Agrega productos, un cliente o créditos de canje antes de guardar en espera.', 'info');
            return Promise.resolve(false);
        }
        var etiqueta = window.prompt(
            'Nombre opcional para identificar esta venta en espera (ej. Cliente María):',
            ''
        );
        if (etiqueta === null) {
            return Promise.resolve(false);
        }
        return syncMeta()
            .then(function () {
                return postAction('pausar_y_nueva', payloadConMetaYPagos({ etiqueta: etiqueta }));
            })
            .then(function (res) {
                if (!res.ok) {
                    throw new Error(res.mensaje || 'No se pudo guardar la venta en espera.');
                }
                prepararUiTicketNuevo(res, { limpiarMensaje: true });
                showMessage(res.mensaje || 'Venta guardada en espera.', 'success');
                return true;
            })
            .catch(function (err) {
                showMessage(err.message || 'Error al pausar la venta.', 'error');
                return false;
            });
    }

    function ejecutarRetomarPausada(idPausada) {
        if (!idPausada) {
            return Promise.resolve(false);
        }
        var msg = '¿Retomar esta venta en espera?';
        if (ticketRequiereConfirmacionParaNuevaVenta()) {
            msg += '\n\nEl ticket actual también se guardará en espera automáticamente.';
        }
        if (!window.confirm(msg)) {
            return Promise.resolve(false);
        }
        return syncMeta()
            .then(function () {
                return postAction('retomar_pausada', payloadConMetaYPagos({ id_pausada: idPausada }));
            })
            .then(function (res) {
                if (!res.ok) {
                    throw new Error(res.mensaje || 'No se pudo retomar la venta.');
                }
                actualizarVista(res);
                refrescarCreditoCliente();
                if (txtCodigo) {
                    txtCodigo.focus();
                }
                showMessage(res.mensaje || 'Venta retomada.', 'success');
                return true;
            })
            .catch(function (err) {
                showMessage(err.message || 'Error al retomar la venta.', 'error');
                return false;
            });
    }

    function ejecutarEliminarPausada(idPausada) {
        if (!idPausada) {
            return Promise.resolve(false);
        }
        if (!window.confirm('¿Eliminar esta venta en espera? No se podrá recuperar.')) {
            return Promise.resolve(false);
        }
        return postAction('eliminar_pausada', { id_pausada: idPausada })
            .then(function (res) {
                if (!res.ok) {
                    throw new Error(res.mensaje || 'No se pudo eliminar.');
                }
                if (res.pausadas) {
                    renderPausadasLista(res.pausadas);
                }
                showMessage(res.mensaje || 'Eliminada.', 'info');
                return true;
            })
            .catch(function (err) {
                showMessage(err.message || 'Error al eliminar.', 'error');
                return false;
            });
    }

    function obtenerTotalActualNumerico() {
        var totalTxt = (qs('total').value || '').replace('$', '').trim();
        var n = parseFloat(totalTxt);
        return isFinite(n) && n > 0 ? n : 0;
    }

    function obtenerMontoTransferenciaPos() {
        var ids = (datosDepositoSpei && Array.isArray(datosDepositoSpei.ids_forma_transferencia))
            ? datosDepositoSpei.ids_forma_transferencia
            : [];
        if (!ids.length) {
            return 0;
        }
        var rows = pagosContainer.querySelectorAll('.form-row');
        var suma = 0;
        for (var i = 0; i < rows.length; i++) {
            var sel = rows[i].querySelector('.pos-forma-pago');
            var mon = rows[i].querySelector('.pos-monto-pago');
            var idForma = sel ? parseInt(sel.value || '0', 10) : 0;
            var montoNum = mon ? parseFloat(mon.value || '0') : 0;
            if (ids.indexOf(idForma) >= 0 && isFinite(montoNum) && montoNum > 0) {
                suma += montoNum;
            }
        }
        return suma;
    }

    function crearFilaPago(isPrimeraFila) {
        var row = document.createElement('div');
        row.className = 'form-row';
        row.style.display = 'grid';
        row.style.gridTemplateColumns = '1fr 180px 32px';
        row.style.gap = '10px';
        row.style.alignItems = 'end';

        var fgForma = document.createElement('div');
        fgForma.className = 'form-group';
        var lblForma = document.createElement('label');
        lblForma.textContent = 'Forma de pago:';
        var selForma = document.createElement('select');
        selForma.className = 'form-input pos-forma-pago';
        var optEmpty = document.createElement('option');
        optEmpty.value = '';
        optEmpty.textContent = '-- Selecciona --';
        selForma.appendChild(optEmpty);
        (formasPago || []).forEach(function (f) {
            var opt = document.createElement('option');
            opt.value = String(f.id_forma_pago);
            opt.textContent = String(f.forma_pago || '');
            selForma.appendChild(opt);
        });
        fgForma.appendChild(lblForma);
        fgForma.appendChild(selForma);

        var fgMonto = document.createElement('div');
        fgMonto.className = 'form-group';
        var lblMonto = document.createElement('label');
        lblMonto.textContent = 'Monto:';
        var inMonto = document.createElement('input');
        inMonto.type = 'number';
        inMonto.step = '0.01';
        inMonto.min = '0';
        inMonto.className = 'form-input pos-monto-pago';
        fgMonto.appendChild(lblMonto);
        fgMonto.appendChild(inMonto);

        var btnDel = document.createElement('button');
        btnDel.type = 'button';
        btnDel.className = 'btn-action-danger';
        btnDel.innerHTML = '<i class="bi bi-x-lg"></i>';
        btnDel.style.width = '30px';
        btnDel.style.height = '30px';
        btnDel.style.minWidth = '30px';
        btnDel.style.padding = '0';
        btnDel.style.display = 'inline-flex';
        btnDel.style.alignItems = 'center';
        btnDel.style.justifyContent = 'center';
        btnDel.style.lineHeight = '1';
        btnDel.addEventListener('click', function () {
            row.remove();
            sincronizarTopesCreditoClienteEnPagos();
        });

        var idCC = obtenerIdFormaPagoCreditoCliente();
        selForma.addEventListener('change', function () {
            if (idCC && String(selForma.value) === String(idCC)) {
                var idCli = (selCliente.value || '').trim();
                if (!idCli) {
                    alert('Para pagar con crédito a favor selecciona primero un cliente.');
                    selForma.value = obtenerIdFormaPagoPredeterminada() || '';
                    return;
                }
                if (posCreditoClienteSaldo <= 0) {
                    alert('El cliente no tiene crédito disponible.');
                    selForma.value = obtenerIdFormaPagoPredeterminada() || '';
                    return;
                }
                var usadoEnOtros = sumarMontosCreditoClienteExcepto(row);
                var tope = Math.max(0, posCreditoClienteSaldo - usadoEnOtros);
                var totalPend = obtenerTotalActualNumerico();
                var sugerido = Math.min(tope, totalPend > 0 ? totalPend : tope);
                inMonto.value = sugerido.toFixed(2);
                var restante = totalPend - sugerido;
                if (restante > 0.02) {
                    var filas = pagosContainer.querySelectorAll('.form-row');
                    var hayOtraConMonto = false;
                    for (var fi = 0; fi < filas.length; fi++) {
                        if (filas[fi] === row) continue;
                        var mo = filas[fi].querySelector('.pos-monto-pago');
                        var vv = mo ? parseFloat(mo.value || '0') : 0;
                        if (isFinite(vv) && vv > 0.009) {
                            hayOtraConMonto = true;
                            break;
                        }
                    }
                    if (!hayOtraConMonto) {
                        var row2 = crearFilaPago(false);
                        pagosContainer.appendChild(row2);
                        var sel2 = row2.querySelector('.pos-forma-pago');
                        var mon2 = row2.querySelector('.pos-monto-pago');
                        var idEf = obtenerFormaEfectivoId();
                        if (sel2 && idEf) sel2.value = idEf;
                        if (mon2) mon2.value = restante.toFixed(2);
                    }
                }
            }
            sincronizarTopesCreditoClienteEnPagos();
        });
        inMonto.addEventListener('input', function () {
            if (idCC && String(selForma.value) === String(idCC)) {
                var usadoEnOtros = sumarMontosCreditoClienteExcepto(row);
                var tope = Math.max(0, posCreditoClienteSaldo - usadoEnOtros);
                var v = parseFloat(inMonto.value || '0');
                if (isFinite(v) && v > tope) {
                    inMonto.value = tope.toFixed(2);
                }
            }
        });

        row.appendChild(fgForma);
        row.appendChild(fgMonto);
        row.appendChild(btnDel);
        var idPred = obtenerIdFormaPagoPredeterminada();
        if (idPred) {
            selForma.value = idPred;
        }
        if (isPrimeraFila) {
            var totalInicial = obtenerTotalActualNumerico();
            if (totalInicial > 0) {
                inMonto.value = totalInicial.toFixed(2);
            }
        }

        return row;
    }

    function obtenerPagosPayload() {
        var rows = pagosContainer.querySelectorAll('.form-row');
        var pagos = [];
        for (var i = 0; i < rows.length; i++) {
            var sel = rows[i].querySelector('.pos-forma-pago');
            var monto = rows[i].querySelector('.pos-monto-pago');
            var idForma = sel ? parseInt(sel.value || '0', 10) : 0;
            var montoNum = monto ? parseFloat(monto.value || '0') : 0;
            if (idForma > 0 && isFinite(montoNum) && montoNum > 0) {
                pagos.push({ id_forma_pago_FK: idForma, monto: montoNum.toFixed(2) });
            }
        }
        return pagos;
    }

    function obtenerTotalTicketNumerico() {
        var totalTxt = (qs('total').value || '').replace('$', '').trim();
        var n = parseFloat(totalTxt);
        return isFinite(n) ? n : 0;
    }

    function sumarPagosEnPantalla() {
        var rows = pagosContainer.querySelectorAll('.form-row');
        var s = 0;
        for (var i = 0; i < rows.length; i++) {
            var mon = rows[i].querySelector('.pos-monto-pago');
            var v = mon ? parseFloat(mon.value || '0') : 0;
            if (isFinite(v) && v > 0) s += v;
        }
        return s;
    }

    /**
     * Solo autocompleta el monto cuando hay una sola fila de pago.
     * Con varias filas (ej. credito + efectivo) no se tocan montos ya capturados.
     */
    function actualizarMontosPagosSegunTotal(totalStr) {
        var rows = pagosContainer.querySelectorAll('.form-row');
        if (rows.length !== 1) {
            return;
        }
        var mon = rows[0].querySelector('.pos-monto-pago');
        if (mon) {
            mon.value = totalStr;
        }
    }

    function formData(obj) {
        var fd = new FormData();
        Object.keys(obj).forEach(function (k) { fd.append(k, obj[k]); });
        if (window.joyeriaAppendCsrfToFormData) {
            window.joyeriaAppendCsrfToFormData(fd);
        }
        return fd;
    }

    function actualizarVista(data) {
        if (!data || !data.estado) return;
        aplicarMetadatosDesdeEstado(data.estado);
        if (esTicketVacuo(data.estado)) {
            limpiarExtrasTicketPos();
            refrescarCreditoCliente();
        }
        var detalles = Array.isArray(data.estado.detalles) ? data.estado.detalles : [];
        if (detalles.length === 0) {
            tablaBody.innerHTML = '<tr><td colspan="7">No hay productos agregados al ticket.</td></tr>';
        } else {
            var html = '';
            detalles.forEach(function (linea, idx) {
                var cant = parseFloat(linea.cantidad || 0);
                var precio = parseFloat(linea.precio_unitario || 0);
                var subtotal = cant * precio;
                html += '<tr>'
                    + '<td>' + (linea.tipo_linea || '') + '</td>'
                    + '<td>' + (linea.codigo || '') + '</td>'
                    + '<td>' + (linea.descripcion || '') + '</td>'
                    + '<td>' + cant.toFixed(3) + '</td>'
                    + '<td>$' + precio.toFixed(2) + '</td>'
                    + '<td>$' + subtotal.toFixed(2) + '</td>'
                    + '<td><button type="button" class="btn-action-danger btn-eliminar-item" data-index="' + idx + '"><i class="bi bi-trash"></i> Quitar</button></td>'
                    + '</tr>';
            });
            tablaBody.innerHTML = html;
        }

        var credInner = qs('pos-creditos-canje-inner');
        if (credInner) {
            var credits = (data.estado && Array.isArray(data.estado.creditos_canje)) ? data.estado.creditos_canje : [];
            if (credits.length === 0) {
                credInner.className = 'text-muted';
                credInner.style.fontSize = '0.95rem';
                credInner.textContent = 'Sin creditos en este ticket. Arma la lista en Devoluciones abajo y pulsa «Aplicar créditos al ticket».';
            } else {
                credInner.className = '';
                credInner.style.fontSize = '';
                var parts = credits.map(function (c, i) {
                    var m = parseFloat(c.monto_credito || 0).toFixed(2);
                    var d = (c.descripcion || '').replace(/</g, '&lt;');
                    return '<li style="margin:6px 0;display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;">'
                        + '<span>Venta #' + (c.id_venta_origen || '') + ' — ' + d + ' <strong>($' + m + ')</strong></span>'
                        + '<button type="button" class="btn-action-danger btn-pos-quitar-credito" data-index="' + i + '" style="padding:4px 10px;font-size:0.85rem;">Quitar</button>'
                        + '</li>';
                });
                credInner.innerHTML = '<ul style="list-style:none;padding:0;margin:0;">' + parts.join('') + '</ul>';
            }
        }

        if (data.totales) {
            qs('conteo_piezas').value = data.totales.conteo_piezas;
            qs('subtotal').value = '$' + data.totales.subtotal;
            qs('descuento').value = formatDescuentoPosTotales(data.totales);
            var mc = parseFloat(data.totales.monto_credito_canje || 0);
            var mcWrap = qs('pos_monto_credito_canje_wrap');
            var mcInp = qs('pos_monto_credito_canje');
            if (mcWrap && mcInp) {
                if (mc > 0.0001) {
                    mcWrap.style.display = '';
                    mcInp.value = '$' + (data.totales.monto_credito_canje || '0.00');
                } else {
                    mcWrap.style.display = 'none';
                }
            }
            qs('impuesto').value = data.totales.impuesto_porcentaje + '% ($' + data.totales.impuesto_monto + ')';
            qs('total').value = '$' + data.totales.total;
            var primeraFilaPago = pagosContainer.querySelector('.form-row');
            if (primeraFilaPago) {
                var selPrimera = primeraFilaPago.querySelector('.pos-forma-pago');
                var idPred = obtenerIdFormaPagoPredeterminada();
                if (selPrimera && idPred && !selPrimera.value) {
                    selPrimera.value = idPred;
                }
            }
            actualizarMontosPagosSegunTotal(data.totales.total);
            if (window.PosSpeiQr && typeof window.PosSpeiQr.actualizarEstadoBoton === 'function') {
                window.PosSpeiQr.actualizarEstadoBoton();
            }
        }

        if (data.pausadas) {
            renderPausadasLista(data.pausadas);
        }
        if (Object.prototype.hasOwnProperty.call(data, 'pagos_borrador')) {
            restaurarPagosBorrador(data.pagos_borrador);
        }
    }

    window.joyeriaPosActualizarDesdeDevoluciones = function (data) {
        if (data && data.ok) {
            actualizarVista(data);
        }
    };

    function mostrarEstadoImpresion(texto, tipo) {
        var box = qs('pos_impresion_status');
        var label = qs('pos_impresion_status_text');
        if (!box || !label) return;
        box.style.display = 'block';
        box.className = 'alert-message ' + (tipo || 'info');
        label.textContent = texto;
    }

    function ocultarEstadoImpresion() {
        var box = qs('pos_impresion_status');
        if (box) box.style.display = 'none';
    }

    function consultarEstadoImpresion(idVenta, intentos) {
        intentos = intentos || 0;
        if (!idVenta || intentos > 20) {
            if (intentos > 20) {
                mostrarEstadoImpresion('Ticket en cola. Verifica la impresora en caja.', 'info');
            }
            return;
        }
        fetch('api/impresion.php?accion=estado&id_venta=' + encodeURIComponent(idVenta), {
            credentials: 'same-origin'
        })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res || !res.success || !res.data) return;
                var estado = res.data.estado || 'sin_cola';
                if (estado === 'impreso') {
                    mostrarEstadoImpresion('Ticket impreso correctamente.', 'success');
                    return;
                }
                if (estado === 'error') {
                    mostrarEstadoImpresion('Error al imprimir en caja. Puedes reimprimir desde Ventas.', 'error');
                    return;
                }
                if (estado === 'pendiente' || estado === 'sin_cola') {
                    mostrarEstadoImpresion('Enviando ticket a impresión...', 'info');
                    window.setTimeout(function () {
                        consultarEstadoImpresion(idVenta, intentos + 1);
                    }, 2000);
                }
            })
            .catch(function () {
                mostrarEstadoImpresion('Venta registrada. Ticket encolado para impresión.', 'info');
            });
    }

    function mensajeCsrfRecarga(msg) {
        if (window.joyeriaIsCsrfErrorMessage && window.joyeriaIsCsrfErrorMessage(msg)) {
            return 'Sesión de seguridad expirada. Recarga la página (F5) e intenta de nuevo.';
        }
        return msg;
    }

    function postAction(action, payload) {
        return fetch('punto_venta.php?accion=' + encodeURIComponent(action), {
            method: 'POST',
            credentials: 'same-origin',
            body: formData(payload || {})
        }).then(function (r) {
            return r.json().then(function (res) {
                if (r.status === 403 && res && !res.ok) {
                    var msgCsrf = mensajeCsrfRecarga(res.mensaje || '');
                    if (msgCsrf !== (res.mensaje || '')) {
                        var errCsrf = new Error(msgCsrf);
                        errCsrf.codigo_error = 'csrf_invalido';
                        throw errCsrf;
                    }
                }
                if (!res.ok) {
                    var err = new Error(res.mensaje || 'Error');
                    err.codigo_error = res.codigo_error || '';
                    throw err;
                }
                return res;
            });
        });
    }

    function syncMeta() {
        return postAction('actualizar_meta', {
            id_cliente_FK: selCliente.value || '',
            id_impuesto_FK: selImpuesto.value || '',
            id_tienda_FK: selTienda.value || ''
        }).then(function (res) {
            if (!res.ok) throw new Error(res.mensaje || 'No se pudo actualizar el ticket.');
            actualizarVista(res);
        });
    }

    qs('btn_agregar').addEventListener('click', function () {
        agregarPorCodigo((txtCodigo.value || '').trim(), false);
    });

    function agregarPorCodigo(codigo, desdeCamara) {
        if (!codigo) {
            showMessage('Ingresa un código para agregar un producto.', 'info');
            if (desdeCamara && window.JoyeriaPosBarcodeScanner) {
                JoyeriaPosBarcodeScanner.notifyScanResult('Código vacío. Intenta de nuevo.', 'error');
            }
            return Promise.resolve();
        }
        return syncMeta()
            .then(function () {
                return postAction('agregar_item', {
                    codigo: codigo,
                    id_tienda_FK: selTienda.value || ''
                });
            })
            .then(function (res) {
                actualizarVista(res);
                txtCodigo.value = '';
                if (!desdeCamara) {
                    txtCodigo.focus();
                }
                showMessage('Producto agregado al ticket.', 'success');
                if (desdeCamara && window.JoyeriaPosScanFeedback) {
                    JoyeriaPosScanFeedback.success();
                }
                if (desdeCamara && window.JoyeriaPosBarcodeScanner) {
                    JoyeriaPosBarcodeScanner.notifyScanResult('Agregado: ' + codigo + '. Escanea la siguiente etiqueta.', 'success');
                }
            })
            .catch(function (err) {
                var msg = err.message || 'Error al agregar producto.';
                showPosError(err, 'Error al agregar producto.');
                if (desdeCamara && window.JoyeriaPosBarcodeScanner) {
                    JoyeriaPosBarcodeScanner.notifyScanResult(msg, 'error');
                }
            });
    }

    var btnEscanear = qs('btn_escanear_camara');
    if (btnEscanear) {
        btnEscanear.addEventListener('click', function () {
            if (!window.JoyeriaPosBarcodeScanner) {
                showMessage('El escáner no está disponible en esta página.', 'error');
                return;
            }
            if (!JoyeriaPosBarcodeScanner.isSupported()) {
                showMessage('Tu navegador no puede usar la cámara aquí. Prueba Chrome/Edge en localhost o con HTTPS.', 'error');
                return;
            }
            if (window.JoyeriaPosScanFeedback) {
                JoyeriaPosScanFeedback.prepare();
            }
            JoyeriaPosBarcodeScanner.open({
                onScan: function (codigo) {
                    txtCodigo.value = codigo;
                    agregarPorCodigo(codigo, true);
                },
                onStatus: function (message, kind) {
                    if (kind === 'error') {
                        showMessage(message, 'error');
                    }
                }
            }).catch(function (err) {
                showMessage((err && err.message) ? err.message : 'No se pudo abrir la camara.', 'error');
            });
        });
    }

    txtCodigo.addEventListener('keydown', function (ev) {
        if (ev.key === 'Enter') {
            ev.preventDefault();
            qs('btn_agregar').click();
        }
    });

    tablaBody.addEventListener('click', function (ev) {
        var btn = ev.target.closest('.btn-eliminar-item');
        if (!btn) return;
        postAction('quitar_item', { index: btn.getAttribute('data-index') || '' })
            .then(function (res) {
                if (!res.ok) throw new Error(res.mensaje || 'No se pudo eliminar la linea.');
                actualizarVista(res);
            })
            .catch(function (err) {
                showMessage(err.message || 'Error al quitar linea.', 'error');
            });
    });

    var credWrap = qs('pos-creditos-canje-wrap');
    if (credWrap) {
        credWrap.addEventListener('click', function (ev) {
            var b = ev.target.closest('.btn-pos-quitar-credito');
            if (!b) return;
            postAction('quitar_credito_canje', { index: b.getAttribute('data-index') || '' })
                .then(function (res) {
                    if (!res.ok) throw new Error(res.mensaje || 'No se pudo quitar el credito.');
                    actualizarVista(res);
                })
                .catch(function (err) {
                    showMessage(err.message || 'Error', 'error');
                });
        });
    }

    selCliente.addEventListener('change', function () {
        refrescarCreditoCliente();
        syncMeta().catch(function (err) { showMessage(err.message, 'error'); });
    });
    selImpuesto.addEventListener('change', function () { syncMeta().catch(function (err) { showMessage(err.message, 'error'); }); });
    selTienda.addEventListener('change', function () { syncMeta().catch(function (err) { showMessage(err.message, 'error'); }); });

    qs('btn_limpiar').addEventListener('click', function () {
        ejecutarNuevaVenta();
    });

    var btnPausarYNueva = qs('btn_pausar_y_nueva');
    if (btnPausarYNueva) {
        btnPausarYNueva.addEventListener('click', function () {
            ejecutarPausarYNueva();
        });
    }

    var btnNuevaVenta = qs('btn_nueva_venta');
    if (btnNuevaVenta) {
        btnNuevaVenta.addEventListener('click', function () {
            ejecutarNuevaVenta();
        });
    }
    var pausadasWrap = qs('pos-pausadas-wrap');
    if (pausadasWrap) {
        pausadasWrap.addEventListener('click', function (ev) {
            var btnRet = ev.target.closest('.btn-pos-retomar');
            if (btnRet) {
                ejecutarRetomarPausada(btnRet.getAttribute('data-id') || '');
                return;
            }
            var btnDel = ev.target.closest('.btn-pos-eliminar-pausada');
            if (btnDel) {
                ejecutarEliminarPausada(btnDel.getAttribute('data-id') || '');
            }
        });
    }

    document.addEventListener('keydown', function (ev) {
        if (ev.ctrlKey || ev.altKey || ev.metaKey) {
            return;
        }
        var tag = (ev.target && ev.target.tagName) ? ev.target.tagName.toLowerCase() : '';
        if (tag === 'textarea') {
            return;
        }
        if (ev.key === 'F2') {
            ev.preventDefault();
            ejecutarNuevaVenta();
            return;
        }
        if (ev.key === 'F3') {
            ev.preventDefault();
            ejecutarPausarYNueva();
        }
    });

    qs('btn_confirmar').addEventListener('click', function () {
        var totalTicket = obtenerTotalTicketNumerico();
        var pagos = obtenerPagosPayload();
        if (pagos.length === 0 && totalTicket > 0.02) {
            showMessage('Agrega al menos una forma de pago con monto (el total a cobrar es mayor a cero).', 'error');
            return;
        }
        syncMeta()
            .then(function () {
                totalTicket = obtenerTotalTicketNumerico();
                var pagosFinal = obtenerPagosPayload();
                if (pagosFinal.length === 0 && totalTicket > 0.02) {
                    showMessage('Agrega al menos una forma de pago con monto (el total a cobrar es mayor a cero).', 'error');
                    return null;
                }
                if (pagosFinal.length > 0 && totalTicket > 0.02) {
                    var sumaPagos = 0;
                    for (var pi = 0; pi < pagosFinal.length; pi++) {
                        sumaPagos += parseFloat(pagosFinal[pi].monto || '0');
                    }
                    if (Math.abs(sumaPagos - totalTicket) > 0.02) {
                        showMessage(
                            'La suma de pagos ($' + sumaPagos.toFixed(2)
                                + ') no coincide con el total ($' + totalTicket.toFixed(2)
                                + '). Ajusta los montos en cada forma de pago.',
                            'error'
                        );
                        return null;
                    }
                }
                var totalStr = (qs('total').value || '').trim() || '$0.00';
                var msg = totalTicket <= 0.02
                    ? '¿Confirmar canje? Total $0.00: el crédito por devolución cubre esta venta (cambio sin cobro). Esta acción no se puede deshacer.'
                    : ('¿Registrar esta venta por ' + totalStr + '? Esta acción no se puede deshacer.');
                if (!window.confirm(msg)) {
                    return null;
                }
                return postAction('confirmar', { pagos: JSON.stringify(pagosFinal) });
            })
            .then(function (res) {
                if (res === null) {
                    return;
                }
                if (!res.ok) throw new Error(res.mensaje || 'No se pudo confirmar la venta.');
                prepararUiTicketNuevo(res, { limpiarMensaje: true });
                if (res.impresion_encolada) {
                    showMessage('Venta confirmada. Folio #' + res.id_venta + '. Ticket enviado a impresión.', 'success');
                    consultarEstadoImpresion(res.id_venta, 0);
                } else {
                    ocultarEstadoImpresion();
                    showMessage('Venta confirmada. Folio #' + res.id_venta, 'success');
                }
            })
            .catch(function (err) {
                showPosError(err, 'Error al confirmar venta.');
            });
    });

    qs('btn_agregar_pago').addEventListener('click', function () {
        pagosContainer.appendChild(crearFilaPago(false));
        if (window.PosSpeiQr && typeof window.PosSpeiQr.actualizarEstadoBoton === 'function') {
            window.PosSpeiQr.actualizarEstadoBoton();
        }
    });

    pagosContainer.addEventListener('input', function (ev) {
        if (ev.target && (ev.target.classList.contains('pos-monto-pago') || ev.target.classList.contains('pos-forma-pago'))) {
            if (window.PosSpeiQr && typeof window.PosSpeiQr.actualizarEstadoBoton === 'function') {
                window.PosSpeiQr.actualizarEstadoBoton();
            }
        }
    });
    pagosContainer.addEventListener('change', function (ev) {
        if (ev.target && ev.target.classList.contains('pos-forma-pago')) {
            if (window.PosSpeiQr && typeof window.PosSpeiQr.actualizarEstadoBoton === 'function') {
                window.PosSpeiQr.actualizarEstadoBoton();
            }
        }
    });

    pagosContainer.appendChild(crearFilaPago(true));

    aplicarImpuestoPredeterminadoSiFalta();

    postAction('estado', {})
        .then(function (res) {
            if (res && res.ok) {
                actualizarVista(res);
                return syncMeta();
            }
            return null;
        })
        .catch(function () { /* silencio */ });

    refrescarCreditoCliente();

    if (window.PosSpeiQr && typeof window.PosSpeiQr.init === 'function') {
        window.PosSpeiQr.init({
            config: datosDepositoSpei,
            obtenerTotal: obtenerTotalActualNumerico,
            obtenerMontoTransferencia: obtenerMontoTransferenciaPos
        });
    }
})();
</script>
