<?php
$esEdicion = isset($familia) && !empty($familia);
$titulo = $esEdicion ? 'Editar Familia' : 'Nueva Familia';
$accionForm = $esEdicion
    ? 'familia.php?accion=actualizar&id=' . urlencode((string) $familia['id_familia'])
    : 'familia.php?accion=crear';
$nombreFamilia = $esEdicion ? $familia['nom_familia'] : '';
$usaTalla = $esEdicion ? (int) ($familia['usa_talla'] ?? 0) : 0;
?>

<div class="form-section">
    <h3>
        <i class="bi <?php echo $esEdicion ? 'bi-pencil-square' : 'bi-plus-circle'; ?>"></i>
        <?php echo htmlspecialchars($titulo); ?>
    </h3>

    <?php if(!$esEdicion || !empty($familia)): ?>
        <form action="<?php echo htmlspecialchars($accionForm); ?>" method="POST" class="admin-form">
            <div class="form-group">
                <label for="nom_familia">
                    <i class="bi bi-collection"></i> Nombre de la Familia:
                </label>
                <input type="text"
                       class="form-input"
                       name="nom_familia"
                       id="nom_familia"
                       maxlength="50"
                       value="<?php echo htmlspecialchars($nombreFamilia); ?>"
                       placeholder="Ej. Anillos, Pulseras, Aretes..."
                       required
                       autofocus>
                <?php if($esEdicion): ?>
                    <small class="form-hint"><i class="bi bi-info-circle"></i> Maximo 50 caracteres. ID: #<?php echo str_pad(htmlspecialchars($familia['id_familia']), 3, '0', STR_PAD_LEFT); ?></small>
                <?php else: ?>
                    <small class="form-hint"><i class="bi bi-info-circle"></i> Maximo 50 caracteres. Este valor identificara a la familia de productos.</small>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label class="form-check-label" for="usa_talla">
                    <input type="checkbox"
                           name="usa_talla"
                           id="usa_talla"
                           value="1"
                           <?php echo $usaTalla === 1 ? 'checked' : ''; ?>>
                    <i class="bi bi-rulers"></i> Esta familia usa talla (anillos)
                </label>
                <small class="form-hint"><i class="bi bi-info-circle"></i> Si se marca, al generar stock se podra elegir talla por unidad (ajustable o por medidas).</small>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-action-primary">
                    <i class="bi bi-check-lg"></i> <?php echo $esEdicion ? 'Guardar Cambios' : 'Guardar Familia'; ?>
                </button>
                <a href="familia.php?accion=leer" class="btn-action-secondary">
                    <i class="bi bi-x-lg"></i> Cancelar
                </a>
            </div>
        </form>
    <?php else: ?>
        <div class="alert-message error">
            <p><i class="bi bi-exclamation-triangle"></i> No se encontró la familia. <a href="familia.php?accion=leer">Volver al listado</a></p>
        </div>
    <?php endif; ?>
</div>