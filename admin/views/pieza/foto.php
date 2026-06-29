<?php
/**
 * Vista minima para gestionar la foto de una pieza con permiso PIEZA_FOTO.
 *
 * Variables esperadas:
 * - $pieza (array|null): pieza leida con Pieza::leerUno().
 * - $imagenesPieza (array): galeria leida con Pieza::leerImagenes().
 * - $mensaje (string|null): mensaje opcional desde el controlador.
 */
$piezaValida = isset($pieza) && !empty($pieza);
$accionForm = $piezaValida
    ? 'pieza.php?accion=subir_foto&id=' . urlencode((string) $pieza['id_pieza'])
    : 'pieza.php?accion=leer';
?>

<div class="form-section">
    <h3>
        <i class="bi bi-image"></i>
        Editar foto<?php echo $piezaValida ? ': ' . htmlspecialchars((string) $pieza['desc_pieza']) : ''; ?>
    </h3>

    <?php if (isset($mensaje) && !empty($mensaje)): ?>
        <div class="alert-message info">
            <p><i class="bi bi-info-circle"></i> <?php echo htmlspecialchars((string) $mensaje); ?></p>
        </div>
    <?php endif; ?>

    <?php if (!$piezaValida): ?>
        <div class="alert-message error">
            <p><i class="bi bi-exclamation-triangle"></i> No se encontró la pieza.
                <a href="pieza.php?accion=leer">Volver al listado</a>
            </p>
        </div>
    <?php else: ?>
        <div class="alert-message info">
            <p style="margin:0;">
                <strong>#<?php echo str_pad((string) $pieza['id_pieza'], 3, '0', STR_PAD_LEFT); ?></strong>
                &middot; <?php echo htmlspecialchars((string) ($pieza['desc_pieza'] ?? '')); ?>
            </p>
        </div>

        <form action="<?php echo htmlspecialchars($accionForm); ?>" method="POST" class="admin-form" enctype="multipart/form-data">
            <input type="hidden" name="origen" value="foto">
            <?php
            $piezaUrlImagenActual = !empty($pieza['url_imagen']) ? (string) $pieza['url_imagen'] : null;
            $mostrarImagenActual = true;
            require __DIR__ . '/partials/foto_capture_fields.php';
            ?>

            <fieldset class="form-fieldset">
                <legend><i class="bi bi-grid"></i> Imagenes registradas</legend>
                <div class="form-row">
                    <div class="form-group" style="width:100%;">
                        <label><i class="bi bi-grid"></i> Galeria actual:</label>

                        <?php
                        $piezaGaleriaOrigenFoto = true;
                        require __DIR__ . '/partials/galeria_imagenes_acciones.php';
                        ?>
                    </div>
                </div>
            </fieldset>

            <div class="form-actions">
                <button type="submit" class="btn-action-primary">
                    <i class="bi bi-check-lg"></i> Guardar foto
                </button>
                <a href="pieza.php?accion=leer" class="btn-action-secondary">
                    <i class="bi bi-x-lg"></i> Volver al listado
                </a>
            </div>
        </form>
        <?php if (is_file($piezaFotoScriptPath)): ?>
            <script src="js/pieza-foto-capture.js?v=<?php echo (int) filemtime($piezaFotoScriptPath); ?>"></script>
        <?php endif; ?>
    <?php endif; ?>
</div>
