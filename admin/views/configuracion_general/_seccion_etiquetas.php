<?php /** @var array $valores */ ?>
<section class="config-hub-card">
    <h3><i class="bi bi-tags"></i> Impresora y modo Argox</h3>
    <div class="form-row">
        <div class="form-group">
            <label for="etiqueta_impresion_nombre_impresora">Impresora Argox (Windows)</label>
            <input class="form-input" type="text" name="etiqueta_impresion_nombre_impresora" id="etiqueta_impresion_nombre_impresora" maxlength="120"
                   value="<?php echo htmlspecialchars((string) $valores['etiqueta_impresion_nombre_impresora']); ?>">
        </div>
        <div class="form-group">
            <label for="etiqueta_lang">Modo de impresión</label>
            <select class="form-input" name="etiqueta_lang" id="etiqueta_lang">
                <option value="IMAGEN" <?php echo ($valores['etiqueta_lang'] === 'IMAGEN') ? 'selected' : ''; ?>>IMAGEN PNG (recomendado)</option>
                <option value="PPLA" <?php echo ($valores['etiqueta_lang'] === 'PPLA') ? 'selected' : ''; ?>>PPLA RAW</option>
                <option value="ZPL" <?php echo ($valores['etiqueta_lang'] === 'ZPL') ? 'selected' : ''; ?>>ZPL</option>
            </select>
        </div>
    </div>
    <label class="config-hub-checks"><input type="checkbox" name="etiqueta_impresion_habilitada" value="1" <?php echo !empty($valores['etiqueta_impresion_habilitada']) ? 'checked' : ''; ?>> Habilitar encolado de etiquetas</label>
</section>

<section class="config-hub-card">
    <h3><i class="bi bi-rulers"></i> Medidas de etiqueta (mm)</h3>
    <div class="form-row">
        <div class="form-group">
            <label for="etiqueta_ancho_mm">Largo total</label>
            <input class="form-input" type="number" min="20" max="120" name="etiqueta_ancho_mm" id="etiqueta_ancho_mm" value="<?php echo (int) $valores['etiqueta_ancho_mm']; ?>">
        </div>
        <div class="form-group">
            <label for="etiqueta_alto_mm">Alto</label>
            <input class="form-input" type="number" min="8" max="80" name="etiqueta_alto_mm" id="etiqueta_alto_mm" value="<?php echo (int) $valores['etiqueta_alto_mm']; ?>">
        </div>
        <div class="form-group">
            <label for="etiqueta_gap_mm">Salto entre etiquetas</label>
            <input class="form-input" type="number" min="0" max="20" step="0.1" name="etiqueta_gap_mm" id="etiqueta_gap_mm"
                   value="<?php echo htmlspecialchars((string) $valores['etiqueta_gap_mm'], ENT_QUOTES, 'UTF-8'); ?>">
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label for="etiqueta_ala_mm">Cabeza izquierda (precio)</label>
            <input class="form-input" type="number" min="10" max="40" name="etiqueta_ala_mm" id="etiqueta_ala_mm" value="<?php echo (int) $valores['etiqueta_ala_mm']; ?>">
        </div>
        <div class="form-group">
            <label for="etiqueta_media_mm">Zona media (barras)</label>
            <input class="form-input" type="number" min="0" max="40" name="etiqueta_media_mm" id="etiqueta_media_mm" value="<?php echo (int) $valores['etiqueta_media_mm']; ?>">
        </div>
        <div class="form-group">
            <label for="etiqueta_cola_mm">Cola estrecha</label>
            <input class="form-input" type="number" min="10" max="50" name="etiqueta_cola_mm" id="etiqueta_cola_mm" value="<?php echo (int) $valores['etiqueta_cola_mm']; ?>">
        </div>
        <div class="form-group">
            <label for="etiqueta_alto_cola_mm">Alto cola</label>
            <input class="form-input" type="number" min="3" max="10" name="etiqueta_alto_cola_mm" id="etiqueta_alto_cola_mm" value="<?php echo (int) $valores['etiqueta_alto_cola_mm']; ?>">
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label for="etiqueta_dpi">DPI</label>
            <input class="form-input" type="number" min="150" max="600" name="etiqueta_dpi" id="etiqueta_dpi" value="<?php echo (int) $valores['etiqueta_dpi']; ?>">
        </div>
        <div class="form-group">
            <label for="etiqueta_offset_x">Offset X</label>
            <input class="form-input" type="number" step="0.5" name="etiqueta_offset_x" id="etiqueta_offset_x"
                   value="<?php echo htmlspecialchars((string) $valores['etiqueta_offset_x'], ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <div class="form-group">
            <label for="etiqueta_offset_y">Offset Y</label>
            <input class="form-input" type="number" step="0.5" name="etiqueta_offset_y" id="etiqueta_offset_y"
                   value="<?php echo htmlspecialchars((string) $valores['etiqueta_offset_y'], ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <div class="form-group">
            <label for="etiqueta_impresion_token">Token agente etiquetas (vacío = no cambiar)</label>
            <input class="form-input" type="password" name="etiqueta_impresion_token" id="etiqueta_impresion_token" autocomplete="new-password" placeholder="********">
            <small class="form-hint">
                Solo para <code>print-agent-etiquetas</code>. Puede ser distinto al de tickets.
                Si lo dejas sin configurar nunca, el servidor usa el token de tickets como respaldo
                (por eso parece que “tienen que ser iguales”).
            </small>
        </div>
    </div>
</section>

