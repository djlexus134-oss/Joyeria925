(function (global) {
    'use strict';

    var STOCK_CODES = {
        inventario_no_disponible: true,
        insumo_sin_existencia: true
    };

    function escHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function isStockCode(codigo) {
        return !!(codigo && STOCK_CODES[codigo]);
    }

    function resolveContainer(options) {
        if (options && options.container) {
            return options.container;
        }
        return document.getElementById('pos-mensaje');
    }

    global.JoyeriaPosStockAlert = {
        isStockCode: isStockCode,

        isStockError: function (err) {
            if (!err) {
                return false;
            }
            return isStockCode(err.codigo_error || '');
        },

        show: function (mensaje, options) {
            options = options || {};
            var wrap = resolveContainer(options);
            var text = String(mensaje || 'Pieza no disponible.');
            if (!options.feedbackOnly && wrap) {
                wrap.innerHTML = '<div class="alert-message error is-stock-unavailable" role="alert">'
                    + '<i class="bi bi-exclamation-octagon-fill" aria-hidden="true"></i>'
                    + '<div class="alert-content"><p>' + escHtml(text) + '</p></div></div>';
                if (typeof wrap.scrollIntoView === 'function') {
                    wrap.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
            }
            if (global.JoyeriaPosScanFeedback && typeof global.JoyeriaPosScanFeedback.error === 'function') {
                global.JoyeriaPosScanFeedback.error();
            }
            var focusId = options.focusCodigoId || 'codigo_busqueda';
            if (options.focusCodigo !== false) {
                var input = document.getElementById(focusId);
                if (input && typeof input.focus === 'function') {
                    input.focus();
                }
            }
        },

        showIfStockError: function (err, options) {
            if (!this.isStockError(err)) {
                return false;
            }
            this.show(err.message || 'Pieza no disponible.', options);
            return true;
        },

        throwFromResponse: function (res, fallbackMsg) {
            if (res && res.ok) {
                return res;
            }
            var err = new Error((res && res.mensaje) ? res.mensaje : (fallbackMsg || 'Error'));
            err.codigo_error = (res && res.codigo_error) ? res.codigo_error : '';
            throw err;
        }
    };
})(window);
