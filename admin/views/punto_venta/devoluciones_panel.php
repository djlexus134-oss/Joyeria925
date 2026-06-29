<?php
/** @var bool $mostrarPanelDevoluciones */
/** @var bool $puedeDevolucionCrear */
/** @var bool $puedeDevolucionLeer */
/** @var bool $puedeDevolucionMonedero */
/** @var int $idEmpleadoSesion */
$puedeDevolucionMonedero = isset($puedeDevolucionMonedero) && $puedeDevolucionMonedero;
?>
<details id="pos-devoluciones" class="form-section" style="margin-top:1.25rem;">
    <summary style="cursor:pointer; font-weight:600; list-style-position: outside;">
        <i class="bi bi-arrow-counterclockwise"></i> Devoluciones y canje (sin salida de efectivo)
    </summary>
    <div style="margin-top:1rem;">

        <?php if (!$puedeDevolucionCrear && $puedeDevolucionLeer): ?>
            <p class="text-muted">
                El alta de devoluciones (mostrador, monedero, reembolso) esta en el menu
                <strong>Comercial &gt; Devoluciones</strong>.
                Para agregar credito de canje a este ticket necesitas permiso <code>DEVOLUCION_CREAR</code>.
            </p>
        <?php endif; ?>

        <?php if ($puedeDevolucionCrear): ?>
            <h4 style="margin-top:0;"><i class="bi bi-receipt"></i> Credito al ticket actual (canje)</h4>
            <p class="text-muted" style="margin-bottom:0.75rem; max-width:760px; line-height:1.45;">
                Como en <strong>Devoluciones mostrador</strong>: escanea o escribe codigos y pulsa <strong>Agregar</strong> para armar la lista.
                Cuando tengas todas las piezas, pulsa <strong>Aplicar créditos al ticket</strong>; el descuento se refleja arriba en
                <em>Credito por devolucion (canje)</em> y en el total. Solo joyas <strong>vendidas</strong> en ventas <strong>completadas</strong>.
                El numero de venta es opcional (el sistema lo detecta por la pieza).
            </p>

            <div class="form-row" style="display:flex; flex-wrap:wrap; gap:10px; align-items:flex-end;">
                <div class="form-group" style="min-width:140px;">
                    <label for="pos_buscar_id_venta">Venta # (opcional)</label>
                    <input type="number" min="1" class="form-input" id="pos_buscar_id_venta" placeholder="Solo para cargar lineas">
                </div>
                <button type="button" class="btn-action-secondary" id="btn_pos_cargar_venta_dev">
                    <i class="bi bi-search"></i> Cargar lineas de venta
                </button>
            </div>

            <div id="pos_devolucion_venta_tabla" class="admin-table-wrapper" style="margin-top:0.5rem;"></div>

            <div class="form-row" style="display:flex; flex-wrap:wrap; gap:10px; align-items:flex-end; margin-top:0.75rem; max-width:760px;">
                <div class="form-group" style="flex:1 1 220px; min-width:0;">
                    <label for="pos_dev_cred_codigo"><i class="bi bi-upc-scan"></i> Código (barras o auxiliar)</label>
                    <div style="display:flex; gap:8px; align-items:stretch; flex-wrap:wrap;">
                        <input type="text" class="form-input joyeria-barcode-input" id="pos_dev_cred_codigo" autocomplete="off" placeholder="Escanea o escribe (ej. 28488/97) y Agregar" style="flex:1 1 160px; min-width:0;">
                        <button type="button" class="btn-action-secondary" id="btn_pos_dev_cred_escanear" title="Escanear con cámara" style="white-space:nowrap;">
                            <i class="bi bi-camera"></i> Camara
                        </button>
                        <button type="button" class="btn-action-primary" id="btn_pos_dev_cred_agregar" style="white-space:nowrap;">
                            <i class="bi bi-plus-lg"></i> Agregar
                        </button>
                        <button type="button" class="btn-action-secondary" id="btn_pos_dev_cred_limpiar" style="white-space:nowrap;">
                            <i class="bi bi-arrow-clockwise"></i> Limpiar lista
                        </button>
                    </div>
                </div>
            </div>

            <div id="pos_dev_scan_leyenda" role="status" aria-live="polite" style="display:none;margin:0.4rem 0 0;padding:0.45rem 0.65rem;border-radius:8px;font-size:0.88rem;line-height:1.35;border:1px solid transparent; max-width:760px;"></div>

            <div class="admin-table-wrapper" style="margin-top:0.5rem;">
                <table class="admin-table" id="pos_dev_tabla_cola">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Código</th>
                            <th>Venta</th>
                            <th>Descripción</th>
                            <th class="text-right">Credito</th>
                            <th style="width:100px;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="pos_dev_cola_body">
                        <tr id="pos_dev_cola_vacia"><td colspan="6" class="text-muted">Sin piezas en la lista. Agrega codigos antes de aplicar al ticket.</td></tr>
                    </tbody>
                    <tfoot id="pos_dev_cola_foot" style="display:none;">
                        <tr>
                            <td colspan="4" class="text-right"><strong>Total credito en lista:</strong></td>
                            <td class="text-right"><strong id="pos_dev_cola_total">$0.00</strong></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="form-group" style="margin-top:0.75rem; max-width:520px;">
                <label for="pos_dev_cred_motivo">Motivo (opcional, aplica a todas al guardar)</label>
                <input type="text" class="form-input" id="pos_dev_cred_motivo" placeholder="Notas" maxlength="500">
            </div>

            <button type="button" class="btn-action-primary" id="btn_pos_dev_aplicar_lote" style="margin-top:0.75rem;">
                <i class="bi bi-check2-circle"></i> Aplicar créditos al ticket
            </button>

            <div id="pos_devolucion_venta_mensaje" style="margin-top:0.75rem;"></div>

            <?php if ($puedeDevolucionMonedero): ?>
            <hr style="margin:1.5rem 0; border:none; border-top:1px solid #e2e8f0;">
            <h4 style="margin-top:0;"><i class="bi bi-wallet2"></i> Dejar credito en monedero del cliente</h4>
            <p class="text-muted" style="max-width:760px; line-height:1.45;">
                Registra la devolución de inmediato y acredita el valor al <strong>monedero</strong> del cliente (sin canje en este ticket).
                Requiere <strong>cliente</strong> seleccionado arriba en el ticket o en el selector de abajo. El cliente podra usar el saldo en ventas o apartados posteriores.
            </p>
            <div class="form-row" style="display:flex; flex-wrap:wrap; gap:10px; align-items:flex-end; max-width:760px;">
                <div class="form-group" style="min-width:200px; flex:1 1 200px;">
                    <label for="pos_dev_monedero_cliente">Cliente (monedero)</label>
                    <select class="form-input" id="pos_dev_monedero_cliente">
                        <option value="">— Usar cliente del ticket —</option>
                        <?php
                        $clientes = isset($catalogos) && is_array($catalogos) ? ($catalogos['clientes'] ?? []) : [];
                        $selectedId = '';
                        $includeEmpty = false;
                        require __DIR__ . '/../partials/cliente_select_options.php';
                        ?>
                    </select>
                </div>
                <div class="form-group" style="min-width:140px;">
                    <label for="pos_dev_monedero_venta">Venta # (opcional)</label>
                    <input type="number" min="1" class="form-input" id="pos_dev_monedero_venta" placeholder="Auto por pieza">
                </div>
            </div>
            <div class="form-row" style="max-width:760px; margin-top:0.5rem;">
                <div class="form-group" style="flex:1 1 220px;">
                    <label for="pos_dev_monedero_codigo">Código pieza</label>
                    <input type="text" class="form-input joyeria-barcode-input" id="pos_dev_monedero_codigo" autocomplete="off" placeholder="Escanear o escribir">
                </div>
                <button type="button" class="btn-action-secondary" id="btn_pos_dev_monedero_preview">Vista previa</button>
                <button type="button" class="btn-action-primary" id="btn_pos_dev_monedero_registrar">Registrar en monedero</button>
            </div>
            <div id="pos_dev_monedero_preview" class="text-muted" style="margin-top:0.5rem; max-width:760px; font-size:0.92rem;"></div>
            <?php endif; ?>

            <p class="text-muted" style="margin-top:1rem; margin-bottom:0; font-size:0.92rem;">
                Devoluciones con flujo unificado (efectivo, otra forma, monedero, solo inventario):
                <a href="devoluciones.php?accion=leer">Pantalla de Devoluciones</a>
            </p>
        <?php endif; ?>

    </div>
