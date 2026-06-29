/**
 * Convierte selects de FK a autocompletable con dropdown custom.
 * Mantiene el select original para conservar contrato POST y backend.
 */
(function (global) {
    'use strict';

    function ensureStyles() {
        if (document.getElementById('joyeria-fk-autocomplete-styles')) {
            return;
        }
        var style = document.createElement('style');
        style.id = 'joyeria-fk-autocomplete-styles';
        style.textContent = [
            '.joyeria-fk-autocomplete-wrap{position:relative;}',
            '.joyeria-fk-autocomplete-dd{position:absolute;left:0;right:0;z-index:60;max-height:220px;overflow-y:auto;margin:2px 0 0;padding:4px 0;list-style:none;background:#fff;border:1px solid #ced4da;border-radius:4px;box-shadow:0 4px 12px rgba(0,0,0,.1)}',
            '.joyeria-fk-autocomplete-item{padding:6px 10px;cursor:pointer;}',
            '.joyeria-fk-autocomplete-item:hover,.joyeria-fk-autocomplete-item.active{background:rgba(11,94,215,.08)}',
            '.joyeria-fk-autocomplete-hidden-select{display:none !important;}'
        ].join('');
        document.head.appendChild(style);
    }

    function normalizeText(value) {
        return String(value || '').toLowerCase().trim();
    }

    function optionSearchHaystack(opt) {
        var fromAttr = opt.getAttribute('data-search');
        if (fromAttr !== null && String(fromAttr).trim() !== '') {
            return String(fromAttr).trim();
        }
        return String(opt.textContent || '').trim();
    }

    function toOptions(selectEl) {
        return Array.prototype.slice.call(selectEl.options || []).map(function (opt) {
            var label = String(opt.textContent || '').trim();
            return {
                value: String(opt.value || ''),
                label: label,
                search: optionSearchHaystack(opt)
            };
        });
    }

    function optionHaystack(opt) {
        return normalizeText(opt.search || opt.label);
    }

    function initSelectAutocomplete(config) {
        var selectEl = document.getElementById(config.selectId);
        if (!selectEl || selectEl.dataset.joyeriaAutocompleteInit === '1') {
            return null;
        }
        ensureStyles();

        var allowEmpty = config.allowEmpty !== false;
        var invalidMessage = config.invalidMessage || 'Selecciona una opcion valida de la lista.';
        var placeholder = config.placeholder || 'Escribe para buscar...';
        var options = toOptions(selectEl).filter(function (opt) {
            return allowEmpty ? true : opt.value !== '';
        });

        var wrapper = document.createElement('div');
        wrapper.className = 'joyeria-fk-autocomplete-wrap';
        selectEl.parentNode.insertBefore(wrapper, selectEl);
        wrapper.appendChild(selectEl);

        var input = document.createElement('input');
        input.type = 'text';
        input.className = selectEl.className || 'form-input';
        input.id = selectEl.id + '_display';
        input.placeholder = placeholder;
        input.autocomplete = 'off';
        wrapper.appendChild(input);

        var dropdown = document.createElement('ul');
        dropdown.className = 'joyeria-fk-autocomplete-dd';
        dropdown.hidden = true;
        wrapper.appendChild(dropdown);

        var currentIndex = -1;
        var visibleOptions = [];

        function selectedOptionFromSelect() {
            var selected = selectEl.options[selectEl.selectedIndex];
            return selected ? {
                value: String(selected.value || ''),
                label: String(selected.textContent || '').trim()
            } : null;
        }

        function syncFromSelect() {
            var selected = selectedOptionFromSelect();
            input.value = (selected && selected.value !== '') ? selected.label : '';
            input.required = !!selectEl.required;
            input.disabled = !!selectEl.disabled;
        }

        function closeDropdown() {
            dropdown.hidden = true;
            dropdown.innerHTML = '';
            currentIndex = -1;
            visibleOptions = [];
        }

        function setValidity() {
            var previousValue = String(selectEl.value || '');
            var normalized = normalizeText(input.value);
            var exact = options.find(function (opt) {
                return optionHaystack(opt) === normalized
                    || normalizeText(opt.label) === normalized;
            });
            if (exact && (allowEmpty || exact.value !== '')) {
                selectEl.value = exact.value;
                input.setCustomValidity('');
                if (String(selectEl.value || '') !== previousValue) {
                    selectEl.dispatchEvent(new Event('change', { bubbles: true }));
                }
                return true;
            }
            if (normalized === '' && allowEmpty) {
                selectEl.value = '';
                input.setCustomValidity('');
                if (String(selectEl.value || '') !== previousValue) {
                    selectEl.dispatchEvent(new Event('change', { bubbles: true }));
                }
                return true;
            }
            selectEl.value = '';
            if (String(selectEl.value || '') !== previousValue) {
                selectEl.dispatchEvent(new Event('change', { bubbles: true }));
            }
            if (normalized === '') {
                input.setCustomValidity(selectEl.required ? 'Este campo es obligatorio.' : '');
            } else {
                input.setCustomValidity(invalidMessage);
            }
            return false;
        }

        function pickOption(opt) {
            selectEl.value = opt.value;
            input.value = opt.label;
            input.setCustomValidity('');
            selectEl.dispatchEvent(new Event('change', { bubbles: true }));
            closeDropdown();
        }

        function render(filtered) {
            visibleOptions = filtered;
            currentIndex = -1;
            dropdown.innerHTML = '';
            if (!filtered.length) {
                closeDropdown();
                return;
            }
            filtered.forEach(function (opt, idx) {
                var li = document.createElement('li');
                li.className = 'joyeria-fk-autocomplete-item';
                li.textContent = opt.label;
                li.dataset.index = String(idx);
                li.addEventListener('mousedown', function (event) {
                    event.preventDefault();
                    pickOption(opt);
                });
                dropdown.appendChild(li);
            });
            dropdown.hidden = false;
        }

        function updateHighlight() {
            var nodes = dropdown.querySelectorAll('.joyeria-fk-autocomplete-item');
            nodes.forEach(function (node, idx) {
                node.classList.toggle('active', idx === currentIndex);
            });
        }

        function filterAndRender() {
            var q = normalizeText(input.value);
            var filtered = options.filter(function (opt) {
                if (opt.value === '') {
                    return false;
                }
                return q === '' || optionHaystack(opt).indexOf(q) !== -1;
            });
            render(filtered);
            setValidity();
        }

        input.addEventListener('focus', filterAndRender);
        input.addEventListener('input', filterAndRender);
        input.addEventListener('blur', function () {
            setTimeout(function () {
                closeDropdown();
                setValidity();
            }, 180);
        });
        input.addEventListener('keydown', function (event) {
            if (dropdown.hidden) {
                return;
            }
            if (event.key === 'ArrowDown') {
                event.preventDefault();
                currentIndex = Math.min(currentIndex + 1, visibleOptions.length - 1);
                updateHighlight();
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                currentIndex = Math.max(currentIndex - 1, 0);
                updateHighlight();
            } else if (event.key === 'Enter' && currentIndex >= 0) {
                event.preventDefault();
                pickOption(visibleOptions[currentIndex]);
            } else if (event.key === 'Escape') {
                closeDropdown();
            }
        });

        if (selectEl.form) {
            selectEl.form.addEventListener('submit', function (event) {
                if (!setValidity()) {
                    event.preventDefault();
                    input.reportValidity();
                }
            });
        }

        var observer = new MutationObserver(function () {
            syncFromSelect();
        });
        observer.observe(selectEl, { attributes: true, attributeFilter: ['disabled', 'required'] });

        selectEl.classList.add('joyeria-fk-autocomplete-hidden-select');
        selectEl.dataset.joyeriaAutocompleteInit = '1';
        syncFromSelect();
        setValidity();

        return {
            refresh: function () {
                options = toOptions(selectEl).filter(function (opt) {
                    return allowEmpty ? true : opt.value !== '';
                });
                syncFromSelect();
                setValidity();
            },
            destroy: function () {
                observer.disconnect();
                wrapper.parentNode.insertBefore(selectEl, wrapper);
                wrapper.remove();
                selectEl.classList.remove('joyeria-fk-autocomplete-hidden-select');
                delete selectEl.dataset.joyeriaAutocompleteInit;
            }
        };
    }

    global.JoyeriaFkAutocomplete = {
        initSelectAutocomplete: initSelectAutocomplete
    };
}(typeof window !== 'undefined' ? window : this));
