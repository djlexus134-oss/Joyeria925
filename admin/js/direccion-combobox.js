/**
 * Combobox de colonia/calle con busqueda por prefijo y alta mediante create_colonia / create_calle.
 * Sin dependencias externas.
 */
(function (global) {
    'use strict';

    function debounce(fn, ms) {
        var t;
        return function () {
            var ctx = this;
            var args = arguments;
            clearTimeout(t);
            t = setTimeout(function () {
                fn.apply(ctx, args);
            }, ms);
        };
    }

    function escHtml(s) {
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    function normalizeText(s) {
        return String(s || '').trim().toLowerCase();
    }

    function normalizeApiPrefix(prefix) {
        if (!prefix || typeof prefix !== 'string') {
            return 'api/';
        }
        return prefix.replace(/\/?$/, '/') ;
    }

    /**
     * @param {object} opts
     * @param {string} opts.apiPrefix - ej. 'api/' o './api/'
     * @param {HTMLInputElement} opts.hiddenInput
     * @param {HTMLInputElement} opts.displayInput
     * @param {HTMLSelectElement} opts.cpSelect
     * @param {function(Array):void} [opts.mergeColoniasCache] - recibe filas para cache global coloniasPorCP
     * @param {function(object):void} opts.onSelect - fila colonia completa (join estados etc.)
     * @param {function(string, boolean):void} [opts.setFeedback]
     * @param {function():number|null} opts.getReferenciaLocalidad - id_localidad para crear colonia nueva
     */
    function initColonia(opts) {
        var api = normalizeApiPrefix(opts.apiPrefix);
        var hidden = opts.hiddenInput;
        var display = opts.displayInput;
        var cpSel = opts.cpSelect;
        var locSel = opts.localidadSelect || null;
        var mergeCache = opts.mergeColoniasCache;
        var onSelect = opts.onSelect;
        var setFeedback = opts.setFeedback || function () {};
        var getRefLoc = opts.getReferenciaLocalidad;

        var dd = document.createElement('ul');
        dd.className = 'joyeria-dir-combobox-dd';
        dd.setAttribute('hidden', '');
        dd.setAttribute('role', 'listbox');
        var wrap = display.parentNode;
        if (wrap && wrap.classList.contains('joyeria-dir-combobox-wrap')) {
            wrap.style.position = 'relative';
            wrap.appendChild(dd);
        }

        var activeIdx = -1;
        var lastResults = [];

        function closeDd() {
            dd.hidden = true;
            dd.innerHTML = '';
            activeIdx = -1;
            lastResults = [];
        }

        function pickRow(row) {
            if (row && row.__create === true && typeof row.__createAction === 'function') {
                row.__createAction();
                return;
            }
            hidden.value = String(row.id_colonia);
            display.value = row.nom_colonia || '';
            if (mergeCache) {
                mergeCache([row]);
            }
            closeDd();
            if (typeof onSelect === 'function') {
                onSelect(row);
            }
        }

        function renderList(rows) {
            lastResults = rows;
            activeIdx = -1;
            dd.innerHTML = '';
            if (!rows.length) {
                dd.hidden = true;
                return;
            }
            rows.forEach(function (row, i) {
                var li = document.createElement('li');
                li.className = 'joyeria-dir-combobox-item';
                li.setAttribute('role', 'option');
                li.dataset.index = String(i);
                li.innerHTML = row.__create
                    ? '<strong>' + escHtml(row.nom_colonia || '') + '</strong>'
                    : escHtml(row.nom_colonia || '');
                li.addEventListener('mousedown', function (e) {
                    e.preventDefault();
                    pickRow(row);
                });
                dd.appendChild(li);
            });
            dd.hidden = false;
        }

        function runSearch() {
            var idCp = cpSel && cpSel.value ? String(cpSel.value) : '';
            var idLoc = locSel && locSel.value ? String(locSel.value) : '';
            if (!idCp && !idLoc) {
                closeDd();
                return;
            }
            var q = display.value.trim();
            var url = api + 'search_colonias.php?';
            if (idCp) {
                url += 'id_codigo_postal=' + encodeURIComponent(idCp);
            } else {
                url += 'id_localidad=' + encodeURIComponent(idLoc);
            }
            url += '&q=' + encodeURIComponent(q) + '&limit=50';
            fetch(url)
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!Array.isArray(data)) {
                        throw new Error('Respuesta invalida');
                    }
                    if (mergeCache) {
                        mergeCache(data);
                    }
                    var qNorm = normalizeText(q);
                    var hasExact = qNorm && data.some(function (row) {
                        return normalizeText(row.nom_colonia) === qNorm;
                    });
                    if (qNorm && !hasExact) {
                        data = data.concat([{
                            __create: true,
                            __createAction: crearNuevaColonia,
                            nom_colonia: 'Crear colonia "' + q + '"'
                        }]);
                    }
                    renderList(data);
                })
                .catch(function () {
                    closeDd();
                });
        }

        var debouncedSearch = debounce(runSearch, 300);

        display.addEventListener('input', function () {
            hidden.value = '';
            debouncedSearch();
        });

        display.addEventListener('focus', function () {
            if ((cpSel && cpSel.value) || (locSel && locSel.value)) {
                runSearch();
            }
        });

        display.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                if (dd.hidden || !lastResults.length) {
                    runSearch();
                    return;
                }
                activeIdx = Math.min(activeIdx + 1, lastResults.length - 1);
                highlightActive();
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                activeIdx = Math.max(activeIdx - 1, 0);
                highlightActive();
            } else if (e.key === 'Escape') {
                closeDd();
            } else if (e.key === 'Enter') {
                if (!dd.hidden && activeIdx >= 0 && lastResults[activeIdx]) {
                    e.preventDefault();
                    pickRow(lastResults[activeIdx]);
                }
            }
        });

        function highlightActive() {
            var items = dd.querySelectorAll('.joyeria-dir-combobox-item');
            items.forEach(function (el, i) {
                el.style.background = i === activeIdx ? 'rgba(11, 94, 215, 0.12)' : '';
            });
        }

        display.addEventListener('blur', function () {
            setTimeout(closeDd, 200);
        });

        async function crearNuevaColonia() {
            var nombre = display.value.trim();
            var idCp = cpSel && cpSel.value ? String(cpSel.value) : '';
            if (!nombre) {
                setFeedback('Escribe el nombre de la colonia.', true);
                display.focus();
                return;
            }
            if (!idCp) {
                setFeedback('Primero selecciona un codigo postal.', true);
                return;
            }
            var idLoc = typeof getRefLoc === 'function' ? getRefLoc() : null;
            if (!idLoc) {
                setFeedback('No hay localidad de referencia para este CP. Usa una colonia existente primero o agrega datos en modo avanzado.', true);
                return;
            }
            try {
                setFeedback('Creando colonia...', false);
                var response = await fetch(api + 'create_colonia.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        nom_colonia: nombre,
                        id_localidad_FK: idLoc,
                        id_codigo_postal_FK: parseInt(idCp, 10)
                    })
                });
                var data = await response.json();
                if (!response.ok || !data.success) {
                    throw new Error(data.error || 'No fue posible crear la colonia.');
                }
                hidden.value = String(data.id_colonia);
                var r2 = await fetch(api + 'search_colonias.php?id_codigo_postal=' + encodeURIComponent(idCp)
                    + '&id_colonia=' + encodeURIComponent(data.id_colonia));
                var rows = await r2.json();
                var row = Array.isArray(rows) && rows[0] ? rows[0] : null;
                if (row) {
                    display.value = row.nom_colonia || nombre;
                    if (mergeCache) {
                        mergeCache([row]);
                    }
                    if (typeof onSelect === 'function') {
                        onSelect(row);
                    }
                } else {
                    display.value = nombre;
                    var fallback = {
                        id_colonia: data.id_colonia,
                        nom_colonia: nombre,
                        id_localidad: idLoc,
                        nom_estado: '',
                        nom_municipio: '',
                        nom_localidad: ''
                    };
                    if (mergeCache) {
                        mergeCache([fallback]);
                    }
                    if (typeof onSelect === 'function') {
                        onSelect(fallback);
                    }
                }
                setFeedback(data.reused ? 'La colonia ya existia y se selecciono.' : 'Colonia creada correctamente.', false);
            } catch (err) {
                setFeedback('Error al crear colonia: ' + err.message, true);
            }
        }

        return {
            crearNueva: crearNuevaColonia,
            clear: function () {
                hidden.value = '';
                display.value = '';
                closeDd();
            },
            refreshSearch: runSearch
        };
    }

    /**
     * @param {object} opts
     * @param {string} opts.apiPrefix
     * @param {HTMLInputElement} opts.hiddenInput
     * @param {HTMLInputElement} opts.displayInput
     * @param {HTMLInputElement} opts.coloniaHiddenInput
     * @param {function(object):void} [opts.onSelect]
     * @param {function(string, boolean):void} [opts.setFeedback]
     */
    function initCalle(opts) {
        var api = normalizeApiPrefix(opts.apiPrefix);
        var hidden = opts.hiddenInput;
        var display = opts.displayInput;
        var coloniaHidden = opts.coloniaHiddenInput;
        var onSelect = opts.onSelect || function () {};
        var setFeedback = opts.setFeedback || function () {};

        var dd = document.createElement('ul');
        dd.className = 'joyeria-dir-combobox-dd';
        dd.setAttribute('hidden', '');
        var wrap = display.parentNode;
        if (wrap && wrap.classList.contains('joyeria-dir-combobox-wrap')) {
            wrap.style.position = 'relative';
            wrap.appendChild(dd);
        }

        var activeIdx = -1;
        var lastResults = [];

        function closeDd() {
            dd.hidden = true;
            dd.innerHTML = '';
            activeIdx = -1;
            lastResults = [];
        }

        function pickRow(row) {
            if (row && row.__create === true && typeof row.__createAction === 'function') {
                row.__createAction();
                return;
            }
            hidden.value = String(row.id_calle);
            display.value = row.nom_calle || '';
            closeDd();
            onSelect(row);
        }

        function renderList(rows) {
            lastResults = rows;
            activeIdx = -1;
            dd.innerHTML = '';
            if (!rows.length) {
                dd.hidden = true;
                return;
            }
            rows.forEach(function (row, i) {
                var li = document.createElement('li');
                li.className = 'joyeria-dir-combobox-item';
                li.dataset.index = String(i);
                li.innerHTML = row.__create
                    ? '<strong>' + escHtml(row.nom_calle || '') + '</strong>'
                    : escHtml(row.nom_calle || '');
                li.addEventListener('mousedown', function (e) {
                    e.preventDefault();
                    pickRow(row);
                });
                dd.appendChild(li);
            });
            dd.hidden = false;
        }

        function runSearch() {
            var idCol = coloniaHidden && coloniaHidden.value ? String(coloniaHidden.value) : '';
            if (!idCol) {
                closeDd();
                return;
            }
            var q = display.value.trim();
            var url = api + 'search_calles.php?id_colonia=' + encodeURIComponent(idCol)
                + '&q=' + encodeURIComponent(q) + '&limit=50';
            fetch(url)
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!Array.isArray(data)) {
                        throw new Error('Respuesta invalida');
                    }
                    var qNorm = normalizeText(q);
                    var hasExact = qNorm && data.some(function (row) {
                        return normalizeText(row.nom_calle) === qNorm;
                    });
                    if (qNorm && !hasExact) {
                        data = data.concat([{
                            __create: true,
                            __createAction: function () {
                                crearNuevaCalle(setFeedback);
                            },
                            nom_calle: 'Crear calle "' + q + '"'
                        }]);
                    }
                    renderList(data);
                })
                .catch(function () {
                    closeDd();
                });
        }

        var debouncedSearch = debounce(runSearch, 300);

        display.addEventListener('input', function () {
            hidden.value = '';
            debouncedSearch();
        });

        display.addEventListener('focus', function () {
            if (coloniaHidden && coloniaHidden.value) {
                runSearch();
            }
        });

        display.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                if (!coloniaHidden.value) {
                    return;
                }
                if (dd.hidden || !lastResults.length) {
                    runSearch();
                    return;
                }
                activeIdx = Math.min(activeIdx + 1, lastResults.length - 1);
                highlightActive();
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                activeIdx = Math.max(activeIdx - 1, 0);
                highlightActive();
            } else if (e.key === 'Escape') {
                closeDd();
            } else if (e.key === 'Enter') {
                if (!dd.hidden && activeIdx >= 0 && lastResults[activeIdx]) {
                    e.preventDefault();
                    pickRow(lastResults[activeIdx]);
                }
            }
        });

        function highlightActive() {
            var items = dd.querySelectorAll('.joyeria-dir-combobox-item');
            items.forEach(function (el, i) {
                el.style.background = i === activeIdx ? 'rgba(11, 94, 215, 0.12)' : '';
            });
        }

        display.addEventListener('blur', function () {
            setTimeout(closeDd, 200);
        });

        async function crearNuevaCalle(setFeedbackParam) {
            var feedback = typeof setFeedbackParam === 'function' ? setFeedbackParam : setFeedback;
            var nombre = display.value.trim();
            var idColonia = coloniaHidden && coloniaHidden.value ? String(coloniaHidden.value) : '';
            if (!nombre) {
                feedback('Escribe el nombre de la calle.', true);
                display.focus();
                return;
            }
            if (!idColonia) {
                feedback('Primero selecciona una colonia.', true);
                return;
            }
            try {
                feedback('Creando calle...', false);
                var response = await fetch(api + 'create_calle.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        nom_calle: nombre,
                        id_colonia_FK: parseInt(idColonia, 10)
                    })
                });
                var data = await response.json();
                if (!response.ok || !data.success) {
                    throw new Error(data.error || 'No fue posible crear la calle.');
                }
                hidden.value = String(data.id_calle);
                display.value = nombre;
                feedback(data.reused ? 'La calle ya existia y se selecciono.' : 'Calle creada correctamente.', false);
                onSelect(data);
            } catch (err) {
                feedback('Error al crear calle: ' + err.message, true);
            }
        }

        return {
            crearNueva: crearNuevaCalle,
            clear: function () {
                hidden.value = '';
                display.value = '';
                closeDd();
            },
            refreshSearch: runSearch
        };
    }

    /**
     * Combobox generico para catalogos de direccion (pais, estado, municipio, localidad, CP).
     *
     * @param {object} opts
     * @param {string} opts.apiPrefix
     * @param {HTMLInputElement} opts.hiddenInput
     * @param {HTMLInputElement} opts.displayInput
     * @param {string} opts.searchEndpoint - ej. search_paises.php
     * @param {string} opts.createEndpoint - ej. create_pais.php
     * @param {string} opts.idField - ej. id_pais
     * @param {string} opts.labelField - ej. nom_pais
     * @param {function():string|null} opts.buildSearchQuery - devuelve querystring sin ? o null si no se puede buscar
     * @param {function(string):object} opts.buildCreatePayload - body JSON para crear
     * @param {function(string, boolean):void} [opts.setFeedback]
     * @param {function(object):void} [opts.onSelect]
     * @param {function(function(string,boolean):void):boolean} [opts.validateCreate]
     * @param {function(string):string} [opts.createLabel] - texto opcion crear
     */
    function initGenerico(opts) {
        var api = normalizeApiPrefix(opts.apiPrefix);
        var hidden = opts.hiddenInput;
        var display = opts.displayInput;
        var setFeedback = opts.setFeedback || function () {};
        var onSelect = opts.onSelect || function () {};
        var idField = opts.idField;
        var labelField = opts.labelField;
        var searchEndpoint = opts.searchEndpoint;
        var createEndpoint = opts.createEndpoint;
        var buildSearchQuery = opts.buildSearchQuery;
        var buildCreatePayload = opts.buildCreatePayload;
        var validateCreate = opts.validateCreate || function () { return true; };
        var createLabel = opts.createLabel || function (q) {
            return 'Crear "' + q + '"';
        };

        var dd = document.createElement('ul');
        dd.className = 'joyeria-dir-combobox-dd';
        dd.setAttribute('hidden', '');
        dd.setAttribute('role', 'listbox');
        var wrap = display.parentNode;
        if (wrap && wrap.classList.contains('joyeria-dir-combobox-wrap')) {
            wrap.style.position = 'relative';
            wrap.appendChild(dd);
        }

        var activeIdx = -1;
        var lastResults = [];

        function closeDd() {
            dd.hidden = true;
            dd.innerHTML = '';
            activeIdx = -1;
            lastResults = [];
        }

        function pickRow(row) {
            if (row && row.__create === true && typeof row.__createAction === 'function') {
                row.__createAction();
                return;
            }
            hidden.value = String(row[idField]);
            display.value = row[labelField] || '';
            closeDd();
            onSelect(row);
        }

        function renderList(rows) {
            lastResults = rows;
            activeIdx = -1;
            dd.innerHTML = '';
            if (!rows.length) {
                dd.hidden = true;
                return;
            }
            rows.forEach(function (row, i) {
                var li = document.createElement('li');
                li.className = 'joyeria-dir-combobox-item';
                li.setAttribute('role', 'option');
                li.dataset.index = String(i);
                var lbl = row.__create ? row[labelField] : (row[labelField] || '');
                li.innerHTML = row.__create
                    ? '<strong>' + escHtml(lbl) + '</strong>'
                    : escHtml(lbl);
                li.addEventListener('mousedown', function (e) {
                    e.preventDefault();
                    pickRow(row);
                });
                dd.appendChild(li);
            });
            dd.hidden = false;
        }

        function runSearch() {
            var qs = typeof buildSearchQuery === 'function' ? buildSearchQuery() : null;
            if (qs === null) {
                closeDd();
                return;
            }
            var url = api + searchEndpoint + '?' + qs;
            fetch(url)
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!Array.isArray(data)) {
                        throw new Error('Respuesta invalida');
                    }
                    var q = display.value.trim();
                    var qNorm = normalizeText(q);
                    var hasExact = qNorm && data.some(function (row) {
                        return normalizeText(row[labelField]) === qNorm;
                    });
                    if (qNorm && !hasExact) {
                        var createRow = {};
                        createRow.__create = true;
                        createRow[labelField] = createLabel(q);
                        createRow.__createAction = crearNuevo;
                        data = data.concat([createRow]);
                    }
                    renderList(data);
                })
                .catch(function () {
                    closeDd();
                });
        }

        var debouncedSearch = debounce(runSearch, 300);

        display.addEventListener('input', function () {
            hidden.value = '';
            debouncedSearch();
        });

        display.addEventListener('focus', function () {
            runSearch();
        });

        display.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                if (dd.hidden || !lastResults.length) {
                    runSearch();
                    return;
                }
                activeIdx = Math.min(activeIdx + 1, lastResults.length - 1);
                highlightActive();
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                activeIdx = Math.max(activeIdx - 1, 0);
                highlightActive();
            } else if (e.key === 'Escape') {
                closeDd();
            } else if (e.key === 'Enter') {
                if (!dd.hidden && activeIdx >= 0 && lastResults[activeIdx]) {
                    e.preventDefault();
                    pickRow(lastResults[activeIdx]);
                }
            }
        });

        function highlightActive() {
            var items = dd.querySelectorAll('.joyeria-dir-combobox-item');
            items.forEach(function (el, i) {
                el.style.background = i === activeIdx ? 'rgba(11, 94, 215, 0.12)' : '';
            });
        }

        display.addEventListener('blur', function () {
            setTimeout(closeDd, 200);
        });

        async function crearNuevo() {
            var nombre = display.value.trim();
            if (!nombre) {
                setFeedback('Escribe un valor.', true);
                display.focus();
                return;
            }
            if (!validateCreate(setFeedback)) {
                return;
            }
            try {
                setFeedback('Creando...', false);
                var payload = typeof buildCreatePayload === 'function'
                    ? buildCreatePayload(nombre)
                    : {};
                var response = await fetch(api + createEndpoint, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                var data = await response.json();
                if (!response.ok || !data.success) {
                    throw new Error(data.error || 'No fue posible crear el registro.');
                }
                hidden.value = String(data[idField]);
                display.value = nombre;
                var row = {};
                row[idField] = data[idField];
                row[labelField] = nombre;
                setFeedback(data.reused ? 'Ya existia y se selecciono.' : 'Creado correctamente.', false);
                onSelect(row);
            } catch (err) {
                setFeedback('Error: ' + err.message, true);
            }
        }

        return {
            crearNueva: crearNuevo,
            clear: function () {
                hidden.value = '';
                display.value = '';
                closeDd();
            },
            refreshSearch: runSearch
        };
    }

    global.JoyeriaDirCombobox = {
        initColonia: initColonia,
        initCalle: initCalle,
        initGenerico: initGenerico,
        debounce: debounce
    };
}(typeof window !== 'undefined' ? window : this));
