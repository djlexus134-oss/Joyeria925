<?php
/** @var array $catalogoClientes */
/** @var array $catalogoImpuestos */
/** @var float $descuentoGeneralMostrador */
/** @var array $formasPago */
/** @var int|null $idEmpleadoSesion */
/** @var int|null $idImpuestoDefault */
/** @var string $fechaVencimientoDefecto */
$puedeCrearCliente = auth_has_permission('CLIENTE_CREAR');
$impuestoDefectoId = !empty($idImpuestoDefault) ? (int) $idImpuestoDefault : 0;
?>
<div class="admin-modules">
    <div class="form-section">
        <h3><i class="bi bi-plus-circle"></i> Nuevo apartado (varias piezas)</h3>
        <p class="text-muted">
            Agrega piezas como en <strong>punto de venta</strong>: escanea o escribe código y <strong>Agregar</strong>.
            Todas deben estar <strong>disponibles</strong> y en la <strong>misma tienda</strong>.
            El abono sugerido es el <strong>20%</strong> del total tras <strong>descuento del cliente</strong> (o mostrador) e <strong>impuesto</strong>.
            <strong>Selecciona el cliente antes de agregar piezas</strong> para aplicar su descuento especial.
        </p>

        <?php if (!auth_has_permission('APARTADO_GESTION_LEER')): ?>
            <div class="alert-message error"><p>Sin permiso de lectura del módulo.</p></div>
        <?php elseif ($idEmpleadoSesion === null): ?>
            <div class="alert-message error"><p>Tu usuario no tiene empleado activo vinculado.</p></div>
        <?php else: ?>
            <form id="form_apartado_alta" class="form-card form-card--lg" data-fecha-def="<?php echo htmlspecialchars($fechaVencimientoDefecto); ?>">
                <input type="hidden" name="id_empleado_FK" value="<?php echo (int) $idEmpleadoSesion; ?>">
                <input type="hidden" id="ag_fecha_vencimiento" name="fecha_vencimiento" value="<?php echo htmlspecialchars($fechaVencimientoDefecto); ?>">
                <input type="hidden" id="ag_imp" name="impuesto_monto" value="0">

                <div class="form-row">
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label for="id_cliente_FK"><i class="bi bi-person"></i> Cliente</label>
                        <select id="id_cliente_FK" name="id_cliente_FK" class="form-input" required>
                            <?php
                            $clientes = $catalogoClientes;
                            $selectedId = '';
                            $emptyLabel = '— Selecciona —';
                            $emptyValue = '';
                            $emptyDataDescuento = '';
                            require __DIR__ . '/../partials/cliente_select_options.php';
                            ?>
                        </select>
                    </div>
                </div>
                <div id="ag_descuento_especial_wrap" class="alert-message info" style="display:none; margin-top:0.5rem;">
                    <p style="margin:0;"><i class="bi bi-tag-fill"></i> <span id="ag_descuento_especial_txt"></span></p>
                </div>
                <div class="form-row" style="align-items:center; gap:8px; flex-wrap:wrap;">
                    <?php if ($puedeCrearCliente): ?>
                        <button type="button" class="btn-action-primary" id="btn_ag_cliente_nuevo"><i class="bi bi-person-plus"></i> Cliente nuevo</button>
                    <?php else: ?>
                        <p class="text-muted" style="margin:0;">Para dar de alta un cliente aquí necesitas permiso <code>CLIENTE_CREAR</code>.
                            <a href="cliente.php?accion=crear">Ir a clientes</a></p>
                    <?php endif; ?>
                </div>

                <div class="form-row form-row--inline">
                    <div class="form-group">
                        <label>
                            <input type="checkbox" id="ag_editar_vencimiento" value="1">
                            Editar fecha de vencimiento manualmente
                        </label>
                    </div>
                </div>
                <div class="form-row" id="ag_wrap_fecha_venc" style="display:none;">
                    <div class="form-group">
                        <label for="ag_fecha_vencimiento_visible">Vencimiento</label>
                        <input type="date" id="ag_fecha_vencimiento_visible" class="form-input" value="<?php echo htmlspecialchars($fechaVencimientoDefecto); ?>">
                    </div>
                </div>

                <hr style="margin: 1.25rem 0;">
                <h4><i class="bi bi-shop"></i> Piezas del apartado</h4>

                <div class="form-row">
                    <div class="form-group" style="flex: 1 1 70%;">
                        <label for="ag_codigo_busqueda"><i class="bi bi-upc-scan"></i> Código (barras o auxiliar)</label>
                        <div style="display:flex; gap:8px; align-items:stretch;">
                            <input type="text" id="ag_codigo_busqueda" class="form-input joyeria-barcode-input" style="flex:1 1 auto;" autocomplete="off" placeholder="Escribe o escanea (ej. 28488/97) y Agregar">
                            <button type="button" class="btn-action-secondary" id="btn_ag_escanear" title="Escanear con cámara" style="white-space:nowrap; min-width:48px;">
                                <i class="bi bi-camera"></i>
                            </button>
                        </div>
                    </div>
                    <div class="form-group" style="display:flex; gap:8px; align-items:flex-end;">
                        <button type="button" class="btn-action-primary" id="btn_ag_agregar"><i class="bi bi-plus-lg"></i> Agregar</button>
                    </div>
                </div>

                <div id="ag_mensaje_linea"></div>

                <div class="admin-table-wrapper" style="margin-top:1rem;">
                    <table class="admin-table" id="ag_tabla">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Código</th>
                                <th>Descripción</th>
                                <th class="text-right">Precio lista</th>
                                <th class="text-right">Precio apartado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="ag_tabla_body">
                            <tr id="ag_tabla_empty"><td colspan="6" class="text-muted">No hay piezas. Agrega al menos una.</td></tr>
                        </tbody>
                    </table>
                </div>

                <div class="form-row" style="margin-top:1rem; flex-wrap:wrap; gap:12px;">
                    <div class="form-group" style="min-width:140px;">
                        <label>Líneas</label>
                        <input type="text" class="form-input" id="ag_resumen_lineas" value="0" readonly>
                    </div>
                    <div class="form-group" style="min-width:140px;">
                        <label>Subtotal</label>
                        <input type="text" class="form-input" id="ag_subtotal" value="$0.00" readonly>
                    </div>
                    <div class="form-group" style="min-width:200px;">
                        <label>Descuento aplicado</label>
                        <input type="text" class="form-input" id="ag_descuento_txt" value="0% ($0.00)" readonly>
                    </div>
                    <div class="form-group" style="min-width:220px;">
                        <label for="ag_id_impuesto">Impuesto</label>
                        <select id="ag_id_impuesto" class="form-input">
                            <?php foreach ($catalogoImpuestos as $imp): ?>
                                <option value="<?php echo (int) $imp['id_impuesto']; ?>" data-pct="<?php echo htmlspecialchars((string) ($imp['porcentaje'] ?? '0')); ?>"<?php echo ($impuestoDefectoId > 0 && (int) $imp['id_impuesto'] === $impuestoDefectoId) ? ' selected' : ''; ?>>
                                    <?php echo htmlspecialchars((string) ($imp['tipo_impuesto'] ?? '')); ?> (<?php echo htmlspecialchars((string) ($imp['porcentaje'] ?? '0')); ?>%)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="min-width:160px;">
                        <label>Impuesto monto</label>
                        <input type="text" class="form-input" id="ag_impuesto_monto_txt" value="$0.00" readonly>
                    </div>
                    <div class="form-group" style="min-width:160px;">
                        <label><strong>Total</strong></label>
                        <input type="text" class="form-input" id="ag_total" value="$0.00" readonly style="font-weight:600;">
                    </div>
                </div>

                <div class="alert-message info" id="ag_abono_sugerido_wrap" style="margin-top:0.75rem;">
                    <p style="margin:0 0 0.35rem 0;"><strong>Abono inicial sugerido (20% del total con descuento e impuesto)</strong></p>
                    <p style="margin:0; font-size:1rem;" id="ag_abono_sugerido_txt">$0.00</p>
                    <p class="text-muted" style="margin:0.35rem 0 0 0; font-size:0.85rem;">El abono no puede superar el total del apartado (precios con descuento del cliente). Si hiciera falta, el monto sugerido se ajusta a ese tope.</p>
                    <div class="form-actions" style="margin-top:0.5rem; justify-content:flex-start;">
                        <button type="button" class="btn-action-secondary" id="btn_ag_usar_abono"><i class="bi bi-cash-coin"></i> Usar abono sugerido</button>
                    </div>
                </div>

                <hr style="margin: 1.25rem 0;">
                <h4>Abono inicial (opcional)</h4>
                <div class="form-row">
                    <div class="form-group">
                        <label for="ag_abono">Monto</label>
                        <input type="number" step="0.01" min="0" id="ag_abono" name="abono_monto" class="form-input" value="0" placeholder="0">
                    </div>
                    <div class="form-group">
                        <label for="ag_fp_abono">Forma de pago (si hay abono)</label>
                        <select id="ag_fp_abono" name="id_forma_pago_abono" class="form-input">
                        <option value="">—</option>
                        <?php foreach ($formasPago as $fp): ?>
                            <option value="<?php echo (int) $fp['id_forma_pago']; ?>"<?php echo (!empty($idFormaPagoDefault) && (int) $fp['id_forma_pago'] === (int) $idFormaPagoDefault) ? ' selected' : ''; ?>><?php echo htmlspecialchars((string) $fp['forma_pago']); ?></option>
                        <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="alert-message info" id="ag_credito_cliente_wrap" style="display:none; margin-top:0.75rem;">
                    <p style="margin:0 0 0.35rem 0;">
                        <i class="bi bi-wallet2"></i>
                        Crédito a favor disponible del cliente:
                        <strong>$<span id="ag_credito_cliente_saldo">0.00</span></strong>
                    </p>
                    <div class="form-row" style="margin-top:0.35rem; align-items:flex-end; gap:8px; flex-wrap:wrap;">
                        <div class="form-group" style="min-width:160px;">
                            <label for="ag_abono_credito">Aplicar crédito a favor al alta</label>
                            <input type="number" step="0.01" min="0" id="ag_abono_credito" name="abono_credito_monto" class="form-input" value="0">
                        </div>
                        <button type="button" class="btn-action-secondary" id="btn_ag_usar_credito_max"><i class="bi bi-wallet"></i> Usar todo</button>
                    </div>
                    <p class="text-muted" style="margin:0.35rem 0 0 0; font-size:0.85rem;">El crédito aplicado al alta se suma al abono y descuenta del monedero. No puede exceder el total del apartado ni el saldo disponible.</p>
                </div>
                <?php if (auth_has_permission('APARTADO_GESTION_CREAR')): ?>
                    <div class="form-actions">
                        <button type="submit" class="btn-action-primary" id="btn_ag_crear"><i class="bi bi-check2-circle"></i> Crear apartado</button>
                    </div>
                <?php else: ?>
                    <div class="alert-message error"><p>Sin permiso para crear apartados.</p></div>
                <?php endif; ?>
            </form>
            <div id="ag_result_alta" class="alert-message info" style="display:none; margin-top:1rem;"></div>
        <?php endif; ?>
    </div>