</details>

<script>
(function () {
    var puedeCrear = <?php echo $puedeDevolucionCrear ? 'true' : 'false'; ?>;

    function el(id) { return document.getElementById(id); }

    function escHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function posPostAccion(accion, fields) {
        var fd = new FormData();
        Object.keys(fields || {}).forEach(function (k) {
            fd.append(k, fields[k] == null ? '' : String(fields[k]));
        });
        if (window.joyeriaAppendCsrfToFormData) {
            window.joyeriaAppendCsrfToFormData(fd);
        }
        return fetch('punto_venta.php?accion=' + encodeURIComponent(accion), {
            method: 'POST',
            body: fd,
            credentials: 'same-origin'
        }).then(function (r) {
            return r.json().then(function (res) {
                if (r.status === 403 && res && !res.ok
                    && window.joyeriaIsCsrfErrorMessage
                    && window.joyeriaIsCsrfErrorMessage(res.mensaje || '')) {
                    throw new Error('Sesión de seguridad expirada. Recarga la página (F5) e intenta de nuevo.');
                }
                if (!res || !res.ok) {
                    var err = new Error((res && res.mensaje) || 'Error en la operacion.');
                    if (res && res.codigo_error) {
                        err.codigo_error = res.codigo_error;
                    }
                    throw err;
                }
                return res;
            });
        });
    }

    function posDevMsg(html, isError) {
        var w = el('pos_devolucion_venta_mensaje');
        if (!w) return;
        w.innerHTML = html ? '<div class="alert-message ' + (isError ? 'error' : 'info') + '"><p>' + html + '</p></div>' : '';
    }

    function setLeyenda(kind, message) {
        var box = el('pos_dev_scan_leyenda');
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

    function notificarPosPrincipal(data) {
        if (window.joyeriaPosActualizarDesdeDevoluciones && typeof window.joyeriaPosActualizarDesdeDevoluciones === 'function') {
            window.joyeriaPosActualizarDesdeDevoluciones(data);
        }
    }

    function abrirCamaraParaCampo(codigoInput) {
        if (!codigoInput) return;
        if (!window.JoyeriaPosBarcodeScanner) {
            alert('El escáner no está cargado. Espera un momento y vuelve a intentar, o recarga la pagina.');
            return;
        }
        if (!JoyeriaPosBarcodeScanner.isSupported()) {
            alert('Tu navegador no puede usar la cámara aquí. Prueba Chrome/Edge en localhost o con HTTPS.');
            return;
        }
        if (window.JoyeriaPosScanFeedback) {
            JoyeriaPosScanFeedback.prepare();
        }
        JoyeriaPosBarcodeScanner.open({
            onScan: function (codigo) {
                codigoInput.value = codigo || '';
                if (window.JoyeriaPosScanFeedback) {
                    JoyeriaPosScanFeedback.success();
                }
                if (window.JoyeriaPosBarcodeScanner) {
                    JoyeriaPosBarcodeScanner.notifyScanResult('Codigo capturado.', 'success');
                }
                agregarLineaCanje(true);
            },
            onStatus: function (message, kind) {
                if (kind === 'error') {
                    alert(message);
                }
            }
        }).catch(function (err) {
            alert((err && err.message) ? err.message : 'No se pudo abrir la camara.');
        });
    }

    if (!puedeCrear) return;

    var colaCanje = [];
    var inpCodigo = el('pos_dev_cred_codigo');
    var tbodyCola = el('pos_dev_cola_body');
    var tfootCola = el('pos_dev_cola_foot');
    var totalColaEl = el('pos_dev_cola_total');

    function idVentaFiltro() {
        var raw = (el('pos_buscar_id_venta').value || '').trim();
        if (!raw) return 0;
        var idV = parseInt(raw, 10);
        return isFinite(idV) && idV > 0 ? idV : 0;
    }

    function piezaEnCola(idPs) {
        for (var i = 0; i < colaCanje.length; i++) {
            if (parseInt(colaCanje[i].id_pieza_stock_FK || 0, 10) === idPs) {
                return true;
            }
        }
        return false;
    }

    function renderColaCanje() {
        if (!tbodyCola) return;
        tbodyCola.innerHTML = '';
        var suma = 0;
        if (colaCanje.length === 0) {
            var tr0 = document.createElement('tr');
            tr0.id = 'pos_dev_cola_vacia';
            tr0.innerHTML = '<td colspan="6" class="text-muted">Sin piezas en la lista. Agrega codigos antes de aplicar al ticket.</td>';
            tbodyCola.appendChild(tr0);
            if (tfootCola) tfootCola.style.display = 'none';
            return;
        }
        for (var i = 0; i < colaCanje.length; i++) {
            var c = colaCanje[i];
            var m = parseFloat(c.monto_credito || 0);
            if (isFinite(m)) suma += m;
            var cod = (c.codigo || '').trim();
            var tr = document.createElement('tr');
            tr.innerHTML = '<td>' + (i + 1) + '</td>'
                + '<td>' + escHtml(cod !== '' ? cod : '—') + '</td>'
                + '<td>#' + escHtml(c.id_venta_origen || '') + '</td>'
                + '<td>' + escHtml(c.descripcion || '') + '</td>'
                + '<td class="text-right">$' + m.toFixed(2) + '</td>'
                + '<td><button type="button" class="btn-action-danger btn-pos-dev-quitar" data-idx="' + i + '"><i class="bi bi-trash"></i></button></td>';
            tbodyCola.appendChild(tr);
        }
        if (tfootCola) {
            tfootCola.style.display = '';
        }
        if (totalColaEl) {
            totalColaEl.textContent = '$' + suma.toFixed(2);
        }
    }

    function focoCodigo() {
        if (inpCodigo) {
            inpCodigo.focus();
            try { inpCodigo.select(); } catch (e0) {}
        }
    }

    function agregarCreditoObjeto(cred, codigoCaptura) {
        var idPs = parseInt(cred.id_pieza_stock_FK || 0, 10);
        if (piezaEnCola(idPs)) {
            throw new Error('Esa pieza ya esta en la lista.');
        }
        if (codigoCaptura) {
            cred.codigo = codigoCaptura;
        } else if (!cred.codigo) {
            cred.codigo = '';
        }
        colaCanje.push(cred);
        renderColaCanje();
    }

    function agregarLineaCanje(desdeCamara) {
        var codigo = (inpCodigo && inpCodigo.value ? inpCodigo.value : '').trim();
        if (!codigo) {
            setLeyenda('info', 'Ingresa un código para agregarlo a la lista.');
            return;
        }
        var idV = idVentaFiltro();
        var motivo = (el('pos_dev_cred_motivo') && el('pos_dev_cred_motivo').value)
            ? el('pos_dev_cred_motivo').value.toString().trim()
            : '';
        var btn = el('btn_pos_dev_cred_agregar');
        if (btn) btn.disabled = true;
        posPostAccion('preparar_credito_canje', {
            id_venta: idV,
            codigo: codigo,
            motivo: motivo
        })
            .then(function (res) {
                if (!res || !res.ok || !res.credito) {
                    throw new Error((res && res.mensaje) || 'No se pudo validar la pieza.');
                }
                agregarCreditoObjeto(res.credito, codigo);
                if (inpCodigo) inpCodigo.value = '';
                setLeyenda('success', 'Agregado a la lista: ' + codigo + ' (venta #' + res.credito.id_venta_origen + ').');
                if (!desdeCamara) focoCodigo();
            })
            .catch(function (err) {
                var m = err.message || 'Error';
                setLeyenda('error', m);
                if (desdeCamara) alert(m);
            })
            .finally(function () {
                if (btn) btn.disabled = false;
            });
    }

    function agregarLineaDesdeVenta(idV, idPs, desc, codAux) {
        var motivo = (el('pos_dev_cred_motivo') && el('pos_dev_cred_motivo').value)
            ? el('pos_dev_cred_motivo').value.toString().trim()
            : '';
        return posPostAccion('preparar_credito_canje', {
            id_venta: idV,
            id_pieza_stock_FK: idPs,
            motivo: motivo
        }).then(function (res) {
            if (!res || !res.ok || !res.credito) {
                throw new Error((res && res.mensaje) || 'No se pudo validar la pieza.');
            }
            agregarCreditoObjeto(res.credito, codAux || res.credito.codigo || '');
            setLeyenda('success', 'Agregado a la lista: ' + (desc || 'pieza') + '.');
        });
    }

    el('btn_pos_cargar_venta_dev').addEventListener('click', function () {
        var idV = idVentaFiltro();
        posDevMsg('');
        el('pos_devolucion_venta_tabla').innerHTML = '';
        if (!idV) {
            posDevMsg('Indica el numero de venta para cargar sus lineas.', true);
            return;
        }
        fetch('api/ventas.php?id=' + encodeURIComponent(idV), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res || !res.success || !res.data) {
                    throw new Error((res && res.error) || 'Venta no encontrada.');
                }
                var v = res.data;
                if (String(v.estado || '') !== 'completada') {
                    posDevMsg('Solo se pueden usar ventas completadas (esta en: ' + (v.estado || '') + ').', true);
                    return;
                }
                var det = Array.isArray(v.detalle) ? v.detalle : [];
                var htmlRows = [];
                det.forEach(function (ln) {
                    if (String(ln.tipo_linea || '') !== 'joya') return;
                    var anulada = parseInt(ln.anulada || 0, 10);
                    var idPs = parseInt(ln.id_pieza_stock_FK || 0, 10);
                    var estadoP = String(ln.estado_pieza || '');
                    var puede = anulada === 0 && idPs > 0 && estadoP === 'vendida' && !piezaEnCola(idPs);
                    var desc = String(ln.nombre_item || '');
                    var codRaw = String(ln.pieza_codigo_auxiliar || ln.codigo_auxiliar || '').trim();
                    htmlRows.push('<tr>'
                        + '<td>' + escHtml(codRaw !== '' ? codRaw : '—') + '</td>'
                        + '<td>' + escHtml(desc) + '</td>'
                        + '<td>' + escHtml(estadoP) + '</td>'
                        + '<td class="text-right">$' + parseFloat(ln.subtotal || 0).toFixed(2) + '</td>'
                        + '<td>' + (puede
                            ? '<button type="button" class="btn-action-secondary btn-pos-agregar-lista" data-id-venta="' + idV + '" data-id-pieza="' + idPs + '" data-desc="' + escHtml(desc) + '" data-cod="' + escHtml(codRaw) + '"><i class="bi bi-plus-lg"></i> A la lista</button>'
                            : (piezaEnCola(idPs) ? '<span class="text-muted">En lista</span>' : '—'))
                        + '</td>'
                        + '</tr>');
                });
                if (!htmlRows.length) {
                    posDevMsg('No hay lineas de joyeria disponibles en esta venta.', true);
                    return;
                }
                el('pos_devolucion_venta_tabla').innerHTML = '<p class="text-muted" style="margin:0 0 0.35rem;">Lineas de venta #' + idV + '</p>'
                    + '<table class="admin-table"><thead><tr><th>Cod.</th><th>Descripción</th><th>Estado</th><th class="text-right">Subtotal</th><th></th></tr></thead><tbody>'
                    + htmlRows.join('') + '</tbody></table>';
            })
            .catch(function (err) {
                posDevMsg(err.message || 'Error al cargar la venta.', true);
            });
    });

    document.addEventListener('click', function (ev) {
        var bLista = ev.target.closest('.btn-pos-agregar-lista');
        if (bLista) {
            var idV = parseInt(bLista.getAttribute('data-id-venta') || '0', 10);
            var idPs = parseInt(bLista.getAttribute('data-id-pieza') || '0', 10);
            var desc = bLista.getAttribute('data-desc') || '';
            var cod = bLista.getAttribute('data-cod') || '';
            bLista.disabled = true;
            agregarLineaDesdeVenta(idV, idPs, desc, cod)
                .catch(function (err) { alert(err.message || 'Error'); })
                .finally(function () {
                    bLista.disabled = false;
                    bLista.outerHTML = '<span class="text-muted">En lista</span>';
                });
            return;
        }
        var bQuitar = ev.target.closest('.btn-pos-dev-quitar');
        if (bQuitar) {
            var idx = parseInt(bQuitar.getAttribute('data-idx') || '-1', 10);
            if (idx >= 0 && idx < colaCanje.length) {
                colaCanje.splice(idx, 1);
                renderColaCanje();
                setLeyenda('', '');
                focoCodigo();
            }
        }
    });

    el('btn_pos_dev_aplicar_lote').addEventListener('click', function () {
        var btn = el('btn_pos_dev_aplicar_lote');
        posDevMsg('');
        if (colaCanje.length === 0) {
            setLeyenda('error', 'Agrega al menos una pieza a la lista antes de aplicar.');
            posDevMsg('Agrega al menos una pieza a la lista.', true);
            return;
        }
        var motivo = (el('pos_dev_cred_motivo') && el('pos_dev_cred_motivo').value)
            ? el('pos_dev_cred_motivo').value.toString().trim()
            : '';
        if (btn) btn.disabled = true;
        posPostAccion('aplicar_creditos_canje_lote', {
            creditos: JSON.stringify(colaCanje),
            motivo: motivo
        })
            .then(function (res) {
                if (!res || !res.ok) {
                    throw new Error((res && res.mensaje) || 'No se pudieron aplicar los creditos.');
                }
                var n = colaCanje.length;
                colaCanje = [];
                renderColaCanje();
                el('pos_devolucion_venta_tabla').innerHTML = '';
                setLeyenda('success', 'Se aplicaron ' + n + ' credito(s) al ticket. Revisa el total y el bloque de canje arriba.');
                posDevMsg('Creditos aplicados. Agrega productos nuevos al ticket y confirma la venta para completar el canje.', false);
                notificarPosPrincipal(res);
                focoCodigo();
            })
            .catch(function (err) {
                var m = err.message || 'Error';
                setLeyenda('error', m);
                posDevMsg(escHtml(m), true);
            })
            .finally(function () {
                if (btn) btn.disabled = false;
            });
    });

    var btnCredCam = el('btn_pos_dev_cred_escanear');
    if (btnCredCam && inpCodigo) {
        btnCredCam.addEventListener('click', function () {
            abrirCamaraParaCampo(inpCodigo);
        });
    }
    el('btn_pos_dev_cred_agregar').addEventListener('click', function () {
        agregarLineaCanje(false);
    });
  el('btn_pos_dev_cred_limpiar').addEventListener('click', function () {
        if (colaCanje.length === 0) return;
        if (!window.confirm('Vaciar la lista de piezas pendientes de canje?')) return;
        colaCanje = [];
        renderColaCanje();
        setLeyenda('', '');
    });
    if (inpCodigo) {
        inpCodigo.addEventListener('keydown', function (ev) {
            if (ev.key === 'Enter') {
                ev.preventDefault();
                agregarLineaCanje(false);
            }
        });
    }

    window.joyeriaPosLimpiarDevolucionesUi = function (silencioso) {
        colaCanje = [];
        renderColaCanje();
        setLeyenda('', '');
        if (!silencioso) {
            posDevMsg('');
        } else {
            posDevMsg('');
        }
        var inpVenta = el('pos_buscar_id_venta');
        if (inpVenta) {
            inpVenta.value = '';
        }
        var tablaVenta = el('pos_devolucion_venta_tabla');
        if (tablaVenta) {
            tablaVenta.innerHTML = '';
        }
        if (inpCodigo) {
            inpCodigo.value = '';
        }
        var inpMotivo = el('pos_dev_cred_motivo');
        if (inpMotivo) {
            inpMotivo.value = '';
        }
        var inpMonVenta = el('pos_dev_monedero_venta');
        if (inpMonVenta) {
            inpMonVenta.value = '';
        }
        var inpMonCodigo = el('pos_dev_monedero_codigo');
        if (inpMonCodigo) {
            inpMonCodigo.value = '';
        }
        var boxMonPreview = el('pos_dev_monedero_preview');
        if (boxMonPreview) {
            boxMonPreview.textContent = '';
        }
        var selMonCliente = el('pos_dev_monedero_cliente');
        if (selMonCliente) {
            selMonCliente.value = '';
        }
    };

    renderColaCanje();
    setTimeout(focoCodigo, 300);

    var puedeMonedero = <?php echo !empty($puedeDevolucionMonedero) ? 'true' : 'false'; ?>;
    if (puedeMonedero) {
        var selMonCliente = el('pos_dev_monedero_cliente');
        var inpMonVenta = el('pos_dev_monedero_venta');
        var inpMonCodigo = el('pos_dev_monedero_codigo');
        var boxPreview = el('pos_dev_monedero_preview');
        var selTicketCliente = document.getElementById('id_cliente_FK');

        function idClienteMonedero() {
            var v = selMonCliente && selMonCliente.value ? parseInt(selMonCliente.value, 10) : 0;
            if (v > 0) return v;
            if (selTicketCliente && selTicketCliente.value) {
                return parseInt(selTicketCliente.value, 10) || 0;
            }
            return 0;
        }

        function postDevolucionesApi(body) {
            return fetch('api/devoluciones.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json; charset=utf-8' },
                body: JSON.stringify(body)
            }).then(function (r) {
                return r.text().then(function (t) {
                    var res = null;
                    try { res = t ? JSON.parse(t) : null; } catch (e) { throw new Error('Respuesta invalida del API.'); }
                    if (!res || !res.success) {
                        throw new Error((res && res.error) || 'Error en devoluciones.');
                    }
                    return res;
                });
            });
        }

        function renderPreviewMonedero(data) {
            if (!boxPreview) return;
            if (!data) {
                boxPreview.textContent = '';
                return;
            }
            boxPreview.innerHTML = 'Pieza: <strong>' + escHtml(data.descripcion || '') + '</strong> (' + escHtml(data.codigo || '') + ')'
                + ' · Venta #' + escHtml(data.id_venta_origen || '—')
                + ' · Credito: <strong>$' + parseFloat(data.monto_credito || 0).toFixed(2) + '</strong>'
                + ' · Monedero actual: $' + parseFloat(data.monedero_saldo_actual || 0).toFixed(2)
                + ' → tras registro: <strong>$' + parseFloat(data.monedero_saldo_tras || 0).toFixed(2) + '</strong>';
        }

        el('btn_pos_dev_monedero_preview').addEventListener('click', function () {
            var idCli = idClienteMonedero();
            var cod = inpMonCodigo && inpMonCodigo.value ? inpMonCodigo.value.trim() : '';
            if (idCli <= 0) {
                alert('Selecciona el cliente del ticket o en el selector de monedero.');
                return;
            }
            if (!cod) {
                alert('Indica el código de la pieza.');
                return;
            }
            postDevolucionesApi({
                tipo: 'preview_monedero',
                id_cliente_FK: idCli,
                id_venta_FK: inpMonVenta && inpMonVenta.value ? parseInt(inpMonVenta.value, 10) : 0,
                codigo: cod
            }).then(function (res) {
                renderPreviewMonedero(res.data || null);
                setLeyenda('success', 'Vista previa lista. Confirma con Registrar en monedero.');
            }).catch(function (err) {
                renderPreviewMonedero(null);
                setLeyenda('error', err.message || 'Error');
            });
        });

        el('btn_pos_dev_monedero_registrar').addEventListener('click', function () {
            var idCli = idClienteMonedero();
            var cod = inpMonCodigo && inpMonCodigo.value ? inpMonCodigo.value.trim() : '';
            var motivo = (el('pos_dev_cred_motivo') && el('pos_dev_cred_motivo').value)
                ? el('pos_dev_cred_motivo').value.toString().trim()
                : '';
            if (idCli <= 0) {
                alert('Selecciona el cliente del ticket o en el selector de monedero.');
                return;
            }
            if (!cod) {
                alert('Indica el código de la pieza.');
                return;
            }
            if (!window.confirm('Registrar devolución y acreditar $ en el monedero del cliente?')) {
                return;
            }
            postDevolucionesApi({
                tipo: 'monedero',
                id_cliente_FK: idCli,
                id_venta_FK: inpMonVenta && inpMonVenta.value ? parseInt(inpMonVenta.value, 10) : 0,
                codigo: cod,
                motivo: motivo
            }).then(function (res) {
                var d = res.data || {};
                posDevMsg(escHtml(res.message || 'Credito registrado en monedero.'), false);
                setLeyenda('success', res.message || 'Listo.');
                renderPreviewMonedero(null);
                if (inpMonCodigo) inpMonCodigo.value = '';
            }).catch(function (err) {
                posDevMsg(escHtml(err.message || 'Error'), true);
                setLeyenda('error', err.message || 'Error');
            });
        });

        if (window.JoyeriaFkAutocomplete && selMonCliente) {
            JoyeriaFkAutocomplete.initSelectAutocomplete({
                selectId: 'pos_dev_monedero_cliente',
                allowEmpty: true,
                placeholder: 'Nombre, apellido, correo o teléfono...'
            });
        }
    }
})();
</script>
