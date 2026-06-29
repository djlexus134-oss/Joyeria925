<?php
/**
 * Campos de imagen con captura de camara y compresion (pieza-foto-capture.js).
 *
 * Variables opcionales:
 * - $piezaUrlImagenActual (string|null): URL de imagen principal existente.
 * - $mostrarImagenActual (bool): mostrar bloque "imagen actual" al lado.
 */
$piezaUrlImagenActual = $piezaUrlImagenActual ?? null;
$mostrarImagenActual = !empty($mostrarImagenActual) && !empty($piezaUrlImagenActual);
$piezaFotoScriptPath = __DIR__ . '/../../../js/pieza-foto-capture.js';
?>
<div data-pieza-foto-capture>
    <fieldset class="form-fieldset">
        <legend><i class="bi bi-image"></i> Imagen principal</legend>
        <div class="form-row">
            <div class="form-group" style="width:100%;">
                <label for="imagen_principal"><i class="bi bi-upload"></i> Reemplazar imagen principal:</label>
                <input type="file"
                       class="pieza-foto-input-hidden"
                       name="imagen_principal"
                       id="imagen_principal"
                       accept="image/*"
                       tabindex="-1"
                       aria-hidden="true">
                <div class="pieza-foto-actions">
                    <button type="button" class="btn-action-primary" data-pieza-foto-camera="principal">
                        <i class="bi bi-camera"></i> Camara
                    </button>
                    <button type="button" class="btn-action-secondary" data-pieza-foto-archivos="principal">
                        <i class="bi bi-folder2-open"></i> Archivos
                    </button>
                    <button type="button" class="btn-action-secondary" data-pieza-foto-drive="principal">
                        <i class="bi bi-hdd"></i> Drive
                    </button>
                    <button type="button" class="btn-action-secondary" data-pieza-foto-clear="principal">
                        <i class="bi bi-x-lg"></i> Quitar seleccion
                    </button>
                </div>
                <div id="pieza-foto-preview-principal" class="pieza-foto-preview" aria-live="polite">
                    <p class="pieza-foto-preview-status is-empty">Sin imagen nueva seleccionada.</p>
                </div>
                <small class="form-hint"><i class="bi bi-info-circle"></i> Elige camara, archivos del dispositivo o carpetas (Drive local). Se optimiza automaticamente antes de guardar.</small>
            </div>

            <?php if ($mostrarImagenActual): ?>
                <div class="form-group">
                    <label><i class="bi bi-eye"></i> Imagen actual:</label>
                    <div>
                        <img src="<?php echo htmlspecialchars((string) $piezaUrlImagenActual); ?>" alt="Imagen actual" style="max-width:150px;max-height:150px;object-fit:cover;border-radius:8px;">
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </fieldset>

    <fieldset class="form-fieldset">
        <legend><i class="bi bi-images"></i> Galeria de imagenes</legend>
        <div data-pieza-foto-adicionales>
            <div class="form-row">
                <div class="form-group" style="width:100%;">
                    <label for="imagenes_adicionales"><i class="bi bi-upload"></i> Agregar imagenes adicionales:</label>
                    <input type="file"
                           class="pieza-foto-input-hidden"
                           name="imagenes_adicionales[]"
                           id="imagenes_adicionales"
                           accept="image/*"
                           multiple
                           tabindex="-1"
                           aria-hidden="true">
                    <div class="pieza-foto-actions">
                        <button type="button" class="btn-action-primary" data-pieza-foto-camera="adicionales">
                            <i class="bi bi-camera"></i> Camara
                        </button>
                        <button type="button" class="btn-action-secondary" data-pieza-foto-archivos="adicionales">
                            <i class="bi bi-folder2-open"></i> Archivos
                        </button>
                        <button type="button" class="btn-action-secondary" data-pieza-foto-drive="adicionales">
                            <i class="bi bi-hdd"></i> Drive
                        </button>
                    </div>
                    <div id="pieza-foto-preview-adicionales" class="pieza-foto-preview" aria-live="polite"></div>
                    <small class="form-hint"><i class="bi bi-info-circle"></i> Camara, archivos o carpetas locales; puedes agregar varias imagenes.</small>
                </div>
            </div>
        </div>
    </fieldset>
</div>
