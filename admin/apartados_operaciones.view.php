<?php
/** @var array $listaApartados */
/** @var array $catalogoClientes */
/** @var array $formasPago */
/** @var int|null $idEmpleadoSesion */
/** @var int $idApartadoUrl */
/** @var string $prefillDestino */
$idApartadoUrl = isset($idApartadoUrl) ? (int) $idApartadoUrl : 0;
$destinosValidos = ['abono', 'quitar', 'agregar'];
$prefillDestino = isset($prefillDestino) && in_array($prefillDestino, $destinosValidos, true)
    ? $prefillDestino
    : 'abono';

$puedeVerTabla = auth_has_permission('APARTADO_GESTION_LEER');
$puedeAccionAbonar = auth_has_permission('APARTADO_GESTION_ACTUALIZAR') && $idEmpleadoSesion !== null;
$puedeQuitarPieza = auth_has_permission('APARTADO_GESTION_QUITAR_PIEZA') && $idEmpleadoSesion !== null;
$puedeAgregarPieza = auth_has_permission('APARTADO_GESTION_AGREGAR_PIEZA') && $idEmpleadoSesion !== null;
?>
<div class="admin-modules">

    <?php if ($puedeVerTabla): ?>
        <?php
        $aa_context = 'unificado';
        $aa_rows = $listaApartados;
        $aa_catalogoClientes = $catalogoClientes;
        $aa_heading = 'Apartados activos';
        $aa_intro = 'Elige en <strong>Acciones</strong>: <strong>Abonar</strong>, <strong>Quitar pieza</strong> o <strong>Agregar pieza</strong>. El formulario correspondiente aparece debajo de la tabla al seleccionar la accion.';
        $aa_puede_abonar = $puedeAccionAbonar;
        $aa_puede_quitar_pieza = $puedeQuitarPieza;
        $aa_puede_agregar_pieza = $puedeAgregarPieza;
        $aa_puede_ver_abonos = $puedeVerTabla;
        require __DIR__ . '/views/partials/apartados_activos_tabla.php';
        ?>
    <?php endif; ?>

    <div id="ao_acciones_host" class="form-section" style="margin-top:2rem; display:none;">
        <div style="display:flex; justify-content:space-between; align-items:center; gap:1rem; margin-bottom:1rem; flex-wrap:wrap;">
            <p class="text-muted" style="margin:0;" id="ao_panel_titulo">Formulario segun la accion elegida.</p>
            <button type="button" class="btn-action-secondary" id="ao_btn_cerrar_accion"><i class="bi bi-x-lg"></i> Cerrar</button>
        </div>

        <div id="ao_wrap_abono" style="display:none;">
            <h3><i class="bi bi-cash-coin"></i> Abono a apartado activo</h3>
            <p class="text-muted">Registra pagos sobre apartados en estado <strong>activo</strong>. Para nuevos apartados usa <a href="apartados_alta.php?accion=leer">Apartados alta</a>.</p>
            <?php if (!auth_has_permission('APARTADO_GESTION_ACTUALIZAR')): ?>
                <p class="text-muted">Sin permiso para registrar abonos.</p>
            <?php elseif ($idEmpleadoSesion === null): ?>
                <p class="text-muted">Empleado no vinculado.</p>
            <?php else: ?>
                <form id="form_apartado_abono" class="form-card form-card--sm">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="ac_ab_id">ID apartado</label>
                            <input type="number" min="1" id="ac_ab_id" name="id_apartado_FK" class="form-input" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="ac_ab_monto">Monto</label>
                            <input type="number" step="0.01" min="0.01" id="ac_ab_monto" name="monto" class="form-input" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>
                                <input type="checkbox" id="ac_ab_usar_credito" name="usar_credito_cliente" value="1">
                                Usar credito a favor del cliente (monedero)
                            </label>
                            <div id="ac_ab_credito_info" class="text-muted" style="font-size:0.85rem; margin-top:0.25rem;">
                                Selecciona un ID de apartado para ver el saldo disponible del cliente.
                            </div>
                        </div>
                    </div>
                    <div class="form-row" id="ac_ab_fp_wrap">
                        <div class="form-group">
                            <label for="ac_ab_fp">Forma de pago</label>
                            <select id="ac_ab_fp" name="id_forma_pago_FK" class="form-input" required>
                            <?php foreach ($formasPago as $fp): ?>
                                <option value="<?php echo (int) $fp['id_forma_pago']; ?>"<?php echo (!empty($idFormaPagoDefault) && (int) $fp['id_forma_pago'] === (int) $idFormaPagoDefault) ? ' selected' : ''; ?>><?php echo htmlspecialchars((string) $fp['forma_pago']); ?></option>
                            <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn-action-primary" id="btn_ac_abono"><i class="bi bi-check2-circle"></i> Registrar abono</button>
                    </div>
                </form>
                <div id="ac_result_abono" class="alert-message info" style="display:none; margin-top:1rem;"></div>
            <?php endif; ?>
        </div>

        <div id="ao_wrap_quitar" style="display:none;">
            <h3><i class="bi bi-dash-square"></i> Quitar pieza del apartado</h3>
            <p class="text-muted">
                Libera una pieza del apartado y recalcula total y saldo. Si lo abonado supera el nuevo total,
                el apartado se <strong>liquida</strong> y el excedente se acredita como <strong>credito a favor del cliente</strong>.
                Si se quita la ultima pieza, el apartado se <strong>cancela</strong> y todo lo abonado pasa al credito del cliente.
            </p>

            <?php if (!auth_has_permission('APARTADO_GESTION_QUITAR_PIEZA')): ?>
                <div class="alert-message error"><p>No tienes permiso para quitar piezas de apartados.</p></div>
            <?php elseif ($idEmpleadoSesion === null): ?>
                <div class="alert-message error"><p>Tu usuario no tiene un empleado activo vinculado.</p></div>
            <?php else: ?>
                <div class="form-card form-card--md">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="ao_qp_id_apartado">ID apartado</label>
                            <input type="number" min="1" id="ao_qp_id_apartado" class="form-input" placeholder="Ej. 42">
                        </div>
                    </div>
                    <div class="form-actions" style="margin-bottom: 1rem;">
                        <button type="button" class="btn-action-secondary" id="ao_qp_btn_cargar">
                            <i class="bi bi-search"></i> Cargar piezas
                        </button>
                    </div>
                    <div id="ao_qp_resumen" class="alert-message info" style="display:none;"></div>
                    <div id="ao_qp_lineas_wrap" style="display:none;">
                        <h4 style="margin-top:1.25rem;">Lineas del apartado</h4>
                        <div class="admin-table-wrapper">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Codigo</th>
                                        <th>Descripcion</th>
                                        <th class="text-right">Precio</th>
                                        <th>Impacto</th>
                                        <th>Accion</th>
                                    </tr>
                                </thead>
                                <tbody id="ao_qp_lineas_body"></tbody>
                            </table>
                        </div>
                        <div class="form-row" style="margin-top:1rem;">
                            <div class="form-group">
                                <label for="ao_qp_observaciones">Observaciones (opcional)</label>
                                <textarea id="ao_qp_observaciones" class="form-input" rows="2" placeholder="Motivo o notas internas"></textarea>
                            </div>
                        </div>
                    </div>
                    <div id="ao_qp_resultado" class="alert-message info" style="display:none; margin-top:1rem;"></div>
                </div>
            <?php endif; ?>
        </div>

        <div id="ao_wrap_agregar" style="display:none;">
            <h3><i class="bi bi-plus-square"></i> Agregar pieza al apartado</h3>
            <p class="text-muted">
                Agrega una pieza <strong>disponible</strong> de la <strong>misma tienda</strong> al apartado activo.
                El total sube y el saldo se recalcula manteniendo lo ya abonado.
            </p>

            <?php if (!auth_has_permission('APARTADO_GESTION_AGREGAR_PIEZA')): ?>
                <div class="alert-message error"><p>No tienes permiso para agregar piezas a apartados.</p></div>
            <?php elseif ($idEmpleadoSesion === null): ?>
                <div class="alert-message error"><p>Tu usuario no tiene un empleado activo vinculado.</p></div>
            <?php else: ?>
                <form id="form_apartado_agregar" class="form-card form-card--md">
                    <input type="hidden" name="id_empleado_FK" value="<?php echo (int) $idEmpleadoSesion; ?>">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="ao_ag_id_apartado">ID apartado</label>
                            <input type="number" min="1" id="ao_ag_id_apartado" name="id_apartado_FK" class="form-input" required placeholder="Ej. 42">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="ao_ag_codigo">Codigo pieza nueva (disponible)</label>
                            <div style="display:flex; gap:8px; align-items:stretch; flex-wrap:wrap;">
                                <input type="text" id="ao_ag_codigo" name="codigo_pieza" class="form-input joyeria-barcode-input" style="flex:1 1 200px; min-width:0;" autocomplete="off" placeholder="Barras o auxiliar" required>
                                <button type="button" class="btn-action-secondary" id="btn_ao_ag_escanear" title="Escanear con camara"><i class="bi bi-camera"></i></button>
                            </div>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="ao_ag_precio">Precio para apartado (opcional)</label>
                            <input type="number" step="0.01" min="0.01" id="ao_ag_precio" name="precio_apartado" class="form-input" placeholder="Dejar vacio: precio venta con descuento del cliente">
                            <p class="text-muted" style="margin:0.35rem 0 0 0; font-size:0.85rem;">Si dejas el precio vacio, se aplica el descuento especial del cliente del apartado (o descuento mostrador).</p>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="ao_ag_observaciones">Observaciones (opcional)</label>
                            <textarea id="ao_ag_observaciones" name="observaciones" class="form-input" rows="2" placeholder="Notas internas"></textarea>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn-action-primary" id="ao_ag_btn_enviar">
                            <i class="bi bi-check2-circle"></i> Agregar pieza al apartado
                        </button>
                    </div>
                </form>
                <div id="ao_ag_resultado" class="alert-message info" style="display:none; margin-top:1rem;"></div>
            <?php endif; ?>
        </div>
    </div>

