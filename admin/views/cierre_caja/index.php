<?php
$authCaps = function_exists('auth_current_capabilities') ? auth_current_capabilities() : ['canCreate' => false];
$fmtMoney = static function ($n): string {
    return '$' . number_format((float) $n, 2, '.', ',');
};

$resumenJson = null;
if (is_array($cierreGuardado ?? null) && !empty($cierreGuardado['resumen_json'])) {
    $raw = $cierreGuardado['resumen_json'];
    if (is_string($raw)) {
        $decoded = json_decode($raw, true);
        $resumenJson = is_array($decoded) ? $decoded : null;
    } elseif (is_array($raw)) {
        $resumenJson = $raw;
    }
}
?>

<div class="admin-modules">

    <?php if (!empty($mensaje)): ?>
        <div class="alert-message <?php echo $mensajeTipo === 'error' ? 'error' : ($mensajeTipo === 'success' ? 'success' : 'info'); ?>">
            <p><i class="bi bi-<?php echo $mensajeTipo === 'error' ? 'exclamation-triangle' : ($mensajeTipo === 'success' ? 'check-circle' : 'info-circle'); ?>"></i> <?php echo htmlspecialchars($mensaje); ?></p>
        </div>
    <?php endif; ?>

    <div class="form-section">
        <h3><i class="bi bi-calendar3"></i> Fecha de operación</h3>
        <p class="form-hint" style="margin-top:0;">
            <i class="bi bi-calculator"></i> Para revisar el descuadre sin guardar, usa
            <a href="arqueo_caja.php?fecha=<?php echo urlencode($fechaSeleccion); ?>">Arqueo / descuadre</a>.
        </p>
        <form method="get" action="cierre_caja.php" class="admin-form cierre-fecha-bar">
            <input type="hidden" name="accion" value="leer">
            <div class="form-group">
                <label for="fecha">Dia a consultar</label>
                <input type="date" class="form-input" name="fecha" id="fecha" value="<?php echo htmlspecialchars($fechaSeleccion); ?>" required>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn-action-primary"><i class="bi bi-arrow-repeat"></i> Actualizar vista</button>
            </div>
        </form>
    </div>

    <div class="form-section cierre-historial-wrap">
        <h3><i class="bi bi-clock-history"></i> Ultimos cierres registrados</h3>
        <?php if (!empty($historial)): ?>
            <div class="admin-table-wrapper">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Fecha operación</th>
                            <th class="text-end">Saldo inicial</th>
                            <th class="text-end">Efectivo esperado</th>
                            <th class="text-end">Contado</th>
                            <th class="text-end">Descuadre</th>
                            <th>Usuario</th>
                            <th>Registro</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($historial as $h): ?>
                            <tr>
                                <td>
                                    <a href="cierre_caja.php?accion=leer&amp;fecha=<?php echo urlencode((string) ($h['fecha_operacion'] ?? '')); ?>"><?php echo htmlspecialchars((string) ($h['fecha_operacion'] ?? '')); ?></a>
                                </td>
                                <td class="text-end"><?php
                                    $si = $h['saldo_inicial'] ?? null;
                                    echo ($si !== null && $si !== '') ? $fmtMoney($si) : '—';
                                ?></td>
                                <td class="text-end"><?php echo $fmtMoney($h['efectivo_esperado'] ?? 0); ?></td>
                                <td class="text-end"><?php echo isset($h['efectivo_contado']) && $h['efectivo_contado'] !== null && $h['efectivo_contado'] !== '' ? $fmtMoney($h['efectivo_contado']) : '—'; ?></td>
                                <td class="text-end"><?php echo isset($h['diferencia']) && $h['diferencia'] !== null && $h['diferencia'] !== '' ? $fmtMoney($h['diferencia']) : '—'; ?></td>
                                <td><?php echo htmlspecialchars((string) ($h['usuario_nombre'] ?? '')); ?></td>
                                <td><small><?php echo htmlspecialchars((string) ($h['fecha_registro'] ?? '')); ?></small></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="form-hint">Aún no hay cierres guardados en la base de datos.</p>
        <?php endif; ?>
    </div>

    <?php if (!empty($errorResumen)): ?>
        <div class="alert-message error">
            <p><i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($errorResumen); ?></p>
        </div>
    <?php endif; ?>

    <?php if (is_array($resumen)): ?>
        <div class="form-section">
            <h3><i class="bi bi-calculator"></i> Arqueo teorico (<?php echo htmlspecialchars($resumen['fecha']); ?>)</h3>
            <p class="form-hint">
                <strong>Efectivo esperado en caja</strong> = saldo inicial + movimiento del dia.
                Movimiento = ventas en efectivo + abonos de apartado en efectivo + cobros de taller en efectivo − gastos que afectan caja − devoluciones en efectivo.
                Las ventas en efectivo incluyen pagos negativos por reembolsos registrados en ventas con ticket.
            </p>
            <?php if (!empty($resumen['saldo_inicial_mensaje'])): ?>
                <p class="form-hint"><i class="bi bi-info-circle"></i> <?php echo htmlspecialchars((string) $resumen['saldo_inicial_mensaje']); ?></p>
            <?php endif; ?>
            <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); gap:1rem; margin-top:1rem;">
                <div class="admin-stat-card" style="padding:1rem; border:1px solid #0d6efd; border-radius:8px; background: rgba(13,110,253,0.05);">
                    <small>Saldo inicial</small>
                    <strong style="font-size:1.1rem; color:#0d6efd;"><?php echo $fmtMoney($resumen['saldo_inicial'] ?? 0); ?></strong>
                </div>
                <div class="admin-stat-card" style="padding:1rem; border:1px solid var(--border-color, #ddd); border-radius:8px;">
                    <small>Ventas (efectivo)</small>
                    <strong style="font-size:1.1rem;"><?php echo $fmtMoney($resumen['ventas_efectivo']); ?></strong>
                    <small class="text-muted" style="display:block;margin-top:0.35rem;font-size:0.8rem;">Incluye reembolsos negativos</small>
                </div>
                <div class="admin-stat-card" style="padding:1rem; border:1px solid var(--border-color, #ddd); border-radius:8px;">
                    <small>Apartados (efectivo)</small>
                    <strong style="font-size:1.1rem;"><?php echo $fmtMoney($resumen['apartados_efectivo'] ?? 0); ?></strong>
                </div>
                <div class="admin-stat-card" style="padding:1rem; border:1px solid var(--border-color, #ddd); border-radius:8px;">
                    <small>Taller (efectivo)</small>
                    <strong style="font-size:1.1rem;"><?php echo $fmtMoney($resumen['taller_efectivo'] ?? 0); ?></strong>
                </div>
                <div class="admin-stat-card" style="padding:1rem; border:1px solid var(--border-color, #ddd); border-radius:8px;">
                    <small>Gastos caja (efectivo)</small>
                    <strong style="font-size:1.1rem;"><?php echo $fmtMoney($resumen['gastos_efectivo']); ?></strong>
                </div>
                <div class="admin-stat-card" style="padding:1rem; border:1px solid var(--border-color, #ddd); border-radius:8px;">
                    <small>Devoluciones (efectivo)</small>
                    <strong style="font-size:1.1rem;"><?php echo $fmtMoney($resumen['devoluciones_efectivo']); ?></strong>
                </div>
                <?php if (abs((float) ($resumen['canje_interno_neto_dia'] ?? 0)) > 0.009): ?>
                <div class="admin-stat-card" style="padding:1rem; border:1px solid #6c757d; border-radius:8px; background: rgba(108,117,125,0.06);">
                    <small>Canje interno (sin efectivo)</small>
                    <strong style="font-size:1.1rem;"><?php echo $fmtMoney($resumen['canje_interno_neto_dia'] ?? 0); ?></strong>
                    <small class="text-muted" style="display:block;margin-top:0.35rem;font-size:0.8rem;">No afecta el efectivo fisico en caja</small>
                </div>
                <?php endif; ?>
                <div class="admin-stat-card" style="padding:1rem; border:1px solid var(--border-color, #ddd); border-radius:8px;">
                    <small>Movimiento del dia</small>
                    <strong style="font-size:1.1rem;"><?php echo $fmtMoney($resumen['movimiento_efectivo_dia'] ?? 0); ?></strong>
                </div>
                <div class="admin-stat-card" style="padding:1rem; border:1px solid #198754; border-radius:8px; background: rgba(25,135,84,0.06);">
                    <small>Efectivo esperado en caja</small>
                    <strong style="font-size:1.25rem; color:#198754;"><?php echo $fmtMoney($resumen['efectivo_esperado']); ?></strong>
                </div>
            </div>
        </div>

        <?php if (!empty($resumen['por_forma_pago'])): ?>
            <div class="form-section">
                <h3><i class="bi bi-credit-card"></i> Movimientos del dia por forma de pago</h3>
                <p class="form-hint" style="margin-top:0;">La columna «Efectivo fisico» indica si esa forma de pago mueve billetes/monedas. El canje interno no afecta el arqueo de efectivo.</p>
                <div class="admin-table-wrapper">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Forma de pago</th>
                                <th>Efectivo fisico</th>
                                <th class="text-end">Ventas</th>
                                <th class="text-end">Gastos (afecta caja)</th>
                                <th class="text-end">Devoluciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($resumen['por_forma_pago'] as $fila): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars((string) ($fila['forma_pago'] ?? '')); ?></td>
                                    <td><?php echo !empty($fila['es_efectivo']) ? 'Si' : 'No'; ?></td>
                                    <td class="text-end"><?php echo $fmtMoney($fila['monto_ventas'] ?? 0); ?></td>
                                    <td class="text-end"><?php echo $fmtMoney($fila['monto_gastos'] ?? 0); ?></td>
                                    <td class="text-end"><?php echo $fmtMoney($fila['monto_devoluciones'] ?? 0); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <?php if (is_array($cierreGuardado)): ?>
            <div class="alert-message success">
                <p><i class="bi bi-lock-fill"></i> Ya existe un <strong>cierre registrado</strong> para esta fecha. No se puede registrar otro.</p>
            </div>
            <div class="form-section">
                <h3><i class="bi bi-receipt"></i> Cierre guardado</h3>
                <ul style="list-style:none; padding:0; margin:0;">
                    <?php if (isset($cierreGuardado['saldo_inicial']) && $cierreGuardado['saldo_inicial'] !== null && $cierreGuardado['saldo_inicial'] !== ''): ?>
                        <li><strong>Saldo inicial:</strong> <?php echo $fmtMoney($cierreGuardado['saldo_inicial']); ?></li>
                    <?php elseif (is_array($resumenJson) && isset($resumenJson['saldo_inicial'])): ?>
                        <li><strong>Saldo inicial (al cerrar):</strong> <?php echo $fmtMoney($resumenJson['saldo_inicial']); ?></li>
                    <?php endif; ?>
                    <li><strong>Efectivo esperado (al cerrar):</strong> <?php echo $fmtMoney($cierreGuardado['efectivo_esperado'] ?? 0); ?></li>
                    <?php if (isset($cierreGuardado['efectivo_contado']) && $cierreGuardado['efectivo_contado'] !== null && $cierreGuardado['efectivo_contado'] !== ''): ?>
                        <li><strong>Efectivo contado:</strong> <?php echo $fmtMoney($cierreGuardado['efectivo_contado']); ?></li>
                    <?php endif; ?>
                    <?php if (isset($cierreGuardado['diferencia']) && $cierreGuardado['diferencia'] !== null && $cierreGuardado['diferencia'] !== ''): ?>
                        <li><strong>Descuadre (contado − esperado):</strong> <?php echo $fmtMoney($cierreGuardado['diferencia']); ?></li>
                    <?php endif; ?>
                    <li><strong>Registrado por:</strong> <?php echo htmlspecialchars((string) ($cierreGuardado['usuario_nombre'] ?? '')); ?> el <?php echo htmlspecialchars((string) ($cierreGuardado['fecha_registro'] ?? '')); ?></li>
                    <?php if (!empty($cierreGuardado['observaciones'])): ?>
                        <li><strong>Observaciones:</strong> <?php echo nl2br(htmlspecialchars((string) $cierreGuardado['observaciones'])); ?></li>
                    <?php endif; ?>
                </ul>
                <?php if (is_array($resumenJson)): ?>
                    <details style="margin-top:1rem;">
                        <summary>Resumen JSON al momento del cierre</summary>
                        <pre style="overflow:auto; max-height:240px; font-size:0.85rem;"><?php echo htmlspecialchars(json_encode($resumenJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></pre>
                    </details>
                <?php endif; ?>
            </div>
        <?php elseif (!empty($authCaps['canCreate'])): ?>
            <div class="form-section">
                <h3><i class="bi bi-check2-square"></i> Registrar cierre del dia</h3>
                <p class="form-hint">Se guarda una copia del cálculo actual. Las ventas o gastos que se editen después no modifican este registro.</p>
                <form method="post" action="cierre_caja.php?accion=crear" class="admin-form" id="formCierreCaja">
                    <input type="hidden" name="fecha_operacion" value="<?php echo htmlspecialchars($resumen['fecha']); ?>">
                    <div class="form-group">
                        <label for="saldo_inicial">Saldo inicial en caja</label>
                        <input type="number" step="0.01" min="0" class="form-input" name="saldo_inicial" id="saldo_inicial"
                               value="<?php echo htmlspecialchars((string) ($resumen['saldo_inicial'] ?? '0')); ?>" required>
                        <?php if (is_array($saldoSugerido) && !empty($saldoSugerido['mensaje'])): ?>
                            <small class="form-hint"><?php echo htmlspecialchars((string) $saldoSugerido['mensaje']); ?></small>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="efectivo_contado">Efectivo contado en caja</label>
                        <input type="number" step="0.01" min="0" class="form-input" name="efectivo_contado" id="efectivo_contado"
                               placeholder="Cuente el efectivo fisico al cierre">
                        <small class="form-hint">Efectivo esperado segun sistema: <strong id="lbl_esperado"><?php echo $fmtMoney($resumen['efectivo_esperado']); ?></strong></small>
                        <small class="form-hint" id="lbl_descuadre_preview" style="display:none;"></small>
                    </div>
                    <div class="form-group">
                        <label for="observaciones">Observaciones (opcional)</label>
                        <textarea class="form-input" name="observaciones" id="observaciones" rows="2" maxlength="500" placeholder="Notas del turno, faltantes, etc."></textarea>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn-action-primary" onclick="return confirm('¿Registrar el cierre de caja para <?php echo htmlspecialchars($resumen['fecha']); ?>? Esta acción no se puede deshacer.');">
                            <i class="bi bi-save"></i> Registrar cierre
                        </button>
                    </div>
                </form>
            </div>
            <script>
            (function () {
                var esperado = <?php echo json_encode((float) ($resumen['efectivo_esperado'] ?? 0)); ?>;
                var inp = document.getElementById('efectivo_contado');
                var lbl = document.getElementById('lbl_descuadre_preview');
                if (!inp || !lbl) return;
                function fmt(n) {
                    return '$' + Number(n).toFixed(2);
                }
                function actualizar() {
                    var v = inp.value.trim();
                    if (v === '') {
                        lbl.style.display = 'none';
                        return;
                    }
                    var contado = parseFloat(v);
                    if (isNaN(contado)) return;
                    var diff = contado - esperado;
                    lbl.style.display = 'block';
                    lbl.textContent = 'Descuadre previo: ' + fmt(diff) + (Math.abs(diff) < 0.01 ? ' (cuadra)' : (diff > 0 ? ' (sobra)' : ' (falta)'));
                }
                inp.addEventListener('input', actualizar);
            })();
            </script>
        <?php else: ?>
            <div class="alert-message info">
                <p><i class="bi bi-info-circle"></i> No tienes permiso para registrar cierres. Puedes consultar los totales con la fecha indicada.</p>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
