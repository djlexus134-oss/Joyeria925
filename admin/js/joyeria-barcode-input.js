/**
 * Campos de codigo barras/auxiliar: teclado con "/" en telefonos.
 * Marca inputs con class="joyeria-barcode-input" o data-joyeria-barcode="1".
 */
(function (global) {
    'use strict';

    var ENHANCED = 'data-joyeria-barcode-enhanced';

    function isCoarsePointer() {
        try {
            return global.matchMedia('(hover: none) and (pointer: coarse)').matches;
        } catch (e) {
            return 'ontouchstart' in global;
        }
    }

    function normalizeScanCode(raw) {
        var value = String(raw || '').trim();
        if (value === '') {
            return '';
        }
        value = value.replace(/[\x00-\x1F\x7F]+/g, '');
        // Codigo auxiliar ARTPIE/CODPIE: pistola CODE128 suele emitir guion en lugar de diagonal
        if (/^\d+-\d+$/.test(value)) {
            return value.replace('-', '/');
        }
        return value;
    }

    function insertAtCursor(el, text) {
        var val = String(el.value || '');
        var start = typeof el.selectionStart === 'number' ? el.selectionStart : val.length;
        var end = typeof el.selectionEnd === 'number' ? el.selectionEnd : start;
        el.value = val.slice(0, start) + text + val.slice(end);
        var pos = start + text.length;
        try {
            el.setSelectionRange(pos, pos);
        } catch (e2) { /* noop */ }
        try {
            el.dispatchEvent(new Event('input', { bubbles: true }));
            el.dispatchEvent(new Event('change', { bubbles: true }));
        } catch (e3) { /* noop */ }
    }

    function buildMobileKeysBar(input) {
        var bar = document.createElement('div');
        bar.className = 'joyeria-barcode-mobile-keys';
        bar.setAttribute('role', 'toolbar');
        bar.setAttribute('aria-label', 'Teclas para codigo auxiliar');

        var slashBtn = document.createElement('button');
        slashBtn.type = 'button';
        slashBtn.className = 'joyeria-barcode-key-btn';
        slashBtn.textContent = '/';
        slashBtn.setAttribute('aria-label', 'Insertar diagonal');
        slashBtn.addEventListener('mousedown', function (ev) {
            ev.preventDefault();
        });
        slashBtn.addEventListener('click', function () {
            insertAtCursor(input, '/');
            input.focus();
        });

        var hint = document.createElement('span');
        hint.className = 'joyeria-barcode-mobile-hint';
        hint.textContent = 'Auxiliar: ARTPIE/CODPIE (ej. 28488/97)';

        bar.appendChild(slashBtn);
        bar.appendChild(hint);

        function setVisible(show) {
            if (!isCoarsePointer()) {
                bar.classList.remove('is-visible');
                return;
            }
            bar.classList.toggle('is-visible', !!show);
        }

        input.addEventListener('focus', function () {
            setVisible(true);
        });
        input.addEventListener('blur', function () {
            global.setTimeout(function () {
                if (document.activeElement === slashBtn) {
                    return;
                }
                setVisible(false);
            }, 150);
        });

        return bar;
    }

    function enhance(input) {
        if (!input || (input.nodeName !== 'INPUT' && input.nodeName !== 'TEXTAREA')) {
            return false;
        }
        if (input.getAttribute(ENHANCED) === '1') {
            return true;
        }
        input.setAttribute(ENHANCED, '1');
        input.classList.add('joyeria-barcode-input');
        input.setAttribute('inputmode', 'text');
        input.setAttribute('autocapitalize', 'off');
        input.setAttribute('autocorrect', 'off');
        input.setAttribute('spellcheck', 'false');
        if (!input.getAttribute('autocomplete')) {
            input.setAttribute('autocomplete', 'off');
        }

        input.addEventListener('keydown', function (ev) {
            if (ev.key === 'Enter') {
                var normalized = normalizeScanCode(input.value);
                if (normalized !== input.value) {
                    input.value = normalized;
                }
            }
        });

        var flexRow = input.parentElement;
        var bar = buildMobileKeysBar(input);

        if (flexRow && flexRow.querySelector && !flexRow.parentElement.querySelector('.joyeria-barcode-mobile-keys')) {
            flexRow.insertAdjacentElement('afterend', bar);
        } else if (!input.nextElementSibling || !input.nextElementSibling.classList.contains('joyeria-barcode-mobile-keys')) {
            input.insertAdjacentElement('afterend', bar);
        }

        return true;
    }

    function enhanceAll(root) {
        var scope = root && root.querySelectorAll ? root : document;
        var nodes = scope.querySelectorAll(
            '.joyeria-barcode-input, [data-joyeria-barcode="1"], input.recuento-scan-input'
        );
        for (var i = 0; i < nodes.length; i++) {
            enhance(nodes[i]);
        }
    }

    function init() {
        enhanceAll(document);
    }

    global.JoyeriaBarcodeInput = {
        enhance: enhance,
        enhanceAll: enhanceAll,
        normalizeScanCode: normalizeScanCode,
        insertSlash: function (inputOrId) {
            var el = typeof inputOrId === 'string' ? document.getElementById(inputOrId) : inputOrId;
            if (el) {
                insertAtCursor(el, '/');
            }
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}(typeof window !== 'undefined' ? window : this));