</div>

<?php if ($puedeVerTabla): ?>
<script>
(function () {
    var host = document.getElementById('ao_acciones_host');
    var wraps = {
        abono: document.getElementById('ao_wrap_abono'),
        quitar: document.getElementById('ao_wrap_quitar'),
        agregar: document.getElementById('ao_wrap_agregar')
    };
    var btnCerrar = document.getElementById('ao_btn_cerrar_accion');
    var titulo = document.getElementById('ao_panel_titulo');

    function ocultarTodos() {
        Object.keys(wraps).forEach(function (k) {
            if (wraps[k]) wraps[k].style.display = 'none';
        });
    }

    function mostrarPanel(clave, tituloTxt) {
        if (!host) return;
        ocultarTodos();
        if (wraps[clave]) wraps[clave].style.display = 'block';
        host.style.display = 'block';
        if (titulo) titulo.textContent = tituloTxt;
        host.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    window.joyeriaApartadosOperacionesCerrarPanel = function () {
        if (host) host.style.display = 'none';
        ocultarTodos();
    };

    window.joyeriaApartadosOperacionesMostrarAbono = function (idApartado) {
        mostrarPanel('abono', 'Registrar abono al apartado #' + (parseInt(idApartado, 10) || ''));
        var inp = document.getElementById('ac_ab_id');
        if (inp && parseInt(idApartado, 10) > 0) inp.value = String(parseInt(idApartado, 10));
        var monto = document.getElementById('ac_ab_monto');
        if (monto) setTimeout(function () { monto.focus(); }, 350);
    };

    window.joyeriaApartadosOperacionesMostrarQuitar = function (idApartado) {
        mostrarPanel('quitar', 'Quitar pieza del apartado #' + (parseInt(idApartado, 10) || ''));
        var inp = document.getElementById('ao_qp_id_apartado');
        var btn = document.getElementById('ao_qp_btn_cargar');
        if (inp && parseInt(idApartado, 10) > 0) {
            inp.value = String(parseInt(idApartado, 10));
            if (btn) setTimeout(function () { btn.click(); }, 200);
        }
    };

    window.joyeriaApartadosOperacionesMostrarAgregar = function (idApartado) {
        mostrarPanel('agregar', 'Agregar pieza al apartado #' + (parseInt(idApartado, 10) || ''));
        var inp = document.getElementById('ao_ag_id_apartado');
        if (inp && parseInt(idApartado, 10) > 0) inp.value = String(parseInt(idApartado, 10));
        var cod = document.getElementById('ao_ag_codigo');
        if (cod) setTimeout(function () { cod.focus(); }, 350);
    };

    if (btnCerrar) {
        btnCerrar.addEventListener('click', function () {
            window.joyeriaApartadosOperacionesCerrarPanel();
        });
    }
})();
window.JOYERIA_APARTADOS_ACTIVOS = <?php echo json_encode([
    'context' => 'unificado',
    'puedeAbonar' => !empty($puedeAccionAbonar),
    'puedeQuitarPieza' => !empty($puedeQuitarPieza),
    'puedeAgregarPieza' => !empty($puedeAgregarPieza),
    'puedeVerAbonos' => !empty($puedeVerTabla),
    'idApartadoUrl' => (int) $idApartadoUrl,
    'prefillDestino' => $prefillDestino,
], JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="js/fk-autocomplete.js"></script>
<script src="js/apartados-activos-tabla.js"></script>
<?php endif; ?>

<?php if (auth_has_permission('APARTADO_GESTION_ACTUALIZAR') && $idEmpleadoSesion !== null): ?>
<script>
(function () {
    var formAbono = document.getElementById('form_apartado_abono');
    if (!formAbono) return;

    var inpId = document.getElementById('ac_ab_id');
    var inpMonto = document.getElementById('ac_ab_monto');
    var chkCredito = document.getElementById('ac_ab_usar_credito');
    var infoCredito = document.getElementById('ac_ab_credito_info');
    var fpWrap = document.getElementById('ac_ab_fp_wrap');
    var selFp = document.getElementById('ac_ab_fp');

    var saldoCreditoActual = 0;
    var ultimoApartadoConsultado = 0;

    function sincronizarUiCredito() {
        if (!chkCredito) return;
        var usar = chkCredito.checked;
        if (usar && saldoCreditoActual <= 0) {
            chkCredito.checked = false;
            return;
        }
        if (fpWrap) fpWrap.style.display = usar ? 'none' : '';
        if (selFp) selFp.required = !usar;
        if (usar && inpMonto) {
            var m = parseFloat(inpMonto.value || '0');
            if (isFinite(m) && m > saldoCreditoActual) {
                inpMonto.value = saldoCreditoActual.toFixed(2);
            }
            inpMonto.max = saldoCreditoActual.toFixed(2);
        } else if (inpMonto) {
            inpMonto.removeAttribute('max');
        }
    }

    function refrescarCreditoApartado() {
        var idAp = parseInt(inpId.value || '0', 10);
        if (!idAp || idAp <= 0 || idAp === ultimoApartadoConsultado) return;
        ultimoApartadoConsultado = idAp;
        saldoCreditoActual = 0;
        if (infoCredito) infoCredito.textContent = 'Consultando saldo del cliente...';
        fetch('api/apartados_gestion.php?id_apartado=' + encodeURIComponent(idAp), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res || !res.success || !res.data) throw new Error((res && res.error) || 'No se pudo cargar el apartado.');
                var idCli = parseInt(res.data.id_cliente_FK || '0', 10);
                if (idCli <= 0) {
                    saldoCreditoActual = 0;
                    if (infoCredito) infoCredito.textContent = 'El apartado no tiene cliente.';
                    sincronizarUiCredito();
                    return;
                }
                return fetch('api/clientes_creditos.php?id_cliente=' + encodeURIComponent(idCli) + '&estado=disponible', {
                    credentials: 'same-origin'
                })
                    .then(function (r) { return r.json(); })
                    .then(function (res2) {
                        if (res2 && res2.success && res2.data) {
                            var s = parseFloat(res2.data.total_disponible || 0);
                            saldoCreditoActual = isFinite(s) ? s : 0;
                        }
                        if (infoCredito) {
                            if (saldoCreditoActual > 0.0001) {
                                infoCredito.innerHTML = '<i class="bi bi-wallet2"></i> Credito disponible: <strong>$' + saldoCreditoActual.toFixed(2) + '</strong>';
                            } else {
                                infoCredito.textContent = 'El cliente no tiene credito disponible.';
                            }
                        }
                        sincronizarUiCredito();
                    });
            })
            .catch(function (err) {
                if (infoCredito) infoCredito.textContent = err.message || 'No se pudo consultar el saldo.';
            });
    }

    if (inpId) {
        inpId.addEventListener('change', refrescarCreditoApartado);
        inpId.addEventListener('blur', refrescarCreditoApartado);
    }
    if (chkCredito) chkCredito.addEventListener('change', sincronizarUiCredito);
    if (inpMonto) inpMonto.addEventListener('input', sincronizarUiCredito);

    formAbono.addEventListener('submit', function (e) {
        e.preventDefault();
        var fd = new FormData(formAbono);
        var btn = document.getElementById('btn_ac_abono');
        var usarCredito = chkCredito ? !!chkCredito.checked : false;
        if (usarCredito && saldoCreditoActual <= 0) {
            alert('El cliente no tiene credito disponible.');
            return;
        }
        if (btn) btn.disabled = true;
        var body = {
            tipo: 'abono',
            id_apartado_FK: parseInt(fd.get('id_apartado_FK') || '0', 10),
            monto: fd.get('monto'),
            usar_credito_cliente: usarCredito ? 1 : 0
        };
        if (!usarCredito) {
            body.id_forma_pago_FK = parseInt(fd.get('id_forma_pago_FK') || '0', 10);
        }
        fetch('api/apartados_gestion.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res || !res.success) throw new Error((res && res.error) || 'Error');
                var d = res.data || {};
                var extraTicket = '';
                if (d.impresion_encolada) {
                    extraTicket = '<p style="margin-top:0.75rem;"><i class="bi bi-printer"></i> <strong>Ticket encolado</strong> para impresion en caja';
                    if (d.id_cola_impresion) {
                        extraTicket += ' <span class="text-muted">(cola # ' + parseInt(d.id_cola_impresion, 10) + ')</span>';
                    }
                    extraTicket += '.</p>';
                }
                var extraCredito = '';
                if (d.usar_credito_cliente && d.credito_consumido) {
                    extraCredito = '<p style="margin-top:0.4rem;"><i class="bi bi-wallet2"></i> Aplicado del monedero del cliente: <strong>$' + (d.credito_consumido.monto_aplicado || '') + '</strong></p>';
                }
                var o = document.getElementById('ac_result_abono');
                o.className = 'alert-message info';
                o.innerHTML = '<p>' + (res.message || '') + '</p><p>Nuevo saldo: <strong>$' + (d.saldo_pendiente || '') + '</strong></p>' + extraCredito + extraTicket;
                o.style.display = 'block';
                formAbono.reset();
                ultimoApartadoConsultado = 0;
                saldoCreditoActual = 0;
                if (chkCredito) chkCredito.checked = false;
                sincronizarUiCredito();
                if (infoCredito) infoCredito.textContent = 'Selecciona un ID de apartado para ver el saldo disponible del cliente.';
                if (typeof window.joyeriaApartadosActivosRecargarTabla === 'function') {
                    window.joyeriaApartadosActivosRecargarTabla();
                }
            })
            .catch(function (err) { alert(err.message || 'Error'); })
            .finally(function () { if (btn) btn.disabled = false; });
    });
})();
</script>
<?php endif; ?>

