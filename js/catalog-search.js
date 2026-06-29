/**
 * Buscador en vivo del catálogo público.
 * Filtra .producto-card por descripción, familia, subfamilia, metal y rango de precio.
 * No hace fetch ni recarga: opera sobre los data-* que el SSR escribió en cada tarjeta.
 */
(function () {
    var btnAbrir = document.getElementById('btnAbrirBuscador');
    var overlay = document.getElementById('buscadorPiezas');
    if (!btnAbrir || !overlay) {
        return;
    }

    var inputTexto = overlay.querySelector('[data-filtro="texto"]');
    var selFamilia = overlay.querySelector('[data-filtro="familia"]');
    var selSubfamilia = overlay.querySelector('[data-filtro="subfamilia"]');
    var selMetal = overlay.querySelector('[data-filtro="metal"]');
    var inMin = overlay.querySelector('[data-filtro="precio-min"]');
    var inMax = overlay.querySelector('[data-filtro="precio-max"]');
    var resumen = overlay.querySelector('[data-resumen]');
    var botonesCerrar = overlay.querySelectorAll('[data-accion="cerrar"]');
    var btnLimpiar = overlay.querySelector('[data-accion="limpiar"]');

    var cards = Array.prototype.slice.call(document.querySelectorAll('.producto-card[data-precio]'));
    var grupos = Array.prototype.slice.call(document.querySelectorAll('.catalog-group'));
    var promos = Array.prototype.slice.call(document.querySelectorAll('.catalog-promo-stripe'));
    var emptyMsg = document.querySelector('[data-empty-busqueda]');

    var subOptionsAll = selSubfamilia ? Array.prototype.slice.call(selSubfamilia.querySelectorAll('option[data-id-familia]')) : [];

    function normalizar(str) {
        if (typeof str !== 'string') {
            return '';
        }
        var lower = str.toLowerCase();
        if (typeof lower.normalize === 'function') {
            return lower.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        }
        return lower;
    }

    function dispararChange(el) {
        try {
            el.dispatchEvent(new Event('change', { bubbles: true }));
        } catch (e) {
            try {
                var evt = document.createEvent('Event');
                evt.initEvent('change', true, true);
                el.dispatchEvent(evt);
            } catch (e2) { /* noop */ }
        }
    }

    function crearComboDesdeSelect(selectEl, placeholder) {
        if (!selectEl || selectEl.getAttribute('data-combo-ready') === '1') {
            return null;
        }

        selectEl.setAttribute('data-combo-ready', '1');

        var wrap = document.createElement('div');
        wrap.className = 'buscador-combo';

        var input = document.createElement('input');
        input.type = 'text';
        input.className = selectEl.className;
        input.setAttribute('autocomplete', 'off');
        input.setAttribute('spellcheck', 'false');
        input.setAttribute('inputmode', 'search');
        if (placeholder) {
            input.setAttribute('placeholder', placeholder);
        }

        var list = document.createElement('div');
        list.className = 'buscador-combo-list is-hidden';
        list.setAttribute('role', 'listbox');

        var parent = selectEl.parentNode;
        parent.insertBefore(wrap, selectEl);
        wrap.appendChild(input);
        wrap.appendChild(list);
        wrap.appendChild(selectEl);

        selectEl.classList.add('is-hidden');
        selectEl.setAttribute('aria-hidden', 'true');
        selectEl.setAttribute('tabindex', '-1');

        function opcionesVisibles() {
            return Array.prototype.slice.call(selectEl.querySelectorAll('option')).filter(function (o) {
                if (!o || typeof o.value !== 'string') return false;
                if (o.value === '') return true; // "Todas"
                if (o.disabled) return false;
                if (o.hidden) return false;
                return true;
            });
        }

        function textoOpcionPorValor(val) {
            var opt = selectEl.querySelector('option[value="' + String(val).replace(/"/g, '\\"') + '"]');
            return opt ? (opt.textContent || '') : '';
        }

        function setInputFromSelect() {
            var v = selectEl.value || '';
            if (!v) {
                input.value = '';
                return;
            }
            input.value = (textoOpcionPorValor(v) || '').trim();
        }

        function cerrarLista() {
            list.classList.add('is-hidden');
        }

        function abrirLista() {
            list.classList.remove('is-hidden');
        }

        function renderLista() {
            list.innerHTML = '';
            var q = normalizar(input.value || '').trim();
            var opts = opcionesVisibles();

            var matches = opts.filter(function (o) {
                if (!q) return true;
                return normalizar((o.textContent || '')).indexOf(q) !== -1;
            });

            if (matches.length === 0) {
                var empty = document.createElement('div');
                empty.className = 'buscador-combo-empty';
                empty.textContent = 'Sin coincidencias';
                list.appendChild(empty);
                return;
            }

            matches.slice(0, 80).forEach(function (o) {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'buscador-combo-item';
                btn.textContent = (o.textContent || '').trim();
                btn.setAttribute('role', 'option');
                btn.setAttribute('data-value', o.value);
                if (o.value === (selectEl.value || '')) {
                    btn.setAttribute('aria-selected', 'true');
                }
                btn.addEventListener('mousedown', function (e) {
                    e.preventDefault(); // no perder foco antes de setear
                });
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    selectEl.value = o.value;
                    setInputFromSelect();
                    cerrarLista();
                    dispararChange(selectEl);
                });
                list.appendChild(btn);
            });
        }

        input.addEventListener('focus', function () {
            renderLista();
            abrirLista();
        });
        input.addEventListener('input', function () {
            renderLista();
            abrirLista();
            if ((input.value || '').trim() === '') {
                if (selectEl.value !== '') {
                    selectEl.value = '';
                    dispararChange(selectEl);
                }
            }
        });
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                cerrarLista();
                input.blur();
                return;
            }
        });

        document.addEventListener('click', function (e) {
            if (!wrap.contains(e.target)) {
                cerrarLista();
            }
        });

        selectEl.addEventListener('change', function () {
            setInputFromSelect();
            renderLista();
        });

        setInputFromSelect();

        selectEl._comboRefresh = function () {
            renderLista();
        };
        selectEl._comboSync = function () {
            setInputFromSelect();
            renderLista();
        };

        return { wrap: wrap, input: input, list: list };
    }

    function refrescarSubfamilias() {
        if (!selSubfamilia || subOptionsAll.length === 0) {
            return;
        }
        var fam = selFamilia ? selFamilia.value : '';
        var seleccionVigente = selSubfamilia.value;
        var seleccionSigueValida = false;

        subOptionsAll.forEach(function (opt) {
            var idFamOpt = opt.getAttribute('data-id-familia') || '';
            var visible = !fam || idFamOpt === fam;
            opt.hidden = !visible;
            opt.disabled = !visible;
            if (visible && opt.value === seleccionVigente) {
                seleccionSigueValida = true;
            }
        });

        if (!seleccionSigueValida) {
            selSubfamilia.value = '';
        }

        if (selSubfamilia && typeof selSubfamilia._comboSync === 'function') {
            selSubfamilia._comboSync();
        } else if (selSubfamilia && typeof selSubfamilia._comboRefresh === 'function') {
            selSubfamilia._comboRefresh();
        }
    }

    function aplicarFiltros() {
        var q = normalizar(inputTexto ? inputTexto.value : '').trim();
        var fam = selFamilia ? selFamilia.value : '';
        var sub = selSubfamilia ? selSubfamilia.value : '';
        var met = selMetal ? selMetal.value : '';

        var minVal = inMin ? parseFloat(inMin.value) : NaN;
        var maxVal = inMax ? parseFloat(inMax.value) : NaN;
        if (isNaN(minVal)) { minVal = -Infinity; }
        if (isNaN(maxVal)) { maxVal = Infinity; }

        var hayFiltro = !!(q || fam || sub || met)
            || isFinite(minVal) || isFinite(maxVal);

        var visibles = 0;
        cards.forEach(function (c) {
            var ok = true;
            if (q) {
                var descNorm = normalizar(c.dataset.desc || '');
                if (descNorm.indexOf(q) === -1) {
                    ok = false;
                }
            }
            if (ok && fam && (c.dataset.idFamilia || '') !== fam) {
                ok = false;
            }
            if (ok && sub && (c.dataset.idSubfamilia || '') !== sub) {
                ok = false;
            }
            if (ok && met && (c.dataset.idMetal || '') !== met) {
                ok = false;
            }
            if (ok) {
                var p = parseFloat(c.dataset.precio);
                if (isNaN(p) || p < minVal || p > maxVal) {
                    ok = false;
                }
            }
            c.classList.toggle('is-hidden', !ok);
            if (ok && !c.hasAttribute('data-carousel-clone')) {
                visibles++;
            }
        });

        grupos.forEach(function (g) {
            var quedan = g.querySelectorAll(
                '.producto-card:not(.is-hidden):not([data-carousel-clone])'
            ).length;
            g.classList.toggle('is-hidden', quedan === 0);
        });

        promos.forEach(function (p) {
            p.classList.toggle('is-hidden', hayFiltro);
        });

        if (emptyMsg) {
            emptyMsg.classList.toggle('is-hidden', visibles !== 0);
        }

        if (resumen) {
            if (visibles === 0) {
                resumen.textContent = 'Sin coincidencias';
            } else {
                resumen.textContent = visibles + (visibles === 1 ? ' pieza' : ' piezas');
            }
        }
    }

    function debounce(fn, wait) {
        var t = null;
        return function () {
            var ctx = this;
            var args = arguments;
            if (t) {
                clearTimeout(t);
            }
            t = setTimeout(function () {
                fn.apply(ctx, args);
            }, wait);
        };
    }

    var aplicarDebounced = debounce(aplicarFiltros, 120);

    function abrir() {
        overlay.classList.add('is-open');
        overlay.setAttribute('aria-hidden', 'false');
        btnAbrir.setAttribute('aria-expanded', 'true');
        document.body.classList.add('buscador-abierto');
        if (inputTexto) {
            setTimeout(function () { inputTexto.focus(); }, 50);
        }
    }

    function cerrar() {
        overlay.classList.remove('is-open');
        overlay.setAttribute('aria-hidden', 'true');
        btnAbrir.setAttribute('aria-expanded', 'false');
        document.body.classList.remove('buscador-abierto');
        try { btnAbrir.focus(); } catch (e) { /* noop */ }
    }

    function limpiar() {
        if (inputTexto) inputTexto.value = '';
        if (selFamilia) { selFamilia.value = ''; dispararChange(selFamilia); }
        if (selSubfamilia) { selSubfamilia.value = ''; dispararChange(selSubfamilia); }
        if (selMetal) { selMetal.value = ''; dispararChange(selMetal); }
        if (inMin) inMin.value = '';
        if (inMax) inMax.value = '';
        refrescarSubfamilias();
        aplicarFiltros();
        if (inputTexto) {
            inputTexto.focus();
        }
    }

    btnAbrir.addEventListener('click', function (e) {
        e.preventDefault();
        abrir();
    });

    Array.prototype.forEach.call(botonesCerrar, function (b) {
        b.addEventListener('click', function (e) {
            e.preventDefault();
            cerrar();
        });
    });

    if (btnLimpiar) {
        btnLimpiar.addEventListener('click', function (e) {
            e.preventDefault();
            limpiar();
        });
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && overlay.classList.contains('is-open')) {
            cerrar();
        }
    });

    if (inputTexto) {
        inputTexto.addEventListener('input', aplicarDebounced);
    }

    crearComboDesdeSelect(selFamilia, 'Todas');
    crearComboDesdeSelect(selSubfamilia, 'Todas');
    crearComboDesdeSelect(selMetal, 'Todos');

    [selFamilia, selSubfamilia, selMetal].forEach(function (el) {
        if (!el) return;
        el.addEventListener('change', function () {
            if (el === selFamilia) {
                refrescarSubfamilias();
            }
            aplicarFiltros();
        });
    });
    [inMin, inMax].forEach(function (el) {
        if (!el) return;
        el.addEventListener('input', aplicarDebounced);
        el.addEventListener('change', aplicarFiltros);
    });

    refrescarSubfamilias();
    aplicarFiltros();
})();
