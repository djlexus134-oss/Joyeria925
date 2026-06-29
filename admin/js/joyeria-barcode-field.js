/**
 * Enlaza un input de codigo (barras/auxiliar) con boton de camara (JoyeriaBarcodeCamera).
 * Uso: JoyeriaBarcodeField.bind('input_id', 'btn_id', function (codigo) { ... });
 */
(function (global) {
    'use strict';

    function resolveEl(ref) {
        if (!ref) {
            return null;
        }
        if (typeof ref === 'string') {
            return document.getElementById(ref);
        }
        return ref;
    }

    function openForInput(input, onAfter) {
        if (!input) {
            return;
        }
        if (!global.JoyeriaBarcodeCamera || typeof global.JoyeriaBarcodeCamera.openModal !== 'function') {
            window.alert('No se cargo el lector de codigo de barras.');
            return;
        }
        global.JoyeriaBarcodeCamera.openModal({
            onCode: function (text) {
                var codigo = String(text || '').trim();
                input.value = codigo;
                try {
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                } catch (e) { /* noop */ }
                try {
                    input.focus();
                } catch (e2) { /* noop */ }
                if (typeof onAfter === 'function') {
                    onAfter(codigo, input);
                }
            },
            onError: function (m) {
                window.alert(m);
            }
        });
    }

    function bind(inputOrId, buttonOrId, onAfter) {
        var input = resolveEl(inputOrId);
        var btn = resolveEl(buttonOrId);
        if (!input || !btn) {
            return false;
        }
        btn.addEventListener('click', function () {
            openForInput(input, onAfter);
        });
        return true;
    }

    global.JoyeriaBarcodeField = {
        bind: bind,
        open: openForInput
    };
}(typeof window !== 'undefined' ? window : this));
