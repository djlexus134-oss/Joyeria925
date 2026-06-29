/**
 * Tabla unificada de apartados activos (consulta + abonar + quitar/agregar pieza).
 * Requiere window.JOYERIA_APARTADOS_ACTIVOS definido antes de cargar este script.
 */
(function () {
    'use strict';

    function escapeHtml(s) {
        if (s === null || s === undefined) return '';
        var t = document.createElement('div');
        t.textContent = String(s);
        return t.innerHTML;
    }

    function fmtMoney(v) {
        var n = parseFloat(v);
        if (isNaN(n)) return '0.00';
        return n.toFixed(2);
    }

    function getCfg() {
        return window.JOYERIA_APARTADOS_ACTIVOS || {};
    }

    function labelTipoOrigen(tipo) {
        var t = String(tipo || '').toLowerCase();
        if (t === 'cobro_tienda') return 'Cobro en tienda';
        if (t === 'credito_por_cambio') return 'Credito por cambio';
        if (t === 'credito_cliente') return 'Credito a favor (monedero)';
        return t || '—';
    }

    function fmtFecha(f) {
        if (!f) return '';
        var s = String(f);
        if (s.length >= 16) return s.substring(0, 16).replace('T', ' ');
        return s;
    }

    function abrirModalAbonos(idApartado) {
        var dlg = document.getElementById('aa_modal_abonos');
        var tbodyM = document.getElementById('aa_modal_abonos_body');
        var sub = document.getElementById('aa_modal_abonos_sub');
        var title = document.getElementById('aa_modal_abonos_title');
        var totalEl = document.getElementById('aa_modal_abonos_total');
        if (!dlg || !tbodyM) return;
        tbodyM.innerHTML = '<tr><td colspan="6">Cargando...</td></tr>';
        if (sub) sub.textContent = '';
        if (totalEl) totalEl.textContent = '';
        if (title) title.textContent = 'Abonos — apartado #' + idApartado;
        if (typeof dlg.showModal === 'function') {
            dlg.showModal();
        }
        fetch('api/apartados_gestion.php?id_apartado=' + encodeURIComponent(String(idApartado)), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res || !res.success) throw new Error((res && res.error) || 'Error');
                var d = res.data || {};
                var pagos = d.pagos || [];
                if (sub) {
                    sub.textContent = (d.cliente_nombre || '') + ' — Total $' + fmtMoney(d.total_apartado)
                        + ' | Saldo $' + fmtMoney(d.saldo_pendiente);
                }
                if (pagos.length === 0) {
                    tbodyM.innerHTML = '<tr><td colspan="6">Sin abonos registrados.</td></tr>';
                    if (totalEl) totalEl.textContent = 'Total abonado (registrado): $0.00';
                    return;
                }
                var sumReg = 0;
                var rows = '';
                for (var j = 0; j < pagos.length; j++) {
                    var P = pagos[j];
                    var est = String(P.estado || '');
                    var monto = parseFloat(P.monto);
                    if (isNaN(monto)) monto = 0;
                    if (est === 'registrado') sumReg += monto;
                    rows += '<tr>' +
                        '<td>' + escapeHtml(P.id_pago || (j + 1)) + '</td>' +
                        '<td>' + escapeHtml(fmtFecha(P.fecha_registro)) + '</td>' +
                        '<td class="text-right">$' + fmtMoney(P.monto) + '</td>' +
                        '<td>' + escapeHtml(P.forma_pago || '') + '</td>' +
                        '<td>' + escapeHtml(labelTipoOrigen(P.tipo_origen)) + '</td>' +
                        '<td>' + escapeHtml(est || '—') + '</td>' +
                        '</tr>';
                }
                tbodyM.innerHTML = rows;
                if (totalEl) {
                    totalEl.textContent = 'Total abonado (registrado): $' + fmtMoney(sumReg);
                }
            })
            .catch(function (e) {
                tbodyM.innerHTML = '<tr><td colspan="6">' + escapeHtml(e.message || 'Error') + '</td></tr>';
            });
    }

    function abrirModalPiezas(idApartado) {
        var dlg = document.getElementById('aa_modal_piezas');
        var tbodyM = document.getElementById('aa_modal_piezas_body');
        var sub = document.getElementById('aa_modal_piezas_sub');
        var title = document.getElementById('aa_modal_piezas_title');
        if (!dlg || !tbodyM) return;
        tbodyM.innerHTML = '<tr><td colspan="5">Cargando...</td></tr>';
        if (sub) sub.textContent = '';
        if (title) title.textContent = 'Apartado #' + idApartado;
        if (typeof dlg.showModal === 'function') {
            dlg.showModal();
        }
        fetch('api/apartados_gestion.php?id_apartado=' + encodeURIComponent(String(idApartado)), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res || !res.success) throw new Error((res && res.error) || 'Error');
                var d = res.data || {};
                var dets = d.detalles || [];
                if (sub) {
                    sub.textContent = (d.cliente_nombre || '') + ' — Total $' + fmtMoney(d.total_apartado)
                        + ' | Saldo $' + fmtMoney(d.saldo_pendiente);
                }
                if (dets.length === 0) {
                    tbodyM.innerHTML = '<tr><td colspan="5">Sin lineas en este apartado.</td></tr>';
                    return;
                }
                var rows = '';
                for (var j = 0; j < dets.length; j++) {
                    var L = dets[j];
                    rows += '<tr>' +
                        '<td>' + (j + 1) + '</td>' +
                        '<td>' + escapeHtml(L.codigo_barras || '') + '</td>' +
                        '<td>' + escapeHtml(L.desc_pieza || '') + '</td>' +
                        '<td>' + escapeHtml(L.estado_pieza || '') + '</td>' +
                        '<td class="text-right">$' + fmtMoney(L.precio_apartado) + '</td>' +
                        '</tr>';
                }
                tbodyM.innerHTML = rows;
            })
            .catch(function (e) {
                tbodyM.innerHTML = '<tr><td colspan="5">' + escapeHtml(e.message || 'Error') + '</td></tr>';
            });
    }

    function renderTablaFilas(rows) {
        var tb = document.getElementById('aa_tabla_apartados_body');
        if (!tb) return;
        var G = getCfg();
        var ctx = G.context || 'consulta';

        if (!rows || rows.length === 0) {
            tb.innerHTML = '<tr><td colspan="10">Sin registros.</td></tr>';
            return;
        }

        var h = '';
        for (var i = 0; i < rows.length; i++) {
            var r = rows[i];
            var idAp = parseInt(r.id_apartado, 10);
            var acc = '<td class="aa-td-acciones" style="white-space:nowrap;">';
            if (G.puedeVerAbonos !== false) {
                acc += '<button type="button" class="btn-action-secondary aa-btn-ver-abonos" data-id-apartado="' + idAp + '" title="Ver abonos registrados">' +
                    '<i class="bi bi-receipt"></i> Abonos</button> ';
            }

            if (ctx === 'consulta') {
                if (G.puedeAbonar) {
                    acc += '<button type="button" class="btn-action-primary aa-btn-abonar" data-id-apartado="' + idAp + '">' +
                        '<i class="bi bi-cash-coin"></i> Abonar</button> ';
                }
                if (G.puedeCambioLink) {
                    acc += '<a href="apartados_operaciones.php?accion=leer&amp;destino=quitar&amp;id_apartado=' + idAp + '" class="btn-action-secondary aa-link-cambio" style="display:inline-block;margin-left:4px;">' +
                        '<i class="bi bi-dash-square"></i> Quitar pieza</a>' +
                        '<a href="apartados_operaciones.php?accion=leer&amp;destino=agregar&amp;id_apartado=' + idAp + '" class="btn-action-secondary aa-link-agregar" style="display:inline-block;margin-left:4px;">' +
                        '<i class="bi bi-plus-square"></i> Agregar pieza</a>';
                }
            } else if (ctx === 'unificado') {
                if (G.puedeAbonar) {
                    acc += '<button type="button" class="btn-action-primary aa-btn-abonar" data-id-apartado="' + idAp + '">' +
                        '<i class="bi bi-cash-coin"></i> Abonar</button> ';
                }
                if (G.puedeQuitarPieza) {
                    acc += '<button type="button" class="btn-action-secondary aa-btn-quitar-pieza" data-id-apartado="' + idAp + '" style="margin-left:4px;">' +
                        '<i class="bi bi-dash-square"></i> Quitar pieza</button>';
                }
                if (G.puedeAgregarPieza) {
                    acc += '<button type="button" class="btn-action-secondary aa-btn-agregar-pieza" data-id-apartado="' + idAp + '" style="margin-left:4px;">' +
                        '<i class="bi bi-plus-square"></i> Agregar pieza</button>';
                }
            } else if (ctx === 'cambio') {
                if (G.linkAbonarConsulta) {
                    acc += '<a href="apartados_operaciones.php?accion=leer&amp;destino=abono&amp;id_apartado=' + idAp + '" class="btn-action-primary aa-link-abonar" style="display:inline-block;">' +
                        '<i class="bi bi-cash-coin"></i> Abonar</a> ';
                }
                if (G.usarCambio) {
                    acc += '<button type="button" class="btn-action-secondary aa-btn-usar-cambio" data-id-apartado="' + idAp + '" style="margin-left:4px;">' +
                        '<i class="bi bi-arrow-left-right"></i> Usar en cambio</button>';
                }
            }
            acc += '</td>';

            h += '<tr>' +
                '<td>' + idAp + '</td>' +
                '<td>' + escapeHtml(r.estado) + '</td>' +
                '<td>' + escapeHtml(r.cliente_nombre) + '</td>' +
                '<td class="text-right">' + parseInt(r.lineas_count || 1, 10) + '</td>' +
                '<td>' + escapeHtml(r.codigo_pieza != null && r.codigo_pieza !== '' ? r.codigo_pieza : '—') + '</td>' +
                '<td>' + escapeHtml(r.fecha_vencimiento || '') + '</td>' +
                '<td class="text-right">$' + fmtMoney(r.total_apartado) + '</td>' +
                '<td class="text-right">$' + fmtMoney(r.saldo_pendiente) + '</td>' +
                '<td><button type="button" class="btn-action-secondary aa-btn-piezas" data-id-apartado="' + idAp + '">' +
                '<i class="bi bi-box-seam"></i> Ver</button></td>' +
                acc +
                '</tr>';
        }
        tb.innerHTML = h;
    }

    function cargarLista(idCliente) {
        var url = 'api/apartados_gestion.php?limit=150&estado=activo';
        if (idCliente > 0) {
            url += '&id_cliente=' + encodeURIComponent(String(idCliente));
        }
        fetch(url, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res || !res.success) throw new Error((res && res.error) || 'Error');
                renderTablaFilas(res.data || []);
            })
            .catch(function (e) {
                alert(e.message || 'Error al cargar la lista');
            });
    }

    function recargarDesdeFiltro() {
        var s = document.getElementById('aa_filtro_cliente');
        var id = s ? parseInt(s.value || '0', 10) : 0;
        cargarLista(id);
    }

    window.joyeriaApartadosActivosRecargarTabla = recargarDesdeFiltro;
    window.joyeriaApartadosConsultaRecargarTabla = recargarDesdeFiltro;
    window.joyeriaApartadosCambioRecargarTabla = recargarDesdeFiltro;

    document.addEventListener('DOMContentLoaded', function () {
        var sel = document.getElementById('aa_filtro_cliente');
        if (!sel) return;

        var G = getCfg();

        if (window.JoyeriaFkAutocomplete) {
            JoyeriaFkAutocomplete.initSelectAutocomplete({
                selectId: 'aa_filtro_cliente',
                allowEmpty: true,
                placeholder: 'Nombre, apellido, correo o teléfono...',
                invalidMessage: 'Elige un cliente de la lista o deja en blanco para ver todos los activos.'
            });
        }

        sel.addEventListener('change', function () {
            cargarLista(parseInt(sel.value || '0', 10));
        });

        var tbApart = document.getElementById('aa_tabla_apartados_body');
        if (tbApart) {
            tbApart.addEventListener('click', function (ev) {
                var G2 = getCfg();
                var ctxAb = G2.context || 'consulta';
                var host = document.getElementById('ao_acciones_host');

                if (ctxAb === 'consulta' || ctxAb === 'unificado') {
                    var btnAb = ev.target.closest('.aa-btn-abonar');
                    if (btnAb) {
                        var idAb = parseInt(btnAb.getAttribute('data-id-apartado') || '0', 10);
                        if (ctxAb === 'unificado' && host && typeof window.joyeriaApartadosOperacionesMostrarAbono === 'function') {
                            window.joyeriaApartadosOperacionesMostrarAbono(idAb);
                        } else {
                            var inpAb = document.getElementById('ac_ab_id');
                            if (inpAb && idAb > 0) {
                                inpAb.value = String(idAb);
                                inpAb.scrollIntoView({ behavior: 'smooth', block: 'center' });
                                var montoInp = document.getElementById('ac_ab_monto');
                                if (montoInp) {
                                    setTimeout(function () { montoInp.focus(); }, 350);
                                }
                            }
                        }
                        return;
                    }
                }

                if (ctxAb === 'unificado') {
                    var btnQ = ev.target.closest('.aa-btn-quitar-pieza');
                    if (btnQ) {
                        var idQ = parseInt(btnQ.getAttribute('data-id-apartado') || '0', 10);
                        if (typeof window.joyeriaApartadosOperacionesMostrarQuitar === 'function') {
                            window.joyeriaApartadosOperacionesMostrarQuitar(idQ);
                        }
                        return;
                    }

                    var btnA = ev.target.closest('.aa-btn-agregar-pieza');
                    if (btnA) {
                        var idA = parseInt(btnA.getAttribute('data-id-apartado') || '0', 10);
                        if (typeof window.joyeriaApartadosOperacionesMostrarAgregar === 'function') {
                            window.joyeriaApartadosOperacionesMostrarAgregar(idA);
                        }
                        return;
                    }
                }

                var btnAbonos = ev.target.closest('.aa-btn-ver-abonos');
                if (btnAbonos) {
                    var idAbonos = parseInt(btnAbonos.getAttribute('data-id-apartado') || '0', 10);
                    if (idAbonos > 0) {
                        abrirModalAbonos(idAbonos);
                    }
                    return;
                }

                var btn = ev.target.closest('.aa-btn-piezas');
                if (!btn) return;
                var idP = parseInt(btn.getAttribute('data-id-apartado') || '0', 10);
                if (idP > 0) {
                    abrirModalPiezas(idP);
                }
            });
        }

        var dlgAbonos = document.getElementById('aa_modal_abonos');
        var btnCerrarAbonos = document.getElementById('aa_modal_abonos_cerrar');
        if (dlgAbonos && btnCerrarAbonos && typeof dlgAbonos.close === 'function') {
            btnCerrarAbonos.addEventListener('click', function () {
                dlgAbonos.close();
            });
        }

        var dlgPiezas = document.getElementById('aa_modal_piezas');
        var btnCerrarPiezas = document.getElementById('aa_modal_piezas_cerrar');
        if (dlgPiezas && btnCerrarPiezas && typeof dlgPiezas.close === 'function') {
            btnCerrarPiezas.addEventListener('click', function () {
                dlgPiezas.close();
            });
        }

        var u = parseInt(G.idApartadoUrl, 10) || 0;
        if (u > 0) {
            if (G.context === 'consulta') {
                var inpPref = document.getElementById('ac_ab_id');
                if (inpPref) {
                    inpPref.value = String(u);
                    inpPref.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            } else if (G.context === 'unificado') {
                var dest = String(G.prefillDestino || 'abono').toLowerCase();
                if (dest === 'quitar' && typeof window.joyeriaApartadosOperacionesMostrarQuitar === 'function') {
                    window.joyeriaApartadosOperacionesMostrarQuitar(u);
                } else if (dest === 'agregar' && typeof window.joyeriaApartadosOperacionesMostrarAgregar === 'function') {
                    window.joyeriaApartadosOperacionesMostrarAgregar(u);
                } else if (typeof window.joyeriaApartadosOperacionesMostrarAbono === 'function') {
                    window.joyeriaApartadosOperacionesMostrarAbono(u);
                }
            }
        }
    });
})();
