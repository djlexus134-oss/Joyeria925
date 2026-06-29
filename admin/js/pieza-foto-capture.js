/**
 * Captura y compresion de fotos para piezas (imagen principal y adicionales).
 * Uso: JoyeriaPiezaFotoCapture.initFromDom();
 */
(function (global) {
    'use strict';

    var MODAL_ID = 'joyeria-pieza-foto-modal';
    var MAX_EDGE = 1600;
    var JPEG_QUALITY = 0.82;
    var COMPRESS_THRESHOLD = 800 * 1024;
    var styleInjected = false;

    function ensureStyles() {
        if (styleInjected || document.getElementById('joyeria-pieza-foto-styles')) {
            styleInjected = true;
            return;
        }
        var s = document.createElement('style');
        s.id = 'joyeria-pieza-foto-styles';
        s.textContent = [
            '#' + MODAL_ID + '{position:fixed;inset:0;z-index:2000;background:rgba(0,0,0,.88);display:flex;align-items:center;justify-content:center;padding:12px;}',
            '#' + MODAL_ID + ' .jpf-inner{max-width:640px;width:100%;background:#111;color:#eee;border-radius:8px;padding:12px;}',
            '#' + MODAL_ID + ' video{width:100%;max-height:60vh;background:#000;border-radius:4px;}',
            '#' + MODAL_ID + ' .jpf-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px;justify-content:flex-end;}',
            '#' + MODAL_ID + ' .jpf-hint{font-size:13px;margin:8px 0;color:#ccc;}',
            '.pieza-foto-actions{display:flex;flex-wrap:wrap;gap:8px;margin-top:8px;align-items:center;}',
            '.pieza-foto-preview{margin-top:10px;}',
            '.pieza-foto-preview img{max-width:160px;max-height:160px;object-fit:cover;border-radius:8px;border:1px solid #ddd;display:block;}',
            '.pieza-foto-preview-status{font-size:13px;color:#2e7d32;margin-top:6px;}',
            '.pieza-foto-preview-status.is-empty{color:#666;}'
        ].join('');
        document.head.appendChild(s);
        styleInjected = true;
    }

    function openFilePicker(input) {
        if (!input) {
            return;
        }
        input.removeAttribute('capture');
        try {
            input.click();
        } catch (e) {
            window.alert('No se pudo abrir el selector de archivos.');
        }
    }

    function bindFileSourceButtons(input, btnArchivos, btnDrive) {
        if (btnArchivos) {
            btnArchivos.addEventListener('click', function () {
                openFilePicker(input);
            });
        }
        if (btnDrive) {
            btnDrive.addEventListener('click', function () {
                openFilePicker(input);
            });
        }
    }

    function assignFileToInput(input, file) {
        if (!input || !file) {
            return;
        }
        try {
            var dt = new DataTransfer();
            dt.items.add(file);
            input.files = dt.files;
        } catch (e) {
            window.alert('No se pudo asignar la imagen al formulario. Prueba otro navegador.');
        }
    }

    function loadImageFromFile(file) {
        return new Promise(function (resolve, reject) {
            var url = URL.createObjectURL(file);
            var img = new Image();
            img.onload = function () {
                URL.revokeObjectURL(url);
                resolve(img);
            };
            img.onerror = function () {
                URL.revokeObjectURL(url);
                reject(new Error('No se pudo leer la imagen.'));
            };
            img.src = url;
        });
    }

    function canvasToJpegBlob(canvas, quality) {
        return new Promise(function (resolve, reject) {
            canvas.toBlob(function (blob) {
                if (!blob) {
                    reject(new Error('No se pudo generar la imagen.'));
                    return;
                }
                resolve(blob);
            }, 'image/jpeg', quality);
        });
    }

    function compressImageFile(file) {
        if (!file || !file.type || file.type.indexOf('image/') !== 0) {
            return Promise.resolve(file);
        }
        if (file.size <= COMPRESS_THRESHOLD && file.type === 'image/jpeg') {
            return Promise.resolve(file);
        }

        return loadImageFromFile(file).then(function (img) {
            var w = img.naturalWidth || img.width;
            var h = img.naturalHeight || img.height;
            if (!w || !h) {
                return file;
            }

            var scale = 1;
            if (w > MAX_EDGE || h > MAX_EDGE) {
                scale = Math.min(MAX_EDGE / w, MAX_EDGE / h);
            }

            var cw = Math.max(1, Math.round(w * scale));
            var ch = Math.max(1, Math.round(h * scale));
            var canvas = document.createElement('canvas');
            canvas.width = cw;
            canvas.height = ch;
            var ctx = canvas.getContext('2d');
            ctx.drawImage(img, 0, 0, cw, ch);

            return canvasToJpegBlob(canvas, JPEG_QUALITY).then(function (blob) {
                var baseName = (file.name || 'pieza').replace(/\.[^.]+$/, '');
                var name = baseName + '.jpg';
                return new File([blob], name, { type: 'image/jpeg', lastModified: Date.now() });
            });
        }).catch(function () {
            return file;
        });
    }

    function updatePreview(previewWrap, file) {
        if (!previewWrap) {
            return;
        }
        var imgEl = previewWrap.querySelector('img');
        var statusEl = previewWrap.querySelector('.pieza-foto-preview-status');
        if (!imgEl) {
            imgEl = document.createElement('img');
            imgEl.alt = 'Vista previa';
            previewWrap.appendChild(imgEl);
        }
        if (!statusEl) {
            statusEl = document.createElement('p');
            statusEl.className = 'pieza-foto-preview-status';
            previewWrap.appendChild(statusEl);
        }

        if (previewWrap._objectUrl) {
            URL.revokeObjectURL(previewWrap._objectUrl);
            previewWrap._objectUrl = null;
        }

        if (!file) {
            imgEl.style.display = 'none';
            statusEl.textContent = 'Sin imagen nueva seleccionada.';
            statusEl.classList.add('is-empty');
            return;
        }

        previewWrap._objectUrl = URL.createObjectURL(file);
        imgEl.src = previewWrap._objectUrl;
        imgEl.style.display = 'block';
        var kb = Math.round(file.size / 1024);
        statusEl.textContent = 'Lista para guardar (' + kb + ' KB).';
        statusEl.classList.remove('is-empty');
    }

    function openCameraModal(onCapture, onError) {
        ensureStyles();
        var old = document.getElementById(MODAL_ID);
        if (old) {
            old.remove();
        }

        var wrap = document.createElement('div');
        wrap.id = MODAL_ID;
        wrap.innerHTML =
            '<div class="jpf-inner">' +
            '<p class="jpf-hint">Encuadra la pieza. Requiere HTTPS y permiso de camara (camara trasera en movil).</p>' +
            '<video id="jpf-video" playsinline muted autoplay></video>' +
            '<div class="jpf-actions">' +
            '<button type="button" class="btn-action-primary" id="jpf-btn-capture">Usar esta foto</button>' +
            '<button type="button" class="btn-action-danger" id="jpf-btn-close">Cerrar</button>' +
            '</div></div>';

        document.body.appendChild(wrap);

        var video = wrap.querySelector('#jpf-video');
        var stream = null;
        var closed = false;

        function cleanup() {
            if (closed) {
                return;
            }
            closed = true;
            if (stream && stream.getTracks) {
                stream.getTracks().forEach(function (t) { t.stop(); });
                stream = null;
            }
            if (video) {
                video.srcObject = null;
            }
            if (wrap.parentNode) {
                wrap.parentNode.removeChild(wrap);
            }
        }

        wrap.querySelector('#jpf-btn-close').addEventListener('click', cleanup);

        wrap.querySelector('#jpf-btn-capture').addEventListener('click', function () {
            if (!video.videoWidth || !video.videoHeight) {
                onError('La camara aun no esta lista. Espera un momento e intenta de nuevo.');
                return;
            }
            var w = video.videoWidth;
            var h = video.videoHeight;
            var scale = 1;
            if (w > MAX_EDGE || h > MAX_EDGE) {
                scale = Math.min(MAX_EDGE / w, MAX_EDGE / h);
            }
            var cw = Math.max(1, Math.round(w * scale));
            var ch = Math.max(1, Math.round(h * scale));
            var canvas = document.createElement('canvas');
            canvas.width = cw;
            canvas.height = ch;
            canvas.getContext('2d').drawImage(video, 0, 0, cw, ch);

            canvasToJpegBlob(canvas, JPEG_QUALITY).then(function (blob) {
                var name = 'pieza-' + Date.now() + '.jpg';
                var file = new File([blob], name, { type: 'image/jpeg', lastModified: Date.now() });
                cleanup();
                onCapture(file);
            }).catch(function (err) {
                onError(err && err.message ? err.message : 'No se pudo capturar la foto.');
            });
        });

        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            onError('Tu navegador no permite acceso a la camara desde aqui. Usa el boton de subir archivo.');
            cleanup();
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
        }).catch(function (err) {
            onError(err && err.message ? err.message : 'Permiso de camara denegado o no disponible.');
            cleanup();
        });
    }

    function bindField(config) {
        var input = config.input;
        var previewWrap = config.preview;
        var btnCamera = config.btnCamera;
        var btnArchivos = config.btnArchivos;
        var btnDrive = config.btnDrive;
        var btnClear = config.btnClear;
        var pendingFile = null;

        if (!input) {
            return;
        }

        function applyFile(file) {
            pendingFile = file;
            if (file) {
                assignFileToInput(input, file);
                updatePreview(previewWrap, file);
            } else {
                input.value = '';
                pendingFile = null;
                updatePreview(previewWrap, null);
            }
        }

        function processSelectedFile(file) {
            if (!file) {
                return;
            }
            compressImageFile(file).then(function (compressed) {
                applyFile(compressed);
            });
        }

        input.addEventListener('change', function () {
            var file = input.files && input.files[0] ? input.files[0] : null;
            if (file) {
                processSelectedFile(file);
            }
        });

        if (btnCamera) {
            btnCamera.addEventListener('click', function () {
                openCameraModal(processSelectedFile, function (msg) {
                    window.alert(msg);
                });
            });
        }

        bindFileSourceButtons(input, btnArchivos, btnDrive);

        if (btnClear) {
            btnClear.addEventListener('click', function () {
                applyFile(null);
            });
        }

        return {
            hasPendingFile: function () {
                return !!(pendingFile || (input.files && input.files.length > 0));
            },
            getInput: function () {
                return input;
            }
        };
    }

    function initFromDom() {
        var root = document.querySelector('[data-pieza-foto-capture]');
        if (!root) {
            return;
        }

        var form = root.closest('form');
        var principalInput = root.querySelector('#imagen_principal');
        var principalPreview = root.querySelector('#pieza-foto-preview-principal');
        var btnCameraPrincipal = root.querySelector('[data-pieza-foto-camera="principal"]');
        var btnArchivosPrincipal = root.querySelector('[data-pieza-foto-archivos="principal"]');
        var btnDrivePrincipal = root.querySelector('[data-pieza-foto-drive="principal"]');
        var btnClearPrincipal = root.querySelector('[data-pieza-foto-clear="principal"]');

        var principalField = bindField({
            input: principalInput,
            preview: principalPreview,
            btnCamera: btnCameraPrincipal,
            btnArchivos: btnArchivosPrincipal,
            btnDrive: btnDrivePrincipal,
            btnClear: btnClearPrincipal
        });

        var adicionalRoot = root.querySelector('[data-pieza-foto-adicionales]');
        var adicionalInput = adicionalRoot ? adicionalRoot.querySelector('#imagenes_adicionales') : null;
        var adicionalPreview = adicionalRoot ? adicionalRoot.querySelector('#pieza-foto-preview-adicionales') : null;
        var btnCameraAdicional = adicionalRoot ? adicionalRoot.querySelector('[data-pieza-foto-camera="adicionales"]') : null;
        var btnArchivosAdicional = adicionalRoot ? adicionalRoot.querySelector('[data-pieza-foto-archivos="adicionales"]') : null;
        var btnDriveAdicional = adicionalRoot ? adicionalRoot.querySelector('[data-pieza-foto-drive="adicionales"]') : null;

        if (adicionalInput) {
            bindFileSourceButtons(adicionalInput, btnArchivosAdicional, btnDriveAdicional);
            adicionalInput.addEventListener('change', function () {
                var files = adicionalInput.files;
                if (!files || !files.length) {
                    return;
                }
                var promises = [];
                for (var i = 0; i < files.length; i++) {
                    promises.push(compressImageFile(files[i]));
                }
                Promise.all(promises).then(function (compressedList) {
                    try {
                        var dt = new DataTransfer();
                        compressedList.forEach(function (f) {
                            if (f) {
                                dt.items.add(f);
                            }
                        });
                        adicionalInput.files = dt.files;
                        if (adicionalPreview) {
                            var statusEl = adicionalPreview.querySelector('.pieza-foto-preview-status');
                            if (!statusEl) {
                                statusEl = document.createElement('p');
                                statusEl.className = 'pieza-foto-preview-status';
                                adicionalPreview.appendChild(statusEl);
                            }
                            statusEl.textContent = compressedList.length + ' imagen(es) lista(s) para guardar.';
                            statusEl.classList.remove('is-empty');
                        }
                    } catch (e) {
                        /* mantener archivos originales */
                    }
                });
            });

            if (btnCameraAdicional) {
                btnCameraAdicional.addEventListener('click', function () {
                    openCameraModal(function (file) {
                        try {
                            var dt = new DataTransfer();
                            if (adicionalInput.files) {
                                for (var j = 0; j < adicionalInput.files.length; j++) {
                                    dt.items.add(adicionalInput.files[j]);
                                }
                            }
                            dt.items.add(file);
                            adicionalInput.files = dt.files;
                            if (adicionalPreview) {
                                var st = adicionalPreview.querySelector('.pieza-foto-preview-status');
                                if (!st) {
                                    st = document.createElement('p');
                                    st.className = 'pieza-foto-preview-status';
                                    adicionalPreview.appendChild(st);
                                }
                                st.textContent = adicionalInput.files.length + ' imagen(es) lista(s) para guardar.';
                                st.classList.remove('is-empty');
                            }
                        } catch (err) {
                            window.alert('No se pudo agregar la foto a la galeria.');
                        }
                    }, function (msg) {
                        window.alert(msg);
                    });
                });
            }
        }

        if (form && principalField) {
            form.addEventListener('submit', function (e) {
                var adicionalHas = adicionalInput && adicionalInput.files && adicionalInput.files.length > 0;
                var principalHas = principalField.hasPendingFile();
                if (!principalHas && !adicionalHas) {
                    return;
                }
            });
        }
    }

    global.JoyeriaPiezaFotoCapture = {
        initFromDom: initFromDom,
        compressImageFile: compressImageFile
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initFromDom);
    } else {
        initFromDom();
    }
}(typeof window !== 'undefined' ? window : this));