</div>

<?php if (auth_has_permission('APARTADO_GESTION_LEER') && $idEmpleadoSesion !== null): ?>
<dialog id="modal_ag_cliente" class="admin-dialog">
    <div class="ja-modal-card">
        <h3 id="modal_ag_cliente_title" style="margin-top:0;">Cliente nuevo</h3>
        <p class="text-muted" style="font-size:0.9rem;">Correo opcional. La contrasena de acceso se genera en el servidor; si indicas correo, se pueden enviar credenciales.</p>
        <div class="form-row">
            <div class="form-group">
                <label for="mc_nombre">Nombre</label>
                <input type="text" id="mc_nombre" class="form-input" maxlength="50" required>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label for="mc_paterno">Primer apellido</label>
                <input type="text" id="mc_paterno" class="form-input" maxlength="25" required>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label for="mc_materno">Segundo apellido (opcional)</label>
                <input type="text" id="mc_materno" class="form-input" maxlength="25">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label for="mc_correo">Correo (opcional)</label>
                <input type="email" id="mc_correo" class="form-input" maxlength="80" autocomplete="email">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label for="mc_tel">Telefono</label>
                <input type="text" id="mc_tel" class="form-input" maxlength="15" required>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label for="mc_descuento">Descuento % (opcional)</label>
                <input type="number" id="mc_descuento" class="form-input" min="0" max="100" step="0.01" placeholder="Ej. 50">
            </div>
        </div>
    </div>
    <div class="ja-modal-footer">
        <button type="button" class="btn-action-primary" id="mc_guardar">Guardar</button>
        <button type="button" class="btn-action-danger" id="mc_cancel">Cancelar</button>
    </div>
