<?php
$fmtMoney = static function ($n): string {
    return '$' . number_format((float) $n, 2, '.', ',');
};

$saldoForm = $saldoInicialReq;
if ($saldoForm === null && is_array($saldoSugerido)) {
    $saldoForm = (float) ($saldoSugerido['saldo_inicial'] ?? 0);
}
?>

<div class="admin-modules">

    <div class="alert-message info" style="margin-bottom:1rem;">
        <p><i class="bi bi-info-circle"></i> Consulta el descuadre <strong>sin registrar</strong> un cierre. Para dejar constancia oficial del día, usa <a href="cierre_caja.php?accion=leer&amp;fecha=<?php echo urlencode($fechaSeleccion); ?>">Cierre de caja</a>.</p>
    </div>

    <div class="form-section">
        <h3><i class="bi bi-sliders"></i> Parámetros del arqueo</h3>
        <form method="get" action="arqueo_caja.php" class="admin-form" style="display:flex; flex-wrap:wrap; gap:1rem; align-items:flex-end;">
            <div class="form-group">
                <label for="fecha">Fecha de operación</label>
                <input type="date" class="form-input" name="fecha" id="fecha" value="<?php echo htmlspecialchars($fechaSeleccion); ?>" required>
            </div>
            <div class="form-group">
                <label for="saldo_inicial">Saldo inicial en caja</label>
                <input type="number" step="0.01" min="0" class="form-input" name="saldo_inicial" id="saldo_inicial"
                       value="<?php echo $saldoForm !== null ? htmlspecialchars((string) $saldoForm) : ''; ?>"
                       placeholder="0.00">
                <?php if (is_array($saldoSugerido) && !empty($saldoSugerido['mensaje'])): ?>
                    <small class="form-hint"><?php echo htmlspecialchars((string) $saldoSugerido['mensaje']); ?></small>
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label for="efectivo_contado">Efectivo contado (arqueo)</label>
                <input type="number" step="0.01" min="0" class="form-input" name="efectivo_contado" id="efectivo_contado"
                       value="<?php echo $efectivoContadoReq !== null ? htmlspecialchars((string) $efectivoContadoReq) : ''; ?>"
                       placeholder="Cuente el efectivo fisico">
            </div>
            <div class="form-actions" style="margin:0;">
                <button type="submit" class="btn-action-primary"><i class="bi bi-calculator"></i> Calcular descuadre</button>
            </div>
        </form>
    </div>

    <?php if (!empty($errorResumen)): ?>
        <div class="alert-message error">
            <p><i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($errorResumen); ?></p>
        </div>
    <?php endif; ?>

    <?php if (is_array($cierreGuardado)): ?>
        <div class="alert-message success">
            <p><i class="bi bi-lock-fill"></i> Ya hay un <strong>cierre registrado</strong> para esta fecha. Este arqueo es solo informativo y no lo modifica.</p>
        </div>
    <?php endif; ?>

    <?php if (is_array($arqueo)): ?>
        <div class="form-section">
            <h3><i class="bi bi-cash-stack"></i> Resumen (<?php echo htmlspecialchars((string) $arqueo['fecha']); ?>)</h3>
            <p class="form-hint">Efectivo esperado = saldo inicial + ventas en efectivo + abonos de apartado en efectivo + cobros de taller en efectivo − gastos en efectivo − devoluciones en efectivo. Las ventas en efectivo incluyen pagos negativos por reembolsos con ticket.</p>
            <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); gap:1rem; margin-top:1rem;">
                <div class="admin-stat-card" style="padding:1rem; border:1px solid var(--border-color, #ddd); border-radius:8px;">
                    <small>Saldo inicial</small>
                    <strong style="font-size:1.1rem;"><?php echo $fmtMoney($arqueo['saldo_inicial'] ?? 0); ?></strong>
                </div>
                <div class="admin-stat-card" style="padding:1rem; border:1px solid var(--border-color, #ddd); border-radius:8px;">
                    <small>Ventas (efectivo)</small>
                    <strong style="font-size:1.1rem;"><?php echo $fmtMoney($arqueo['ventas_efectivo'] ?? 0); ?></strong>
                    <small class="text-muted" style="display:block;margin-top:0.35rem;font-size:0.8rem;">Incluye reembolsos negativos</small>
                </div>
                <div class="admin-stat-card" style="padding:1rem; border:1px solid var(--border-color, #ddd); border-radius:8px;">
                    <small>Apartados (efectivo)</small>
                    <strong style="font-size:1.1rem;"><?php echo $fmtMoney($arqueo['apartados_efectivo'] ?? 0); ?></strong>
                </div>
                <div class="admin-stat-card" style="padding:1rem; border:1px solid var(--border-color, #ddd); border-radius:8px;">
                    <small>Taller (efectivo)</small>
                    <strong style="font-size:1.1rem;"><?php echo $fmtMoney($arqueo['taller_efectivo'] ?? 0); ?></strong>
                </div>
                <div class="admin-stat-card" style="padding:1rem; border:1px solid var(--border-color, #ddd); border-radius:8px;">
                    <small>Gastos caja (efectivo)</small>
                    <strong style="font-size:1.1rem;"><?php echo $fmtMoney($arqueo['gastos_efectivo'] ?? 0); ?></strong>
                </div>
                <div class="admin-stat-card" style="padding:1rem; border:1px solid var(--border-color, #ddd); border-radius:8px;">
                    <small>Devoluciones (efectivo)</small>
                    <strong style="font-size:1.1rem;"><?php echo $fmtMoney($arqueo['devoluciones_efectivo'] ?? 0); ?></strong>
                </div>
                <?php if (abs((float) ($arqueo['canje_interno_neto_dia'] ?? 0)) > 0.009): ?>
                <div class="admin-stat-card" style="padding:1rem; border:1px solid #6c757d; border-radius:8px; background: rgba(108,117,125,0.06);">
                    <small>Canje interno (sin efectivo)</small>
                    <strong style="font-size:1.1rem;"><?php echo $fmtMoney($arqueo['canje_interno_neto_dia'] ?? 0); ?></strong>
                    <small class="text-muted" style="display:block;margin-top:0.35rem;font-size:0.8rem;">No afecta el efectivo fisico en caja</small>
                </div>
                <?php endif; ?>
                <div class="admin-stat-card" style="padding:1rem; border:1px solid var(--border-color, #ddd); border-radius:8px;">
                    <small>Movimiento del dia</small>
                    <strong style="font-size:1.1rem;"><?php echo $fmtMoney($arqueo['movimiento_efectivo_dia'] ?? 0); ?></strong>
                </div>
                <div class="admin-stat-card" style="padding:1rem; border:1px solid #198754; border-radius:8px; background: rgba(25,135,84,0.06);">
                    <small>Efectivo esperado en caja</small>
                    <strong style="font-size:1.25rem; color:#198754;"><?php echo $fmtMoney($arqueo['efectivo_esperado'] ?? 0); ?></strong>
                </div>
            </div>
        </div>

        <?php if ($arqueo['efectivo_contado'] !== null): ?>
            <?php
                $desc = (float) ($arqueo['descuadre'] ?? 0);
                $cuadra = !empty($arqueo['cuadra']);
                $alertClass = $cuadra ? 'success' : 'error';
            ?>
            <div class="form-section">
                <h3><i class="bi bi-<?php echo $cuadra ? 'check-circle' : 'exclamation-octagon'; ?>"></i> Resultado del arqueo</h3>
                <div class="alert-message <?php echo $alertClass; ?>">
                    <ul style="list-style:none; padding:0; margin:0;">
                        <li><strong>Efectivo contado:</strong> <?php echo $fmtMoney($arqueo['efectivo_contado']); ?></li>
                        <li><strong>Efectivo esperado:</strong> <?php echo $fmtMoney($arqueo['efectivo_esperado'] ?? 0); ?></li>
                        <li><strong>Descuadre (contado − esperado):</strong>
                            <?php echo $fmtMoney($desc); ?>
                            <?php if ($cuadra): ?>
                                — <span>La caja cuadra.</span>
                            <?php elseif ($desc > 0): ?>
                                — <span>Sobra efectivo.</span>
                            <?php else: ?>
                                — <span>Falta efectivo.</span>
                            <?php endif; ?>
                        </li>
                    </ul>
                </div>
            </div>
        <?php else: ?>
            <div class="alert-message info">
                <p><i class="bi bi-info-circle"></i> Ingresa el <strong>efectivo contado</strong> y pulsa «Calcular descuadre» para ver si la caja cuadra.</p>
            </div>
        <?php endif; ?>

        <?php if (!empty($arqueo['por_forma_pago'])): ?>
            <div class="form-section">
                <h3><i class="bi bi-credit-card"></i> Movimientos por forma de pago</h3>
                <p class="form-hint" style="margin-top:0;">La columna «Efectivo fisico» indica si esa forma de pago mueve billetes/monedas. El canje interno no afecta el arqueo de efectivo.</p>
                <div class="admin-table-wrapper">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Forma de pago</th>
                                <th>Efectivo fisico</th>
                                <th class="text-end">Ventas</th>
                                <th class="text-end">Gastos</th>
                                <th class="text-end">Devoluciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($arqueo['por_forma_pago'] as $fila): ?>
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
    <?php endif; ?>
</div>
