(function () {
    'use strict';

    var STORAGE_PENDING = 'joyeria_pending_add';

    function rootDir() {
        var p = window.location.pathname.replace(/\\/g, '/');
        if (p.indexOf('/user/') !== -1) return '../';
        return '';
    }
    function carritoApiUrl() { return rootDir() + 'tienda_carrito_api.php'; }
    function piezaApiUrl() { return rootDir() + 'tienda_pieza_api.php'; }
    function loginUrl() { return rootDir() + 'login.php'; }

    function imgUrl(url) {
        var u = String(url || '').trim();
        if (u === '') return '';
        if (/^(https?:)?\/\//i.test(u) || u.charAt(0) === '/' || u.indexOf('data:') === 0) {
            return u;
        }
        return rootDir() + u;
    }

    /** Fallback si la API no envía alto_cm / ancho_cm (datos legacy). */
    function formatDimensionDisplay(etiqueta, raw) {
        var v = String(raw || '').trim();
        if (v === '') return '';
        v = v.replace(/^(largo|ancho|alto)\s*:\s*/i, '').trim();
        v = v.replace(/\s*cm\s*$/i, '').trim();
        var m = v.match(/^([\d]+(?:[.,]\d+)?)/);
        if (!m) return '';
        var num = parseFloat(m[1].replace(',', '.'));
        if (!(num > 0)) return '';
        var display = (num % 1 === 0) ? String(Math.round(num)) : String(num);
        return display + ' cm';
    }

    async function apiCall(url, body) {
        try {
            var res = await fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {'Content-Type':'application/json'},
                body: JSON.stringify(body || {})
            });
            return await res.json();
        } catch (e) {
            return { ok: false, error: 'Error de red.' };
        }
    }

    function setBadge(count) {
        var nodes = document.querySelectorAll('.cart-count');
        nodes.forEach(function(n){
            n.textContent = String(count || 0);
            if (count > 0) {
                n.classList.remove('d-none');
            } else {
                n.classList.add('d-none');
            }
        });
    }

    async function refrescarBadge() {
        var data = await apiCall(carritoApiUrl(), {action:'contar'});
        if (data && data.ok) setBadge(parseInt(data.count || 0, 10));
    }

    function guardarPendingAdd(idPieza, seleccion) {
        try {
            var payload = { id_pieza: parseInt(idPieza, 10) };
            seleccion = seleccion || {};
            if (seleccion.variante_valor) payload.variante_valor = String(seleccion.variante_valor);
            if (seleccion.variante_color) payload.variante_color = String(seleccion.variante_color);
            if (seleccion.variante_talla) payload.variante_talla = String(seleccion.variante_talla);
            if (seleccion.variante_eje1) payload.variante_eje1 = String(seleccion.variante_eje1);
            if (seleccion.variante_eje2) payload.variante_eje2 = String(seleccion.variante_eje2);
            if (seleccion.variante_valor1_id) payload.variante_valor1_id = parseInt(seleccion.variante_valor1_id, 10);
            if (seleccion.variante_valor2_id) payload.variante_valor2_id = parseInt(seleccion.variante_valor2_id, 10);
            sessionStorage.setItem(STORAGE_PENDING, JSON.stringify(payload));
        } catch (e) { /* noop */ }
    }

    function leerPendingAdd() {
        try {
            var raw = sessionStorage.getItem(STORAGE_PENDING);
            if (!raw) return null;
            sessionStorage.removeItem(STORAGE_PENDING);
            if (/^\d+$/.test(raw)) {
                return { id_pieza: parseInt(raw, 10) };
            }
            var obj = JSON.parse(raw);
            if (!obj || !obj.id_pieza) return null;
            return {
                id_pieza: parseInt(obj.id_pieza, 10),
                variante_valor: obj.variante_valor ? String(obj.variante_valor) : null,
                variante_color: obj.variante_color ? String(obj.variante_color) : null,
                variante_talla: obj.variante_talla ? String(obj.variante_talla) : null,
                variante_eje1: obj.variante_eje1 ? String(obj.variante_eje1) : null,
                variante_eje2: obj.variante_eje2 ? String(obj.variante_eje2) : null,
                variante_valor1_id: obj.variante_valor1_id ? parseInt(obj.variante_valor1_id, 10) : null,
                variante_valor2_id: obj.variante_valor2_id ? parseInt(obj.variante_valor2_id, 10) : null
            };
        } catch (e) {
            return null;
        }
    }

    function triggerLoginRequired(idPieza, seleccion) {
        guardarPendingAdd(idPieza, seleccion);
        window.location.assign(loginUrl());
    }

    async function agregarPieza(idPieza, feedbackEl, seleccion) {
        if (!idPieza) return;
        seleccion = seleccion || {};
        var payload = { action: 'agregar', id_pieza: parseInt(idPieza, 10) };
        if (seleccion.variante_valor) payload.variante_valor = String(seleccion.variante_valor);
        if (seleccion.variante_color) payload.variante_color = String(seleccion.variante_color);
        if (seleccion.variante_talla) payload.variante_talla = String(seleccion.variante_talla);
        if (seleccion.variante_eje1) payload.variante_eje1 = String(seleccion.variante_eje1);
        if (seleccion.variante_eje2) payload.variante_eje2 = String(seleccion.variante_eje2);
        if (seleccion.variante_valor1_id) payload.variante_valor1_id = parseInt(seleccion.variante_valor1_id, 10);
        if (seleccion.variante_valor2_id) payload.variante_valor2_id = parseInt(seleccion.variante_valor2_id, 10);
        var data = await apiCall(carritoApiUrl(), payload);
        if (data && data.login_required) {
            triggerLoginRequired(idPieza, seleccion);
            return { loggedOut: true };
        }
        if (data && data.ok) {
            setBadge(parseInt(data.count || 0, 10));
            if (feedbackEl) {
                feedbackEl.innerHTML = '<span class="text-success"><i class="bi bi-check-circle"></i> Pieza agregada a tu carrito.</span>';
            }
            return { ok: true };
        }
        if (feedbackEl) {
            feedbackEl.innerHTML = '<span class="text-danger"><i class="bi bi-x-circle"></i> ' + (data && data.error ? data.error : 'No se pudo agregar.') + '</span>';
        }
        return { ok: false, error: (data && data.error) || 'Error' };
    }

    function clearModal(modalEl) {
        modalEl.querySelector('.modal-pieza-loading').classList.remove('d-none');
        modalEl.querySelector('.modal-pieza-error').classList.add('d-none');
        modalEl.querySelector('.modal-pieza-contenido').classList.add('d-none');
        var fb = modalEl.querySelector('.modal-pieza-feedback');
        if (fb) fb.innerHTML = '';
        var varWrap = modalEl.querySelector('.modal-pieza-variantes-wrap');
        if (varWrap) {
            varWrap.querySelectorAll('.modal-pieza-variante-opciones, .modal-pieza-variante-color-opciones, .modal-pieza-variante-talla-opciones').forEach(function (ops) {
                ops.innerHTML = '';
            });
            var ejesWrap = varWrap.querySelector('.modal-pieza-variantes-ejes');
            if (ejesWrap) ejesWrap.innerHTML = '';
            varWrap.querySelectorAll('.modal-pieza-variante-color-wrap, .modal-pieza-variante-talla-wrap, .modal-pieza-variante-simple-wrap, .modal-pieza-variante-eje-wrap').forEach(function (w) {
                w.classList.add('d-none');
            });
            var selTxt = varWrap.querySelector('.modal-pieza-variante-seleccion');
            if (selTxt) {
                selTxt.textContent = '';
                selTxt.classList.add('d-none');
            }
        }
        modalEl._varianteSeleccionada = null;
        modalEl._varianteColor = null;
        modalEl._varianteTalla = null;
        modalEl._varianteEje1 = null;
        modalEl._varianteEje2 = null;
        modalEl._varianteValor1Id = null;
        modalEl._varianteValor2Id = null;
        modalEl._piezaVariantes = null;
        var imgWrap = modalEl.querySelector('.modal-pieza-img-principal-wrap');
        if (imgWrap && !imgWrap.querySelector('.modal-pieza-img-principal')) {
            imgWrap.innerHTML = '<img class="modal-pieza-img-principal w-100" src="" alt=""'
                + ' style="object-fit:cover;aspect-ratio:1/1;display:block;">';
        }
        var thumbs = modalEl.querySelector('.modal-pieza-img-thumbs');
        if (thumbs) thumbs.innerHTML = '';
    }

    function showModalError(modalEl, msg) {
        modalEl.querySelector('.modal-pieza-loading').classList.add('d-none');
        modalEl.querySelector('.modal-pieza-contenido').classList.add('d-none');
        var er = modalEl.querySelector('.modal-pieza-error');
        er.textContent = msg || 'No se pudo cargar la pieza.';
        er.classList.remove('d-none');
    }

    function esModoDosEjes(modo) {
        return modo === 'talla_color' || modo === 'dos_ejes';
    }

    function formatoValorVariante(valor, esTalla) {
        if (!valor) return '';
        return esTalla ? ('T' + String(valor)) : String(valor);
    }

    function resolverIdsVariante(modalEl, vars) {
        var v1 = modalEl._varianteEje1;
        var v2 = modalEl._varianteEje2;
        modalEl._varianteValor1Id = null;
        modalEl._varianteValor2Id = null;
        if (!v1 || !v2 || !Array.isArray(vars.variantes)) {
            return;
        }
        var modo = vars.modo || vars.variante_tipo || '';
        vars.variantes.forEach(function (row) {
            if (!row) return;
            var ok = false;
            if (modo === 'talla_color') {
                ok = String(row.color || '') === String(v1) && String(row.talla || '') === String(v2);
            } else {
                ok = String(row.valor1 || '') === String(v1) && String(row.valor2 || '') === String(v2);
                if (!ok) {
                    ok = String(row.valor1 || '') === String(v2) && String(row.valor2 || '') === String(v1);
                }
            }
            if (!ok) return;
            if (modo === 'talla_color') {
                if (row.valor1_id) modalEl._varianteValor1Id = parseInt(row.valor1_id, 10);
                if (row.valor2_id) modalEl._varianteValor2Id = parseInt(row.valor2_id, 10);
            } else if (String(row.valor1 || '') === String(v1)) {
                if (row.valor1_id) modalEl._varianteValor1Id = parseInt(row.valor1_id, 10);
                if (row.valor2_id) modalEl._varianteValor2Id = parseInt(row.valor2_id, 10);
            } else {
                if (row.valor2_id) modalEl._varianteValor1Id = parseInt(row.valor2_id, 10);
                if (row.valor1_id) modalEl._varianteValor2Id = parseInt(row.valor1_id, 10);
            }
        });
    }

    function valoresConStockEnMatriz(matriz) {
        return Object.keys(matriz || {}).filter(function (k) {
            var filas = matriz[k] || {};
            return Object.keys(filas).some(function (k2) {
                return parseInt(filas[k2] || 0, 10) > 0;
            });
        });
    }

    function ordenarValoresVariante(valores, esTalla) {
        var list = (valores || []).slice();
        list.sort(function (a, b) {
            var sa = String(a);
            var sb = String(b);
            if (esTalla) {
                var na = parseFloat(sa);
                var nb = parseFloat(sb);
                if (!isNaN(na) && !isNaN(nb)) return na - nb;
            }
            return sa.localeCompare(sb, 'es', { numeric: true, sensitivity: 'base' });
        });
        return list;
    }

    function precioDefaultPieza(p) {
        if (p.precio_default) {
            return p.precio_default;
        }
        return {
            precio: p.precio,
            precio_lista: p.precio_lista,
            precio_desde: !!p.precio_desde,
            precio_formateado: p.precio_formateado || ('$' + (p.precio || 0)),
            precio_lista_formateado: p.precio_lista_formateado || '',
            tiene_promocion: !!p.tiene_promocion,
            porcentaje_descuento: p.porcentaje_descuento || 0,
            promocion_nombre: p.promocion_nombre || ''
        };
    }

    function minPrecioInfo(listaInfos) {
        if (!listaInfos || !listaInfos.length) {
            return null;
        }
        var min = null;
        listaInfos.forEach(function (info) {
            if (!info) {
                return;
            }
            var p = parseFloat(info.precio);
            if (!isFinite(p) || p <= 0) {
                return;
            }
            if (!min || p < parseFloat(min.precio)) {
                min = info;
            }
        });
        if (!min) {
            return null;
        }
        var out = Object.assign({}, min);
        out.precio_desde = true;
        var pf = out.precio_formateado || ('$' + out.precio);
        if (pf.indexOf('Desde ') !== 0) {
            out.precio_formateado = 'Desde ' + pf.replace(/^Desde\s+/, '');
        }
        if (out.precio_lista_formateado && out.precio_lista_formateado.indexOf('Desde ') !== 0) {
            out.precio_lista_formateado = 'Desde ' + out.precio_lista_formateado.replace(/^Desde\s+/, '');
        }
        return out;
    }

    function resolverPrecioInfoSeleccion(modalEl) {
        var p = modalEl._piezaActual;
        if (!p) {
            return null;
        }
        var def = precioDefaultPieza(p);
        var vars = p.variantes || {};
        if (!vars.tiene_variantes) {
            return def;
        }

        var modo = vars.modo || vars.variante_tipo || 'ninguna';
        if (esModoDosEjes(modo)) {
            var v1 = modalEl._varianteEje1;
            var v2 = modalEl._varianteEje2;
            var matrizInfo = vars.matriz_precios_info || {};
            if (v1 && v2 && matrizInfo[v1] && matrizInfo[v1][v2]) {
                return matrizInfo[v1][v2];
            }
            if (v1 && matrizInfo[v1]) {
                var fila = matrizInfo[v1];
                return minPrecioInfo(Object.keys(fila).map(function (k) { return fila[k]; })) || def;
            }
            if (Array.isArray(vars.variantes)) {
                var infosMatriz = vars.variantes.map(function (row) { return row.precio_info; }).filter(Boolean);
                return minPrecioInfo(infosMatriz) || def;
            }
            return def;
        }

        var selRaw = modalEl._varianteEje1;
        var selEtiqueta = modalEl._varianteSeleccionada;
        if ((selRaw || selEtiqueta) && Array.isArray(vars.variantes)) {
            for (var i = 0; i < vars.variantes.length; i++) {
                var row = vars.variantes[i];
                if (!row || !row.precio_info) {
                    continue;
                }
                if (selRaw && String(row.valor1 || '') === String(selRaw)) {
                    return row.precio_info;
                }
                if (selEtiqueta && String(row.valor || '') === String(selEtiqueta)) {
                    return row.precio_info;
                }
            }
        }

        if (Array.isArray(vars.variantes)) {
            var infos = vars.variantes.map(function (row) { return row.precio_info; }).filter(Boolean);
            return minPrecioInfo(infos) || def;
        }

        return def;
    }

    function renderPrecioEnModal(modalEl, info) {
        var precioEl = modalEl.querySelector('.modal-pieza-precio');
        if (!precioEl || !info) {
            return;
        }
        var precioFmt = info.precio_formateado || ('$' + (info.precio || 0));
        if (info.tiene_promocion && info.precio_lista && info.precio_lista > info.precio) {
            var pctLabel = info.porcentaje_descuento ? ('-' + Math.round(info.porcentaje_descuento) + '% ') : '';
            var promoNombre = info.promocion_nombre
                ? ('<span class="d-block small promo-descuento-label fw-normal">' + info.promocion_nombre + '</span>')
                : '';
            var listaFmt = info.precio_lista_formateado || ('$' + info.precio_lista);
            precioEl.innerHTML = promoNombre
                + '<span class="precio-lista-tachado fs-6 fw-normal me-2">' + listaFmt + '</span>'
                + '<span class="precio-promo">' + precioFmt + '</span>'
                + (pctLabel ? (' <span class="producto-badge-promo">' + pctLabel.trim() + '</span>') : '');
        } else {
            precioEl.textContent = precioFmt;
        }
    }

    function actualizarPrecioModal(modalEl) {
        renderPrecioEnModal(modalEl, resolverPrecioInfoSeleccion(modalEl));
    }

    function seleccionVarianteCompleta(modalEl, vars) {
        var modo = vars.modo || vars.variante_tipo || 'ninguna';
        if (esModoDosEjes(modo)) {
            return !!(modalEl._varianteEje1 && modalEl._varianteEje2);
        }
        return !!(modalEl._varianteSeleccionada || modalEl._varianteEje1);
    }

    function obtenerSeleccionVariante(modalEl) {
        var p = modalEl._piezaActual;
        var vars = (p && p.variantes) ? p.variantes : {};
        var modo = vars.modo || vars.variante_tipo || 'ninguna';
        if (esModoDosEjes(modo)) {
            resolverIdsVariante(modalEl, vars);
            var sel = {
                variante_eje1: modalEl._varianteEje1 || null,
                variante_eje2: modalEl._varianteEje2 || null,
                variante_valor1_id: modalEl._varianteValor1Id || null,
                variante_valor2_id: modalEl._varianteValor2Id || null
            };
            if (modo === 'talla_color') {
                sel.variante_color = modalEl._varianteEje1 || null;
                sel.variante_talla = modalEl._varianteEje2 || null;
            }
            return sel;
        }
        if (modalEl._varianteEje1) {
            return {
                variante_valor: modalEl._varianteSeleccionada || modalEl._varianteEje1,
                variante_eje1: modalEl._varianteEje1,
                variante_valor1_id: modalEl._varianteValor1Id || null
            };
        }
        if (modalEl._varianteSeleccionada) {
            return { variante_valor: modalEl._varianteSeleccionada };
        }
        return {};
    }

    function actualizarTextoSeleccionVariante(modalEl) {
        var wrap = modalEl.querySelector('.modal-pieza-variantes-wrap');
        if (!wrap) return;
        var selTxt = wrap.querySelector('.modal-pieza-variante-seleccion');
        if (!selTxt) return;
        var p = modalEl._piezaActual;
        var vars = (p && p.variantes) ? p.variantes : {};
        var modo = vars.modo || vars.variante_tipo || 'ninguna';
        var ejes = Array.isArray(vars.ejes) ? vars.ejes : [];

        if (esModoDosEjes(modo) && modalEl._varianteEje1 && modalEl._varianteEje2) {
            var t1 = (ejes[0] && ejes[0].tipo) ? ejes[0].tipo : 'Opción';
            var t2 = (ejes[1] && ejes[1].tipo) ? ejes[1].tipo : 'Opción';
            var pref2 = (ejes[1] && ejes[1].es_talla) ? 'T' : '';
            selTxt.textContent = 'Tu selección: ' + t1 + ' ' + modalEl._varianteEje1
                + ' · ' + t2 + ' ' + pref2 + modalEl._varianteEje2;
            selTxt.classList.remove('d-none');
            return;
        }
        if (modalEl._varianteEje1 || modalEl._varianteSeleccionada) {
            var etiq = vars.variante_etiqueta || (ejes[0] && ejes[0].tipo) || 'Opción';
            var val = modalEl._varianteSeleccionada || modalEl._varianteEje1;
            selTxt.textContent = 'Tu selección: ' + etiq + ' ' + val;
            selTxt.classList.remove('d-none');
            return;
        }
        selTxt.textContent = '';
        selTxt.classList.add('d-none');
    }

    function actualizarBotonAgregarModal(modalEl) {
        var btn = modalEl.querySelector('.modal-pieza-btn-agregar');
        if (!btn) return;
        var p = modalEl._piezaActual;
        if (!p) return;
        var comprable = p.comprable_online === true || p.comprable_online === 1
            || (p.stock_disponible >= 1);
        var vars = p.variantes || {};
        var requiereVar = !!vars.tiene_variantes;
        var seleccionOk = !requiereVar || seleccionVarianteCompleta(modalEl, vars);
        btn.disabled = !comprable || !seleccionOk;
        if (requiereVar && !seleccionOk) {
            var modo = vars.modo || vars.variante_tipo || '';
            var ejes = Array.isArray(vars.ejes) ? vars.ejes : [];
            if (esModoDosEjes(modo)) {
                var t1 = (ejes[0] && ejes[0].tipo) ? ejes[0].tipo.toLowerCase() : 'opción';
                var t2 = (ejes[1] && ejes[1].tipo) ? ejes[1].tipo.toLowerCase() : 'opción';
                btn.innerHTML = '<i class="bi bi-cart-plus" aria-hidden="true"></i> Selecciona ' + t1 + ' y ' + t2;
            } else {
                btn.innerHTML = '<i class="bi bi-cart-plus" aria-hidden="true"></i> Selecciona ' + (vars.variante_etiqueta || 'opción').toLowerCase();
            }
        } else {
            btn.innerHTML = '<i class="bi bi-cart-plus" aria-hidden="true"></i> Agregar al carrito';
        }
    }

    function crearBotonVariante(valor, cantidad, claseExtra, dataAttrs) {
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-outline-dark btn-sm modal-pieza-variante-btn' + (claseExtra ? (' ' + claseExtra) : '');
        btn.setAttribute('data-variante-valor', String(valor));
        btn.setAttribute('aria-pressed', 'false');
        if (dataAttrs) {
            Object.keys(dataAttrs).forEach(function (k) {
                btn.setAttribute(k, String(dataAttrs[k]));
            });
        }
        btn.innerHTML = '<span class="modal-pieza-variante-valor">' + String(valor) + '</span>'
            + ' <span class="badge text-bg-light border">' + parseInt(cantidad || 0, 10) + '</span>';
        return btn;
    }

    function pintarVariantesModal(modalEl, p) {
        var wrap = modalEl.querySelector('.modal-pieza-variantes-wrap');
        var ejesContainer = wrap ? wrap.querySelector('.modal-pieza-variantes-ejes') : null;
        var hint = wrap ? wrap.querySelector('.modal-pieza-variante-hint') : null;
        if (!wrap || !ejesContainer) return;

        var vars = p.variantes || {};
        modalEl._varianteSeleccionada = null;
        modalEl._varianteColor = null;
        modalEl._varianteTalla = null;
        modalEl._varianteEje1 = null;
        modalEl._varianteEje2 = null;
        modalEl._varianteValor1Id = null;
        modalEl._varianteValor2Id = null;
        ejesContainer.innerHTML = '';

        if (!vars.tiene_variantes) {
            wrap.classList.add('d-none');
            return;
        }

        wrap.classList.remove('d-none');
        var modo = vars.modo || vars.variante_tipo || 'ninguna';
        var ejes = Array.isArray(vars.ejes) ? vars.ejes : [];
        var matriz = vars.matriz || {};

        if (esModoDosEjes(modo)) {
            var meta1 = ejes[0] || { tipo: 'Opción', valores: vars.colores || [], es_talla: false };
            var meta2 = ejes[1] || { tipo: 'Opción', valores: vars.tallas || [], es_talla: true };
            var valores1 = valoresConStockEnMatriz(matriz);
            if (valores1.length === 0) {
                valores1 = Array.isArray(meta1.valores) ? meta1.valores : (vars.colores || []);
            }
            valores1 = ordenarValoresVariante(valores1, !!meta1.es_talla);
            if (Array.isArray(meta2.valores) && meta2.valores.length > 0) {
                meta2.valores = ordenarValoresVariante(meta2.valores, !!meta2.es_talla);
            }
            if (hint) {
                hint.textContent = 'Primero elige ' + String(meta1.tipo || 'opción').toLowerCase()
                    + ' y luego ' + String(meta2.tipo || 'opción').toLowerCase() + ' disponible.';
            }

            var wrap1 = document.createElement('div');
            wrap1.className = 'modal-pieza-variante-eje-wrap mb-2';
            var label1 = document.createElement('label');
            label1.className = 'form-label fw-semibold mb-2';
            label1.textContent = 'Selecciona ' + String(meta1.tipo || 'opción').toLowerCase() + ':';
            var ops1 = document.createElement('div');
            ops1.className = 'modal-pieza-variante-eje1-opciones d-flex flex-wrap gap-2';
            ops1.setAttribute('role', 'radiogroup');
            wrap1.appendChild(label1);
            wrap1.appendChild(ops1);

            var wrap2 = document.createElement('div');
            wrap2.className = 'modal-pieza-variante-eje-wrap mb-2 d-none';
            var label2 = document.createElement('label');
            label2.className = 'form-label fw-semibold mb-2';
            label2.textContent = 'Selecciona ' + String(meta2.tipo || 'opción').toLowerCase() + ':';
            var ops2 = document.createElement('div');
            ops2.className = 'modal-pieza-variante-eje2-opciones d-flex flex-wrap gap-2';
            ops2.setAttribute('role', 'radiogroup');
            wrap2.appendChild(label2);
            wrap2.appendChild(ops2);

            ejesContainer.appendChild(wrap1);
            ejesContainer.appendChild(wrap2);

            function pintarEje2(valor1Sel) {
                ops2.innerHTML = '';
                modalEl._varianteEje2 = null;
                modalEl._varianteValor2Id = null;
                wrap2.classList.remove('d-none');
                var filas = matriz[valor1Sel] || {};
                var valores2 = Object.keys(filas).filter(function (k) {
                    return parseInt(filas[k] || 0, 10) > 0;
                });
                if (valores2.length === 0) {
                    valores2 = Array.isArray(meta2.valores) ? meta2.valores : [];
                }
                valores2 = ordenarValoresVariante(valores2, !!meta2.es_talla);
                valores2.forEach(function (valor2) {
                    if (!valor2) return;
                    var cant = parseInt(filas[valor2] || 0, 10);
                    if (cant <= 0) return;
                    var etiqueta = formatoValorVariante(valor2, !!meta2.es_talla);
                    var btn = crearBotonVariante(etiqueta, cant, 'modal-pieza-variante-eje2-btn', {
                        'data-variante-eje2': String(valor2)
                    });
                    btn.addEventListener('click', function () {
                        ops2.querySelectorAll('.modal-pieza-variante-eje2-btn').forEach(function (b) {
                            b.classList.remove('active');
                            b.setAttribute('aria-pressed', 'false');
                        });
                        btn.classList.add('active');
                        btn.setAttribute('aria-pressed', 'true');
                        modalEl._varianteEje2 = String(valor2);
                        resolverIdsVariante(modalEl, vars);
                        actualizarTextoSeleccionVariante(modalEl);
                        actualizarPrecioModal(modalEl);
                        actualizarBotonAgregarModal(modalEl);
                    });
                    ops2.appendChild(btn);
                });
            }

            valores1.forEach(function (valor1) {
                if (!valor1) return;
                var total = 0;
                var filas = matriz[valor1] || {};
                Object.keys(filas).forEach(function (k) { total += parseInt(filas[k] || 0, 10); });
                if (total <= 0) return;
                var btn1 = crearBotonVariante(String(valor1), total, 'modal-pieza-variante-eje1-btn', {
                    'data-variante-eje1': String(valor1)
                });
                btn1.addEventListener('click', function () {
                    ops1.querySelectorAll('.modal-pieza-variante-eje1-btn').forEach(function (b) {
                        b.classList.remove('active');
                        b.setAttribute('aria-pressed', 'false');
                    });
                    btn1.classList.add('active');
                    btn1.setAttribute('aria-pressed', 'true');
                    modalEl._varianteEje1 = String(valor1);
                    modalEl._varianteEje2 = null;
                    modalEl._varianteValor2Id = null;
                    pintarEje2(String(valor1));
                    actualizarTextoSeleccionVariante(modalEl);
                    actualizarPrecioModal(modalEl);
                    actualizarBotonAgregarModal(modalEl);
                });
                ops1.appendChild(btn1);
            });

            actualizarTextoSeleccionVariante(modalEl);
            actualizarPrecioModal(modalEl);
            return;
        }

        var meta = ejes[0] || { tipo: vars.variante_etiqueta || 'Opción', es_talla: modo === 'talla' };
        if (hint) {
            hint.textContent = 'Disponibilidad por unidad. Elige la que prefieres antes de agregar al carrito.';
        }

        var wrapSimple = document.createElement('div');
        wrapSimple.className = 'modal-pieza-variante-eje-wrap mb-2';
        var labelSimple = document.createElement('label');
        labelSimple.className = 'form-label fw-semibold mb-2';
        labelSimple.textContent = 'Selecciona ' + String(meta.tipo || vars.variante_etiqueta || 'opción').toLowerCase() + ':';
        var ops = document.createElement('div');
        ops.className = 'modal-pieza-variante-opciones d-flex flex-wrap gap-2';
        ops.setAttribute('role', 'radiogroup');
        wrapSimple.appendChild(labelSimple);
        wrapSimple.appendChild(ops);
        ejesContainer.appendChild(wrapSimple);

        (vars.variantes || []).forEach(function (v) {
            if (!v) return;
            var raw = String(v.valor1 || v.valor || '');
            if (!raw) return;
            var etiqueta = v.valor ? String(v.valor) : formatoValorVariante(raw, !!meta.es_talla);
            var btn = crearBotonVariante(etiqueta, v.cantidad);
            btn.addEventListener('click', function () {
                ops.querySelectorAll('.modal-pieza-variante-btn').forEach(function (b) {
                    b.classList.remove('active');
                    b.setAttribute('aria-pressed', 'false');
                });
                btn.classList.add('active');
                btn.setAttribute('aria-pressed', 'true');
                modalEl._varianteSeleccionada = etiqueta;
                modalEl._varianteEje1 = raw;
                modalEl._varianteValor1Id = v.valor1_id ? parseInt(v.valor1_id, 10) : null;
                var stockEl = modalEl.querySelector('.modal-pieza-stock');
                if (stockEl) {
                    stockEl.innerHTML = '<span class="text-success">' + (meta.tipo || vars.variante_etiqueta || 'Opción') + ' '
                        + etiqueta + ': ' + parseInt(v.cantidad || 0, 10) + ' disponible(s)</span>';
                }
                actualizarTextoSeleccionVariante(modalEl);
                actualizarPrecioModal(modalEl);
                actualizarBotonAgregarModal(modalEl);
            });
            ops.appendChild(btn);
        });

        if (vars.variantes && vars.variantes.length === 1 && vars.variantes[0].valor) {
            var unico = ops.querySelector('.modal-pieza-variante-btn');
            if (unico) unico.click();
        } else {
            actualizarPrecioModal(modalEl);
        }
    }

    function pintarPieza(modalEl, p) {
        modalEl.querySelector('.modal-pieza-loading').classList.add('d-none');
        modalEl.querySelector('.modal-pieza-error').classList.add('d-none');
        modalEl.querySelector('.modal-pieza-contenido').classList.remove('d-none');
        modalEl._piezaActual = p;

        var imgs = (p.imagenes || []);
        var principal = imgs.find(function(i){return i.es_principal;}) || imgs[0];
        var imgEl = modalEl.querySelector('.modal-pieza-img-principal');
        var principalUrl = principal ? imgUrl(principal.url) : '';
        if (imgEl) {
            if (principalUrl) {
                imgEl.src = principalUrl;
                imgEl.alt = p.desc_pieza || '';
                imgEl.style.display = '';
                imgEl.onerror = function(){
                    imgEl.onerror = null;
                    imgEl.style.display = 'none';
                };
            } else {
                imgEl.removeAttribute('src');
                imgEl.alt = '';
                imgEl.style.display = 'none';
            }
        }
        var thumbs = modalEl.querySelector('.modal-pieza-img-thumbs');
        if (thumbs) {
            thumbs.innerHTML = '';
            imgs.forEach(function(im){
                var u = imgUrl(im.url);
                if (!u) return;
                var t = document.createElement('img');
                t.src = u;
                t.alt = '';
                t.style.cssText = 'width:64px;height:64px;object-fit:cover;border:1px solid #ddd;border-radius:6px;cursor:pointer;';
                t.addEventListener('click', function(){
                    var freshImg = modalEl.querySelector('.modal-pieza-img-principal');
                    if (freshImg) freshImg.src = u;
                });
                thumbs.appendChild(t);
            });
        }

        modalEl.querySelector('.modal-pieza-familia').textContent = [p.nom_familia, p.nom_sub_familia].filter(Boolean).join(' / ');
        modalEl.querySelector('.modal-pieza-descripcion').textContent = p.desc_pieza || '';

        pintarVariantesModal(modalEl, p);
        actualizarPrecioModal(modalEl);

        modalEl.querySelector('.modal-pieza-metal').textContent = p.nom_metal || '—';
        modalEl.querySelector('.modal-pieza-categoria').textContent = p.nom_sub_familia || '—';

        var pesoWrap = modalEl.querySelector('.modal-pieza-peso-wrap');
        var pesoSpan = modalEl.querySelector('.modal-pieza-peso');
        if (p.peso_gr && parseFloat(p.peso_gr) > 0) {
            pesoWrap.style.display = '';
            pesoSpan.textContent = p.peso_gr;
        } else {
            pesoWrap.style.display = 'none';
        }
        var altoWrap = modalEl.querySelector('.modal-pieza-alto-wrap');
        var altoSpan = modalEl.querySelector('.modal-pieza-alto');
        var anchoWrap = modalEl.querySelector('.modal-pieza-ancho-wrap');
        var anchoSpan = modalEl.querySelector('.modal-pieza-ancho');
        var altoTexto = p.alto_cm || formatDimensionDisplay('Alto', p.largo);
        var anchoTexto = p.ancho_cm || formatDimensionDisplay('Ancho', p.ancho);
        if (altoTexto) {
            altoWrap.classList.remove('d-none');
            altoSpan.textContent = altoTexto;
        } else {
            altoWrap.classList.add('d-none');
            altoSpan.textContent = '';
        }
        if (anchoTexto) {
            anchoWrap.classList.remove('d-none');
            anchoSpan.textContent = anchoTexto;
        } else {
            anchoWrap.classList.add('d-none');
            anchoSpan.textContent = '';
        }

        var stockEl = modalEl.querySelector('.modal-pieza-stock');
        var comprable = p.comprable_online === true || p.comprable_online === 1
            || (p.stock_disponible >= 1);
        var vars = p.variantes || {};
        if (vars.tiene_variantes) {
            var modoStock = vars.modo || vars.variante_tipo || '';
            var ejesStock = Array.isArray(vars.ejes) ? vars.ejes : [];
            if (esModoDosEjes(modoStock) && ejesStock.length >= 2) {
                var partesGrupo = [];
                var valores1Stock = Array.isArray(ejesStock[0].valores) ? ejesStock[0].valores : (vars.colores || []);
                var esTalla2 = !!ejesStock[1].es_talla;
                valores1Stock.forEach(function (valor1) {
                    var filas = (vars.matriz && vars.matriz[valor1]) ? vars.matriz[valor1] : {};
                    var partes2 = [];
                    Object.keys(filas).forEach(function (valor2) {
                        var cant = parseInt(filas[valor2] || 0, 10);
                        if (cant > 0) {
                            partes2.push((esTalla2 ? 'T' : '') + valor2 + ' (' + cant + ')');
                        }
                    });
                    if (partes2.length) {
                        partesGrupo.push(valor1 + ': ' + partes2.join(' · '));
                    }
                });
                var etiqStock = (ejesStock[0].tipo || 'Opción') + ' y ' + (ejesStock[1].tipo || 'Opción');
                stockEl.innerHTML = '<span class="text-success">' + etiqStock + ': ' + partesGrupo.join(' | ') + '</span>';
            } else if (Array.isArray(vars.variantes) && vars.variantes.length > 0) {
                var partes = vars.variantes.map(function (v) {
                    return (v.valor || '') + ' (' + parseInt(v.cantidad || 0, 10) + ')';
                });
                stockEl.innerHTML = '<span class="text-success">' + (vars.variante_etiqueta || 'Variantes') + ': '
                    + partes.join(' · ') + '</span>';
            } else if (comprable) {
                stockEl.innerHTML = '<span class="text-success">Disponible en línea (' + parseInt(p.stock_disponible || 0, 10) + ' unidades)</span>';
            } else if ((p.stock_disponible || 0) > 0) {
                stockEl.innerHTML = '<span class="text-danger">No disponible en línea</span>';
            } else {
                stockEl.innerHTML = '<span class="text-danger">Agotado</span>';
            }
        } else if (comprable) {
            stockEl.innerHTML = '<span class="text-success">Disponible en línea (' + parseInt(p.stock_disponible || 0, 10) + ' unidades)</span>';
        } else if ((p.stock_disponible || 0) > 0) {
            stockEl.innerHTML = '<span class="text-danger">No disponible en línea</span>';
        } else {
            stockEl.innerHTML = '<span class="text-danger">Agotado</span>';
        }

        modalEl.querySelector('.modal-pieza-badge-tienda-texto').textContent = 'Recoger en: ' + (p.nom_tienda || '—');
        modalEl.querySelector('.modal-pieza-leyenda-tienda').textContent = p.nom_tienda || '—';

        var btn = modalEl.querySelector('.modal-pieza-btn-agregar');
        btn.dataset.idPieza = String(p.id_pieza);
        actualizarBotonAgregarModal(modalEl);
    }

    async function abrirDetalle(idPieza) {
        var modalEl = document.getElementById('modalPiezaDetalle');
        if (!modalEl) return;
        if (typeof bootstrap === 'undefined' || !bootstrap.Modal) {
            window.location.assign(rootDir() + 'catalogo.php#pieza-' + idPieza);
            return;
        }
        var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        clearModal(modalEl);
        modal.show();
        try {
            var res = await fetch(piezaApiUrl() + '?id_pieza=' + encodeURIComponent(idPieza), {credentials:'same-origin'});
            var data = await res.json();
            if (!data || !data.ok) {
                showModalError(modalEl, (data && data.error) || 'No se pudo cargar la pieza.');
                return;
            }
            pintarPieza(modalEl, data.pieza);
        } catch (e) {
            showModalError(modalEl, 'Error de red al cargar la pieza.');
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        refrescarBadge();

        var pending = leerPendingAdd();
        if (pending && pending.id_pieza > 0) {
            setTimeout(function () {
                agregarPieza(pending.id_pieza, null, pending);
            }, 300);
        }

        document.body.addEventListener('click', function(e){
            var btnAgregar = e.target.closest('[data-pieza-action="agregar"]');
            var btnVer = e.target.closest('[data-pieza-action="ver"]');
            var card = e.target.closest('.producto-card');

            if (btnAgregar) {
                e.preventDefault();
                e.stopPropagation();
                var id = parseInt(btnAgregar.dataset.idPieza || (card ? card.dataset.idPieza : 0), 10);
                if (id <= 0) return;
                if (card && card.getAttribute('data-tiene-variantes') === '1') {
                    abrirDetalle(id);
                    return;
                }
                agregarPieza(id, null, null);
                return;
            }
            if (btnVer) {
                e.preventDefault();
                e.stopPropagation();
                var idv = parseInt(btnVer.dataset.idPieza || (card ? card.dataset.idPieza : 0), 10);
                if (idv > 0) abrirDetalle(idv);
                return;
            }
            if (card && card.dataset.idPieza) {
                var idc = parseInt(card.dataset.idPieza, 10);
                if (idc > 0) abrirDetalle(idc);
            }
        });

        var modalEl = document.getElementById('modalPiezaDetalle');
        if (modalEl) {
            var btn = modalEl.querySelector('.modal-pieza-btn-agregar');
            if (btn) {
                btn.addEventListener('click', async function(){
                    var idP = parseInt(btn.dataset.idPieza || 0, 10);
                    if (!idP) return;
                    var seleccion = obtenerSeleccionVariante(modalEl);
                    btn.disabled = true;
                    var fb = modalEl.querySelector('.modal-pieza-feedback');
                    var r = await agregarPieza(idP, fb, seleccion);
                    if (r && r.ok) {
                        btn.innerHTML = '<i class="bi bi-check2"></i> Agregada';
                        setTimeout(function(){
                            try { bootstrap.Modal.getInstance(modalEl).hide(); } catch(e){}
                        }, 1200);
                    } else {
                        actualizarBotonAgregarModal(modalEl);
                    }
                });
            }
        }
    });

    window.JoyeriaCarrito = {
        refrescarBadge: refrescarBadge,
        agregar: agregarPieza,
        abrirDetalle: abrirDetalle,
    };
})();
