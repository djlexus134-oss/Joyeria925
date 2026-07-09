<?php
$estadoPos = $estado ?? [];
$detalles = isset($estadoPos['detalles']) && is_array($estadoPos['detalles']) ? $estadoPos['detalles'] : [];
$ultimaLinea = !empty($detalles) ? $detalles[count($detalles) - 1] : null;
?>

<script>document.body.classList.add('pos-escaner-only');</script>

<div id="pos-escaner-app" class="pos-escaner-app">
    <header class="pos-escaner-topbar">
        <a href="punto_venta.php" class="btn-action-secondary" style="padding:8px 12px;">
            <i class="bi bi-arrow-left"></i> POS
        </a>
        <div class="pos-escaner-stats" aria-live="polite">
            <span><strong id="escaner_conteo"><?php echo (int) ($totalesIniciales['conteo_piezas'] ?? 0); ?></strong> piezas</span>
            <span>Total <strong id="escaner_total">$<?php echo htmlspecialchars((string) ($totalesIniciales['total'] ?? '0.00')); ?></strong></span>
        </div>
    </header>

    <div class="pos-escaner-viewport-wrap">
        <div id="pos-scanner-reader" class="pos-scanner-viewport pos-escaner-viewport"></div>
    </div>

    <footer class="pos-escaner-foot">
        <div id="pos-escaner-last" class="pos-escaner-last" aria-live="polite">
            <?php if (is_array($ultimaLinea)): ?>
                Ultimo: <?php echo htmlspecialchars((string) ($ultimaLinea['codigo'] ?? '')); ?>
                â€” <?php echo htmlspecialchars((string) ($ultimaLinea['descripcion'] ?? '')); ?>
            <?php else: ?>
                Enfoca una etiqueta EAN13 para agregar piezas al ticket.
            <?php endif; ?>
        </div>
        <p id="pos-scanner-status" class="pos-scanner-status is-info">Iniciando camara...</p>
        <div class="pos-escaner-actions">
            <a href="punto_venta.php" class="btn-action-primary">
                <i class="bi bi-cash-coin"></i> Ir a cobrar
            </a>
            <button type="button" class="btn-action-secondary" id="btn_escaner_limpiar" title="Descartar ticket e iniciar venta nueva">
                <i class="bi bi-file-earmark-plus"></i> Nueva venta
            </button>
        </div>
    </footer>

    <select id="id_impuesto_FK" style="display:none;" aria-hidden="true">
        <?php
        $idImpuestoSesionEsc = isset($estadoPos['id_impuesto']) ? (string) $estadoPos['id_impuesto'] : '';
        foreach (($catalogos['impuestos'] ?? []) as $impuesto):
            $idImpOptEsc = (int) $impuesto['id_impuesto'];
            $selImpuestoEsc = ($idImpuestoSesionEsc !== '' && $idImpuestoSesionEsc === (string) $idImpOptEsc)
                || ($idImpuestoSesionEsc === '' && !empty($idImpuestoDefault) && (int) $idImpuestoDefault === $idImpOptEsc);
        ?>
            <option value="<?php echo $idImpOptEsc; ?>" <?php echo $selImpuestoEsc ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars((string) $impuesto['tipo_impuesto']); ?>
            </option>
        <?php endforeach; ?>
    </select>
    <select id="id_cliente_FK" style="display:none;" aria-hidden="true">
        <option value="" <?php echo empty($estadoPos['id_cliente']) ? 'selected' : ''; ?>></option>
        <?php
        $clientes = $catalogos['clientes'] ?? [];
        $selectedId = $estadoPos['id_cliente'] ?? '';
        $includeEmpty = false;
        require __DIR__ . '/../partials/cliente_select_options.php';
        ?>
    </select>
    <select id="id_tienda_FK" style="display:none;" aria-hidden="true">
        <option value="" <?php echo empty($estadoPos['id_tienda']) ? 'selected' : ''; ?>></option>
        <?php foreach (($tiendas ?? []) as $tienda): ?>
            <option value="<?php echo (int) $tienda['id_tienda']; ?>" <?php echo ((string) ($estadoPos['id_tienda'] ?? '') === (string) $tienda['id_tienda']) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars((string) $tienda['nom_tienda']); ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>

