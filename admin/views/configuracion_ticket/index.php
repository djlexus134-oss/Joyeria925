<?php
/** @var array $valores */
/** @var string|null $mensaje */
/** @var string $tipoMensaje */
/** @var string $vistaPrevia */
?>

<div class="admin-modules">
    <?php if (!empty($mensaje)): ?>
        <div class="alert-message <?php echo htmlspecialchars($tipoMensaje ?? 'success'); ?>">
            <p><i class="bi bi-info-circle"></i> <?php echo htmlspecialchars($mensaje); ?></p>
        </div>
    <?php endif; ?>

    <form method="post" action="configuracion_ticket.php?accion=guardar" class="form-section">
        <h3><i class="bi bi-receipt"></i> Encabezado y pie del ticket</h3>
        <div class="form-row">
            <div class="form-group">
                <label for="ticket_nombre_comercial">Nombre comercial</label>
                <input class="form-input" type="text" name="ticket_nombre_comercial" id="ticket_nombre_comercial" maxlength="255"
                       value="<?php echo htmlspecialchars((string) ($valores['ticket_nombre_comercial'] ?? '')); ?>">
            </div>
            <div class="form-group">
                <label for="ticket_leyenda_folio">Leyenda del folio</label>
                <input class="form-input" type="text" name="ticket_leyenda_folio" id="ticket_leyenda_folio" maxlength="80"
                       value="<?php echo htmlspecialchars((string) ($valores['ticket_leyenda_folio'] ?? 'Folio')); ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label for="ticket_horario">Horario</label>
                <input class="form-input" type="text" name="ticket_horario" id="ticket_horario" maxlength="255"
                       value="<?php echo htmlspecialchars((string) ($valores['ticket_horario'] ?? '')); ?>">
            </div>
            <div class="form-group">
                <label for="ticket_mensaje_pie">Mensaje al pie</label>
                <input class="form-input" type="text" name="ticket_mensaje_pie" id="ticket_mensaje_pie" maxlength="255"
                       value="<?php echo htmlspecialchars((string) ($valores['ticket_mensaje_pie'] ?? '')); ?>">
            </div>
        </div>

        <h3 style="margin-top:1.5rem;"><i class="bi bi-printer"></i> Impresion</h3>
        <div class="form-row">
            <div class="form-group">
                <label for="ticket_ancho_columnas">Ancho (caracteres, 80mm ~ 38)</label>
                <input class="form-input" type="number" min="24" max="48" name="ticket_ancho_columnas" id="ticket_ancho_columnas"
                       value="<?php echo (int) ($valores['ticket_ancho_columnas'] ?? 38); ?>">
                <small class="form-hint">Si el margen es alto, el builder limita columnas para no desbordar 576 pts.</small>
            </div>
            <div class="form-group">
                <label for="ticket_margen_izquierdo">Margen izquierdo (puntos, ~40 recomendado)</label>
                <input class="form-input" type="number" min="0" max="120" name="ticket_margen_izquierdo" id="ticket_margen_izquierdo"
                       value="<?php echo (int) ($valores['ticket_margen_izquierdo'] ?? 40); ?>">
                <small class="form-hint">Evita valores &gt; 80 con ancho &gt; 40 (riesgo de error de autocutter en TM-T20IV).</small>
            </div>
            <div class="form-group">
                <label for="impresion_nombre_impresora">Nombre impresora Windows</label>
                <input class="form-input" type="text" name="impresion_nombre_impresora" id="impresion_nombre_impresora" maxlength="120"
                       value="<?php echo htmlspecialchars((string) ($valores['impresion_nombre_impresora'] ?? '')); ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label for="impresion_id_tienda_caja">ID tienda de esta caja (0 = todas)</label>
                <input class="form-input" type="number" min="0" name="impresion_id_tienda_caja" id="impresion_id_tienda_caja"
                       value="<?php echo (int) ($valores['impresion_id_tienda_caja'] ?? 0); ?>">
            </div>
            <div class="form-group">
                <label for="impresion_caja_token">Token agente tickets (dejar vacío para no cambiar)</label>
                <input class="form-input" type="password" name="impresion_caja_token" id="impresion_caja_token" autocomplete="new-password" placeholder="********">
                <small class="form-hint">Solo <code>print-agent</code> (tickets). Independiente del de etiquetas.</small>
            </div>
        </div>

        <div class="form-row">
            <label class="form-group"><input type="checkbox" name="impresion_habilitada" value="1" <?php echo !empty($valores['impresion_habilitada']) ? 'checked' : ''; ?>> Encolar tickets al confirmar venta</label>
            <label class="form-group"><input type="checkbox" name="ticket_mostrar_impuesto" value="1" <?php echo !empty($valores['ticket_mostrar_impuesto']) ? 'checked' : ''; ?>> Mostrar impuesto</label>
            <label class="form-group"><input type="checkbox" name="ticket_mostrar_empleado" value="1" <?php echo !empty($valores['ticket_mostrar_empleado']) ? 'checked' : ''; ?>> Mostrar numero de empleado</label>
        </div>

        <h3 style="margin-top:1.5rem;"><i class="bi bi-tags"></i> Etiquetas Argox (cola)</h3>
        <div class="form-row">
            <div class="form-group">
                <label for="etiqueta_impresion_nombre_impresora">Impresora Argox (Windows)</label>
                <input class="form-input" type="text" name="etiqueta_impresion_nombre_impresora" id="etiqueta_impresion_nombre_impresora" maxlength="120"
                       value="<?php echo htmlspecialchars((string) ($valores['etiqueta_impresion_nombre_impresora'] ?? '')); ?>">
            </div>
            <div class="form-group">
                <label for="etiqueta_lang">Modo de impresión</label>
                <select class="form-input" name="etiqueta_lang" id="etiqueta_lang">
                    <option value="IMAGEN" <?php echo (($valores['etiqueta_lang'] ?? 'IMAGEN') === 'IMAGEN') ? 'selected' : ''; ?>>IMAGEN PNG (recomendado, estilo Gemarun) - el driver Argox maneja PPLA</option>
                    <option value="PPLA" <?php echo (($valores['etiqueta_lang'] ?? '') === 'PPLA') ? 'selected' : ''; ?>>PPLA RAW (envío directo, sintaxis manual)</option>
                    <option value="ZPL" <?php echo (($valores['etiqueta_lang'] ?? '') === 'ZPL') ? 'selected' : ''; ?>>ZPL (PPLZ)</option>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label for="etiqueta_ancho_mm">Largo total (mm)</label>
                <input class="form-input" type="number" min="20" max="120" name="etiqueta_ancho_mm" id="etiqueta_ancho_mm"
                       value="<?php echo (int) ($valores['etiqueta_ancho_mm'] ?? 60); ?>">
                <small class="form-hint">Igual que driver: 60 mm (17+17+26).</small>
            </div>
            <div class="form-group">
                <label for="etiqueta_alto_mm">Alto (mm)</label>
                <input class="form-input" type="number" min="8" max="80" name="etiqueta_alto_mm" id="etiqueta_alto_mm"
                       value="<?php echo (int) ($valores['etiqueta_alto_mm'] ?? 10); ?>">
                <small class="form-hint">Igual que driver: 10 mm.</small>
            </div>
            <div class="form-group">
                <label for="etiqueta_gap_mm">Salto entre etiquetas (mm)</label>
                <input class="form-input" type="number" min="0" max="20" step="0.1" name="etiqueta_gap_mm" id="etiqueta_gap_mm"
                       value="<?php echo htmlspecialchars((string) ($valores['etiqueta_gap_mm'] ?? '3'), ENT_QUOTES, 'UTF-8'); ?>">
                <small class="form-hint">Solo referencia; el driver ya usa gap 3 mm (Material).</small>
            </div>
            <div class="form-group">
                <label for="etiqueta_ala_mm">Cabeza izq. (mm)</label>
                <input class="form-input" type="number" min="10" max="40" name="etiqueta_ala_mm" id="etiqueta_ala_mm"
                       value="<?php echo (int) ($valores['etiqueta_ala_mm'] ?? 18); ?>">
                <small class="form-hint">Precio (pad izq). Plano: 17 mm.</small>
            </div>
            <div class="form-group">
                <label for="etiqueta_media_mm">Pad derecho (mm)</label>
                <input class="form-input" type="number" min="0" max="40" name="etiqueta_media_mm" id="etiqueta_media_mm"
                       value="<?php echo (int) ($valores['etiqueta_media_mm'] ?? 17); ?>">
                <small class="form-hint">Código de barras. Plano: 17 mm.</small>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label for="etiqueta_cola_mm">Cola estrecha (mm)</label>
                <input class="form-input" type="number" min="10" max="50" name="etiqueta_cola_mm" id="etiqueta_cola_mm"
                       value="<?php echo (int) ($valores['etiqueta_cola_mm'] ?? 26); ?>">
                <small class="form-hint">Auxiliar en cola (60-17-17=26 mm).</small>
            </div>
            <div class="form-group">
                <label for="etiqueta_alto_cola_mm">Alto cola (mm)</label>
                <input class="form-input" type="number" min="3" max="10" name="etiqueta_alto_cola_mm" id="etiqueta_alto_cola_mm"
                       value="<?php echo (int) ($valores['etiqueta_alto_cola_mm'] ?? 5); ?>">
                <small class="form-hint">Mismo alto que etiqueta (11 mm).</small>
            </div>
            <div class="form-group">
                <label for="etiqueta_dpi">DPI</label>
                <input class="form-input" type="number" min="150" max="600" name="etiqueta_dpi" id="etiqueta_dpi"
                       value="<?php echo (int) ($valores['etiqueta_dpi'] ?? 203); ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label for="etiqueta_offset_x">Offset X (mm)</label>
                <input class="form-input" type="number" name="etiqueta_offset_x" id="etiqueta_offset_x"
                       value="<?php echo htmlspecialchars((string) ($valores['etiqueta_offset_x'] ?? '0'), ENT_QUOTES, 'UTF-8'); ?>" step="0.5">
                <small class="form-hint">Ajuste fino horizontal (toda la etiqueta).</small>
            </div>
            <div class="form-group">
                <label for="etiqueta_offset_y">Offset Y (mm)</label>
                <input class="form-input" type="number" name="etiqueta_offset_y" id="etiqueta_offset_y"
                       value="<?php echo htmlspecialchars((string) ($valores['etiqueta_offset_y'] ?? '0'), ENT_QUOTES, 'UTF-8'); ?>" step="0.5">
                <small class="form-hint">Ajuste fino vertical (toda la etiqueta).</small>
            </div>
            <div class="form-group">
                <label for="etiqueta_impresion_token">Token agente etiquetas (vacío = no cambiar)</label>
                <input class="form-input" type="password" name="etiqueta_impresion_token" id="etiqueta_impresion_token" autocomplete="new-password" placeholder="********">
                <small class="form-hint">
                    Solo <code>print-agent-etiquetas</code>. Puede ser distinto al de tickets.
                    Si nunca lo configuras, el servidor acepta el token de tickets como respaldo.
                </small>
            </div>
        </div>

        <h4 style="margin-top:1rem;"><i class="bi bi-image"></i> Acomodo PNG (solo modo IMAGEN)</h4>
        <p class="form-hint" style="margin:0 0 0.75rem;">Desplazamientos relativos a los pads; tamaño de texto y proporcion del barcode. No afecta PPLA/ZPL.</p>
        <div class="form-row">
            <div class="form-group">
                <label for="etiqueta_img_shift_barcode_mm">Desplazar barcode + aux (mm)</label>
                <input class="form-input" type="number" step="0.5" name="etiqueta_img_shift_barcode_mm" id="etiqueta_img_shift_barcode_mm"
                       value="<?php echo htmlspecialchars((string) ($valores['etiqueta_img_shift_barcode_mm'] ?? '4'), ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="form-group">
                <label for="etiqueta_img_shift_precio_mm">Desplazar precio (mm)</label>
                <input class="form-input" type="number" step="0.5" name="etiqueta_img_shift_precio_mm" id="etiqueta_img_shift_precio_mm"
                       value="<?php echo htmlspecialchars((string) ($valores['etiqueta_img_shift_precio_mm'] ?? '6'), ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="form-group">
                <label for="etiqueta_img_margen_izq_barcode_mm">Margen izq. barcode (mm)</label>
                <input class="form-input" type="number" step="0.1" name="etiqueta_img_margen_izq_barcode_mm" id="etiqueta_img_margen_izq_barcode_mm"
                       value="<?php echo htmlspecialchars((string) ($valores['etiqueta_img_margen_izq_barcode_mm'] ?? '2.5'), ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="form-group">
                <label for="etiqueta_img_margen_der_barcode_mm">Margen der. barcode (mm)</label>
                <input class="form-input" type="number" step="0.1" name="etiqueta_img_margen_der_barcode_mm" id="etiqueta_img_margen_der_barcode_mm"
                       value="<?php echo htmlspecialchars((string) ($valores['etiqueta_img_margen_der_barcode_mm'] ?? '1'), ENT_QUOTES, 'UTF-8'); ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label for="etiqueta_img_gap_barcode_texto_mm">Espacio barcode &rarr; texto (mm)</label>
                <input class="form-input" type="number" step="0.1" name="etiqueta_img_gap_barcode_texto_mm" id="etiqueta_img_gap_barcode_texto_mm"
                       value="<?php echo htmlspecialchars((string) ($valores['etiqueta_img_gap_barcode_texto_mm'] ?? '0.3'), ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="form-group">
                <label for="etiqueta_img_margen_inferior_aux_mm">Margen inferior codigo aux. (mm)</label>
                <input class="form-input" type="number" step="0.1" name="etiqueta_img_margen_inferior_aux_mm" id="etiqueta_img_margen_inferior_aux_mm"
                       value="<?php echo htmlspecialchars((string) ($valores['etiqueta_img_margen_inferior_aux_mm'] ?? '1.5'), ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="form-group">
                <label for="etiqueta_img_alto_barcode_ratio">Alto barcode / alto etiqueta</label>
                <input class="form-input" type="number" step="0.01" min="0.35" max="0.92" name="etiqueta_img_alto_barcode_ratio" id="etiqueta_img_alto_barcode_ratio"
                       value="<?php echo htmlspecialchars((string) ($valores['etiqueta_img_alto_barcode_ratio'] ?? '0.72'), ENT_QUOTES, 'UTF-8'); ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label for="etiqueta_img_tam_aux_pt">Tamaño código auxiliar (pt)</label>
                <input class="form-input" type="number" min="6" max="22" name="etiqueta_img_tam_aux_pt" id="etiqueta_img_tam_aux_pt"
                       value="<?php echo (int) ($valores['etiqueta_img_tam_aux_pt'] ?? 11); ?>">
            </div>
            <div class="form-group">
                <label for="etiqueta_img_tam_precio_pt">Tamaño precio (pt)</label>
                <input class="form-input" type="number" min="10" max="56" name="etiqueta_img_tam_precio_pt" id="etiqueta_img_tam_precio_pt"
                       value="<?php echo (int) ($valores['etiqueta_img_tam_precio_pt'] ?? 24); ?>">
            </div>
            <div class="form-group">
                <label for="etiqueta_img_precio_baseline_factor">Centrado vertical precio (0.1 a 0.55)</label>
                <input class="form-input" type="number" step="0.01" min="0.1" max="0.55" name="etiqueta_img_precio_baseline_factor" id="etiqueta_img_precio_baseline_factor"
                       value="<?php echo htmlspecialchars((string) ($valores['etiqueta_img_precio_baseline_factor'] ?? '0.30'), ENT_QUOTES, 'UTF-8'); ?>">
                <small class="form-hint">Sube o baja el precio respecto al centro (baseline TTF).</small>
            </div>
        </div>
        <label class="form-group"><input type="checkbox" name="etiqueta_impresion_habilitada" value="1" <?php echo !empty($valores['etiqueta_impresion_habilitada']) ? 'checked' : ''; ?>> Habilitar encolado de etiquetas</label>

        <div class="module-actions" style="margin-top:1rem;">
            <button type="submit" class="btn-action-primary"><i class="bi bi-save"></i> Guardar configuración</button>
        </div>
    </form>

    <div class="form-section" style="margin-top:2rem;">
        <h3><i class="bi bi-eye"></i> Vista previa</h3>
        <?php echo $vistaPrevia; ?>
    </div>
</div>
