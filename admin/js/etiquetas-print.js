(function (global) {
    'use strict';

    var API_BASE = 'api/impresion.php';
    var toastEl = null;
    var pollTimers = {};

    function ensureToast() {
        if (toastEl) {
            return toastEl;
        }
        toastEl = document.createElement('div');
        toastEl.id = 'etiquetas-print-toast';
        toastEl.setAttribute('role', 'status');
        toastEl.style.cssText = [
            'position:fixed',
            'left:50%',
            'bottom:16px',
            'transform:translateX(-50%)',
            'z-index:9999',
            'max-width:min(92vw,420px)',
            'padding:12px 16px',
            'border-radius:10px',
            'font-size:14px',
            'line-height:1.4',
            'box-shadow:0 8px 24px rgba(0,0,0,.18)',
            'display:none'
        ].join(';');
        document.body.appendChild(toastEl);
        return toastEl;
    }

    function showToast(message, kind) {
        var el = ensureToast();
        var bg = '#1f4b7a';
        if (kind === 'success') bg = '#1f7a4d';
        if (kind === 'error') bg = '#a33';
        if (kind === 'info') bg = '#6b4f1d';
        el.style.background = bg;
        el.style.color = '#fff';
        el.textContent = message;
        el.style.display = 'block';
    }

    function hideToastLater(ms) {
        setTimeout(function () {
            if (toastEl) {
                toastEl.style.display = 'none';
            }
        }, ms || 6000);
    }

    function postJson(url, body) {
        return fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify(body || {})
        }).then(function (res) {
            return res.json().then(function (data) {
                if (!res.ok || !data.success) {
                    var err = new Error((data && data.error) ? data.error : ('HTTP ' + res.status));
                    err.response = data;
                    throw err;
                }
                return data;
            });
        });
    }

    function getJson(url) {
        return fetch(url, {
            method: 'GET',
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin'
        }).then(function (res) {
            return res.json().then(function (data) {
                if (!res.ok || !data.success) {
                    var err = new Error((data && data.error) ? data.error : ('HTTP ' + res.status));
                    err.response = data;
                    throw err;
                }
                return data;
            });
        });
    }

    function pollEstadoCola(idCola) {
        if (!idCola || pollTimers[idCola]) {
            return;
        }
        var intentos = 0;
        var maxIntentos = 24;

        function tick() {
            intentos += 1;
            getJson(API_BASE + '?accion=estado&id_cola_impresion=' + encodeURIComponent(idCola))
                .then(function (res) {
                    var estado = res.data && res.data.estado ? res.data.estado : '';
                    var qty = res.data && res.data.cantidad_etiquetas ? res.data.cantidad_etiquetas : '';
                    if (estado === 'impreso') {
                        showToast('Etiquetas impresas correctamente (' + qty + ').', 'success');
                        hideToastLater(5000);
                        clearInterval(pollTimers[idCola]);
                        delete pollTimers[idCola];
                        return;
                    }
                    if (estado === 'error') {
                        var msg = res.data.mensaje_error || 'Error al imprimir etiquetas.';
                        showToast(msg, 'error');
                        hideToastLater(8000);
                        clearInterval(pollTimers[idCola]);
                        delete pollTimers[idCola];
                        return;
                    }
                    if (estado === 'pendiente' && intentos === 4) {
                        showToast('Sigue en cola. Inicia print-agent-etiquetas en la PC con la Argox.', 'info');
                        hideToastLater(8000);
                    }
                    if (intentos >= maxIntentos) {
                        clearInterval(pollTimers[idCola]);
                        delete pollTimers[idCola];
                    }
                })
                .catch(function () {
                    if (intentos >= maxIntentos) {
                        clearInterval(pollTimers[idCola]);
                        delete pollTimers[idCola];
                    }
                });
        }

        pollTimers[idCola] = setInterval(tick, 2500);
        tick();
    }

    function encolar(payload) {
        showToast('Encolando etiquetas...', 'info');
        return postJson(API_BASE + '?accion=encolar_etiquetas', payload).then(function (res) {
            var qty = res.cantidad || 0;
            var idCola = res.id_cola_impresion || 0;
            showToast(
                'Encolado: ' + qty + ' etiqueta(s). Cola #' + idCola + '. '
                + 'La PC con Argox debe tener el agente print-agent-etiquetas activo.',
                'info'
            );
            hideToastLater(9000);
            if (idCola) {
                pollEstadoCola(idCola);
            }
            return res;
        }).catch(function (err) {
            showToast(err.message || 'No se pudo encolar.', 'error');
            hideToastLater(8000);
            throw err;
        });
    }

    function encolarIds(ids) {
        return encolar({ ids: ids });
    }

    function encolarRango(idPieza, desde, hasta) {
        return encolar({ id_pieza: idPieza, desde: desde, hasta: hasta, solo_disponibles: false });
    }

    function encolarInsumo(idInsumo, copias) {
        var id = parseInt(idInsumo, 10);
        var n = parseInt(copias, 10);
        if (!id || id <= 0) {
            return Promise.reject(new Error('ID de insumo invalido.'));
        }
        if (!n || n < 1) {
            n = 1;
        }
        return encolar({ items: [{ id_insumo: id, copias: n }] });
    }

    function encolarInsumosItems(items) {
        if (!items || !items.length) {
            return Promise.reject(new Error('No hay insumos para encolar.'));
        }
        return encolar({ items: items });
    }

    function abrirPanelRangoStock(idPieza, tituloPieza) {
        var panel = document.getElementById('panel-etiquetas-rango-stock');
        if (!panel) {
            return;
        }
        var inputDesde = document.getElementById('etiquetas-rango-stock-desde');
        var inputHasta = document.getElementById('etiquetas-rango-stock-hasta');
        var hiddenPieza = document.getElementById('etiquetas-rango-stock-id-pieza');
        var titulo = document.getElementById('etiquetas-rango-stock-titulo');
        if (hiddenPieza) hiddenPieza.value = String(idPieza || '');
        if (titulo) titulo.textContent = tituloPieza ? tituloPieza : '—';
        if (inputDesde) inputDesde.value = '1';
        if (inputHasta) inputHasta.value = '1';
        panel.style.display = 'block';
    }

    function cerrarPanelRangoStock() {
        var panel = document.getElementById('panel-etiquetas-rango-stock');
        if (panel) panel.style.display = 'none';
    }

    function confirmarPanelRangoStock() {
        var hiddenPieza = document.getElementById('etiquetas-rango-stock-id-pieza');
        var inputDesde = document.getElementById('etiquetas-rango-stock-desde');
        var inputHasta = document.getElementById('etiquetas-rango-stock-hasta');
        var idPieza = hiddenPieza ? parseInt(hiddenPieza.value, 10) : 0;
        var desde = inputDesde ? parseInt(inputDesde.value, 10) : 0;
        var hasta = inputHasta ? parseInt(inputHasta.value, 10) : 0;
        if (!idPieza || !desde || !hasta) {
            alert('Indica un rango valido.');
            return;
        }
        encolarRango(idPieza, desde, hasta).then(function () {
            cerrarPanelRangoStock();
        });
    }

    function abrirModalRango(idPieza, tituloPieza) {
        if (document.getElementById('panel-etiquetas-rango-stock')) {
            abrirPanelRangoStock(idPieza, tituloPieza);
            return;
        }
        var overlay = document.getElementById('modal-etiquetas-rango');
        if (!overlay) {
            return;
        }
        var inputDesde = document.getElementById('etiquetas-rango-desde');
        var inputHasta = document.getElementById('etiquetas-rango-hasta');
        var hiddenPieza = document.getElementById('etiquetas-rango-id-pieza');
        var titulo = document.getElementById('etiquetas-rango-titulo');
        if (hiddenPieza) hiddenPieza.value = String(idPieza || '');
        if (titulo && tituloPieza) titulo.textContent = tituloPieza;
        if (inputDesde) inputDesde.value = '1';
        if (inputHasta) inputHasta.value = '1';
        overlay.style.display = 'flex';
    }

    function cerrarModalRango() {
        var overlay = document.getElementById('modal-etiquetas-rango');
        if (overlay) overlay.style.display = 'none';
    }

    function confirmarModalRango() {
        var hiddenPieza = document.getElementById('etiquetas-rango-id-pieza');
        var inputDesde = document.getElementById('etiquetas-rango-desde');
        var inputHasta = document.getElementById('etiquetas-rango-hasta');
        var idPieza = hiddenPieza ? parseInt(hiddenPieza.value, 10) : 0;
        var desde = inputDesde ? parseInt(inputDesde.value, 10) : 0;
        var hasta = inputHasta ? parseInt(inputHasta.value, 10) : 0;
        if (!idPieza || !desde || !hasta) {
            alert('Indica un rango valido.');
            return;
        }
        encolarRango(idPieza, desde, hasta).then(function () {
            cerrarModalRango();
        });
    }

    function bindUi() {
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-etiqueta-accion]');
            if (!btn) return;
            e.preventDefault();
            var accion = btn.getAttribute('data-etiqueta-accion');
            if (accion === 'insumo') {
                var idInsumo = parseInt(btn.getAttribute('data-id-insumo') || '0', 10);
                if (idInsumo > 0) {
                    encolarInsumo(idInsumo, 1);
                }
                return;
            }
            if (accion === 'una') {
                var idUna = parseInt(btn.getAttribute('data-id-stock') || '0', 10);
                if (idUna > 0) encolarIds([idUna]);
                return;
            }
            if (accion === 'rango') {
                var idPieza = parseInt(btn.getAttribute('data-id-pieza') || '0', 10);
                var titulo = btn.getAttribute('data-titulo-pieza') || '';
                if (idPieza > 0) abrirModalRango(idPieza, titulo);
                return;
            }
            if (accion === 'ids') {
                var raw = btn.getAttribute('data-ids-stock') || '';
                var ids = raw.split(',').map(function (x) { return parseInt(x, 10); }).filter(function (n) { return n > 0; });
                if (ids.length) encolarIds(ids);
            }
        });

        var btnConfirmar = document.getElementById('btn-etiquetas-rango-confirmar');
        var btnCerrar = document.getElementById('btn-etiquetas-rango-cerrar');
        var overlay = document.getElementById('modal-etiquetas-rango');
        if (btnConfirmar) btnConfirmar.addEventListener('click', confirmarModalRango);
        if (btnCerrar) btnCerrar.addEventListener('click', cerrarModalRango);
        if (overlay) {
            overlay.addEventListener('click', function (ev) {
                if (ev.target === overlay) cerrarModalRango();
            });
        }

        var btnStockConfirmar = document.getElementById('btn-etiquetas-rango-stock-confirmar');
        var btnStockCerrar = document.getElementById('btn-etiquetas-rango-stock-cerrar');
        if (btnStockConfirmar) btnStockConfirmar.addEventListener('click', confirmarPanelRangoStock);
        if (btnStockCerrar) btnStockCerrar.addEventListener('click', cerrarPanelRangoStock);

        var bannerBtn = document.getElementById('btn-encolar-stock-nuevo');
        if (bannerBtn) {
            bannerBtn.addEventListener('click', function () {
                var raw = bannerBtn.getAttribute('data-ids-stock') || '';
                var ids = raw.split(',').map(function (x) { return parseInt(x, 10); }).filter(function (n) { return n > 0; });
                if (ids.length) encolarIds(ids);
            });
        }
    }

    global.JoyeriaEtiquetasPrint = {
        encolar: encolar,
        encolarIds: encolarIds,
        encolarRango: encolarRango,
        encolarInsumo: encolarInsumo,
        encolarInsumosItems: encolarInsumosItems,
        pollEstadoCola: pollEstadoCola,
        abrirModalRango: abrirModalRango
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bindUi);
    } else {
        bindUi();
    }
})(window);
