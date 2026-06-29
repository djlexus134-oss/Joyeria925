/**
 * Orquestador de direccion: 7 niveles con combobox generico + colonia/calle existentes.
 */
(function (global) {
    'use strict';

    function JoyeriaDireccionForm() {}

    function mergeColoniasCache(arr, coloniasPorCP) {
        if (!Array.isArray(arr)) {
            return;
        }
        arr.forEach(function (r) {
            var i = coloniasPorCP.findIndex(function (x) {
                return String(x.id_colonia) === String(r.id_colonia);
            });
            if (i >= 0) {
                coloniasPorCP[i] = r;
            } else {
                coloniasPorCP.push(r);
            }
        });
    }

    /**
     * @param {object} opts
     * @param {string|HTMLElement} opts.root - selector o elemento contenedor .joyeria-direccion-root
     * @param {string} [opts.apiPrefix]
     * @param {string} [opts.prefix] - prefijo de names (ej. rapida_)
     * @param {string} [opts.feedbackId]
     */
    JoyeriaDireccionForm.init = function (opts) {
        var root = typeof opts.root === 'string'
            ? document.querySelector(opts.root)
            : opts.root;
        if (!root) {
            return null;
        }

        var apiPrefix = opts.apiPrefix || root.getAttribute('data-api-prefix') || './api/';
        var feedbackId = opts.feedbackId || opts.feedback || root.getAttribute('data-feedback-id') || '';

        function setFeedback(msg, esError) {
            if (!feedbackId) {
                return;
            }
            var el = document.getElementById(feedbackId);
            if (!el) {
                return;
            }
            el.textContent = msg || '';
            el.style.color = esError ? '#b02a37' : '#0b5ed7';
        }

        function fk(entity) {
            return root.querySelector('.joyeria-field[data-entity="' + entity + '"] .joyeria-fk');
        }
        function disp(entity) {
            return root.querySelector('.joyeria-field[data-entity="' + entity + '"] .joyeria-display');
        }
        function resumen(key) {
            return root.querySelector('.joyeria-resumen[data-resumen="' + key + '"]');
        }

        var coloniasPorCP = [];
        var referenciaLocalidadCp = null;

        var combos = {};

        function clearEntity(entity) {
            var h = fk(entity);
            var d = disp(entity);
            if (h) {
                h.value = '';
            }
            if (d) {
                d.value = '';
            }
        }

        function clearFrom(estado) {
            var order = ['pais', 'estado', 'municipio', 'localidad', 'cp', 'colonia', 'calle'];
            var start = order.indexOf(estado);
            if (start < 0) {
                return;
            }
            for (var i = start + 1; i < order.length; i++) {
                clearEntity(order[i]);
            }
            if (combos.colonia) {
                combos.colonia.clear();
            }
            if (combos.calle) {
                combos.calle.clear();
            }
            ['estado', 'municipio', 'localidad'].forEach(function (k) {
                var r = resumen(k);
                if (r) {
                    r.value = '';
                }
            });
        }

        function aplicarColoniaRow(row) {
            if (!row) {
                return;
            }
            function ensureFk(entity, idVal, nomVal) {
                var h = fk(entity);
                var d = disp(entity);
                if (h && idVal) {
                    h.value = String(idVal);
                }
                if (d && nomVal) {
                    d.value = nomVal;
                }
            }
            ensureFk('pais', row.id_pais, row.nom_pais);
            ensureFk('estado', row.id_estado, row.nom_estado);
            ensureFk('municipio', row.id_municipio, row.nom_municipio);
            ensureFk('localidad', row.id_localidad, row.nom_localidad);
            var rEst = resumen('estado');
            var rMun = resumen('municipio');
            var rLoc = resumen('localidad');
            if (rEst) {
                rEst.value = row.nom_estado || '';
            }
            if (rMun) {
                rMun.value = row.nom_municipio || '';
            }
            if (rLoc) {
                rLoc.value = row.nom_localidad || '';
            }
            mergeColoniasCache([row], coloniasPorCP);
            referenciaLocalidadCp = row.id_localidad || referenciaLocalidadCp;
            if (row.id_codigo_postal && fk('cp')) {
                fk('cp').value = String(row.id_codigo_postal);
                var dCp = disp('cp');
                if (dCp && row.codigo_postal) {
                    dCp.value = row.codigo_postal;
                }
            }
        }

        function getReferenciaLocalidad() {
            var loc = fk('localidad');
            if (loc && loc.value) {
                return parseInt(loc.value, 10);
            }
            if (referenciaLocalidadCp) {
                return referenciaLocalidadCp;
            }
            var ch = fk('colonia');
            if (ch && ch.value) {
                var row = coloniasPorCP.find(function (x) {
                    return String(x.id_colonia) === String(ch.value);
                });
                if (row) {
                    return row.id_localidad;
                }
            }
            return coloniasPorCP[0] ? coloniasPorCP[0].id_localidad : null;
        }

        combos.pais = global.JoyeriaDirCombobox.initGenerico({
            apiPrefix: apiPrefix,
            hiddenInput: fk('pais'),
            displayInput: disp('pais'),
            searchEndpoint: 'search_paises.php',
            createEndpoint: 'create_pais.php',
            idField: 'id_pais',
            labelField: 'nom_pais',
            setFeedback: setFeedback,
            buildSearchQuery: function () {
                var q = disp('pais').value.trim();
                return 'q=' + encodeURIComponent(q) + '&limit=50';
            },
            buildCreatePayload: function (text) {
                return { nom_pais: text };
            },
            createLabel: function (q) {
                return 'Crear pais "' + q + '"';
            },
            onSelect: function () {
                clearFrom('pais');
            }
        });

        combos.estado = global.JoyeriaDirCombobox.initGenerico({
            apiPrefix: apiPrefix,
            hiddenInput: fk('estado'),
            displayInput: disp('estado'),
            searchEndpoint: 'search_estados.php',
            createEndpoint: 'create_estado.php',
            idField: 'id_estado',
            labelField: 'nom_estado',
            setFeedback: setFeedback,
            buildSearchQuery: function () {
                var idP = fk('pais') && fk('pais').value ? fk('pais').value : '';
                if (!idP) {
                    return null;
                }
                var q = disp('estado').value.trim();
                return 'id_pais=' + encodeURIComponent(idP) + '&q=' + encodeURIComponent(q) + '&limit=50';
            },
            buildCreatePayload: function (text) {
                return {
                    nom_estado: text,
                    id_pais_FK: parseInt(fk('pais').value, 10)
                };
            },
            validateCreate: function (sf) {
                if (!fk('pais') || !fk('pais').value) {
                    sf('Selecciona un pais primero.', true);
                    return false;
                }
                return true;
            },
            createLabel: function (q) {
                return 'Crear estado "' + q + '"';
            },
            onSelect: function () {
                clearFrom('estado');
            }
        });

        combos.municipio = global.JoyeriaDirCombobox.initGenerico({
            apiPrefix: apiPrefix,
            hiddenInput: fk('municipio'),
            displayInput: disp('municipio'),
            searchEndpoint: 'search_municipios.php',
            createEndpoint: 'create_municipio.php',
            idField: 'id_municipio',
            labelField: 'nom_municipio',
            setFeedback: setFeedback,
            buildSearchQuery: function () {
                var idE = fk('estado') && fk('estado').value ? fk('estado').value : '';
                if (!idE) {
                    return null;
                }
                var q = disp('municipio').value.trim();
                return 'id_estado=' + encodeURIComponent(idE) + '&q=' + encodeURIComponent(q) + '&limit=50';
            },
            buildCreatePayload: function (text) {
                return {
                    nom_municipio: text,
                    id_estado_FK: parseInt(fk('estado').value, 10)
                };
            },
            validateCreate: function (sf) {
                if (!fk('estado') || !fk('estado').value) {
                    sf('Selecciona un estado primero.', true);
                    return false;
                }
                return true;
            },
            createLabel: function (q) {
                return 'Crear municipio "' + q + '"';
            },
            onSelect: function () {
                clearFrom('municipio');
            }
        });

        combos.localidad = global.JoyeriaDirCombobox.initGenerico({
            apiPrefix: apiPrefix,
            hiddenInput: fk('localidad'),
            displayInput: disp('localidad'),
            searchEndpoint: 'search_localidades.php',
            createEndpoint: 'create_localidad.php',
            idField: 'id_localidad',
            labelField: 'nom_localidad',
            setFeedback: setFeedback,
            buildSearchQuery: function () {
                var idM = fk('municipio') && fk('municipio').value ? fk('municipio').value : '';
                if (!idM) {
                    return null;
                }
                var q = disp('localidad').value.trim();
                return 'id_municipio=' + encodeURIComponent(idM) + '&q=' + encodeURIComponent(q) + '&limit=50';
            },
            buildCreatePayload: function (text) {
                return {
                    nom_localidad: text,
                    id_municipio_FK: parseInt(fk('municipio').value, 10)
                };
            },
            validateCreate: function (sf) {
                if (!fk('municipio') || !fk('municipio').value) {
                    sf('Selecciona un municipio primero.', true);
                    return false;
                }
                return true;
            },
            createLabel: function (q) {
                return 'Crear localidad "' + q + '"';
            },
            onSelect: function () {
                clearFrom('localidad');
                coloniasPorCP = [];
                referenciaLocalidadCp = null;
                if (fk('localidad') && fk('localidad').value) {
                    var url = apiPrefix.replace(/\/?$/, '/') + 'search_colonias.php?id_localidad='
                        + encodeURIComponent(fk('localidad').value) + '&q=&limit=100';
                    fetch(url)
                        .then(function (r) { return r.json(); })
                        .then(function (data) {
                            coloniasPorCP = Array.isArray(data) ? data : [];
                            referenciaLocalidadCp = coloniasPorCP[0] ? coloniasPorCP[0].id_localidad : null;
                        })
                        .catch(function () {});
                }
            }
        });

        combos.cp = global.JoyeriaDirCombobox.initGenerico({
            apiPrefix: apiPrefix,
            hiddenInput: fk('cp'),
            displayInput: disp('cp'),
            searchEndpoint: 'search_codigos_postales.php',
            createEndpoint: 'create_codigo_postal.php',
            idField: 'id_codigo_postal',
            labelField: 'codigo_postal',
            setFeedback: setFeedback,
            buildSearchQuery: function () {
                var q = disp('cp').value.trim();
                return 'q=' + encodeURIComponent(q) + '&limit=50';
            },
            buildCreatePayload: function (text) {
                return { codigo_postal: text };
            },
            createLabel: function (q) {
                return 'Crear CP "' + q + '"';
            },
            onSelect: function () {
                clearFrom('cp');
                coloniasPorCP = [];
                referenciaLocalidadCp = null;
                var idCp = fk('cp') && fk('cp').value ? fk('cp').value : '';
                if (!idCp) {
                    return;
                }
                var url = apiPrefix.replace(/\/?$/, '/') + 'search_colonias.php?id_codigo_postal='
                    + encodeURIComponent(idCp) + '&q=&limit=100';
                fetch(url)
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        coloniasPorCP = Array.isArray(data) ? data : [];
                        referenciaLocalidadCp = coloniasPorCP[0] ? coloniasPorCP[0].id_localidad : null;
                        if (coloniasPorCP[0]) {
                            aplicarColoniaRow(coloniasPorCP[0]);
                        }
                    })
                    .catch(function () {});
            }
        });

        combos.colonia = global.JoyeriaDirCombobox.initColonia({
            apiPrefix: apiPrefix,
            hiddenInput: fk('colonia'),
            displayInput: disp('colonia'),
            cpSelect: fk('cp'),
            localidadSelect: fk('localidad'),
            mergeColoniasCache: function (rows) {
                mergeColoniasCache(rows, coloniasPorCP);
            },
            setFeedback: setFeedback,
            getReferenciaLocalidad: getReferenciaLocalidad,
            onSelect: function (row) {
                aplicarColoniaRow(row);
                if (combos.calle) {
                    combos.calle.clear();
                    combos.calle.refreshSearch();
                }
            }
        });

        combos.calle = global.JoyeriaDirCombobox.initCalle({
            apiPrefix: apiPrefix,
            hiddenInput: fk('calle'),
            displayInput: disp('calle'),
            coloniaHiddenInput: fk('colonia'),
            setFeedback: setFeedback,
            onSelect: function () {}
        });

        function cargarColoniasPorCpExterno(idCp) {
            coloniasPorCP = [];
            referenciaLocalidadCp = null;
            if (!idCp) {
                return;
            }
            var url = apiPrefix.replace(/\/?$/, '/') + 'search_colonias.php?id_codigo_postal='
                + encodeURIComponent(idCp) + '&q=&limit=100';
            fetch(url)
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    coloniasPorCP = Array.isArray(data) ? data : [];
                    referenciaLocalidadCp = coloniasPorCP[0] ? coloniasPorCP[0].id_localidad : null;
                    if (coloniasPorCP[0]) {
                        aplicarColoniaRow(coloniasPorCP[0]);
                    }
                })
                .catch(function () {});
        }

        function prefillFromIds() {
            var idCp = fk('cp') && fk('cp').value;
            var idCol = fk('colonia') && fk('colonia').value;
            var idCal = fk('calle') && fk('calle').value;
            if (idCol) {
                var u = apiPrefix.replace(/\/?$/, '/') + 'search_colonias.php?id_colonia='
                    + encodeURIComponent(idCol) + (idCp ? '&id_codigo_postal=' + encodeURIComponent(idCp) : '');
                fetch(u)
                    .then(function (r) { return r.json(); })
                    .then(function (rows) {
                        if (Array.isArray(rows) && rows[0]) {
                            aplicarColoniaRow(rows[0]);
                            fk('colonia').value = String(rows[0].id_colonia);
                            disp('colonia').value = rows[0].nom_colonia || '';
                            coloniasPorCP = rows;
                            referenciaLocalidadCp = rows[0].id_localidad || null;
                            if (idCal) {
                                return fetch(apiPrefix.replace(/\/?$/, '/') + 'search_calles.php?id_colonia='
                                    + encodeURIComponent(rows[0].id_colonia) + '&id_calle=' + encodeURIComponent(idCal))
                                    .then(function (r2) { return r2.json(); })
                                    .then(function (cr) {
                                        if (Array.isArray(cr) && cr[0]) {
                                            fk('calle').value = String(cr[0].id_calle);
                                            disp('calle').value = cr[0].nom_calle || '';
                                        }
                                    });
                            }
                        }
                    })
                    .catch(function () {});
            } else if (idCp) {
                cargarColoniasPorCpExterno(idCp);
            }
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', prefillFromIds);
        } else {
            prefillFromIds();
        }

        var api = {
            root: root,
            combos: combos,
            coloniasPorCP: coloniasPorCP,
            setFeedback: setFeedback,
            aplicarColoniaRow: aplicarColoniaRow,
            clearFrom: clearFrom,
            mergeColoniasCache: function (rows) {
                mergeColoniasCache(rows, coloniasPorCP);
            },
            refreshColoniasCp: cargarColoniasPorCpExterno,
            prefillFromIds: prefillFromIds
        };

        root.joyeriaDireccionApi = api;
        return api;
    };

    global.JoyeriaDireccionForm = JoyeriaDireccionForm;
}(typeof window !== 'undefined' ? window : this));