<?php if (auth_has_permission('APARTADO_GESTION_QUITAR_PIEZA') && $idEmpleadoSesion !== null): ?>
<script>
(function () {
    var inpId = document.getElementById('ao_qp_id_apartado');
    var btnCargar = document.getElementById('ao_qp_btn_cargar');
    var resumen = document.getElementById('ao_qp_resumen');
    var lineasWrap = document.getElementById('ao_qp_lineas_wrap');
    var lineasBody = document.getElementById('ao_qp_lineas_body');
    var resultado = document.getElementById('ao_qp_resultado');
    if (!inpId || !btnCargar || !lineasBody) return;

    var ctxApartado = null;

    function fmtMoney(v) {
        var n = parseFloat(v);
        if (isNaN(n)) return v;
        return n.toFixed(2);
    }

    function escapeHtml(s) {
        if (s === null || s === undefined) return '';
        var t = document.createElement('div');
        t.textContent = String(s);
        return t.innerHTML;
    }

    function calcularAbonadoApartado(data) {
        var pagos = data.pagos || [];
        var sum = 0;
        var labelMonedero = 'Credito a favor cliente';
        for (var k = 0; k < pagos.length; k++) {
            var p = pagos[k];
            if (String(p.estado || '') !== 'registrado') continue;
            var orig = String(p.tipo_origen || '');
            var fp = String(p.forma_pago || '');
            if (orig === 'cobro_tienda' || orig === 'credito_por_cambio' || orig === 'credito_cliente' || fp === labelMonedero) {
                sum += parseFloat(p.monto) || 0;
            }
        }
        if (pagos.length > 0) {
            return sum;
        }
        var api = parseFloat(data.abonado);
        if (!isNaN(api)) {
            return api;
        }
        var total = parseFloat(data.total_apartado) || 0;
        var saldo = parseFloat(data.saldo_pendiente) || 0;
        return Math.max(0, total - saldo);
    }

    function impactoLinea(precio) {
        if (!ctxApartado) return '';
        var totalNuevo = parseFloat(ctxApartado.total_apartado) - parseFloat(precio || 0);
        if (isNaN(totalNuevo)) totalNuevo = 0;
        if (totalNuevo < 0) totalNuevo = 0;
        var abonado = parseFloat(ctxApartado.abonado) || 0;
        var lineasRestantes = (ctxApartado.lineas_count || 1) - 1;
        if (lineasRestantes <= 0) {
            return '<span class="text-muted">Apartado se <strong>cancela</strong>. Credito al cliente: <strong>$' + fmtMoney(abonado) + '</strong></span>';
        }
        if (abonado - totalNuevo > 0.02) {
            var excedente = abonado - totalNuevo;
            return '<span class="text-muted">Auto-liquidar: total $' + fmtMoney(totalNuevo)
                + ' / credito al cliente <strong>$' + fmtMoney(excedente) + '</strong></span>';
        }
        var saldoNuevo = totalNuevo - abonado;
        if (saldoNuevo < 0) saldoNuevo = 0;
        return '<span class="text-muted">Total $' + fmtMoney(totalNuevo)
            + ' / saldo $' + fmtMoney(saldoNuevo) + '</span>';
    }

    function renderLineas() {
        if (!ctxApartado) {
            lineasBody.innerHTML = '<tr><td colspan="6">Sin datos.</td></tr>';
            return;
        }
        var dets = ctxApartado.detalles || [];
        if (dets.length === 0) {
            lineasBody.innerHTML = '<tr><td colspan="6">Sin lineas activas.</td></tr>';
            return;
        }
        var h = '';
        for (var i = 0; i < dets.length; i++) {
            var d = dets[i];
            h += '<tr>'
                + '<td>' + (i + 1) + '</td>'
                + '<td>' + escapeHtml(d.codigo_barras || '') + '</td>'
                + '<td>' + escapeHtml(d.desc_pieza || '') + '</td>'
                + '<td class="text-right">$' + fmtMoney(d.precio_apartado) + '</td>'
                + '<td>' + impactoLinea(d.precio_apartado) + '</td>'
                + '<td><button type="button" class="btn-action-danger ao-qp-confirmar" data-id-detalle="'
                + parseInt(d.id_apartado_detalle, 10) + '"><i class="bi bi-trash"></i> Quitar</button></td>'
                + '</tr>';
        }
        lineasBody.innerHTML = h;
    }

    function cargar() {
        var id = parseInt(inpId.value || '0', 10);
        if (id <= 0) {
            alert('Indica el ID del apartado.');
            return;
        }
        resumen.style.display = 'none';
        lineasWrap.style.display = 'none';
        resultado.style.display = 'none';
        ctxApartado = null;
        btnCargar.disabled = true;
        fetch('api/apartados_gestion.php?id_apartado=' + encodeURIComponent(String(id)), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res || !res.success) throw new Error((res && res.error) || 'Error');
                var data = res.data || {};
                if ((data.estado || '') !== 'activo') {
                    throw new Error('El apartado no esta activo (estado: ' + (data.estado || 'desconocido') + ').');
                }
                var detalles = data.detalles || [];
                var abonado = calcularAbonadoApartado(data);
                ctxApartado = {
                    id_apartado: parseInt(data.id_apartado, 10) || id,
                    cliente_nombre: data.cliente_nombre || '',
                    total_apartado: parseFloat(data.total_apartado) || 0,
                    saldo_pendiente: parseFloat(data.saldo_pendiente) || 0,
                    abonado: abonado,
                    detalles: detalles,
                    lineas_count: detalles.length
                };
                resumen.className = 'alert-message info';
                resumen.innerHTML = '<p><strong>Apartado #' + ctxApartado.id_apartado + '</strong> — Cliente: '
                    + escapeHtml(ctxApartado.cliente_nombre) + '</p>'
                    + '<p>Total: <strong>$' + fmtMoney(ctxApartado.total_apartado)
                    + '</strong> | Abonado: <strong>$' + fmtMoney(ctxApartado.abonado)
                    + '</strong> | Saldo: <strong>$' + fmtMoney(ctxApartado.saldo_pendiente)
                    + '</strong> | Lineas: <strong>' + ctxApartado.lineas_count + '</strong></p>';
                resumen.style.display = 'block';
                renderLineas();
                lineasWrap.style.display = 'block';
            })
            .catch(function (e) {
                alert(e.message || 'Error');
            })
            .finally(function () { btnCargar.disabled = false; });
    }

    btnCargar.addEventListener('click', cargar);

    lineasBody.addEventListener('click', function (ev) {
        var btn = ev.target.closest('.ao-qp-confirmar');
        if (!btn) return;
        var idDet = parseInt(btn.getAttribute('data-id-detalle') || '0', 10);
        if (!ctxApartado || idDet <= 0) return;

        if (!confirm('Quitar esta pieza del apartado #' + ctxApartado.id_apartado + '? Se recalculara el saldo automaticamente.')) {
            return;
        }

        var obs = (document.getElementById('ao_qp_observaciones').value || '').trim();
        btn.disabled = true;
        fetch('api/apartados_gestion.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                tipo: 'quitar_pieza',
                id_apartado_FK: ctxApartado.id_apartado,
                id_apartado_detalle: idDet,
                observaciones: obs
            })
        })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res || !res.success) throw new Error((res && res.error) || 'Error');
                var d = res.data || {};
                var html = '<p>' + (res.message || 'OK') + '</p>'
                    + '<p>Apartado #' + parseInt(d.id_apartado, 10) + ' — Estado: <strong>' + (d.estado || '') + '</strong></p>'
                    + '<p>Total: <strong>$' + fmtMoney(d.total_apartado) + '</strong> | Saldo: <strong>$' + fmtMoney(d.saldo_pendiente) + '</strong></p>';
                if (parseFloat(d.excedente || 0) > 0) {
                    html += '<p><i class="bi bi-wallet2"></i> Credito a favor del cliente: <strong>$' + fmtMoney(d.excedente) + '</strong>';
                    if (d.id_credito_cliente) {
                        html += ' <span class="text-muted">(#' + parseInt(d.id_credito_cliente, 10) + ')</span>';
                    }
                    html += '</p>';
                }
                resultado.className = 'alert-message info';
                resultado.innerHTML = html;
                resultado.style.display = 'block';

                if (typeof window.joyeriaApartadosActivosRecargarTabla === 'function') {
                    window.joyeriaApartadosActivosRecargarTabla();
                }
                if ((d.estado || '') === 'activo') {
                    cargar();
                } else {
                    lineasWrap.style.display = 'none';
                    resumen.style.display = 'none';
                    ctxApartado = null;
                }
            })
            .catch(function (e) {
                alert(e.message || 'Error');
            })
            .finally(function () { btn.disabled = false; });
    });
})();
</script>
<?php endif; ?>

