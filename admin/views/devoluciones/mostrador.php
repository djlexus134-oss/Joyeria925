<?php
/** @var array $formasPago */
/** @var ?int $idEmpleadoSesion */
/** @var bool $puedeCrear */
/** @var bool $puedeLeer */
/** @var bool $puedeMonedero */
/** @var array $catalogoClientes */
$puedeMonedero = isset($puedeMonedero) && $puedeMonedero;
$catalogoClientes = isset($catalogoClientes) && is_array($catalogoClientes) ? $catalogoClientes : [];
?>

<div class="form-section">
    <?php if ($idEmpleadoSesion === null): ?>
        <div class="alert-message error"><p>Tu usuario no está vinculado a un empleado activo; no puedes registrar devoluciones desde aquí.</p></div>
    <?php endif; ?>

    <?php if (!$puedeCrear && !$puedeLeer): ?>
        <div class="alert-message error"><p>No tienes permisos sobre devoluciones.</p></div>
    <?php else: ?>

        <?php if ($puedeCrear && $idEmpleadoSesion !== null): ?>
            <h3 style="margin-top:0;"><i class="bi bi-shop"></i> Registrar devoluciónes de mostrador</h3>
            <p class="text-muted" style="max-width:720px;line-height:1.45;">
                Como en <strong>Punto de venta</strong>: captura codigos con pistola, teclado o camara; cada lectura con <strong>Enter</strong> o el boton <strong>Agregar</strong> solo los pone en la lista.
                Al final pulsa <strong>Registrar devolución mostrador</strong> para confirmar todas en inventario (pieza en estado <strong>vendida</strong> pasa a <strong>disponible</strong>).
                El monto de referencia en sistema se toma del <strong>precio de venta</strong> en inventario (o de la ultima venta registrada si no hay precio).
            </p>

            <form id="form_devolucion_mostrador" class="form-row" style="flex-direction:column; align-items:stretch; max-width:720px;">
                <input type="hidden" name="id_empleado_FK" value="<?php echo (int) $idEmpleadoSesion; ?>">
                <div class="form-group" style="max-width:100%;">
                    <label for="dm_codigo"><i class="bi bi-upc-scan"></i> Código (barras o auxiliar)</label>
                    <div style="display:flex; gap:8px; align-items:stretch; flex-wrap:wrap;">
                        <input type="text" class="form-input joyeria-barcode-input" id="dm_codigo" name="codigo" autocomplete="off" placeholder="Escanea o escribe (ej. 28488/97) y Agregar" style="flex:1 1 220px; min-width:0;font-size:1.02rem;">
                        <button type="button" class="btn-action-secondary" id="btn_dm_escanear" title="Escanear con cámara" style="white-space:nowrap;">
                            <i class="bi bi-camera"></i> Camara
                        </button>
                        <button type="button" class="btn-action-primary" id="btn_dm_agregar_linea" style="white-space:nowrap;">
                            <i class="bi bi-plus-lg"></i> Agregar
                        </button>
                        <button type="button" class="btn-action-secondary" id="btn_dm_limpiar_cola" style="white-space:nowrap;">
                            <i class="bi bi-arrow-clockwise"></i> Limpiar lista
                        </button>
                    </div>
                    <div id="dm_scan_leyenda" role="status" aria-live="polite" style="display:none;margin:0.4rem 0 0;padding:0.45rem 0.65rem;border-radius:8px;font-size:0.88rem;line-height:1.35;border:1px solid transparent;"></div>
                </div>

                <div class="admin-table-wrapper" style="margin-top:0.35rem;">
                    <table class="admin-table" id="dm_tabla_cola">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Código en lista</th>
                                <th style="width:110px;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="dm_cola_body">
                            <tr id="dm_cola_vacia"><td colspan="3" class="text-muted">Sin codigos en la lista.</td></tr>
                        </tbody>
                    </table>
                </div>

                <?php if ($puedeMonedero): ?>
                <div class="form-group" style="margin-top:0.75rem; max-width:520px;">
                    <label for="dm_cliente"><i class="bi bi-wallet2"></i> Cliente para monedero (opcional)</label>
                    <select class="form-input" id="dm_cliente" name="id_cliente_FK">
                        <option value="">— Solo inventario (sin monedero) —</option>
                        <?php
                        $clientes = $catalogoClientes;
                        $selectedId = '';
                        $includeEmpty = false;
                        require __DIR__ . '/../partials/cliente_select_options.php';
                        ?>
                    </select>
                </div>
                <div class="form-group" style="max-width:520px;">
                    <label>
                        <input type="checkbox" id="dm_acreditar_monedero" value="1" disabled>
                        Acreditar monedero del cliente (habilita al elegir cliente)
                    </label>
                    <div id="dm_monedero_preview" class="text-muted" style="font-size:0.88rem; margin-top:0.35rem;"></div>
                </div>
                <?php endif; ?>
                <div class="form-group" style="margin-top:0.75rem;">
                    <label for="dm_motivo">Motivo (opcional, aplica a cada pieza al registrar)</label>
                    <textarea class="form-input" id="dm_motivo" name="motivo" rows="2" placeholder="Notas u observaciones"></textarea>
                </div>
                <div class="form-group">
                    <label for="dm_fp">Forma de pago del reembolso (opcional, aplica a cada pieza al registrar)</label>
                    <select class="form-input" id="dm_fp" name="id_forma_pago_FK">
                        <option value="">— Sin registrar —</option>
                        <?php foreach ($formasPago as $fp): ?>
                            <option value="<?php echo (int) $fp['id_forma_pago']; ?>"><?php echo htmlspecialchars((string) $fp['forma_pago']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn-action-primary" id="btn_dm_enviar" style="align-self:flex-start;">
                    <i class="bi bi-check2-circle"></i> Registrar devolución mostrador
                </button>
            </form>
            <div id="dm_mensaje" style="margin-top:0.75rem;"></div>
        <?php elseif (!$puedeCrear && $puedeLeer): ?>
            <p class="text-muted">Solo tienes permiso de consulta. Para registrar necesitas <code>DEVOLUCION_CREAR</code>.</p>
        <?php endif; ?>

        <?php if ($puedeLeer): ?>
            <h3 style="margin-top:2rem;"><i class="bi bi-clock-history"></i> Ultimas devoluciones</h3>
            <div id="devoluciones_recientes" class="admin-table-wrapper">
                <p class="text-muted">Cargando...</p>
            </div>
        <?php endif; ?>

    <?php endif; ?>
</div>

<?php if ($puedeLeer || ($puedeCrear && $idEmpleadoSesion !== null)): ?>
<script src="js/pos-scan-feedback.js"></script>
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script src="js/pos-barcode-scanner.js"></script>
<script>
(function () {
    var idEmpleado = <?php echo (int) ($idEmpleadoSesion ?? 0); ?>;
    var puedeCrear = <?php echo $puedeCrear ? 'true' : 'false'; ?>;
    var puedeLeer = <?php echo $puedeLeer ? 'true' : 'false'; ?>;
    var puedeMonedero = <?php echo !empty($puedeMonedero) ? 'true' : 'false'; ?>;

    function el(id) { return document.getElementById(id); }

    function escHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function setModoRapidoLeyenda(kind, message) {
        var box = el('dm_scan_leyenda');
        if (!box) return;
        var base = 'margin:0.4rem 0 0;padding:0.45rem 0.65rem;border-radius:8px;font-size:0.88rem;line-height:1.35;';
        if (!message) {
            box.style.display = 'none';
            box.textContent = '';
            return;
        }
        box.style.display = 'block';
        box.textContent = message;
        if (kind === 'success') {
            box.style.cssText = base + 'display:block;background:#ecfdf5;color:#14532d;border:1px solid #a7f3d0;';
        } else if (kind === 'error') {
            box.style.cssText = base + 'display:block;background:#fef2f2;color:#991b1b;border:1px solid #fecaca;';
        } else {
            box.style.cssText = base + 'display:block;background:#f1f5f9;color:#334155;border:1px solid #e2e8f0;';
        }
    }

    function cargarDevolucionesRecientes() {
        if (!puedeLeer) return;
        var box = el('devoluciones_recientes');
        if (!box) return;
        fetch('api/devoluciones.php?limit=30', { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!box || !res || !res.success || !Array.isArray(res.data)) {
                    if (box) box.innerHTML = '<p class="alert-message error">No se pudo cargar el listado.</p>';
                    return;
                }
                if (res.data.length === 0) {
                    box.innerHTML = '<p class="text-muted">Sin registros recientes.</p>';
                    return;
                }
                var rows = res.data.map(function (d) {
                    var codAux = (d.pieza_codigo_auxiliar != null && String(d.pieza_codigo_auxiliar).trim() !== '')
                        ? String(d.pieza_codigo_auxiliar).trim()
                        : '—';
                    return '<tr>'
                        + '<td>' + escHtml(d.fecha_devolucion) + '</td>'
                        + '<td>' + escHtml(d.tipo_origen) + '</td>'
                        + '<td>' + (d.id_venta_FK != null ? escHtml(d.id_venta_FK) : '—') + '</td>'
                        + '<td>' + escHtml(codAux) + '</td>'
                        + '<td class="text-right">$' + parseFloat(d.monto_reembolso || 0).toFixed(2) + '</td>'
                        + '<td>' + escHtml(d.forma_pago || '') + '</td>'
                        + '<td>' + (d.credito_id ? ('Credito #' + escHtml(d.credito_id)) : '—') + '</td>'
                        + '</tr>';
                }).join('');
                box.innerHTML = '<table class="admin-table"><thead><tr><th>Fecha</th><th>Origen</th><th>Venta</th><th>Cod. auxiliar</th><th class="text-right">Monto</th><th>Forma pago</th><th>Monedero</th></tr></thead><tbody>'
                    + rows + '</tbody></table>';
            })
            .catch(function () {
                if (box) box.innerHTML = '<p class="alert-message error">Error de red.</p>';
            });
    }

    function postDevolucionMostrador(payload) {
        var body = Object.assign({ tipo: 'mostrador' }, payload || {});
        body.tipo = 'mostrador';
        if (puedeMonedero) {
            var chkMon = el('dm_acreditar_monedero');
            var selCli = el('dm_cliente');
            var idCli = selCli && selCli.value ? parseInt(selCli.value, 10) : 0;
            if (chkMon && chkMon.checked && idCli > 0) {
                body.acreditar_monedero = true;
                body.id_cliente_FK = idCli;
            }
        }
        return fetch('api/devoluciones.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json; charset=utf-8' },
            body: JSON.stringify(body)
        }).then(function (r) {
            return r.text().then(function (text) {
                var res = null;
                try {
                    res = text ? JSON.parse(text) : null;
                } catch (e) {
                    throw new Error('Respuesta no valida del API de devoluciones (no es JSON). Vuelve a iniciar sesión o revisa la red.');
                }
                if (!res || typeof res !== 'object') {
                    throw new Error('Respuesta vacia o invalida del API de devoluciones.');
                }
                if (!res.success) {
                    var errMsg = (typeof res.error === 'string' && res.error.trim() !== '')
                        ? res.error.trim()
                        : 'No se pudo registrar la devolución de mostrador.';
                    throw new Error(errMsg);
                }
                return res;
            });
        });
    }

    cargarDevolucionesRecientes();

    function dmScannerModalAbierto() {
        var m = document.getElementById('pos-scanner-modal');
        return !!(m && m.style.display === 'flex');
    }

    if (puedeMonedero) {
        var selDmCli = el('dm_cliente');
        var chkDmMon = el('dm_acreditar_monedero');
        var boxDmPrev = el('dm_monedero_preview');
        function syncDmMonederoUi() {
            var idCli = selDmCli && selDmCli.value ? parseInt(selDmCli.value, 10) : 0;
            if (chkDmMon) {
                chkDmMon.disabled = idCli <= 0;
                if (idCli <= 0) chkDmMon.checked = false;
            }
            if (boxDmPrev && idCli <= 0) boxDmPrev.textContent = '';
        }
        if (selDmCli) {
            selDmCli.addEventListener('change', syncDmMonederoUi);
        }
        syncDmMonederoUi();
        if (window.JoyeriaFkAutocomplete && selDmCli) {
            JoyeriaFkAutocomplete.initSelectAutocomplete({
                selectId: 'dm_cliente',
                allowEmpty: true,
                placeholder: 'Nombre, apellido, correo o teléfono...'
            });
        }
    }

    if (!puedeCrear || idEmpleado <= 0) return;

    var colaDm = [];
    var inpCodigo = el('dm_codigo');
    var btnAgregar = el('btn_dm_agregar_linea');
    var btnLimpiarCola = el('btn_dm_limpiar_cola');
    var tbodyCola = el('dm_cola_body');

    function renderColaDm() {
        if (!tbodyCola) return;
        tbodyCola.innerHTML = '';
        if (colaDm.length === 0) {
            var tr0 = document.createElement('tr');
            tr0.id = 'dm_cola_vacia';
            tr0.innerHTML = '<td colspan="3" class="text-muted">Sin codigos en la lista.</td>';
            tbodyCola.appendChild(tr0);
            return;
        }
        for (var i = 0; i < colaDm.length; i++) {
            var c = colaDm[i];
            var tr = document.createElement('tr');
            tr.innerHTML = '<td>' + (i + 1) + '</td><td>' + escHtml(c) + '</td><td>'
                + '<button type="button" class="btn-action-danger btn-dm-quitar" data-idx="' + i + '"><i class="bi bi-trash"></i> Quitar</button></td>';
            tbodyCola.appendChild(tr);
        }
    }

    function focoCodigoDm() {
        if (inpCodigo) {
            inpCodigo.focus();
            try { inpCodigo.select(); } catch (e0) {}
        }
    }

    function agregarLineaDm(desdeCamara, codigoPre) {
        var raw = codigoPre != null ? String(codigoPre) : (inpCodigo && inpCodigo.value ? inpCodigo.value : '');
        var codigo = (raw || '').trim();
        if (!codigo) {
            setModoRapidoLeyenda('info', 'Ingresa un código para agregarlo a la lista.');
            if (desdeCamara && window.JoyeriaPosBarcodeScanner && dmScannerModalAbierto()) {
                JoyeriaPosBarcodeScanner.notifyLegend('Código vacío. Intenta de nuevo.', 'error');
            }
            return;
        }
        var k = 0;
        for (; k < colaDm.length; k++) {
            if (colaDm[k] === codigo) break;
        }
        if (k < colaDm.length) {
            var dup = 'Ese código ya está en la lista.';
            setModoRapidoLeyenda('error', dup);
            if (desdeCamara && window.JoyeriaPosScanFeedback) JoyeriaPosScanFeedback.error();
            if (desdeCamara && window.JoyeriaPosBarcodeScanner && dmScannerModalAbierto()) {
                JoyeriaPosBarcodeScanner.notifyLegend(dup, 'error');
            }
            return;
        }
        colaDm.push(codigo);
        renderColaDm();
        if (inpCodigo) inpCodigo.value = '';
        setModoRapidoLeyenda('success', 'Agregado a la lista: ' + codigo);
        if (!desdeCamara) focoCodigoDm();
        if (desdeCamara && window.JoyeriaPosScanFeedback) JoyeriaPosScanFeedback.success();
        if (desdeCamara && window.JoyeriaPosBarcodeScanner && dmScannerModalAbierto()
            && typeof JoyeriaPosBarcodeScanner.notifyLegend === 'function') {
            JoyeriaPosBarcodeScanner.notifyLegend('Agregado a la lista: ' + codigo, 'success');
        }
    }

    var btnCam = el('btn_dm_escanear');
    if (btnCam) {
        btnCam.addEventListener('click', function () {
            if (!window.JoyeriaPosBarcodeScanner) {
                alert('El escáner no está cargado. Espera un momento y vuelve a intentar, o recarga la pagina.');
                return;
            }
            if (!JoyeriaPosBarcodeScanner.isSupported()) {
                alert('Tu navegador no puede usar la cámara aquí. Prueba Chrome/Edge en localhost o con HTTPS.');
                return;
            }
            if (window.JoyeriaPosScanFeedback) JoyeriaPosScanFeedback.prepare();
            JoyeriaPosBarcodeScanner.open({
                onScan: function (codigo) {
                    agregarLineaDm(true, codigo);
                },
                onStatus: function (message, kind) {
                    if (kind === 'error') alert(message);
                }
            }).catch(function (err) {
                alert((err && err.message) ? err.message : 'No se pudo abrir la camara.');
            });
        });
    }

    if (btnAgregar) {
        btnAgregar.addEventListener('click', function () { agregarLineaDm(false, null); });
    }
    if (inpCodigo) {
        inpCodigo.addEventListener('keydown', function (ev) {
            if (ev.key === 'Enter') {
                ev.preventDefault();
                agregarLineaDm(false, null);
            }
        });
    }
    if (btnLimpiarCola) {
        btnLimpiarCola.addEventListener('click', function () {
            if (colaDm.length === 0) return;
            if (!window.confirm('Vaciar la lista de codigos pendientes?')) return;
            colaDm = [];
            renderColaDm();
            setModoRapidoLeyenda('', '');
        });
    }
    if (tbodyCola) {
        tbodyCola.addEventListener('click', function (ev) {
            var b = ev.target.closest('.btn-dm-quitar');
            if (!b) return;
            var idx = parseInt(b.getAttribute('data-idx') || '-1', 10);
            if (idx < 0 || idx >= colaDm.length) return;
            colaDm.splice(idx, 1);
            renderColaDm();
            focoCodigoDm();
        });
    }

    var formDm = el('form_devolucion_mostrador');
    if (formDm) {
        formDm.addEventListener('submit', function (e) {
            e.preventDefault();
            var msgWrap = el('dm_mensaje');
            var btn = el('btn_dm_enviar');
            if (colaDm.length === 0) {
                setModoRapidoLeyenda('error', 'Agrega al menos un codigo a la lista antes de registrar.');
                if (msgWrap) msgWrap.innerHTML = '<div class="alert-message error"><p>Agrega al menos un codigo a la lista.</p></div>';
                return;
            }
            var motivo = (el('dm_motivo') && el('dm_motivo').value) ? el('dm_motivo').value.toString().trim() : '';
            var fpEl = el('dm_fp');
            var fpVal = fpEl && fpEl.value ? fpEl.value.toString().trim() : '';
            if (msgWrap) msgWrap.innerHTML = '';
            btn.disabled = true;

            function registrarIndice(i, okAntes) {
                if (i >= colaDm.length) {
                    colaDm = [];
                    renderColaDm();
                    var n = okAntes;
                    if (msgWrap) {
                        msgWrap.innerHTML = '<div class="alert-message success"><p>'
                            + escHtml('Se registraron ' + n + ' devolucion(es) de mostrador.')
                            + (el('dm_acreditar_monedero') && el('dm_acreditar_monedero').checked ? ' Con acreditacion al monedero del cliente.' : '')
                            + '</p></div>';
                    }
                    setModoRapidoLeyenda('', '');
                    if (puedeLeer) cargarDevolucionesRecientes();
                    btn.disabled = false;
                    focoCodigoDm();
                    return;
                }
                var cod = colaDm[i];
                postDevolucionMostrador({
                    id_empleado_FK: idEmpleado,
                    codigo: cod,
                    motivo: motivo,
                    id_forma_pago_FK: fpVal
                }).then(function () {
                    registrarIndice(i + 1, okAntes + 1);
                }).catch(function (err) {
                    var m = err && err.message ? err.message : 'Error';
                    var restantes = colaDm.slice(i);
                    colaDm = restantes;
                    renderColaDm();
                    var parte = okAntes > 0 ? ' Se registraron ' + okAntes + ' antes de fallar.' : '';
                    if (msgWrap) {
                        msgWrap.innerHTML = '<div class="alert-message error"><p>'
                            + escHtml('Error al registrar codigo "' + cod + '": ' + m + parte)
                            + '</p></div>';
                    }
                    setModoRapidoLeyenda('error', m);
                    if (puedeLeer && okAntes > 0) cargarDevolucionesRecientes();
                    btn.disabled = false;
                    focoCodigoDm();
                });
            }
            registrarIndice(0, 0);
        });
    }

    setTimeout(focoCodigoDm, 200);
})();
</script>
<?php endif; ?>
