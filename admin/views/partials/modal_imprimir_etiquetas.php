<div id="modal-etiquetas-rango" class="ja-modal-overlay" style="display:none;">
    <div class="ja-modal-card">
        <h4 style="margin-top:0;">
            <i class="bi bi-printer"></i> Encolar etiquetas por rango
        </h4>
        <p style="margin-top:0;">
            Pieza: <strong id="etiquetas-rango-titulo">—</strong><br>
            Usa el número después de la barra en el codigo auxiliar (ej. en <code>42/5</code> el rango es <code>5</code>).
        </p>
        <input type="hidden" id="etiquetas-rango-id-pieza" value="">
        <div class="form-row">
            <div class="form-group">
                <label for="etiquetas-rango-desde">Desde</label>
                <input type="number" class="form-input" id="etiquetas-rango-desde" min="1" step="1" value="1">
            </div>
            <div class="form-group">
                <label for="etiquetas-rango-hasta">Hasta</label>
                <input type="number" class="form-input" id="etiquetas-rango-hasta" min="1" step="1" value="1">
            </div>
        </div>
        <div class="form-actions" style="margin-top:0;">
            <button type="button" class="btn-action-primary" id="btn-etiquetas-rango-confirmar">
                <i class="bi bi-send"></i> Encolar
            </button>
            <button type="button" class="btn-action-secondary" id="btn-etiquetas-rango-cerrar">
                Cancelar
            </button>
        </div>
    </div>
</div>