<?php if (auth_has_permission('APARTADO_GESTION_AGREGAR_PIEZA') && $idEmpleadoSesion !== null): ?>
<script src="js/barcode-camera.js"></script>
<script src="js/joyeria-barcode-field.js"></script>
<script>
(function () {
    if (window.JoyeriaBarcodeField) {
        JoyeriaBarcodeField.bind('ao_ag_codigo', 'btn_ao_ag_escanear');
    }
})();
</script>
<script>
(function () {
    var form = document.getElementById('form_apartado_agregar');
    if (!form) return;

    function fmtMoney(v) {
        var n = parseFloat(v);
        if (isNaN(n)) return v;
        return n.toFixed(2);
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var fd = new FormData(form);
        var btn = document.getElementById('ao_ag_btn_enviar');
        if (btn) btn.disabled = true;
        var precio = (fd.get('precio_apartado') || '').toString().trim();
        var payload = {
            tipo: 'agregar_pieza',
            id_apartado_FK: parseInt(fd.get('id_apartado_FK') || '0', 10),
            codigo_pieza: (fd.get('codigo_pieza') || '').toString().trim(),
            id_empleado_FK: parseInt(fd.get('id_empleado_FK') || '0', 10),
            observaciones: (fd.get('observaciones') || '').toString()
        };
        if (precio !== '') {
            payload.precio_apartado = precio;
        }
        fetch('api/apartados_gestion.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res || !res.success) throw new Error((res && res.error) || 'Error');
                var d = res.data || {};
                var o = document.getElementById('ao_ag_resultado');
                o.className = 'alert-message info';
                o.innerHTML = '<p>' + (res.message || 'OK') + '</p>'
                    + '<p>Apartado #' + parseInt(d.id_apartado, 10)
                    + ' — Total: <strong>$' + fmtMoney(d.total_apartado) + '</strong>'
                    + ' | Saldo: <strong>$' + fmtMoney(d.saldo_pendiente) + '</strong></p>'
                    + '<p>Linea agregada #' + parseInt(d.id_apartado_detalle, 10)
                    + ' (pieza_stock ' + parseInt(d.id_pieza_stock_FK, 10)
                    + ') por <strong>$' + fmtMoney(d.precio_apartado) + '</strong></p>';
                o.style.display = 'block';
                form.reset();
                if (typeof window.joyeriaApartadosActivosRecargarTabla === 'function') {
                    window.joyeriaApartadosActivosRecargarTabla();
                }
            })
            .catch(function (err) { alert(err.message || 'Error'); })
            .finally(function () { if (btn) btn.disabled = false; });
    });
})();
</script>
<?php endif; ?>
