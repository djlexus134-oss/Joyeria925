<?php
/** @var array $valores */
/** @var string $vistaPrevia */
?>
<section class="config-hub-card">
    <h3><i class="bi bi-receipt"></i> Encabezado y pie del ticket</h3>
    <div class="form-row">
        <div class="form-group">
            <label for="ticket_nombre_comercial">Nombre comercial</label>
            <input class="form-input" type="text" name="ticket_nombre_comercial" id="ticket_nombre_comercial" maxlength="255"
                   value="<?php echo htmlspecialchars((string) $valores['ticket_nombre_comercial']); ?>">
        </div>
        <div class="form-group">
            <label for="ticket_leyenda_folio">Leyenda del folio</label>
            <input class="form-input" type="text" name="ticket_leyenda_folio" id="ticket_leyenda_folio" maxlength="80"
                   value="<?php echo htmlspecialchars((string) $valores['ticket_leyenda_folio']); ?>">
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label for="ticket_horario">Horario</label>
            <input class="form-input" type="text" name="ticket_horario" id="ticket_horario" maxlength="255"
                   value="<?php echo htmlspecialchars((string) $valores['ticket_horario']); ?>">
        </div>
        <div class="form-group">
            <label for="ticket_mensaje_pie">Mensaje al pie</label>
            <input class="form-input" type="text" name="ticket_mensaje_pie" id="ticket_mensaje_pie" maxlength="255"
                   value="<?php echo htmlspecialchars((string) $valores['ticket_mensaje_pie']); ?>">
        </div>
    </div>
</section>

<section class="config-hub-card">
    <h3><i class="bi bi-printer"></i> Impresora de caja (tickets)</h3>
    <div class="form-row">
        <div class="form-group">
            <label for="ticket_ancho_columnas">Ancho (caracteres)</label>
            <input class="form-input" type="number" min="28" max="48" name="ticket_ancho_columnas" id="ticket_ancho_columnas"
                   value="<?php echo (int) $valores['ticket_ancho_columnas']; ?>">
        </div>
        <div class="form-group">
            <label for="ticket_margen_izquierdo">Margen izquierdo (puntos ESC/POS)</label>
            <input class="form-input" type="number" min="0" max="255" name="ticket_margen_izquierdo" id="ticket_margen_izquierdo"
                   value="<?php echo (int) $valores['ticket_margen_izquierdo']; ?>">
        </div>
        <div class="form-group">
            <label for="ticket_feed_inicio_lineas">Avance al inicio (líneas)</label>
            <input class="form-input" type="number" min="0" max="10" name="ticket_feed_inicio_lineas" id="ticket_feed_inicio_lineas"
                   value="<?php echo (int) ($valores['ticket_feed_inicio_lineas'] ?? 1); ?>">
            <small class="form-hint">0 = sin avance extra. Usa 1–2 si el encabezado queda pegado al corte; 3+ deja mucho papel en blanco arriba.</small>
        </div>
        <div class="form-group">
            <label for="impresion_nombre_impresora">Nombre impresora Windows</label>
            <input class="form-input" type="text" name="impresion_nombre_impresora" id="impresion_nombre_impresora" maxlength="120"
                   value="<?php echo htmlspecialchars((string) $valores['impresion_nombre_impresora']); ?>">
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label for="impresion_id_tienda_caja">ID tienda de esta caja (0 = todas)</label>
            <input class="form-input" type="number" min="0" name="impresion_id_tienda_caja" id="impresion_id_tienda_caja"
                   value="<?php echo (int) $valores['impresion_id_tienda_caja']; ?>">
        </div>
        <div class="form-group">
            <label for="impresion_caja_token">Token agente (vacío = no cambiar)</label>
            <input class="form-input" type="password" name="impresion_caja_token" id="impresion_caja_token" autocomplete="new-password" placeholder="********">
        </div>
    </div>
    <div class="config-hub-checks">
        <label><input type="checkbox" name="impresion_habilitada" value="1" <?php echo !empty($valores['impresion_habilitada']) ? 'checked' : ''; ?>> Encolar tickets al confirmar venta</label>
        <label><input type="checkbox" name="ticket_mostrar_impuesto" value="1" <?php echo !empty($valores['ticket_mostrar_impuesto']) ? 'checked' : ''; ?>> Mostrar impuesto</label>
        <label><input type="checkbox" name="ticket_mostrar_empleado" value="1" <?php echo !empty($valores['ticket_mostrar_empleado']) ? 'checked' : ''; ?>> Mostrar numero de empleado</label>
    </div>
</section>

<?php if (!empty($vistaPrevia)): ?>
<section class="config-hub-card">
    <h3><i class="bi bi-eye"></i> Vista previa del ticket</h3>
    <div class="config-hub-preview"><?php echo $vistaPrevia; ?></div>
</section>
<?php endif; ?>
