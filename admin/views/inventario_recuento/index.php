<div class="admin-modules">

    <?php if (!empty($error)): ?>
        <div class="alert-message error">
            <p><i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars((string) $error); ?></p>
        </div>
    <?php endif; ?>

    <?php if (!empty($mensaje)): ?>
        <div class="alert-message success">
            <p><i class="bi bi-check-circle"></i> <?php echo htmlspecialchars((string) $mensaje); ?></p>
        </div>
    <?php endif; ?>

    <?php if ($idEmpleado === null): ?>
        <div class="alert-message error">
            <p><i class="bi bi-person-x"></i> Tu usuario del panel no está vinculado a un <strong>empleado activo</strong>.
                Los recuentos quedan registrados en <code>auditorias_inventario</code> por empleado. Solicita el alta en <a href="empleado.php?accion=leer">Empleados</a>.</p>
        </div>
    <?php endif; ?>


    <?php if ($idEmpleado !== null && ($pasoVista === '' || $pasoVista === null) && ($auditoriaVista === null || $metaVista === null)): ?>
        <section class="admin-card">
            <h3>Iniciar recuento</h3>
            <p class="form-hint">Se comparará el stock <strong>disponible</strong> de la tienda elegida contra los códigos que captures (código de barras, auxiliar o ID de stock). Opcionalmente limita el recuento a una <strong>familia</strong> de productos.</p>
            <form method="post" action="inventario_recuento.php?accion=crear" class="form-stack">
                <div>
                    <label for="id_tienda"><i class="bi bi-shop"></i> Tienda</label>
                    <select class="form-input" name="id_tienda" id="id_tienda" required>
                        <option value="">— Selecciona —</option>
                        <?php foreach ($tiendasActivas as $t): ?>
                            <option value="<?php echo (int) $t['id_tienda']; ?>">
                                <?php echo htmlspecialchars((string) ($t['nom_tienda'] ?? '')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="id_familia"><i class="bi bi-diagram-3"></i> Familia (filtro)</label>
                    <select class="form-input" name="id_familia" id="id_familia">
                        <option value="0">— Todas las familias —</option>
                        <?php foreach ($familiasActivas as $f): ?>
                            <option value="<?php echo (int) $f['id_familia']; ?>">
                                <?php echo htmlspecialchars((string) ($f['nom_familia'] ?? '')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="form-hint" style="margin-top: 0.35rem;">Solo se contarán piezas cuya subfamilia pertenezca a la familia elegida.</p>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn-action-primary"><i class="bi bi-play-fill"></i> Iniciar recuento</button>
                </div>
            </form>
        </section>
    <?php endif; ?>

    <?php if ($idEmpleado !== null && $pasoVista === 'captura' && $auditoriaVista !== null && $metaVista !== null): ?>
        <style>
            .recuento-scan-input {
                font-size: 1.35rem;
                letter-spacing: 0.03em;
                padding: 0.65rem 0.85rem;
            }
            #recuento-scan-feedback {
                margin-top: 0.5rem;
                min-height: 1.5rem;
                font-size: 0.95rem;
            }
            #recuento-scan-feedback.is-ok { color: var(--color-success, #198754); }
            #recuento-scan-feedback.is-err { color: var(--color-danger, #dc3545); }
        </style>
        <section class="admin-card">
            <h3>Captura de códigos</h3>
            <p>
                <strong>Tienda:</strong> <?php echo htmlspecialchars($nomTiendaVista !== '' ? $nomTiendaVista : ('#' . (string) $idTiendaVista)); ?>
                &nbsp;|&nbsp;
                <strong>Familia:</strong>
                <?php if ($idFamiliaVista > 0): ?>
                    <?php echo htmlspecialchars($nomFamiliaVista); ?>
                <?php else: ?>
                    Todas
                <?php endif; ?>
                &nbsp;|&nbsp;
                <strong>Auditoría #<?php echo (int) $idAuditoriaVista; ?></strong>
                &nbsp;|&nbsp;
                <span class="badge badge-info" id="recuento-badge-contador">Contadas: <?php echo (int) $contadosVista; ?> / <?php echo (int) $esperadosVista; ?></span>
            </p>
            <p class="form-hint"><i class="bi bi-upc-scan"></i> Pistola USB: escribe en el campo y Enter. <i class="bi bi-camera"></i> Camara: boton <strong>Escanear</strong> (mismo lector que en Punto de venta).</p>
            <form method="post" action="inventario_recuento.php?accion=actualizar" class="form-stack" id="form-recuento-scan" autocomplete="off">
                <div>
                    <label for="recuento-codigo">Código</label>
                    <div style="display:flex;gap:10px;align-items:stretch;flex-wrap:wrap;">
                        <input class="form-input recuento-scan-input joyeria-barcode-input" type="text" name="codigo" id="recuento-codigo"
                               style="flex:1 1 220px;min-width:0;"
                               autocomplete="off"
                               placeholder="Escanear, camara o escribir (ej. 28488/97)…">
                        <button type="button" class="btn-action-secondary" id="recuento-btn-camara" title="Escanear con cámara" style="white-space:nowrap;">
                            <i class="bi bi-camera"></i> Escanear
                        </button>
                    </div>
                    <div id="recuento-scan-feedback" aria-live="polite" role="status"></div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn-action-primary" id="recuento-btn-agregar"><i class="bi bi-plus-lg"></i> Agregar</button>
                </div>
            </form>
            <div class="form-actions" style="margin-top: 0.75rem;">
                <form method="post" action="inventario_recuento.php?accion=finalizar" style="display: inline;">
                    <button type="submit" class="btn-action-secondary"
                            onclick="return confirm('¿Finalizar el recuento y ver faltantes?');">
                        <i class="bi bi-flag-fill"></i> Finalizar
                    </button>
                </form>
                <a href="inventario_recuento.php?accion=cancelar" class="btn-action-danger"
                   onclick="return confirm('¿Cancelar este recuento? La auditoría se cerrará sin resultado de faltantes.');">
                    <i class="bi bi-x-lg"></i> Cancelar
                </a>
            </div>
        </section>
        <script src="js/pos-scan-feedback.js"></script>
        <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
        <script src="js/pos-barcode-scanner.js"></script>
        <script>
        (function () {
            var form = document.getElementById('form-recuento-scan');
            var input = document.getElementById('recuento-codigo');
            var feedback = document.getElementById('recuento-scan-feedback');
            var badge = document.getElementById('recuento-badge-contador');
            var btnCamara = document.getElementById('recuento-btn-camara');
            if (!form || !input || !feedback) return;

            var busy = false;
            var scanUrl = 'inventario_recuento.php?accion=actualizar';

            function setFeedback(ok, text) {
                feedback.textContent = text || '';
                feedback.classList.remove('is-ok', 'is-err');
                if (text) {
                    feedback.classList.add(ok ? 'is-ok' : 'is-err');
                }
            }

            function beepOk() {
                try {
                    var ctx = new (window.AudioContext || window.webkitAudioContext)();
                    var o = ctx.createOscillator();
                    var g = ctx.createGain();
                    o.connect(g);
                    g.connect(ctx.destination);
                    o.frequency.value = 880;
                    g.gain.setValueAtTime(0.08, ctx.currentTime);
                    g.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.08);
                    o.start(ctx.currentTime);
                    o.stop(ctx.currentTime + 0.09);
                } catch (e) { /* sin audio */ }
            }

            function enviarCodigo(desdeCamara) {
                desdeCamara = !!desdeCamara;
                if (busy) return;
                var raw = input.value;
                if (!raw || !String(raw).trim()) {
                    setFeedback(false, 'Escribe o escanea un código primero.');
                    if (!desdeCamara) {
                        input.focus();
                    }
                    return;
                }
                busy = true;
                setFeedback(true, 'Registrando…');
                var fd = new FormData(form);
                fd.set('codigo', raw);
                fd.set('ajax_recuento', '1');

                fetch(scanUrl, {
                    method: 'POST',
                    body: fd,
                    credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                    .then(function (r) { return r.json().catch(function () { return { ok: false, error: 'Respuesta no válida del servidor.' }; }); })
                    .then(function (data) {
                        busy = false;
                        if (data.ok) {
                            if (badge && typeof data.contados === 'number' && typeof data.esperados === 'number') {
                                badge.textContent = 'Contadas: ' + data.contados + ' / ' + data.esperados;
                            }
                            var line = data.mensaje || 'Registrado.';
                            if (data.codigo_auxiliar) {
                                line += ' · ' + data.codigo_auxiliar;
                            }
                            setFeedback(true, line);
                            if (desdeCamara && window.JoyeriaPosScanFeedback) {
                                JoyeriaPosScanFeedback.success();
                            } else {
                                beepOk();
                            }
                            if (desdeCamara && window.JoyeriaPosBarcodeScanner) {
                                JoyeriaPosBarcodeScanner.notifyScanResult(
                                    'Agregado. Enfoca la siguiente etiqueta.',
                                    'success'
                                );
                            }
                            input.value = '';
                            if (!desdeCamara) {
                                input.focus();
                            }
                        } else {
                            setFeedback(false, data.error || 'No se pudo registrar.');
                            if (desdeCamara && window.JoyeriaPosScanFeedback) {
                                JoyeriaPosScanFeedback.error();
                            }
                            if (desdeCamara && window.JoyeriaPosBarcodeScanner) {
                                JoyeriaPosBarcodeScanner.notifyScanResult(data.error || 'Error', 'error');
                            }
                            if (!desdeCamara) {
                                input.focus();
                                input.select();
                            }
                        }
                    })
                    .catch(function () {
                        busy = false;
                        setFeedback(false, 'Error de red. Revisa la conexión e intenta de nuevo.');
                        if (desdeCamara && window.JoyeriaPosScanFeedback) {
                            JoyeriaPosScanFeedback.error();
                        }
                        if (desdeCamara && window.JoyeriaPosBarcodeScanner) {
                            JoyeriaPosBarcodeScanner.notifyScanResult('Error de red.', 'error');
                        }
                        if (!desdeCamara) {
                            input.focus();
                        }
                    });
            }

            form.addEventListener('submit', function (e) {
                e.preventDefault();
                enviarCodigo(false);
            });

            if (btnCamara) {
                btnCamara.addEventListener('click', function () {
                    if (!window.JoyeriaPosBarcodeScanner) {
                        alert('El escáner no está cargado. Espera un momento y vuelve a intentar, o recarga la pagina.');
                        return;
                    }
                    if (!JoyeriaPosBarcodeScanner.isSupported()) {
                        alert('Tu navegador no puede usar la cámara aquí. Prueba Chrome/Edge en localhost o con HTTPS.');
                        return;
                    }
                    if (window.JoyeriaPosScanFeedback) {
                        JoyeriaPosScanFeedback.prepare();
                    }
                    JoyeriaPosBarcodeScanner.open({
                        onScan: function (codigo) {
                            input.value = codigo;
                            enviarCodigo(true);
                        },
                        onStatus: function (message, kind) {
                            if (kind === 'error') {
                                setFeedback(false, message);
                            }
                        }
                    }).catch(function (err) {
                        alert((err && err.message) ? err.message : 'No se pudo abrir la camara.');
                    });
                });
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function () { input.focus(); });
            } else {
                input.focus();
            }
        })();
        </script>
    <?php endif; ?>

    <?php if ($idEmpleado !== null && $pasoVista === 'resultado' && $auditoriaVista !== null && $metaVista !== null
        && ($auditoriaVista['estado'] ?? '') === 'cerrada'): ?>
        <section class="admin-card">
            <h3>Resultado — piezas faltantes</h3>
            <p>
                <strong>Tienda:</strong> <?php echo htmlspecialchars($nomTiendaVista !== '' ? $nomTiendaVista : ('#' . (string) $idTiendaVista)); ?>
                &nbsp;|&nbsp;
                <strong>Familia:</strong>
                <?php if ($idFamiliaVista > 0): ?>
                    <?php echo htmlspecialchars($nomFamiliaVista); ?>
                <?php else: ?>
                    Todas
                <?php endif; ?>
                &nbsp;|&nbsp;
                <strong>Auditoría #<?php echo (int) $idAuditoriaVista; ?></strong>
                &nbsp;|&nbsp;
                Contadas: <?php echo (int) $contadosVista; ?> / Esperadas: <?php echo (int) $esperadosVista; ?>
            </p>
            <p class="form-hint">Las piezas listadas estaban marcadas como disponibles en sistema<?php if ($idFamiliaVista > 0): ?> de la familia indicada<?php endif; ?> pero no se escanearon en este recuento. Puedes darlas de baja del stock si confirmas la pérdida o error.</p>

            <?php if (empty($faltantesVista)): ?>
                <p><i class="bi bi-emoji-smile"></i> No hay faltantes: todo el stock disponible fue contado.</p>
            <?php else: ?>
                <form method="post" action="inventario_recuento.php?accion=borrar" id="form-borrar-faltantes">
                    <div class="admin-table-wrapper">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <?php if (!empty($authCaps['canDelete'])): ?>
                                        <th class="id-col"><input type="checkbox" id="chk-all-faltantes" title="Seleccionar todas"></th>
                                    <?php endif; ?>
                                    <th class="id-col">ID</th>
                                    <th>Pieza</th>
                                    <th>Cód. auxiliar</th>
                                    <th>Cód. barras</th>
                                    <th>Precio</th>
                                    <th>Subfamilia / Metal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($faltantesVista as $f): ?>
                                    <tr>
                                        <?php if (!empty($authCaps['canDelete'])): ?>
                                            <td>
                                                <input type="checkbox" name="ids_pieza_stock[]" value="<?php echo (int) $f['id_pieza_stock']; ?>" class="chk-faltante">
                                            </td>
                                        <?php endif; ?>
                                        <td><strong>#<?php echo (int) $f['id_pieza_stock']; ?></strong></td>
                                        <td><?php echo htmlspecialchars((string) ($f['desc_pieza'] ?? '')); ?></td>
                                        <td><code><?php echo htmlspecialchars((string) ($f['codigo_auxiliar'] ?? '')); ?></code></td>
                                        <td><code><?php echo htmlspecialchars((string) ($f['codigo_barras'] ?? '')); ?></code></td>
                                        <td>$<?php echo number_format((float) ($f['precio_venta'] ?? 0), 2, '.', ''); ?></td>
                                        <td><?php echo htmlspecialchars((string) ($f['nom_sub_familia'] ?? '')); ?> / <?php echo htmlspecialchars((string) ($f['nom_metal'] ?? '')); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="form-actions" style="margin-top: 1rem;">
                        <?php if (!empty($authCaps['canDelete'])): ?>
                            <button type="submit" class="btn-action-danger"
                                    onclick="return confirm('¿Dar de baja las piezas seleccionadas del stock? Esta acción usa baja lógica (activo=0).');">
                                <i class="bi bi-trash"></i> Dar de baja seleccionadas
                            </button>
                        <?php else: ?>
                            <p class="form-hint">No tienes permiso de borrado para ejecutar bajas desde aquí.</p>
                        <?php endif; ?>
                    </div>
                </form>
                <script>
                (function () {
                    var master = document.getElementById('chk-all-faltantes');
                    if (!master) return;
                    master.addEventListener('change', function () {
                        document.querySelectorAll('.chk-faltante').forEach(function (c) { c.checked = master.checked; });
                    });
                })();
                </script>
            <?php endif; ?>
<div class="form-actions" style="margin-top: 1rem;">
                <a href="<?php echo htmlspecialchars(joyeria_recuento_url_historial()); ?>" class="btn-action-secondary">
                    <i class="bi bi-list-ul"></i> Volver al listado
                </a>
                <a href="inventario_recuento.php?accion=nuevo" class="btn-action-secondary"><i class="bi bi-arrow-repeat"></i> Nuevo recuento</a>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($idEmpleado !== null): ?>
        <section class="admin-card" id="recuento-historial">
            <h3><i class="bi bi-clock-history"></i> Inventarios realizados</h3>
            <p class="form-hint">Recuentos <strong>finalizados o cancelados</strong> (auditoría cerrada). Filtra por rango de fecha de cierre y familia.</p>
            <form method="get" action="inventario_recuento.php" class="form-stack" style="max-width: 720px;">
                <input type="hidden" name="accion" value="leer">
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:0.75rem 1rem;">
                    <div>
                        <label for="hist_fecha_desde"><i class="bi bi-calendar-event"></i> Desde</label>
                        <input class="form-input" type="date" name="fecha_desde" id="hist_fecha_desde"
                               value="<?php echo $filtroHistFechaDesde !== null ? htmlspecialchars($filtroHistFechaDesde) : ''; ?>">
                    </div>
                    <div>
                        <label for="hist_fecha_hasta"><i class="bi bi-calendar-check"></i> Hasta</label>
                        <input class="form-input" type="date" name="fecha_hasta" id="hist_fecha_hasta"
                               value="<?php echo $filtroHistFechaHasta !== null ? htmlspecialchars($filtroHistFechaHasta) : ''; ?>">
                    </div>
                    <div>
                        <label for="hist_id_familia"><i class="bi bi-diagram-3"></i> Familia</label>
                        <select class="form-input" name="id_familia" id="hist_id_familia">
                            <option value="0"<?php echo ($filtroHistIdFamilia ?? 0) === 0 ? ' selected' : ''; ?>>— Todas —</option>
                            <?php foreach ($familiasActivas as $f): ?>
                                <option value="<?php echo (int) $f['id_familia']; ?>"
                                    <?php echo (int) ($filtroHistIdFamilia ?? 0) === (int) $f['id_familia'] ? ' selected' : ''; ?>>
                                    <?php echo htmlspecialchars((string) ($f['nom_familia'] ?? '')); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn-action-primary"><i class="bi bi-funnel"></i> Filtrar</button>
                    <a href="inventario_recuento.php?accion=leer&amp;fecha_desde=&amp;fecha_hasta=&amp;id_familia=0" class="btn-action-secondary">
                        <i class="bi bi-x-circle"></i> Ver todos
                    </a>
                </div>
            </form>

            <?php if (empty($historialRecuentos)): ?>
                <p style="margin-top: 1rem;"><i class="bi bi-inbox"></i> No hay recuentos cerrados con los filtros actuales.</p>
            <?php else: ?>
                <div class="admin-table-wrapper" style="margin-top: 1rem;">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th class="id-col">#</th>
                                <th>Cierre</th>
                                <th>Tienda</th>
                                <th>Familia</th>
                                <th>Empleado</th>
                                <th>Contadas</th>
                                <th>Esperadas</th>
                                <th>Faltantes</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($historialRecuentos as $h): ?>
                                <tr<?php echo ($idAuditoriaVista ?? 0) === (int) $h['id_auditoria'] ? ' style="background:var(--color-surface-alt,rgba(0,0,0,.04));"' : ''; ?>>
                                    <td><strong>#<?php echo (int) $h['id_auditoria']; ?></strong></td>
                                    <td>
                                        <?php
                                        $fc = (string) ($h['fecha_cierre'] ?? '');
                                        echo $fc !== '' ? htmlspecialchars(substr($fc, 0, 16)) : '—';
                                        ?>
                                    </td>
                                    <td><?php echo htmlspecialchars((string) $h['nom_tienda']); ?></td>
                                    <td><?php echo htmlspecialchars((string) $h['nom_familia']); ?></td>
                                    <td><?php echo htmlspecialchars((string) ($h['empleado_nombre'] !== '' ? $h['empleado_nombre'] : '—')); ?></td>
                                    <td><?php echo (int) $h['contados']; ?></td>
                                    <td><?php echo (int) $h['esperados']; ?></td>
                                    <td>
                                        <?php if ((int) $h['faltantes'] > 0): ?>
                                            <span class="badge badge-warning"><?php echo (int) $h['faltantes']; ?></span>
                                        <?php else: ?>
                                            <span class="badge badge-success">0</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="<?php echo htmlspecialchars(joyeria_recuento_url_historial(['id_auditoria' => (int) $h['id_auditoria']])); ?>"
                                           class="btn-action-secondary btn-sm">
                                            <i class="bi bi-eye"></i> Ver
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</div>