<section class="config-hub-card">
    <h3><i class="bi bi-image"></i> Acomodo PNG (modo IMAGEN)</h3>
    <p class="form-hint" style="margin:0 0 1rem;">Desplazamientos y tipografia del render PNG; no afecta PPLA/ZPL.</p>
    <div class="form-row">
        <div class="form-group">
            <label for="etiqueta_img_shift_barcode_mm">Desplazar barcode + aux (mm)</label>
            <input class="form-input" type="number" step="0.5" name="etiqueta_img_shift_barcode_mm" id="etiqueta_img_shift_barcode_mm"
                   value="<?php echo htmlspecialchars((string) $valores['etiqueta_img_shift_barcode_mm'], ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <div class="form-group">
            <label for="etiqueta_img_shift_precio_mm">Desplazar precio (mm)</label>
            <input class="form-input" type="number" step="0.5" name="etiqueta_img_shift_precio_mm" id="etiqueta_img_shift_precio_mm"
                   value="<?php echo htmlspecialchars((string) $valores['etiqueta_img_shift_precio_mm'], ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <div class="form-group">
            <label for="etiqueta_img_margen_izq_barcode_mm">Margen izq. barcode</label>
            <input class="form-input" type="number" step="0.1" name="etiqueta_img_margen_izq_barcode_mm" id="etiqueta_img_margen_izq_barcode_mm"
                   value="<?php echo htmlspecialchars((string) $valores['etiqueta_img_margen_izq_barcode_mm'], ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <div class="form-group">
            <label for="etiqueta_img_margen_der_barcode_mm">Margen der. barcode</label>
            <input class="form-input" type="number" step="0.1" name="etiqueta_img_margen_der_barcode_mm" id="etiqueta_img_margen_der_barcode_mm"
                   value="<?php echo htmlspecialchars((string) $valores['etiqueta_img_margen_der_barcode_mm'], ENT_QUOTES, 'UTF-8'); ?>">
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label for="etiqueta_img_gap_barcode_texto_mm">Espacio barcode → texto</label>
            <input class="form-input" type="number" step="0.1" name="etiqueta_img_gap_barcode_texto_mm" id="etiqueta_img_gap_barcode_texto_mm"
                   value="<?php echo htmlspecialchars((string) $valores['etiqueta_img_gap_barcode_texto_mm'], ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <div class="form-group">
            <label for="etiqueta_img_margen_inferior_aux_mm">Margen inferior aux.</label>
            <input class="form-input" type="number" step="0.1" name="etiqueta_img_margen_inferior_aux_mm" id="etiqueta_img_margen_inferior_aux_mm"
                   value="<?php echo htmlspecialchars((string) $valores['etiqueta_img_margen_inferior_aux_mm'], ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <div class="form-group">
            <label for="etiqueta_img_alto_barcode_ratio">Alto barcode / alto etiqueta</label>
            <input class="form-input" type="number" step="0.01" min="0.35" max="0.92" name="etiqueta_img_alto_barcode_ratio" id="etiqueta_img_alto_barcode_ratio"
                   value="<?php echo htmlspecialchars((string) $valores['etiqueta_img_alto_barcode_ratio'], ENT_QUOTES, 'UTF-8'); ?>">
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label for="etiqueta_img_tam_aux_pt">Tamaño código auxiliar (pt)</label>
            <input class="form-input" type="number" min="6" max="22" name="etiqueta_img_tam_aux_pt" id="etiqueta_img_tam_aux_pt"
                   value="<?php echo (int) $valores['etiqueta_img_tam_aux_pt']; ?>">
        </div>
        <div class="form-group">
            <label for="etiqueta_img_tam_precio_pt">Tamaño precio (pt)</label>
            <input class="form-input" type="number" min="10" max="56" name="etiqueta_img_tam_precio_pt" id="etiqueta_img_tam_precio_pt"
                   value="<?php echo (int) $valores['etiqueta_img_tam_precio_pt']; ?>">
        </div>
        <div class="form-group">
            <label for="etiqueta_img_precio_baseline_factor">Centrado vertical precio</label>
            <input class="form-input" type="number" step="0.01" min="0.1" max="0.55" name="etiqueta_img_precio_baseline_factor" id="etiqueta_img_precio_baseline_factor"
                   value="<?php echo htmlspecialchars((string) $valores['etiqueta_img_precio_baseline_factor'], ENT_QUOTES, 'UTF-8'); ?>">
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label for="etiqueta_img_precio_con_variante_pt">Tamaño precio con variante (pt)</label>
            <input class="form-input" type="number" min="10" max="40" name="etiqueta_img_precio_con_variante_pt" id="etiqueta_img_precio_con_variante_pt"
                   value="<?php echo (int) ($valores['etiqueta_img_precio_con_variante_pt'] ?? 20); ?>">
        </div>
        <div class="form-group">
            <label for="etiqueta_img_tam_variante_pt">Tamaño variante color/talla (pt)</label>
            <input class="form-input" type="number" min="6" max="16" name="etiqueta_img_tam_variante_pt" id="etiqueta_img_tam_variante_pt"
                   value="<?php echo (int) ($valores['etiqueta_img_tam_variante_pt'] ?? 8); ?>">
        </div>
        <div class="form-group">
            <label for="etiqueta_img_margen_inferior_variante_mm">Margen inferior variante (mm)</label>
            <input class="form-input" type="number" step="0.1" min="0.5" max="3" name="etiqueta_img_margen_inferior_variante_mm" id="etiqueta_img_margen_inferior_variante_mm"
                   value="<?php echo htmlspecialchars((string) ($valores['etiqueta_img_margen_inferior_variante_mm'] ?? '1.2'), ENT_QUOTES, 'UTF-8'); ?>">
        </div>
    </div>
</section>
