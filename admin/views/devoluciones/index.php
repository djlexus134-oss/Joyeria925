<?php
/** @var array $catalogoClientes */
/** @var array $formasPagoReembolso */
/** @var ?int $idEmpleadoSesion */
/** @var bool $puedeCrear */
/** @var bool $puedeLeer */
/** @var bool $puedeMonedero */
/** @var bool $puedeReembolso */
$puedeMonedero = isset($puedeMonedero) && $puedeMonedero;
$puedeReembolso = isset($puedeReembolso) && $puedeReembolso;
$catalogoClientes = isset($catalogoClientes) && is_array($catalogoClientes) ? $catalogoClientes : [];
$formasPagoReembolso = isset($formasPagoReembolso) && is_array($formasPagoReembolso) ? $formasPagoReembolso : [];
$clientePref = isset($_GET['cliente']) ? (int) $_GET['cliente'] : 0;
?>

<div class="form-section">
    <?php if ($idEmpleadoSesion === null): ?>
        <div class="alert-message error"><p>Tu usuario no está vinculado a un empleado activo; no puedes registrar devoluciones.</p></div>
    <?php endif; ?>

    <?php if (!$puedeCrear && !$puedeLeer): ?>
        <div class="alert-message error"><p>No tienes permisos sobre devoluciones.</p></div>
    <?php else: ?>

        <p class="text-muted" style="max-width:780px; line-height:1.45;">
            Pantalla única para devoluciones. Detecta automaticamente si la pieza viene de una venta con ticket o de mostrador y
            te ofrece los modos posibles (efectivo, otra forma, monedero del cliente o solo inventario).
            La forma de pago y la afectacion a caja se determinan por el <strong>modo</strong> elegido.
        </p>

        <?php if ($puedeCrear && $idEmpleadoSesion !== null): ?>
        <form id="form_dev_unif" class="form-card form-card--wide">
            <input type="hidden" name="id_empleado_FK" value="<?php echo (int) $idEmpleadoSesion; ?>">

            <div class="form-row">
                <div class="form-group">
                    <label for="du_cliente"><i class="bi bi-person"></i> Cliente
                        <span class="text-muted" style="font-weight:normal;font-size:0.85rem;">(obligatorio solo para Monedero)</span>
                    </label>
                    <select class="form-input" id="du_cliente" name="id_cliente_FK">
                        <option value="">— Sin cliente —</option>
                        <?php
                        $clientes = $catalogoClientes;
                        $selectedId = $clientePref > 0 ? $clientePref : '';
                        $includeEmpty = false;
                        require __DIR__ . '/../partials/cliente_select_options.php';
                        ?>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="du_codigo"><i class="bi bi-upc-scan"></i> Código pieza (barras o auxiliar) *</label>
                    <div style="display:flex; gap:8px; align-items:stretch; flex-wrap:wrap;">
                        <input type="text" class="form-input joyeria-barcode-input" id="du_codigo" name="codigo" autocomplete="off" placeholder="Escanea o escribe el código" style="flex:1 1 260px; min-width:0;">
                        <button type="button" class="btn-action-secondary" id="btn_du_camara" title="Escanear con cámara"><i class="bi bi-camera"></i> Camara</button>
                        <button type="button" class="btn-action-primary" id="btn_du_cargar"><i class="bi bi-search"></i> Cargar</button>
                    </div>
                </div>
            </div>

            <div id="du_resultado" style="display:none;"></div>

            <div id="du_modos_wrap" class="form-row du-modos-section" style="display:none;">
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label class="du-modos-section-label"><i class="bi bi-tag"></i> Modo de devolución</label>
                    <p class="text-muted du-modos-section-hint">Lee la descripción de cada opción y pulsa el boton para seleccionarla.</p>
                    <div id="du_modos" class="du-modos-grid" role="radiogroup" aria-label="Modo de devolución"></div>
                </div>
            </div>

            <div id="du_forma_wrap" class="form-row" style="display:none;">
                <div class="form-group">
                    <label for="du_forma_pago">Forma de pago</label>
                    <select class="form-input" id="du_forma_pago" name="id_forma_pago_FK">
                        <option value="">— Selecciona —</option>
                        <?php foreach ($formasPagoReembolso as $fp): ?>
                            <option value="<?php echo (int) $fp['id_forma_pago']; ?>" data-efectivo="<?php echo isset($fp['es_efectivo']) ? (int) $fp['es_efectivo'] : ''; ?>">
                                <?php echo htmlspecialchars((string) $fp['forma_pago']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div id="du_forma_hint" class="text-muted" style="font-size:0.85rem;margin-top:0.25rem;"></div>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="du_motivo">Motivo / observaciones</label>
                    <textarea class="form-input" id="du_motivo" name="motivo" rows="2" maxlength="500"></textarea>
                </div>
            </div>

            <div id="du_resumen" class="alert-message info" style="display:none;"></div>

            <div class="form-actions" style="margin-top:1rem; display:flex; gap:8px; flex-wrap:wrap;">
                <button type="submit" class="btn-action-primary" id="btn_du_enviar" disabled><i class="bi bi-check2-circle"></i> Registrar devolución</button>
                <button type="button" class="btn-action-secondary" id="btn_du_reset"><i class="bi bi-arrow-counterclockwise"></i> Limpiar</button>
            </div>
        </form>
        <div id="du_mensaje" style="margin-top:0.75rem;"></div>
        <?php else: ?>
            <p class="text-muted">Necesitas el permiso <code>DEVOLUCION_CREAR</code> para registrar devoluciones aqui.</p>
        <?php endif; ?>

        <?php if ($puedeLeer): ?>
            <h3 style="margin-top:2rem;"><i class="bi bi-clock-history"></i> Ultimas devoluciones</h3>
            <div id="du_recientes" class="admin-table-wrapper"><p class="text-muted">Cargando...</p></div>
        <?php endif; ?>

    <?php endif; ?>
</div>

<?php if ($puedeCrear && $idEmpleadoSesion !== null): ?>
<script src="js/fk-autocomplete.js"></script>
<script src="js/pos-scan-feedback.js"></script>
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script src="js/pos-barcode-scanner.js"></script>
<script>
(function () {
    var puedeMonedero = <?php echo $puedeMonedero ? 'true' : 'false'; ?>;
    var puedeReembolso = <?php echo $puedeReembolso ? 'true' : 'false'; ?>;
    var puedeLeer = <?php echo $puedeLeer ? 'true' : 'false'; ?>;

    var ESTADO = {
        preview: null,
        modo: null,
    };

    function el(id) { return document.getElementById(id); }
    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }
    function money(n) {
        var v = parseFloat(n || 0);
        if (!isFinite(v)) v = 0;
        return v.toFixed(2);
    }

    if (window.JoyeriaFkAutocomplete) {
        JoyeriaFkAutocomplete.initSelectAutocomplete({
            selectId: 'du_cliente',
            allowEmpty: true,
            placeholder: 'Nombre, apellido, correo o teléfono...'
        });
    }

    function mostrarMensaje(kind, html) {
        var box = el('du_mensaje');
        if (!box) return;
        if (!html) { box.innerHTML = ''; return; }
        var cls = kind === 'error' ? 'error' : (kind === 'success' ? 'success' : 'info');
        box.innerHTML = '<div class="alert-message ' + cls + '">' + html + '</div>';
    }

    function resetUiPreview() {
        ESTADO.preview = null;
        ESTADO.modo = null;
        el('du_resultado').style.display = 'none';
        el('du_resultado').innerHTML = '';
        el('du_modos_wrap').style.display = 'none';
        el('du_modos').innerHTML = '';
        el('du_forma_wrap').style.display = 'none';
        el('du_resumen').style.display = 'none';
        el('du_resumen').innerHTML = '';
        el('btn_du_enviar').disabled = true;
    }

    function descripcionModo(m) {
        switch (m) {
            case 'efectivo': return { titulo: 'Reembolso efectivo', icono: 'bi-cash', detalle: 'Sale $X del cajon hoy. Se registra en el cierre del dia.' };
            case 'otra_forma': return { titulo: 'Reembolso otra forma', icono: 'bi-credit-card', detalle: 'Reembolso por la forma elegida (tarjeta, transferencia, etc.). Cuenta en cierre por esa forma.' };
            case 'monedero': return { titulo: 'Credito al monedero', icono: 'bi-wallet2', detalle: 'No sale dinero. El cliente acumula saldo en su monedero.' };
            case 'solo_inventario': return { titulo: 'Solo inventario', icono: 'bi-box-arrow-in-down', detalle: 'Reingresa la pieza al inventario. No hay reembolso ni credito.' };
        }
        return { titulo: m, icono: 'bi-tag', detalle: '' };
    }

    function modoPermitidoLocal(m) {
        if (m === 'monedero' && !puedeMonedero) return false;
        if ((m === 'efectivo' || m === 'otra_forma') && !puedeReembolso) return false;
        return true;
    }

    function renderModos(modos) {
        var cont = el('du_modos');
        cont.innerHTML = '';
        modos.forEach(function (m) {
            if (!modoPermitidoLocal(m)) return;
            var d = descripcionModo(m);
            var card = document.createElement('div');
            card.className = 'du-modo-card';
            card.dataset.modo = m;
            card.setAttribute('role', 'radio');
            card.setAttribute('aria-checked', 'false');
            card.tabIndex = 0;

            var desc = document.createElement('p');
            desc.className = 'du-modo-desc';
            desc.textContent = d.detalle;

            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'du-modo-btn btn-action-secondary';
            btn.innerHTML = '<i class="bi ' + esc(d.icono) + '" aria-hidden="true"></i><span>' + esc(d.titulo) + '</span>';

            card.appendChild(desc);
            card.appendChild(btn);

            function activar() {
                seleccionarModo(m, card);
            }
            card.addEventListener('click', function (ev) {
                ev.preventDefault();
                activar();
            });
            card.addEventListener('keydown', function (ev) {
                if (ev.key === 'Enter' || ev.key === ' ') {
                    ev.preventDefault();
                    activar();
                }
            });
            btn.addEventListener('click', function (ev) {
                ev.stopPropagation();
                activar();
            });

            cont.appendChild(card);
        });
        if (cont.children.length === 0) {
            cont.innerHTML = '<p class="text-muted">No tienes permisos para los modos disponibles en esta pieza.</p>';
        }
    }

    function seleccionarModo(m, cardElegido) {
        ESTADO.modo = m;
        var cards = el('du_modos').querySelectorAll('.du-modo-card');
        cards.forEach(function (card) {
            card.classList.remove('du-modo-card--selected');
            card.setAttribute('aria-checked', 'false');
            var btn = card.querySelector('.du-modo-btn');
            if (btn) {
                btn.classList.remove('btn-action-primary');
                btn.classList.add('btn-action-secondary');
            }
        });
        if (cardElegido) {
            cardElegido.classList.add('du-modo-card--selected');
            cardElegido.setAttribute('aria-checked', 'true');
            var btnSel = cardElegido.querySelector('.du-modo-btn');
            if (btnSel) {
                btnSel.classList.remove('btn-action-secondary');
                btnSel.classList.add('btn-action-primary');
            }
        }

        var formaWrap = el('du_forma_wrap');
        var formaSel = el('du_forma_pago');
        var formaHint = el('du_forma_hint');
        if (m === 'efectivo') {
            formaWrap.style.display = '';
            for (var i = 0; i < formaSel.options.length; i++) {
                var opt = formaSel.options[i];
                if (!opt.value) { opt.hidden = false; continue; }
                opt.hidden = opt.getAttribute('data-efectivo') !== '1';
                if (!opt.hidden && !formaSel.value) formaSel.value = opt.value;
            }
            formaHint.textContent = 'Forma fijada a efectivo.';
        } else if (m === 'otra_forma') {
            formaWrap.style.display = '';
            formaSel.value = '';
            for (var j = 0; j < formaSel.options.length; j++) {
                var op2 = formaSel.options[j];
                if (!op2.value) { op2.hidden = false; continue; }
                op2.hidden = op2.getAttribute('data-efectivo') === '1';
            }
            formaHint.textContent = 'Selecciona la forma distinta a efectivo (tarjeta, transferencia, etc.).';
        } else {
            formaWrap.style.display = 'none';
            formaSel.value = '';
            formaHint.textContent = '';
        }

        actualizarResumen();
        el('btn_du_enviar').disabled = false;
    }

    function actualizarResumen() {
        if (!ESTADO.preview || !ESTADO.modo) { el('du_resumen').style.display = 'none'; return; }
        var box = el('du_resumen');
        var p = ESTADO.preview;
        var monto = parseFloat(p.monto_referencia || 0);
        var idCli = parseInt((el('du_cliente').value || '0'), 10);
        var lineas = [];
        if (ESTADO.modo === 'efectivo') {
            lineas.push('<strong>Sale del cajon:</strong> $' + money(monto) + '. Se cuenta en cierre del dia (efectivo).');
        } else if (ESTADO.modo === 'otra_forma') {
            lineas.push('<strong>Reembolso por la forma seleccionada</strong>: $' + money(monto) + '. Afecta caja en su forma (tarjeta/transfer/etc.).');
        } else if (ESTADO.modo === 'monedero') {
            if (idCli <= 0) {
                lineas.push('<strong>Selecciona un cliente</strong> para acreditar el monedero.');
            } else {
                var saldo = parseFloat(p.monedero_saldo_actual || 0);
                var nuevo = saldo + monto;
                lineas.push('No sale dinero. <strong>Credito $' + money(monto) + '</strong> al monedero del cliente.');
                lineas.push('Saldo monedero: $' + money(saldo) + ' &rarr; <strong>$' + money(nuevo) + '</strong>.');
            }
        } else if (ESTADO.modo === 'solo_inventario') {
            lineas.push('Solo se libera la pieza al inventario. <strong>No hay reembolso</strong> ni credito.');
        }
        box.innerHTML = lineas.join('<br>');
        box.style.display = '';
    }

    function renderPreview(d) {
        ESTADO.preview = d;
        var html = '<div class="alert-message info" style="margin-top:0.75rem;">'
            + '<p style="margin:0;"><strong>' + esc(d.descripcion) + '</strong> · codigo <code>' + esc(d.codigo) + '</code> · estado: ' + esc(d.estado_pieza) + '</p>'
            + '<p style="margin:0.25rem 0 0;">Monto referencia: <strong>$' + money(d.monto_referencia) + '</strong></p>'
            + '<p style="margin:0.25rem 0 0;">' + (d.tiene_ticket
                ? ('Con ticket: venta #' + esc(d.id_venta_origen) + (d.cliente_sugerido ? ' (cliente #' + esc(d.cliente_sugerido) + ')' : ''))
                : 'Sin ticket en sistema (mostrador).') + '</p>';
        if (Array.isArray(d.bloqueos) && d.bloqueos.length) {
            html += '<ul style="margin:0.5rem 0 0 1rem;">';
            d.bloqueos.forEach(function (b) { html += '<li>' + esc(b) + '</li>'; });
            html += '</ul>';
        }
        html += '</div>';
        el('du_resultado').innerHTML = html;
        el('du_resultado').style.display = '';

        if (d.cliente_sugerido && !el('du_cliente').value) {
            try { el('du_cliente').value = String(d.cliente_sugerido); } catch (e) {}
        }

        if (Array.isArray(d.modos_permitidos) && d.modos_permitidos.length > 0) {
            renderModos(d.modos_permitidos);
            el('du_modos_wrap').style.display = '';
        } else {
            el('du_modos_wrap').style.display = 'none';
        }

        el('btn_du_enviar').disabled = true;
    }

    function llamarPreview() {
        var codigo = (el('du_codigo').value || '').trim();
        if (!codigo) { mostrarMensaje('error', '<p>Indica un código de pieza.</p>'); return; }
        var idCli = parseInt((el('du_cliente').value || '0'), 10);
        var url = 'api/devoluciones.php?preview=1&codigo=' + encodeURIComponent(codigo)
            + (idCli > 0 ? ('&id_cliente=' + idCli) : '');
        mostrarMensaje('info', '<p>Consultando pieza...</p>');
        fetch(url, { credentials: 'same-origin' })
            .then(function (r) { return r.text().then(function (t) { try { return JSON.parse(t); } catch (e) { throw new Error('Respuesta no valida del servidor.'); } }); })
            .then(function (res) {
                if (!res || !res.success) throw new Error((res && res.error) || 'No se pudo previsualizar.');
                resetUiPreview();
                renderPreview(res.data || {});
                mostrarMensaje('', '');
            })
            .catch(function (err) {
                resetUiPreview();
                mostrarMensaje('error', '<p>' + esc(err.message) + '</p>');
            });
    }

    el('btn_du_cargar').addEventListener('click', llamarPreview);
    el('du_codigo').addEventListener('keydown', function (ev) {
        if (ev.key === 'Enter') { ev.preventDefault(); llamarPreview(); }
    });

    var btnCam = el('btn_du_camara');
    if (btnCam) {
        btnCam.addEventListener('click', function () {
            if (!window.JoyeriaPosBarcodeScanner) { alert('Escáner aún no cargado.'); return; }
            if (!JoyeriaPosBarcodeScanner.isSupported()) { alert('Tu navegador no puede usar la cámara aquí.'); return; }
            JoyeriaPosBarcodeScanner.open({
                onScan: function (codigo) {
                    el('du_codigo').value = codigo;
                    llamarPreview();
                }
            }).catch(function (err) { alert((err && err.message) || 'No se pudo abrir la camara.'); });
        });
    }

    el('du_cliente').addEventListener('change', function () {
        if (ESTADO.modo === 'monedero') actualizarResumen();
    });

    el('btn_du_reset').addEventListener('click', function () {
        el('du_codigo').value = '';
        el('du_motivo').value = '';
        el('du_forma_pago').value = '';
        resetUiPreview();
        mostrarMensaje('', '');
        try { el('du_codigo').focus(); } catch (e) {}
    });

    el('form_dev_unif').addEventListener('submit', function (e) {
        e.preventDefault();
        if (!ESTADO.preview || !ESTADO.modo) {
            mostrarMensaje('error', '<p>Carga primero la pieza y elige un modo.</p>');
            return;
        }
        var modo = ESTADO.modo;
        var idCli = parseInt((el('du_cliente').value || '0'), 10);
        if (modo === 'monedero' && idCli <= 0) {
            mostrarMensaje('error', '<p>Selecciona el cliente que recibira el credito al monedero.</p>');
            return;
        }
        var idFp = parseInt((el('du_forma_pago').value || '0'), 10);
        if ((modo === 'efectivo' || modo === 'otra_forma') && idFp <= 0) {
            mostrarMensaje('error', '<p>Selecciona la forma de pago del reembolso.</p>');
            return;
        }

        var preg = '¿Registrar la devolucion en modo "' + descripcionModo(modo).titulo + '"?';
        if (modo === 'efectivo' || modo === 'otra_forma') preg += ' Afecta caja.';
        if (!window.confirm(preg)) return;

        var payload = {
            tipo: 'devolucion',
            modo: modo,
            codigo: (el('du_codigo').value || '').trim(),
            id_pieza_stock_FK: ESTADO.preview.id_pieza_stock_FK || 0,
            id_venta_FK: ESTADO.preview.id_venta_origen || 0,
            motivo: (el('du_motivo').value || '').trim(),
        };
        if (idCli > 0) payload.id_cliente_FK = idCli;
        if (modo === 'efectivo' || modo === 'otra_forma') payload.id_forma_pago_FK = idFp;

        var btn = el('btn_du_enviar');
        btn.disabled = true;
        fetch('api/devoluciones.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json; charset=utf-8' },
            body: JSON.stringify(payload)
        }).then(function (r) {
            return r.text().then(function (t) {
                var res = null; try { res = t ? JSON.parse(t) : null; } catch (e) { throw new Error('Respuesta no valida del servidor.'); }
                if (!res || !res.success) throw new Error((res && res.error) || 'No se pudo registrar.');
                return res;
            });
        }).then(function (res) {
            mostrarMensaje('success', '<p>' + esc(res.message || 'Devolución registrada.') + '</p>');
            el('du_codigo').value = '';
            el('du_motivo').value = '';
            el('du_forma_pago').value = '';
            resetUiPreview();
            if (puedeLeer) cargarRecientes();
            try { el('du_codigo').focus(); } catch (e) {}
        }).catch(function (err) {
            mostrarMensaje('error', '<p>' + esc(err.message) + '</p>');
        }).finally(function () { btn.disabled = false; });
    });

    function cargarRecientes() {
        var box = el('du_recientes');
        if (!box) return;
        fetch('api/devoluciones.php?limit=30', { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res || !res.success || !Array.isArray(res.data)) { box.innerHTML = '<p class="text-muted">Sin datos.</p>'; return; }
                if (res.data.length === 0) { box.innerHTML = '<p class="text-muted">Sin registros recientes.</p>'; return; }
                var rows = res.data.map(function (d) {
                    var codAux = (d.pieza_codigo_auxiliar != null && String(d.pieza_codigo_auxiliar).trim() !== '')
                        ? String(d.pieza_codigo_auxiliar).trim() : '—';
                    return '<tr>'
                        + '<td>' + esc(d.fecha_devolucion) + '</td>'
                        + '<td>' + esc(d.tipo_origen) + '</td>'
                        + '<td>' + (d.id_venta_FK != null ? esc(d.id_venta_FK) : '—') + '</td>'
                        + '<td>' + esc(codAux) + '</td>'
                        + '<td class="text-right">$' + money(d.monto_reembolso) + '</td>'
                        + '<td>' + esc(d.forma_pago || '') + '</td>'
                        + '<td>' + (d.credito_id ? ('Credito #' + esc(d.credito_id)) : '—') + '</td>'
                        + '</tr>';
                }).join('');
                box.innerHTML = '<table class="admin-table"><thead><tr><th>Fecha</th><th>Origen</th><th>Venta</th><th>Cod. aux.</th><th class="text-right">Monto</th><th>Forma pago</th><th>Monedero</th></tr></thead><tbody>'
                    + rows + '</tbody></table>';
            })
            .catch(function () { box.innerHTML = '<p class="alert-message error">Error de red.</p>'; });
    }
    if (puedeLeer) cargarRecientes();

    setTimeout(function () { try { el('du_codigo').focus(); } catch (e) {} }, 200);
})();
</script>
<?php endif; ?>
