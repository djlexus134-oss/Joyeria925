/**
 * Escaneo de codigo con camara: BarcodeDetector (Chrome) o html5-qrcode como respaldo.
 * Uso: JoyeriaBarcodeCamera.openModal({ onCode: function (text) {}, onError: function (msg) {} });
 */
(function (global) {
    'use strict';

    var MODAL_ID = 'joyeria-barcode-camera-modal';
    var styleInjected = false;

    function ensureStyles() {
        if (styleInjected || document.getElementById('joyeria-barcode-camera-styles')) {
            styleInjected = true;
            return;
        }
        var s = document.createElement('style');
        s.id = 'joyeria-barcode-camera-styles';
        s.textContent = [
            '#' + MODAL_ID + '{position:fixed;inset:0;z-index:2000;background:rgba(0,0,0,.85);display:flex;flex-direction:column;align-items:center;justify-content:center;padding:12px;}',
            '#' + MODAL_ID + ' .jbc-inner{max-width:640px;width:100%;background:#111;color:#eee;border-radius:8px;padding:12px;}',
            '#' + MODAL_ID + ' video{width:100%;max-height:55vh;background:#000;border-radius:4px;}',
            '#' + MODAL_ID + ' .jbc-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px;justify-content:flex-end;}',
            '#' + MODAL_ID + ' .jbc-hint{font-size:13px;margin:8px 0;color:#ccc;}',
            '#' + MODAL_ID + ' #jbc-reader{margin-top:8px;min-height:200px;}'
        ].join('');
        document.head.appendChild(s);
        styleInjected = true;
    }

    function loadScriptOnce(src, globalName) {
        return new Promise(function (resolve, reject) {
            if (globalName && global[globalName]) {
                resolve(global[globalName]);
                return;
            }
            var existing = document.querySelector('script[data-joyeria-bc="' + src + '"]');
            if (existing) {
                existing.addEventListener('load', function () { resolve(global[globalName]); });
                existing.addEventListener('error', reject);
                return;
            }
            var sc = document.createElement('script');
            sc.src = src;
            sc.async = true;
            sc.setAttribute('data-joyeria-bc', src);
            sc.onload = function () { resolve(global[globalName]); };
            sc.onerror = function () { reject(new Error('No se pudo cargar ' + src)); };
            document.head.appendChild(sc);
        });
    }

    function openModal(opts) {
        var onCode = typeof opts.onCode === 'function' ? opts.onCode : function () {};
        var onError = typeof opts.onError === 'function' ? opts.onError : function (m) { window.alert(m); };
        var onCancel = typeof opts.onCancel === 'function' ? opts.onCancel : function () {};

        ensureStyles();
        var old = document.getElementById(MODAL_ID);
        if (old) {
            old.remove();
        }

        var wrap = document.createElement('div');
        wrap.id = MODAL_ID;
        wrap.innerHTML =
            '<div class="jbc-inner">' +
            '<p class="jbc-hint">Apunta la camara al codigo. Requiere HTTPS y permiso de camara (usa la camara trasera en movil).</p>' +
            '<video id="jbc-video" playsinline muted autoplay></video>' +
            '<div id="jbc-reader" style="display:none;"></div>' +
            '<div class="jbc-actions">' +
            '<button type="button" class="btn-action-primary" id="jbc-btn-fallback">Modo alternativo</button>' +
            '<button type="button" class="btn-action-danger" id="jbc-btn-close">Cerrar</button>' +
            '</div></div>';

        document.body.appendChild(wrap);

        var video = wrap.querySelector('#jbc-video');
        var readerEl = wrap.querySelector('#jbc-reader');
        var stream = null;
        var rafId = null;
        var timeoutId = null;
        var html5Scanner = null;
        var closed = false;

        function cleanup() {
            if (closed) {
                return;
            }
            closed = true;
            if (rafId) {
                cancelAnimationFrame(rafId);
                rafId = null;
            }
            if (timeoutId) {
                clearTimeout(timeoutId);
                timeoutId = null;
            }
            if (stream && stream.getTracks) {
                stream.getTracks().forEach(function (t) { t.stop(); });
                stream = null;
            }
            if (video) {
                video.srcObject = null;
            }
            if (html5Scanner) {
                try {
                    html5Scanner.stop().catch(function () {});
                } catch (e) { /* noop */ }
                html5Scanner = null;
            }
            if (wrap.parentNode) {
                wrap.parentNode.removeChild(wrap);
            }
        }

        function finishOk(text) {
            if (closed) {
                return;
            }
            cleanup();
            onCode(String(text || '').trim());
        }

        wrap.querySelector('#jbc-btn-close').addEventListener('click', function () {
            cleanup();
            onCancel();
        });

        function startBarcodeDetectorLoop() {
            if (!('BarcodeDetector' in global)) {
                return false;
            }
            var formats = ['code_128', 'code_39', 'ean_13', 'ean_8', 'upc_a', 'upc_e', 'itf', 'qr_code'];
            var detector;
            try {
                detector = new BarcodeDetector({ formats: formats });
            } catch (e) {
                return false;
            }

            function tick() {
                if (closed || !video.videoWidth) {
                    rafId = requestAnimationFrame(tick);
                    return;
                }
                detector.detect(video).then(function (codes) {
                    if (codes && codes.length > 0 && codes[0].rawValue) {
                        finishOk(codes[0].rawValue);
                        return;
                    }
                    rafId = requestAnimationFrame(tick);
                }).catch(function () {
                    rafId = requestAnimationFrame(tick);
                });
            }
            rafId = requestAnimationFrame(tick);
            return true;
        }

        function startHtml5Fallback() {
            readerEl.style.display = 'block';
            video.style.display = 'none';
            loadScriptOnce(
                'https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js',
                'Html5Qrcode'
            ).then(function (Html5Qrcode) {
                if (closed || !Html5Qrcode) {
                    return;
                }
                html5Scanner = new Html5Qrcode('jbc-reader');
                var cfg = { fps: 8, qrbox: { width: 280, height: 160 }, aspectRatio: 1.777 };
                html5Scanner.start(
                    { facingMode: 'environment' },
                    cfg,
                    function (decodedText) {
                        finishOk(decodedText);
                    },
                    function () {}
                ).catch(function (err) {
                    onError(err && err.message ? err.message : 'No se pudo iniciar el escaner alternativo.');
                });
            }).catch(function (err) {
                onError(err && err.message ? err.message : 'No se pudo cargar el escaner alternativo.');
            });
        }

        wrap.querySelector('#jbc-btn-fallback').addEventListener('click', function () {
            if (timeoutId) {
                clearTimeout(timeoutId);
                timeoutId = null;
            }
            if (rafId) {
                cancelAnimationFrame(rafId);
                rafId = null;
            }
            if (stream && stream.getTracks) {
                stream.getTracks().forEach(function (t) { t.stop(); });
                stream = null;
            }
            if (video) {
                video.srcObject = null;
            }
            startHtml5Fallback();
        });

        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            onError('Tu navegador no permite acceso a la camara desde aqui.');
            cleanup();
            onCancel();
            return;
        }

        navigator.mediaDevices.getUserMedia({
            video: { facingMode: { ideal: 'environment' }, width: { ideal: 1280 } },
            audio: false
        }).then(function (s) {
            if (closed) {
                s.getTracks().forEach(function (t) { t.stop(); });
                return;
            }
            stream = s;
            video.srcObject = stream;
            return video.play();
        }).then(function () {
            if (closed) {
                return;
            }
            if (startBarcodeDetectorLoop()) {
                return;
            }
            timeoutId = setTimeout(function () {
                timeoutId = null;
                if (!closed) {
                    startHtml5Fallback();
                }
            }, 500);
        }).catch(function (err) {
            onError(err && err.message ? err.message : 'Permiso de camara denegado o no disponible.');
            cleanup();
            onCancel();
        });
    }

    global.JoyeriaBarcodeCamera = {
        openModal: openModal
    };
}(typeof window !== 'undefined' ? window : this));