<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script src="js/pos-scan-feedback.js"></script>
<script src="js/pos-stock-alert.js"></script>
<script src="js/pos-barcode-scanner.js"></script>
<script>
(function () {
    function qs(id) { return document.getElementById(id); }

    var selCliente = qs('id_cliente_FK');
    var selImpuesto = qs('id_impuesto_FK');
    var selTienda = qs('id_tienda_FK');
    var idImpuestoCfg = <?php echo json_encode($idImpuestoDefault ?? null, JSON_UNESCAPED_UNICODE); ?>;
    var catalogoImpuestosPos = <?php echo json_encode($catalogos['impuestos'] ?? [], JSON_UNESCAPED_UNICODE); ?>;
    var lastBox = qs('pos-escaner-last');
    var agregando = false;

    function obtenerIdImpuestoPredeterminado() {
        if (idImpuestoCfg !== null && idImpuestoCfg !== undefined && idImpuestoCfg !== '') {
            var sid = String(idImpuestoCfg);
            for (var k = 0; k < (catalogoImpuestosPos || []).length; k++) {
                if (String(catalogoImpuestosPos[k].id_impuesto) === sid) {
                    return sid;
                }
            }
        }
        if ((catalogoImpuestosPos || []).length > 0) {
            return String(catalogoImpuestosPos[0].id_impuesto);
        }
        return '';
    }

    if (selImpuesto && !(selImpuesto.value || '').trim()) {
        var idImpPred = obtenerIdImpuestoPredeterminado();
        if (idImpPred) {
            selImpuesto.value = idImpPred;
        }
    }

    function formData(obj) {
        var fd = new FormData();
        Object.keys(obj).forEach(function (k) { fd.append(k, obj[k]); });
        if (window.joyeriaAppendCsrfToFormData) {
            window.joyeriaAppendCsrfToFormData(fd);
        }
        return fd;
    }

    function postAction(action, payload) {
        return fetch('punto_venta.php?accion=' + encodeURIComponent(action), {
            method: 'POST',
            credentials: 'same-origin',
            body: formData(payload || {})
        }).then(function (r) { return r.json(); }).then(function (res) {
            if (!res.ok) {
                var msg = res.mensaje || 'Error';
                if (window.joyeriaIsCsrfErrorMessage && window.joyeriaIsCsrfErrorMessage(msg)) {
                    msg = 'Sesión de seguridad expirada. Recarga la página (F5) e intenta de nuevo.';
                }
                var err = new Error(msg);
                err.codigo_error = res.codigo_error || '';
                throw err;
            }
            return res;
        });
    }

    function actualizarResumen(data) {
        if (!data || !data.totales) {
            return;
        }
        qs('escaner_conteo').textContent = String(data.totales.conteo_piezas || 0);
        qs('escaner_total').textContent = '$' + data.totales.total;
    }

    function mostrarUltimo(linea, kind) {
        if (!lastBox) {
            return;
        }
        lastBox.className = 'pos-escaner-last' + (kind ? ' is-' + kind : '');
        if (!linea) {
            lastBox.textContent = 'Enfoca una etiqueta EAN13 para agregar piezas al ticket.';
            return;
        }
        lastBox.textContent = 'Ultimo: ' + (linea.codigo || '') + ' â€” ' + (linea.descripcion || '');
    }

    function syncMeta() {
        return postAction('actualizar_meta', {
            id_cliente_FK: selCliente ? (selCliente.value || '') : '',
            id_impuesto_FK: selImpuesto ? (selImpuesto.value || '') : '',
            id_tienda_FK: selTienda ? (selTienda.value || '') : ''
        });
    }

    function agregarPorCodigo(codigo) {
        if (window.JoyeriaBarcodeInput && typeof JoyeriaBarcodeInput.normalizeScanCode === 'function') {
            codigo = JoyeriaBarcodeInput.normalizeScanCode(codigo);
        } else if (/^\d+-\d+$/.test(String(codigo || '').trim())) {
            codigo = String(codigo).trim().replace('-', '/');
        }
        if (!codigo || agregando) {
            return Promise.resolve();
        }
        agregando = true;
        return syncMeta()
            .then(function () {
                return postAction('agregar_item', {
                    codigo: codigo,
                    id_tienda_FK: selTienda ? (selTienda.value || '') : ''
                });
            })
            .then(function (res) {
                actualizarResumen(res);
                var detalles = Array.isArray(res.estado && res.estado.detalles) ? res.estado.detalles : [];
                var ultima = detalles.length ? detalles[detalles.length - 1] : null;
                mostrarUltimo(ultima, 'success');
                if (window.JoyeriaPosScanFeedback) {
                    JoyeriaPosScanFeedback.success();
                }
                if (window.JoyeriaPosBarcodeScanner) {
                    JoyeriaPosBarcodeScanner.notifyScanResult('Agregado: ' + codigo, 'success');
                }
            })
            .catch(function (err) {
                var msg = err.message || 'Error al agregar producto.';
                mostrarUltimo({ codigo: codigo, descripcion: msg }, 'error');
                if (window.JoyeriaPosStockAlert && JoyeriaPosStockAlert.isStockError(err)) {
                    JoyeriaPosStockAlert.show(msg, { feedbackOnly: true, focusCodigo: false });
                } else if (window.JoyeriaPosScanFeedback) {
                    JoyeriaPosScanFeedback.error();
                }
                if (window.JoyeriaPosBarcodeScanner) {
                    JoyeriaPosBarcodeScanner.notifyScanResult(msg, 'error');
                }
            })
            .finally(function () {
                agregando = false;
            });
    }

    function iniciarEscaner() {
        if (!window.JoyeriaPosBarcodeScanner || !JoyeriaPosBarcodeScanner.isSupported()) {
            if (lastBox) {
                lastBox.className = 'pos-escaner-last is-error';
                lastBox.textContent = 'CÃ¡mara no disponible. Abre esta pÃ¡gina con HTTPS en tu telÃ©fono.';
            }
            return;
        }
        if (window.JoyeriaPosScanFeedback) {
            JoyeriaPosScanFeedback.prepare();
        }
        JoyeriaPosBarcodeScanner.openEmbedded({
            statusElementId: 'pos-scanner-status',
            onScan: function (codigo) {
                agregarPorCodigo(codigo);
            }
        }).catch(function (err) {
            if (lastBox) {
                lastBox.className = 'pos-escaner-last is-error';
                lastBox.textContent = (err && err.message) ? err.message : 'No se pudo iniciar la camara.';
            }
        });
    }

    var btnLimpiar = qs('btn_escaner_limpiar');
    if (btnLimpiar) {
        btnLimpiar.addEventListener('click', function () {
            var conteo = parseInt((qs('escaner_conteo').textContent || '0'), 10);
            if ((isFinite(conteo) && conteo > 0) && !window.confirm(
                'Â¿Descartar el ticket actual e iniciar una venta nueva? No se guardarÃ¡ lo escaneado.'
            )) {
                return;
            }
            postAction('limpiar', {})
                .then(function (res) {
                    if (!res.ok) {
                        throw new Error(res.mensaje || 'No se pudo limpiar el ticket.');
                    }
                    if (selCliente) {
                        selCliente.value = '';
                    }
                    if (selTienda) {
                        selTienda.value = '';
                    }
                    if (selImpuesto) {
                        var idImpPred = obtenerIdImpuestoPredeterminado();
                        if (idImpPred) {
                            selImpuesto.value = idImpPred;
                        }
                    }
                    actualizarResumen(res);
                    mostrarUltimo(null, '');
                    if (window.JoyeriaPosBarcodeScanner) {
                        JoyeriaPosBarcodeScanner.notifyScanResult('Listo para una nueva venta.', 'info');
                    }
                })
                .catch(function (err) {
                    mostrarUltimo({ codigo: '', descripcion: err.message || 'Error al limpiar.' }, 'error');
                    if (window.JoyeriaPosScanFeedback) {
                        JoyeriaPosScanFeedback.error();
                    }
                });
        });
    }

    window.addEventListener('pagehide', function () {
        if (window.JoyeriaPosBarcodeScanner) {
            JoyeriaPosBarcodeScanner.close();
        }
    });

    iniciarEscaner();
})();
</script>