</dialog>

<script src="js/fk-autocomplete.js"></script>
<script src="js/barcode-camera.js"></script>
<script>
window.AG_ALTA_CFG = <?php echo json_encode([
    'descuentoGeneralMostrador' => (float) $descuentoGeneralMostrador,
    'impuestoDefectoId' => $impuestoDefectoId,
    'pctAbonoSugerido' => 20,
], JSON_UNESCAPED_UNICODE); ?>;
</script>
<script>
(function () {
    var acCliente = null;
    var acImpuesto = null;
    var lineas = [];
    var lineIdSeq = 1;

    var formAltaEl = document.getElementById('form_apartado_alta');
    var defFechaPhp = formAltaEl ? (formAltaEl.getAttribute('data-fecha-def') || '') : '';
    var hiddenFv = document.getElementById('ag_fecha_vencimiento');
    var chkEditFv = document.getElementById('ag_editar_vencimiento');
    var wrapFv = document.getElementById('ag_wrap_fecha_venc');
    var visFv = document.getElementById('ag_fecha_vencimiento_visible');
    var hiddenImp = document.getElementById('ag_imp');

    var tablaBody = document.getElementById('ag_tabla_body');
    var tablaEmpty = document.getElementById('ag_tabla_empty');
    var inpCodigo = document.getElementById('ag_codigo_busqueda');
    var selCliente = document.getElementById('id_cliente_FK');
    var selImpuesto = document.getElementById('ag_id_impuesto');
    var msgLinea = document.getElementById('ag_mensaje_linea');

    var CFG = window.AG_ALTA_CFG || { descuentoGeneralMostrador: 0, impuestoDefectoId: 0, pctAbonoSugerido: 20 };

    var wrapDescEspecial = document.getElementById('ag_descuento_especial_wrap');
    var txtDescEspecial = document.getElementById('ag_descuento_especial_txt');
    var totalesServidor = null;

    function fmtMoney(n) {
        var x = parseFloat(n);
        if (isNaN(x)) x = 0;
        return '$' + x.toFixed(2);
    }

    function obtenerIdCliente() {
        return parseInt(selCliente ? (selCliente.value || '0') : '0', 10);
    }

    function obtenerIdImpuesto() {
        var id = parseInt(selImpuesto ? (selImpuesto.value || '0') : '0', 10);
        if (id > 0) return id;
        return CFG.impuestoDefectoId ? parseInt(CFG.impuestoDefectoId, 10) : 0;
    }

    function lineasPayloadPreview() {
        var out = [];
        for (var i = 0; i < lineas.length; i++) {
            out.push({ codigo_pieza: lineas[i].codigo });
        }
        return out;
    }

    function actualizarAvisoDescuentoEspecial(data) {
        if (!wrapDescEspecial || !txtDescEspecial) return;
        if (data && data.tiene_descuento_especial && data.descuento_cliente_especial != null) {
            wrapDescEspecial.style.display = '';
            txtDescEspecial.textContent = 'Cliente con descuento especial del '
                + parseFloat(data.descuento_cliente_especial).toFixed(2) + '% (prioridad sobre descuento mostrador).';
        } else {
            wrapDescEspecial.style.display = 'none';
            txtDescEspecial.textContent = '';
        }
    }

    function aplicarTotalesVacios(sinCliente) {
        totalesServidor = null;
        if (hiddenImp) hiddenImp.value = '0.00';
        document.getElementById('ag_resumen_lineas').value = String(lineas.length);
        document.getElementById('ag_subtotal').value = fmtMoney(0);
        document.getElementById('ag_descuento_txt').value = '0% ($0.00)';
        document.getElementById('ag_impuesto_monto_txt').value = fmtMoney(0);
        document.getElementById('ag_total').value = fmtMoney(0);
        document.getElementById('ag_abono_sugerido_txt').textContent = fmtMoney(0);
        actualizarAvisoDescuentoEspecial(null);
        if (sinCliente && wrapDescEspecial && txtDescEspecial && lineas.length > 0) {
            wrapDescEspecial.style.display = '';
            txtDescEspecial.textContent = 'Selecciona un cliente para calcular su descuento en las piezas.';
        }
        ajustarTopeAbonoCredito();
        return { subtotal: 0, descRate: 0, descMonto: 0, base: 0, impMonto: 0, total: 0, abonoSugerido: 0 };
    }

    function aplicarTotalesDesdeServidor(data) {
        totalesServidor = data;
        var lineasSrv = data.lineas || [];
        for (var i = 0; i < lineasSrv.length; i++) {
            var dl = lineasSrv[i];
            for (var j = 0; j < lineas.length; j++) {
                if (lineas[j].codigo === dl.codigo_pieza) {
                    lineas[j].precio_lista = parseFloat(dl.precio_venta) || 0;
                    lineas[j].precio = parseFloat(dl.precio_apartado) || 0;
                    break;
                }
            }
        }
        if (hiddenImp) {
            hiddenImp.value = data.impuesto_monto || '0.00';
        }
        var subtotalLista = parseFloat(data.subtotal_lista) || 0;
        var descRate = parseFloat(data.descuento_porcentaje) || 0;
        var descMonto = parseFloat(data.descuento_monto) || 0;
        var impMonto = parseFloat(data.impuesto_monto) || 0;
        var total = parseFloat(data.total) || 0;
        var subApartado = parseFloat(data.subtotal_apartado) || 0;
        var pctAbono = (CFG.pctAbonoSugerido || 20) / 100;
        var abonoSug = Math.min(total * pctAbono, subApartado);

        document.getElementById('ag_resumen_lineas').value = String(lineas.length);
        document.getElementById('ag_subtotal').value = fmtMoney(subtotalLista);
        document.getElementById('ag_descuento_txt').value = descRate.toFixed(2) + '% (' + fmtMoney(descMonto) + ')';
        document.getElementById('ag_impuesto_monto_txt').value = fmtMoney(impMonto);
        document.getElementById('ag_total').value = fmtMoney(total);
        document.getElementById('ag_abono_sugerido_txt').textContent = fmtMoney(abonoSug);
        actualizarAvisoDescuentoEspecial(data);
        ajustarTopeAbonoCredito();

        return {
            subtotal: subtotalLista,
            descRate: descRate,
            descMonto: descMonto,
            base: subApartado,
            impMonto: impMonto,
            total: total,
            abonoSugerido: abonoSug
        };
    }

    function calcularTotales() {
        if (lineas.length === 0) {
            return aplicarTotalesVacios(false);
        }
        var idCli = obtenerIdCliente();
        if (!idCli || idCli <= 0) {
            return aplicarTotalesVacios(true);
        }
        var idImp = obtenerIdImpuesto();
        if (!idImp || idImp <= 0) {
            return aplicarTotalesVacios(false);
        }
        return null;
    }

    function refrescarTotalesServidor() {
        var sync = calcularTotales();
        if (sync) {
            renderTablaFilas();
            return Promise.resolve(sync);
        }
        var idCli = obtenerIdCliente();
        var idImp = obtenerIdImpuesto();
        return apiJson('api/apartados_gestion.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                tipo: 'preview_totales',
                id_cliente_FK: idCli,
                id_impuesto_FK: idImp,
                lineas: lineasPayloadPreview()
            })
        })
            .then(function (out) {
                var res = out.data || {};
                var t = aplicarTotalesDesdeServidor(res.data || {});
                renderTablaFilas();
                return t;
            })
            .catch(function (e) {
                alert(mensajeErrorApi(e));
                return aplicarTotalesVacios(false);
            });
    }

    function renderTablaFilas() {
        if (!tablaBody) return;
        if (lineas.length === 0) {
            tablaBody.innerHTML = '';
            var tr0 = document.createElement('tr');
            tr0.id = 'ag_tabla_empty';
            tr0.innerHTML = '<td colspan="6" class="text-muted">No hay piezas. Agrega al menos una.</td>';
            tablaBody.appendChild(tr0);
            return;
        }
        for (var i = 0; i < lineas.length; i++) {
            var L = lineas[i];
            var tr = tablaBody.querySelector('tr[data-lid="' + String(L.id) + '"]');
            if (!tr) continue;
            var listaTd = tr.querySelector('.ag-lista');
            var apartTd = tr.querySelector('.ag-apart');
            if (listaTd) listaTd.textContent = fmtMoney(L.precio_lista != null ? L.precio_lista : L.precio);
            if (apartTd) apartTd.textContent = fmtMoney(L.precio);
        }
    }

    function renderTabla() {
        if (!tablaBody) return;
        tablaBody.innerHTML = '';
        if (lineas.length === 0) {
            var tr0 = document.createElement('tr');
            tr0.id = 'ag_tabla_empty';
            tr0.innerHTML = '<td colspan="6" class="text-muted">No hay piezas. Agrega al menos una.</td>';
            tablaBody.appendChild(tr0);
            calcularTotales();
            return;
        }
        for (var i = 0; i < lineas.length; i++) {
            (function (L, idx) {
                var tr = document.createElement('tr');
                tr.dataset.lid = String(L.id);
                var precioLista = L.precio_lista != null ? L.precio_lista : L.precio;
                tr.innerHTML =
                    '<td>' + (idx + 1) + '</td>' +
                    '<td>' + escapeHtml(L.codigo) + '</td>' +
                    '<td>' + escapeHtml(L.desc || '') + '</td>' +
                    '<td class="text-right ag-lista">' + fmtMoney(precioLista) + '</td>' +
                    '<td class="text-right ag-apart">' + fmtMoney(L.precio) + '</td>' +
                    '<td><button type="button" class="btn-action-danger btn-quitar-linea"><i class="bi bi-trash"></i></button></td>';
                tablaBody.appendChild(tr);

                tr.querySelector('.btn-quitar-linea').addEventListener('click', function () {
                    lineas = lineas.filter(function (x) { return x.id !== L.id; });
                    renderTabla();
                });
            })(lineas[i], i);
        }
        refrescarTotalesServidor().then(function () {
            ajustarTopeAbonoCredito();
        });
    }

    function escapeHtml(s) {
        var t = document.createElement('div');
        t.textContent = s;
        return t.innerHTML;
    }

    function showLineaMsg(text, kind) {
        if (!msgLinea) return;
        msgLinea.innerHTML = text ? '<div class="alert-message ' + (kind || 'info') + '"><p style="margin:0;">' + escapeHtml(text) + '</p></div>' : '';
    }

    function apiJson(url, init) {
        if (typeof window.joyeriaApiFetch === 'function') {
            return window.joyeriaApiFetch(url, init);
        }
        init = init || {};
        init.credentials = init.credentials || 'same-origin';
        return fetch(url, init).then(function (r) {
            return r.text().then(function (text) {
                var data = null;
                try {
                    data = text ? JSON.parse(text) : null;
                } catch (eParse) {
                    throw new Error('El servidor devolvio una respuesta invalida. Recarga la pagina.');
                }
                if (r.status === 401) {
                    var e401 = new Error((data && data.error) || 'Sesion no valida. Inicia sesion nuevamente.');
                    e401.status = 401;
                    throw e401;
                }
                if (!r.ok || (data && data.success === false)) {
                    throw new Error((data && data.error) || ('Error ' + r.status));
                }
                return { response: r, data: data };
            });
        });
    }

    function mensajeErrorApi(err) {
        if (err && err.status === 401) {
            return 'Sesion no valida. Cierra sesion, vuelve a entrar al panel e intenta de nuevo.';
        }
        return (err && err.message) ? err.message : 'Error';
    }

    function agregarPiezaPorCodigo(codigoRaw) {
        var codigo = (codigoRaw || '').trim();
        if (window.JoyeriaBarcodeInput && typeof JoyeriaBarcodeInput.normalizeScanCode === 'function') {
            codigo = JoyeriaBarcodeInput.normalizeScanCode(codigo);
        } else if (/^\d+-\d+$/.test(codigo)) {
            codigo = codigo.replace('-', '/');
        }
        if (!codigo) {
            showLineaMsg('Escribe o escanea un código.', 'error');
            return;
        }
        var idCli = obtenerIdCliente();
        if (!idCli || idCli <= 0) {
            showLineaMsg('Selecciona un cliente antes de agregar piezas (para aplicar su descuento).', 'error');
            if (selCliente) selCliente.focus();
            return;
        }
        apiJson('api/apartados_gestion.php?codigo_pieza=' + encodeURIComponent(codigo))
            .then(function (out) {
                var res = out.data || {};
                var d = res.data;
                var precio = parseFloat(d.precio_venta) || 0;
                if (precio <= 0) throw new Error('Precio de venta invalido.');
                lineas.push({
                    id: lineIdSeq++,
                    codigo: codigo,
                    desc: d.desc_pieza || '',
                    precio_lista: precio,
                    precio: precio
                });
                renderTabla();
                showLineaMsg('');
                if (inpCodigo) inpCodigo.value = '';
                if (inpCodigo) inpCodigo.focus();
            })
            .catch(function (e) {
                showLineaMsg(mensajeErrorApi(e), 'error');
            });
    }

    function addOneMonthYMD() {
        var d = new Date();
        d.setMonth(d.getMonth() + 1);
        var y = d.getFullYear();
        var m = String(d.getMonth() + 1).padStart(2, '0');
        var day = String(d.getDate()).padStart(2, '0');
        return y + '-' + m + '-' + day;
    }

    function syncFechaVencimiento() {
        if (chkEditFv && chkEditFv.checked && visFv) {
            hiddenFv.value = visFv.value || '';
        } else {
            var auto = defFechaPhp || addOneMonthYMD();
            hiddenFv.value = auto;
            if (visFv) visFv.value = auto;
        }
    }

    if (chkEditFv) {
        chkEditFv.addEventListener('change', function () {
            wrapFv.style.display = this.checked ? 'block' : 'none';
            syncFechaVencimiento();
        });
    }
    if (visFv) {
        visFv.addEventListener('change', syncFechaVencimiento);
    }
    syncFechaVencimiento();

    document.getElementById('btn_ag_agregar').addEventListener('click', function () {
        agregarPiezaPorCodigo(inpCodigo ? inpCodigo.value : '');
    });
    if (inpCodigo) {
        inpCodigo.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                agregarPiezaPorCodigo(inpCodigo.value);
            }
        });
    }
    document.getElementById('btn_ag_escanear').addEventListener('click', function () {
        if (!window.JoyeriaBarcodeCamera) {
            alert('No se cargo el script de camara.');
            return;
        }
        JoyeriaBarcodeCamera.openModal({
            onCode: function (text) {
                if (inpCodigo) {
                    inpCodigo.value = text;
                    agregarPiezaPorCodigo(text);
                }
            },
            onError: function (m) { alert(m); }
        });
    });

    var creditoSaldoActual = 0;
    var wrapCredito = document.getElementById('ag_credito_cliente_wrap');
    var lblCreditoSaldo = document.getElementById('ag_credito_cliente_saldo');
    var inpAbonoCredito = document.getElementById('ag_abono_credito');
    var btnUsarCreditoMax = document.getElementById('btn_ag_usar_credito_max');

    function parseMontoInput(val) {
        if (val == null || val === '') return 0;
        var v = parseFloat(String(val).replace(',', '.').trim());
        return isFinite(v) && v >= 0 ? v : 0;
    }

    function obtenerTotalActualAlta() {
        if (totalesServidor && totalesServidor.total != null) {
            var t = parseFloat(totalesServidor.total);
            if (isFinite(t)) return t;
        }
        var txt = (document.getElementById('ag_total').value || '').replace(/[$,]/g, '').trim();
        var v = parseFloat(txt);
        return isFinite(v) ? v : 0;
    }

    function obtenerAbonoEfectivoActual() {
        var inp = document.getElementById('ag_abono');
        var v = parseMontoInput(inp ? inp.value : '0');
        return v > 0 ? v : 0;
    }

    function ajustarTopeAbonoCredito() {
        if (!inpAbonoCredito) return;
        var totalT = obtenerTotalActualAlta();
        var efect = obtenerAbonoEfectivoActual();
        var maxCredito = Math.max(0, Math.min(creditoSaldoActual, totalT - efect));
        inpAbonoCredito.max = maxCredito > 0 ? maxCredito.toFixed(2) : '';
        var v = parseMontoInput(inpAbonoCredito.value);
        if (v > maxCredito) inpAbonoCredito.value = maxCredito.toFixed(2);
    }

    function refrescarCreditoCliente() {
        var id = parseInt(selCliente.value || '0', 10);
        creditoSaldoActual = 0;
        if (!id || id <= 0) {
            if (wrapCredito) wrapCredito.style.display = 'none';
            if (inpAbonoCredito) inpAbonoCredito.value = '0';
            ajustarTopeAbonoCredito();
            return Promise.resolve();
        }
        return apiJson('api/clientes_creditos.php?id_cliente=' + encodeURIComponent(id) + '&estado=disponible')
            .then(function (out) {
                var res = out.data || {};
                if (res.success && res.data) {
                    var s = parseFloat(res.data.total_disponible || 0);
                    creditoSaldoActual = isFinite(s) ? s : 0;
                }
                if (creditoSaldoActual > 0.0001) {
                    if (wrapCredito) wrapCredito.style.display = '';
                    if (lblCreditoSaldo) lblCreditoSaldo.textContent = creditoSaldoActual.toFixed(2);
                } else {
                    if (wrapCredito) wrapCredito.style.display = 'none';
                    if (inpAbonoCredito) inpAbonoCredito.value = '0';
                }
                ajustarTopeAbonoCredito();
            })
            .catch(function () {
                if (wrapCredito) wrapCredito.style.display = 'none';
                ajustarTopeAbonoCredito();
            });
    }

    if (selCliente) {
        selCliente.addEventListener('change', function () {
            Promise.all([
                refrescarTotalesServidor(),
                refrescarCreditoCliente()
            ]).then(function () {
                ajustarTopeAbonoCredito();
            });
        });
    }
    if (selImpuesto) {
        selImpuesto.addEventListener('change', function () {
            refrescarTotalesServidor().then(function () {
                ajustarTopeAbonoCredito();
            });
        });
    }
    var inpAbonoEfect = document.getElementById('ag_abono');
    if (inpAbonoEfect) {
        inpAbonoEfect.addEventListener('input', ajustarTopeAbonoCredito);
    }
    if (inpAbonoCredito) {
        inpAbonoCredito.addEventListener('input', function () {
            if (inpAbonoCredito.value.indexOf(',') !== -1) {
                inpAbonoCredito.value = inpAbonoCredito.value.replace(',', '.');
            }
            ajustarTopeAbonoCredito();
        });
    }
    if (btnUsarCreditoMax) {
        btnUsarCreditoMax.addEventListener('click', function () {
            var totalT = obtenerTotalActualAlta();
            var efect = obtenerAbonoEfectivoActual();
            var maxCredito = Math.max(0, Math.min(creditoSaldoActual, totalT - efect));
            inpAbonoCredito.value = maxCredito.toFixed(2);
        });
    }

    document.getElementById('btn_ag_usar_abono').addEventListener('click', function () {
        refrescarTotalesServidor().then(function (t) {
            var inp = document.getElementById('ag_abono');
            if (inp && t) {
                inp.value = (Math.round((t.abonoSugerido || 0) * 100) / 100).toFixed(2);
            }
        });
    });

    function initFk() {
        if (window.JoyeriaFkAutocomplete) {
            acCliente = JoyeriaFkAutocomplete.initSelectAutocomplete({
                selectId: 'id_cliente_FK',
                allowEmpty: false,
                placeholder: 'Nombre, apellido, correo o teléfono...'
            });
            acImpuesto = JoyeriaFkAutocomplete.initSelectAutocomplete({
                selectId: 'ag_id_impuesto',
                allowEmpty: false,
                placeholder: 'Buscar impuesto...'
            });
        }
        if (selImpuesto && CFG.impuestoDefectoId) {
            selImpuesto.value = String(CFG.impuestoDefectoId);
            if (acImpuesto && acImpuesto.refresh) acImpuesto.refresh();
        }
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initFk);
    } else {
        initFk();
    }

    var modal = document.getElementById('modal_ag_cliente');
    var btnMcCancel = document.getElementById('mc_cancel');
    if (btnMcCancel && modal && typeof modal.close === 'function') {
        btnMcCancel.addEventListener('click', function () { modal.close(); });
    }
    var btnClienteNuevo = document.getElementById('btn_ag_cliente_nuevo');
    if (btnClienteNuevo && modal && typeof modal.showModal === 'function') {
        btnClienteNuevo.addEventListener('click', function () {
            document.getElementById('mc_nombre').value = '';
            document.getElementById('mc_paterno').value = '';
            document.getElementById('mc_materno').value = '';
            document.getElementById('mc_correo').value = '';
            document.getElementById('mc_tel').value = '';
            document.getElementById('mc_descuento').value = '';
            modal.showModal();
        });
    }
    var mcGuardar = document.getElementById('mc_guardar');
    if (mcGuardar) {
        mcGuardar.addEventListener('click', function () {
            var body = {
                nombre: document.getElementById('mc_nombre').value.trim(),
                primer_apellido: document.getElementById('mc_paterno').value.trim(),
                telefono: document.getElementById('mc_tel').value.trim()
            };
            var correoMc = document.getElementById('mc_correo').value.trim();
            if (correoMc) body.correo = correoMc;
            var m2 = document.getElementById('mc_materno').value.trim();
            if (m2) body.segundo_apellido = m2;
            var descMc = document.getElementById('mc_descuento').value.trim();
            if (descMc !== '') {
                body.descuento_porcentaje = descMc;
            }
            if (!body.nombre || !body.primer_apellido || !body.telefono) {
                alert('Completa nombre, primer apellido y telefono.');
                return;
            }
            mcGuardar.disabled = true;
            apiJson('api/clientes.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(body)
            })
                .then(function (out) {
                    var res = out.data || {};
                    if (res.correo_credenciales_mensaje && !res.correo_credenciales_enviado && !res.correo_credenciales_omitido) {
                        alert('Cliente creado, pero no se pudo enviar el correo con las credenciales:\n' + res.correo_credenciales_mensaje);
                    }
                    var sel = document.getElementById('id_cliente_FK');
                    var id = res.id_cliente;
                    var label = res.option_label || (
                        body.nombre + ' ' + body.primer_apellido
                        + (body.segundo_apellido ? ' ' + body.segundo_apellido : '')
                        + ' — ' + (correoMc || body.telefono)
                    );
                    var searchHay = res.option_search || [
                        body.nombre,
                        body.primer_apellido,
                        body.segundo_apellido || '',
                        correoMc,
                        body.telefono
                    ].join(' ').toLowerCase().replace(/\s+/g, ' ').trim();
                    var opt = document.createElement('option');
                    opt.value = String(id);
                    opt.setAttribute('data-descuento', (res.descuento_porcentaje != null && res.descuento_porcentaje !== '')
                        ? String(res.descuento_porcentaje)
                        : '');
                    opt.setAttribute('data-search', searchHay);
                    opt.textContent = label;
                    sel.appendChild(opt);
                    sel.value = String(id);
                    if (acCliente && acCliente.refresh) acCliente.refresh();
                    Promise.all([
                        refrescarTotalesServidor(),
                        refrescarCreditoCliente()
                    ]).then(function () {
                        ajustarTopeAbonoCredito();
                    });
                    if (modal && typeof modal.close === 'function') modal.close();
                })
                .catch(function (e) { alert(mensajeErrorApi(e)); })
                .finally(function () { mcGuardar.disabled = false; });
        });
    }

    var formAlta = document.getElementById('form_apartado_alta');
    if (formAlta) {
        formAlta.addEventListener('submit', function (e) {
            e.preventDefault();
            syncFechaVencimiento();
            var fd = new FormData(formAlta);
            var idCli = parseInt(fd.get('id_cliente_FK') || '0', 10);
            if (!idCli || idCli <= 0) {
                alert('Selecciona un cliente.');
                return;
            }
            var abono = parseFloat(fd.get('abono_monto') || '0');
            var fpAb = fd.get('id_forma_pago_abono');
            if (abono > 0.009 && (!fpAb || fpAb === '')) {
                alert('Si hay abono inicial, elige forma de pago.');
                return;
            }
            if (lineas.length === 0) {
                alert('Agrega al menos una pieza al apartado.');
                return;
            }
            var btn = document.getElementById('btn_ag_crear');
            if (btn) btn.disabled = true;
            refrescarTotalesServidor()
                .then(function () {
                    var lineasPayload = [];
                    for (var j = 0; j < lineas.length; j++) {
                        var L = lineas[j];
                        lineasPayload.push({
                            codigo_pieza: L.codigo,
                            precio_apartado: (Math.round(parseFloat(L.precio) * 100) / 100).toFixed(2)
                        });
                    }
                    var body = {
                        tipo: 'crear',
                        id_cliente_FK: idCli,
                        id_empleado_FK: parseInt(fd.get('id_empleado_FK') || '0', 10),
                        id_impuesto_FK: obtenerIdImpuesto(),
                        fecha_vencimiento: hiddenFv.value,
                        lineas: lineasPayload,
                        impuesto_monto: hiddenImp ? hiddenImp.value : '0',
                        abono_monto: fd.get('abono_monto') || '0',
                        id_forma_pago_abono: fpAb && fpAb !== '' ? parseInt(fpAb, 10) : null,
                        abono_credito_monto: fd.get('abono_credito_monto') || '0'
                    };
                    return apiJson('api/apartados_gestion.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(body)
                    });
                })
                .then(function (out) {
                    var res = out.data || {};
                    var d = res.data || {};
                    var extraTicket = '';
                    if (d.impresion_encolada) {
                        extraTicket = '<p style="margin-top:0.75rem;"><i class="bi bi-printer"></i> <strong>Ticket encolado</strong> para impresión en caja';
                        if (d.id_cola_impresion) {
                            extraTicket += ' <span class="text-muted">(cola # ' + parseInt(d.id_cola_impresion, 10) + ')</span>';
                        }
                        extraTicket += '.</p>';
                    }
                    var o = document.getElementById('ag_result_alta');
                    o.className = 'alert-message info';
                    o.innerHTML = '<p>' + (res.message || '') + '</p><p>Apartado <strong>#' + d.id_apartado + '</strong> — Líneas: ' + (d.lineas || 1) +
                        ' — Saldo: $' + (d.saldo_pendiente || '') + '</p>' + extraTicket;
                    o.style.display = 'block';
                    lineas = [];
                    renderTabla();
                    formAlta.reset();
                    if (selImpuesto && CFG.impuestoDefectoId) {
                        selImpuesto.value = String(CFG.impuestoDefectoId);
                        if (acImpuesto && acImpuesto.refresh) acImpuesto.refresh();
                    }
                    if (inpCodigo) inpCodigo.value = '';
                    document.getElementById('ag_abono').value = '0';
                    syncFechaVencimiento();
                    if (acCliente && acCliente.refresh) acCliente.refresh();
                    aplicarTotalesVacios(false);
                })
                .catch(function (err) { alert(mensajeErrorApi(err)); })
                .finally(function () { if (btn) btn.disabled = false; });
        });
    }

    renderTabla();
    Promise.all([
        refrescarTotalesServidor(),
        refrescarCreditoCliente()
    ]).then(function () {
        ajustarTopeAbonoCredito();
    });
})();
</script>
<?php endif; ?>
