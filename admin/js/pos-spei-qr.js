/**
 * QR informativo de transferencia SPEI en punto de venta.
 */
(function (global) {
    'use strict';

    var config = null;
    var referenciaActual = '';
    var textoActual = '';
    var urlQrActual = '';
    var btnQr = null;
    var modal = null;
    var canvas = null;
    var qrWrap = null;
    var resumenEl = null;
    var btnCopiarClabe = null;
    var btnCopiarTodo = null;
    var btnCerrar = null;
    var obtenerTotalFn = null;
    var obtenerMontoTransferenciaFn = null;

    function pad2(n) {
        return n < 10 ? '0' + n : String(n);
    }

    function construirReferencia() {
        var prefijo = String((config && config.referencia_prefijo) || 'VENTA').replace(/[^A-Za-z0-9_-]/g, '');
        if (!prefijo) {
            prefijo = 'VENTA';
        }
        var now = new Date();
        var stamp = now.getFullYear()
            + pad2(now.getMonth() + 1)
            + pad2(now.getDate())
            + '-'
            + pad2(now.getHours())
            + pad2(now.getMinutes())
            + pad2(now.getSeconds());
        return prefijo + '-' + stamp;
    }

    function formatearMonto(monto) {
        var n = isFinite(monto) ? Math.max(0, monto) : 0;
        return '$' + n.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',') + ' MXN';
    }

    function construirTexto(monto, referencia) {
        var lineas = ['Transferencia SPEI'];
        if (config.beneficiario) {
            lineas.push('Beneficiario: ' + config.beneficiario);
        }
        if (config.banco) {
            lineas.push('Banco: ' + config.banco);
        }
        if (config.clabe) {
            lineas.push('CLABE: ' + config.clabe);
        }
        lineas.push('Monto: ' + formatearMonto(monto));
        lineas.push('Concepto: ' + referencia);
        if (config.instrucciones) {
            lineas.push('Instrucciones: ' + config.instrucciones);
        }
        return lineas.join('\n');
    }

    function construirUrlQr(monto, referencia) {
        var base = String((config && config.url_base) || '').replace(/\/+$/, '');
        if (!base) {
            return '';
        }
        var params = new URLSearchParams();
        params.set('m', Math.max(0, monto).toFixed(2));
        params.set('r', referencia);
        return base + '/spei_deposito.php?' + params.toString();
    }

    function construirHtmlResumen(monto, referencia) {
        var parts = [];
        if (config.beneficiario) {
            parts.push('<p><strong>Beneficiario:</strong> ' + escHtml(config.beneficiario) + '</p>');
        }
        if (config.banco) {
            parts.push('<p><strong>Banco:</strong> ' + escHtml(config.banco) + '</p>');
        }
        if (config.clabe) {
            parts.push('<p><strong>CLABE:</strong> <span class="pos-spei-clabe">' + escHtml(config.clabe) + '</span></p>');
        }
        parts.push('<p><strong>Monto:</strong> ' + escHtml(formatearMonto(monto)) + '</p>');
        parts.push('<p><strong>Concepto:</strong> ' + escHtml(referencia) + '</p>');
        if (config.instrucciones) {
            parts.push('<p class="pos-spei-instrucciones">' + escHtml(config.instrucciones) + '</p>');
        }
        return parts.join('');
    }

    function escHtml(str) {
        return String(str || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function resolverMontoDeposito() {
        if (typeof obtenerMontoTransferenciaFn === 'function') {
            var mTransfer = obtenerMontoTransferenciaFn();
            if (isFinite(mTransfer) && mTransfer > 0.02) {
                return mTransfer;
            }
        }
        if (typeof obtenerTotalFn === 'function') {
            return obtenerTotalFn();
        }
        return 0;
    }

    function actualizarEstadoBoton() {
        if (!btnQr || !config || !config.habilitado) {
            return;
        }
        var monto = resolverMontoDeposito();
        var activo = monto > 0.02;
        btnQr.disabled = !activo;
        btnQr.title = activo ? 'Mostrar datos bancarios y QR para transferencia' : 'Sin monto a depositar';
    }

    function copiarTexto(texto, btn) {
        if (!texto) {
            return;
        }
        var done = function () {
            if (!btn) {
                return;
            }
            var prev = btn.textContent;
            btn.textContent = 'Copiado';
            btn.classList.add('is-copied');
            setTimeout(function () {
                btn.textContent = prev;
                btn.classList.remove('is-copied');
            }, 1600);
        };
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(texto).then(done).catch(function () {
                fallbackCopy(texto);
                done();
            });
            return;
        }
        fallbackCopy(texto);
        done();
    }

    function fallbackCopy(texto) {
        var ta = document.createElement('textarea');
        ta.value = texto;
        ta.setAttribute('readonly', '');
        ta.style.position = 'fixed';
        ta.style.left = '-9999px';
        document.body.appendChild(ta);
        ta.select();
        try {
            document.execCommand('copy');
        } catch (e) { /* ignore */ }
        document.body.removeChild(ta);
    }

    function limpiarErrorQr() {
        if (!qrWrap) {
            return;
        }
        var err = qrWrap.querySelector('.pos-spei-qr-error');
        if (err) {
            err.remove();
        }
    }

    function mostrarErrorQr(msg) {
        limpiarErrorQr();
        if (!qrWrap) {
            return;
        }
        var el = document.createElement('p');
        el.className = 'pos-spei-qr-error alert-message error';
        el.style.margin = '0.5rem 0 0';
        el.textContent = msg;
        qrWrap.appendChild(el);
    }

    function generarQr(texto) {
        limpiarErrorQr();
        if (!canvas) {
            mostrarErrorQr('No se encontró el lienzo del código QR.');
            return Promise.reject(new Error('Canvas missing'));
        }
        if (typeof global.QRCode === 'undefined' || typeof global.QRCode.toCanvas !== 'function') {
            mostrarErrorQr('No se cargó la librería QR. Recarga la página (F5).');
            return Promise.reject(new Error('QRCode missing'));
        }
        return new Promise(function (resolve, reject) {
            global.QRCode.toCanvas(canvas, texto, {
                width: 220,
                margin: 1,
                errorCorrectionLevel: 'M'
            }, function (err) {
                if (err) {
                    mostrarErrorQr('No se pudo generar el código QR.');
                    reject(err);
                    return;
                }
                resolve();
            });
        });
    }

    function abrirModal() {
        if (!modal || !config || !config.habilitado) {
            return;
        }
        var monto = resolverMontoDeposito();
        if (monto <= 0.02) {
            return;
        }
        referenciaActual = construirReferencia();
        textoActual = construirTexto(monto, referenciaActual);
        urlQrActual = construirUrlQr(monto, referenciaActual);
        if (resumenEl) {
            resumenEl.innerHTML = construirHtmlResumen(monto, referenciaActual);
        }
        if (!urlQrActual) {
            mostrarErrorQr('Falta la URL pública del sitio (JOYERIA_APP_URL en config.php).');
            if (typeof modal.showModal === 'function') {
                modal.showModal();
            }
            return;
        }
        generarQr(urlQrActual)
            .finally(function () {
                if (typeof modal.showModal === 'function') {
                    modal.showModal();
                }
            });
    }

    function cerrarModal() {
        if (modal && typeof modal.close === 'function') {
            modal.close();
        }
    }

    function init(opts) {
        opts = opts || {};
        config = opts.config || {};
        obtenerTotalFn = opts.obtenerTotal || null;
        obtenerMontoTransferenciaFn = opts.obtenerMontoTransferencia || null;

        btnQr = document.getElementById('btn_mostrar_qr_spei');
        modal = document.getElementById('modal-spei-deposito');
        canvas = document.getElementById('spei-qr-canvas');
        qrWrap = document.getElementById('spei-qr-canvas-wrap');
        resumenEl = document.getElementById('spei-datos-resumen');
        btnCopiarClabe = document.getElementById('btn-spei-copiar-clabe');
        btnCopiarTodo = document.getElementById('btn-spei-copiar-todo');
        btnCerrar = document.getElementById('btn-spei-cerrar');

        if (!config.habilitado || !btnQr) {
            return;
        }

        btnQr.style.display = '';
        actualizarEstadoBoton();

        btnQr.addEventListener('click', abrirModal);

        if (btnCopiarClabe) {
            btnCopiarClabe.addEventListener('click', function () {
                copiarTexto(config.clabe || '', btnCopiarClabe);
            });
        }
        if (btnCopiarTodo) {
            btnCopiarTodo.addEventListener('click', function () {
                copiarTexto(textoActual, btnCopiarTodo);
            });
        }
        if (btnCerrar) {
            btnCerrar.addEventListener('click', cerrarModal);
        }
        if (modal) {
            modal.addEventListener('cancel', function (ev) {
                ev.preventDefault();
                cerrarModal();
            });
            modal.addEventListener('click', function (ev) {
                if (ev.target === modal) {
                    cerrarModal();
                }
            });
        }
    }

    global.PosSpeiQr = {
        init: init,
        actualizarEstadoBoton: actualizarEstadoBoton
    };
}(window));
