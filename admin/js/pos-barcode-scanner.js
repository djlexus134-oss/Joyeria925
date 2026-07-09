(function (global) {
    'use strict';

    var SCANNER_ELEMENT_ID = 'pos-scanner-reader';
    var COOLDOWN_MS = 1800;
    var html5QrCode = null;
    var scanning = false;
    var lastCode = '';
    var lastScanAt = 0;
    var onScanCallback = null;
    var onStatusCallback = null;
    var onCloseCallback = null;
    var modalEl = null;
    var statusEl = null;
    var embeddedMode = false;

    function isCameraSupported() {
        return !!(global.navigator && global.navigator.mediaDevices && global.navigator.mediaDevices.getUserMedia);
    }

    function isLibraryReady() {
        return typeof global.Html5Qrcode !== 'undefined'
            && typeof global.Html5QrcodeSupportedFormats !== 'undefined';
    }

    function normalizeBarcode(raw) {
        if (global.JoyeriaBarcodeInput && typeof global.JoyeriaBarcodeInput.normalizeScanCode === 'function') {
            return global.JoyeriaBarcodeInput.normalizeScanCode(raw);
        }
        var value = String(raw || '').trim();
        if (value === '') {
            return '';
        }
        if (/^\d+-\d+$/.test(value)) {
            return value.replace('-', '/');
        }
        if (/^\d[\d\s-]*\d$/.test(value) && value.indexOf('/') === -1) {
            return value.replace(/[\s-]/g, '');
        }
        return value;
    }

    function setStatus(message, kind) {
        if (!statusEl) {
            return;
        }
        statusEl.textContent = message || '';
        statusEl.className = 'pos-scanner-status' + (kind ? ' is-' + kind : '');
    }

    function ensureModal() {
        if (modalEl) {
            return modalEl;
        }

        modalEl = document.createElement('div');
        modalEl.id = 'pos-scanner-modal';
        modalEl.className = 'ja-modal-overlay pos-scanner-overlay';
        modalEl.style.display = 'none';
        modalEl.setAttribute('role', 'dialog');
        modalEl.setAttribute('aria-modal', 'true');
        modalEl.setAttribute('aria-labelledby', 'pos-scanner-title');
        modalEl.innerHTML = ''
            + '<div class="ja-modal-card pos-scanner-card">'
            + '  <div class="pos-scanner-header">'
            + '    <h3 id="pos-scanner-title"><i class="bi bi-camera"></i> Escanear etiqueta</h3>'
            + '    <button type="button" class="btn-action-secondary pos-scanner-close" aria-label="Cerrar escaner">'
            + '      <i class="bi bi-x-lg"></i>'
            + '    </button>'
            + '  </div>'
            + '  <p class="pos-scanner-hint">Apunta la camara al codigo de barras (CODE128 / EAN13) de la etiqueta. El producto se agregara automaticamente.</p>'
            + '  <div id="' + SCANNER_ELEMENT_ID + '" class="pos-scanner-viewport"></div>'
            + '  <div id="pos-scanner-last" class="pos-scanner-last" aria-live="polite">Aqui aparecera la ultima lectura registrada.</div>'
            + '  <p id="pos-scanner-status" class="pos-scanner-status">Iniciando camara...</p>'
            + '</div>';

        document.body.appendChild(modalEl);
        statusEl = modalEl.querySelector('#pos-scanner-status');

        modalEl.addEventListener('click', function (ev) {
            if (ev.target === modalEl) {
                close();
            }
        });
        var btnClose = modalEl.querySelector('.pos-scanner-close');
        if (btnClose) {
            btnClose.addEventListener('click', function () {
                close();
            });
        }

        if (!document.getElementById('pos-scanner-styles')) {
            var style = document.createElement('style');
            style.id = 'pos-scanner-styles';
            style.textContent = ''
                + '.ja-modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.45); display: flex; align-items: center; justify-content: center; z-index: 9999; }'
                + '.ja-modal-card { background: #fff; padding: 22px 24px; border-radius: 8px; box-shadow: 0 10px 40px rgba(0,0,0,0.25); font-family: "Arsenal", sans-serif; }'
                + '.pos-scanner-overlay { padding: 12px; }'
                + '.pos-scanner-card { width: min(560px, 96vw); max-height: 96vh; overflow: auto; }'
                + '.pos-scanner-header { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 8px; }'
                + '.pos-scanner-header h3 { margin: 0; font-size: 1.15rem; display: flex; align-items: center; gap: 8px; }'
                + '.pos-scanner-close { min-width: 36px; width: 36px; height: 36px; padding: 0; display: inline-flex; align-items: center; justify-content: center; }'
                + '.pos-scanner-hint { margin: 0 0 12px; color: #4a5568; font-size: 0.95rem; }'
                + '.pos-scanner-viewport { width: 100%; min-height: 220px; border-radius: 8px; overflow: hidden; background: #111; }'
                + '.pos-scanner-viewport video { border-radius: 8px; }'
                + '.pos-scanner-last { min-height: 2.6em; margin: 10px 0 0; padding: 10px 12px; border-radius: 8px; background: #f1f5f9; font-size: 0.92rem; line-height: 1.35; color: #334155; }'
                + '.pos-scanner-last.is-success { background: #ecfdf5; color: #14532d; }'
                + '.pos-scanner-last.is-error { background: #fef2f2; color: #991b1b; }'
                + '.pos-scanner-last.is-info { background: #eff6ff; color: #1e3a5f; }'
                + '.pos-scanner-status { margin: 10px 0 0; font-size: 0.92rem; color: #4a5568; }'
                + '.pos-scanner-status.is-success { color: #1f7a4d; }'
                + '.pos-scanner-status.is-error { color: #a33; }'
                + '.pos-scanner-status.is-info { color: #1f4b7a; }'
                + 'body.pos-escaner-only { overflow: hidden; }'
                + 'body.pos-escaner-only .admin-sidebar,'
                + 'body.pos-escaner-only .admin-header,'
                + 'body.pos-escaner-only .auth-flash-wrap { display: none !important; }'
                + 'body.pos-escaner-only .admin-layout { display: block; min-height: 100dvh; }'
                + 'body.pos-escaner-only .admin-content { margin: 0; padding: 0; max-width: none; }'
                + 'body.pos-escaner-only .admin-main { padding: 0; margin: 0; max-width: none; }'
                + '.pos-escaner-app { display: flex; flex-direction: column; min-height: 100dvh; background: #0f1720; color: #f8fafc; }'
                + '.pos-escaner-topbar { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 10px 12px; background: #162231; border-bottom: 1px solid rgba(255,255,255,0.08); }'
                + '.pos-escaner-topbar a, .pos-escaner-topbar button { color: inherit; text-decoration: none; }'
                + '.pos-escaner-stats { display: flex; gap: 14px; align-items: center; font-size: 0.95rem; }'
                + '.pos-escaner-stats strong { font-size: 1.05rem; }'
                + '.pos-escaner-viewport-wrap { flex: 1 1 auto; display: flex; flex-direction: column; justify-content: center; padding: 8px 10px 0; min-height: 0; }'
                + '.pos-escaner-viewport { flex: 1 1 auto; min-height: 48vh; max-height: 62vh; border-radius: 10px; overflow: hidden; background: #000; }'
                + '.pos-escaner-foot { padding: 10px 12px 16px; display: flex; flex-direction: column; gap: 10px; }'
                + '.pos-escaner-last { min-height: 2.6em; padding: 10px 12px; border-radius: 8px; background: rgba(255,255,255,0.06); font-size: 0.92rem; line-height: 1.35; }'
                + '.pos-escaner-last.is-success { background: rgba(31,122,77,0.22); }'
                + '.pos-escaner-last.is-error { background: rgba(170,51,51,0.22); }'
                + '.pos-escaner-actions { display: flex; gap: 8px; }'
                + '.pos-escaner-actions .btn-action-primary, .pos-escaner-actions .btn-action-secondary { flex: 1 1 50%; justify-content: center; }'
                + '.pos-escaner-foot .pos-scanner-status { color: #cbd5e1; }'
                + '.pos-escaner-foot .pos-scanner-status.is-success { color: #86efac; }'
                + '.pos-escaner-foot .pos-scanner-status.is-error { color: #fca5a5; }'
                + '.pos-escaner-foot .pos-scanner-status.is-info { color: #93c5fd; }'
                + '@media (max-width: 640px) {'
                + '  .pos-scanner-overlay { padding: 0; align-items: stretch; }'
                + '  .pos-scanner-card { width: 100%; max-height: 100vh; border-radius: 0; min-height: 100vh; }'
                + '  .pos-scanner-viewport { min-height: 42vh; }'
                + '}';
            document.head.appendChild(style);
        }

        return modalEl;
    }

    function isSecureCameraContext() {
        return !!(global.isSecureContext || (global.location && global.location.protocol === 'https:')
            || (global.location && (global.location.hostname === 'localhost' || global.location.hostname === '127.0.0.1')));
    }

    function isMobileDevice() {
        var ua = global.navigator ? String(global.navigator.userAgent || '') : '';
        if (/Android|iPhone|iPad|iPod|Mobile|webOS|BlackBerry|IEMobile|Opera Mini/i.test(ua)) {
            return true;
        }
        return !!(global.navigator && global.navigator.maxTouchPoints > 1 && global.innerWidth < 1024);
    }

    function supportedFormats() {
        if (typeof global.Html5QrcodeSupportedFormats === 'undefined') {
            return undefined;
        }
        var F = global.Html5QrcodeSupportedFormats;
        return [
            F.CODE_128,
            F.CODE_39,
            F.EAN_13,
            F.EAN_8,
            F.UPC_A,
            F.UPC_E,
            F.ITF,
            F.CODABAR,
            F.QR_CODE
        ].filter(function (v) { return typeof v !== 'undefined'; });
    }

    function scannerConfig() {
        var cfg = {
            fps: 12,
            qrbox: { width: 320, height: 130 },
            aspectRatio: 1.7777,
            disableFlip: false
        };
        var formats = supportedFormats();
        if (formats && formats.length) {
            cfg.formatsToSupport = formats;
        }
        return cfg;
    }

    function buildCameraConstraints() {
        var list = [];
        if (isMobileDevice()) {
            list.push({ facingMode: { ideal: 'environment' } });
            list.push({ facingMode: 'environment' });
        }
        list.push({ facingMode: { ideal: 'user' } });
        list.push({ facingMode: 'user' });
        return list;
    }

    function formatStartError(err) {
        var name = err && err.name ? String(err.name) : '';
        var message = err && err.message ? String(err.message) : 'Error desconocido';
        if (name === 'NotAllowedError' || name === 'PermissionDeniedError') {
            return 'Permiso de camara denegado. Permite el acceso en el navegador y vuelve a intentar.';
        }
        if (name === 'NotFoundError' || name === 'DevicesNotFoundError') {
            return 'No se detecto ninguna camara en este equipo.';
        }
        if (name === 'NotReadableError' || name === 'TrackStartError') {
            return 'La camara esta en uso por otra aplicacion. Cierra Zoom, Teams u otra app que la use.';
        }
        if (!isSecureCameraContext()) {
            return 'El navegador bloquea la camara sin HTTPS. Usa https:// o abre desde localhost.';
        }
        return 'No se pudo iniciar la camara (' + message + ').';
    }

    function waitForViewportReady() {
        return new Promise(function (resolve) {
            var attempts = 0;
            function check() {
                var el = document.getElementById(SCANNER_ELEMENT_ID);
                var ready = el && (el.offsetWidth > 0 || el.offsetHeight > 0);
                if (ready || attempts >= 12) {
                    resolve();
                    return;
                }
                attempts += 1;
                global.requestAnimationFrame(check);
            }
            check();
        });
    }

    function primeCameraPermission() {
        if (!global.navigator.mediaDevices || !global.navigator.mediaDevices.getUserMedia) {
            return Promise.resolve();
        }
        return global.navigator.mediaDevices.getUserMedia({ video: true })
            .then(function (stream) {
                (stream.getTracks() || []).forEach(function (track) {
                    track.stop();
                });
            })
            .catch(function () {
                // El fallo real se reportara al iniciar el escaner.
            });
    }

    function destroyScannerInstance(instance) {
        if (!instance) {
            return Promise.resolve();
        }
        return instance.stop()
            .then(function () {
                return instance.clear();
            })
            .catch(function () {
                try {
                    instance.clear();
                } catch (e) {
                    // Ignorar limpieza fallida.
                }
            });
    }

    function startWithConstraint(constraint) {
        var instance = new global.Html5Qrcode(SCANNER_ELEMENT_ID);
        return instance.start(
            constraint,
            scannerConfig(),
            function (decodedText) {
                handleScanSuccess(decodedText);
            },
            function () {
                // Sin lectura en este frame.
            }
        ).then(function () {
            return instance;
        }).catch(function (err) {
            return destroyScannerInstance(instance).then(function () {
                throw err;
            });
        });
    }

    function startWithDeviceId(deviceId) {
        var instance = new global.Html5Qrcode(SCANNER_ELEMENT_ID);
        return instance.start(
            deviceId,
            scannerConfig(),
            function (decodedText) {
                handleScanSuccess(decodedText);
            },
            function () {}
        ).then(function () {
            return instance;
        }).catch(function (err) {
            return destroyScannerInstance(instance).then(function () {
                throw err;
            });
        });
    }

    function startCamera() {
        if (!isLibraryReady()) {
            setStatus('No se pudo cargar el lector de codigos. Recarga la pagina.', 'error');
            return Promise.reject(new Error('Libreria de escaneo no disponible.'));
        }
        if (!isCameraSupported()) {
            setStatus('Tu navegador no permite acceso a la camara.', 'error');
            return Promise.reject(new Error('Camara no soportada.'));
        }
        if (!isSecureCameraContext()) {
            var insecureMsg = 'La camara requiere HTTPS o localhost. Abre el sitio con https:// o 127.0.0.1.';
            setStatus(insecureMsg, 'error');
            return Promise.reject(new Error(insecureMsg));
        }

        setStatus('Solicitando acceso a la camara...', 'info');

        return destroyScannerInstance(html5QrCode)
            .then(function () {
                html5QrCode = null;
                scanning = false;
                return waitForViewportReady();
            })
            .then(function () {
                return primeCameraPermission();
            })
            .then(function () {
                var constraints = buildCameraConstraints();
                var chain = Promise.reject(new Error('Sin camaras disponibles'));
                constraints.forEach(function (constraint) {
                    chain = chain.catch(function () {
                        return startWithConstraint(constraint);
                    });
                });
                chain = chain.catch(function () {
                    return global.Html5Qrcode.getCameras().then(function (devices) {
                        if (!devices || !devices.length) {
                            throw new Error('No hay camaras detectadas.');
                        }
                        var ordered = devices.slice();
                        if (isMobileDevice()) {
                            ordered.sort(function (a, b) {
                                var la = String(a.label || '').toLowerCase();
                                var lb = String(b.label || '').toLowerCase();
                                var aBack = la.indexOf('back') !== -1 || la.indexOf('trasera') !== -1 || la.indexOf('rear') !== -1;
                                var bBack = lb.indexOf('back') !== -1 || lb.indexOf('trasera') !== -1 || lb.indexOf('rear') !== -1;
                                if (aBack === bBack) return 0;
                                return aBack ? -1 : 1;
                            });
                        }
                        var deviceChain = Promise.reject(new Error('No se pudo abrir ninguna camara.'));
                        ordered.forEach(function (device) {
                            deviceChain = deviceChain.catch(function () {
                                return startWithDeviceId(device.id);
                            });
                        });
                        return deviceChain;
                    });
                });
                return chain;
            })
            .then(function (instance) {
                html5QrCode = instance;
                scanning = true;
                setStatus('Enfoca el codigo de barras dentro del recuadro.', 'info');
            })
            .catch(function (err) {
                scanning = false;
                html5QrCode = null;
                var msg = formatStartError(err);
                setStatus(msg, 'error');
                if (typeof onStatusCallback === 'function') {
                    onStatusCallback(msg, 'error');
                }
                throw err;
            });
    }

    function stopCamera() {
        if (!html5QrCode) {
            scanning = false;
            return Promise.resolve();
        }

        var instance = html5QrCode;
        html5QrCode = null;
        scanning = false;

        return destroyScannerInstance(instance);
    }

    function handleScanSuccess(decodedText) {
        var code = normalizeBarcode(decodedText);
        if (!code) {
            return;
        }

        var now = Date.now();
        if (code === lastCode && (now - lastScanAt) < COOLDOWN_MS) {
            return;
        }
        lastCode = code;
        lastScanAt = now;

        if (typeof onScanCallback === 'function') {
            onScanCallback(code);
        }
    }

    function open(options) {
        options = options || {};
        embeddedMode = false;
        onScanCallback = typeof options.onScan === 'function' ? options.onScan : null;
        onStatusCallback = typeof options.onStatus === 'function' ? options.onStatus : null;
        onCloseCallback = null;
        lastCode = '';
        lastScanAt = 0;

        ensureModal();
        modalEl.style.display = 'flex';
        setStatus('Iniciando camara...', 'info');

        return stopCamera()
            .then(function () {
                return startCamera();
            });
    }

    function openEmbedded(options) {
        options = options || {};
        embeddedMode = true;
        onScanCallback = typeof options.onScan === 'function' ? options.onScan : null;
        onStatusCallback = typeof options.onStatus === 'function' ? options.onStatus : null;
        onCloseCallback = typeof options.onClose === 'function' ? options.onClose : null;
        lastCode = '';
        lastScanAt = 0;

        if (options.statusElementId) {
            statusEl = document.getElementById(options.statusElementId);
        } else if (!statusEl) {
            statusEl = document.getElementById('pos-scanner-status');
        }

        if (!document.getElementById(SCANNER_ELEMENT_ID)) {
            return Promise.reject(new Error('No se encontro el visor del escaner.'));
        }

        setStatus('Iniciando camara...', 'info');
        return stopCamera()
            .then(function () {
                return startCamera();
            });
    }

    function close() {
        return stopCamera().then(function () {
            if (!embeddedMode && modalEl) {
                modalEl.style.display = 'none';
                var le = modalEl.querySelector('#pos-scanner-last');
                if (le) {
                    le.className = 'pos-scanner-last';
                    le.textContent = 'Aqui aparecera la ultima lectura registrada.';
                }
            }
            setStatus('', '');
            onScanCallback = null;
            onStatusCallback = null;
            if (typeof onCloseCallback === 'function') {
                onCloseCallback();
            }
            onCloseCallback = null;
            embeddedMode = false;
        });
    }

    function notifyScanResult(message, kind) {
        setStatus(message, kind || 'info');
    }

    function notifyLegend(message, kind) {
        ensureModal();
        var le = modalEl ? modalEl.querySelector('#pos-scanner-last') : null;
        if (!le) {
            return;
        }
        le.textContent = message || 'Aqui aparecera la ultima lectura registrada.';
        le.className = 'pos-scanner-last' + (kind ? ' is-' + kind : '');
    }

    global.JoyeriaPosBarcodeScanner = {
        open: open,
        openEmbedded: openEmbedded,
        close: close,
        notifyScanResult: notifyScanResult,
        notifyLegend: notifyLegend,
        isEmbedded: function () {
            return embeddedMode;
        },
        isSupported: function () {
            return isSecureCameraContext() && isCameraSupported() && isLibraryReady();
        },
        normalizeBarcode: normalizeBarcode
    };
})(window);
