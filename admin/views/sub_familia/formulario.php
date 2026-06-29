<?php
$esEdicion = isset($subfamilia) && !empty($subfamilia);
$titulo = $esEdicion ? 'Editar Subfamilia' : 'Nueva Subfamilia';
$accionForm = $esEdicion
    ? 'sub_familia.php?accion=actualizar&id=' . urlencode((string) $subfamilia['id_sub_familia'])
    : 'sub_familia.php?accion=crear';
$nombreSubfamilia = $esEdicion ? $subfamilia['nom_sub_familia'] : '';
$familiaSeleccionada = $esEdicion ? (string) $subfamilia['id_familia_FK'] : '';
?>

<div class="form-section">
    <h3>
        <i class="bi <?php echo $esEdicion ? 'bi-pencil-square' : 'bi-plus-circle'; ?>"></i>
        <?php echo htmlspecialchars($titulo); ?>
    </h3>

    <?php if(!$esEdicion || !empty($subfamilia)): ?>
        <form action="<?php echo htmlspecialchars($accionForm); ?>" method="POST" class="admin-form">
            <div class="form-group">
                <label for="nom_sub_familia">
                    <i class="bi bi-diagram-3"></i> Nombre de la Subfamilia:
                </label>
                <input type="text"
                       class="form-input"
                       name="nom_sub_familia"
                       id="nom_sub_familia"
                       maxlength="50"
                       value="<?php echo htmlspecialchars($nombreSubfamilia); ?>"
                       placeholder="Ej. Anillos de Plata, Pulseras Finas..."
                       required
                       autofocus>
                <?php if($esEdicion): ?>
                    <small class="form-hint"><i class="bi bi-info-circle"></i> Maximo 50 caracteres. ID: #<?php echo str_pad(htmlspecialchars($subfamilia['id_sub_familia']), 3, '0', STR_PAD_LEFT); ?></small>
                <?php else: ?>
                    <small class="form-hint"><i class="bi bi-info-circle"></i> Maximo 50 caracteres. Especifica el tipo dentro de la familia.</small>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="id_familia_FK">
                    <i class="bi bi-collection"></i> Familia:
                </label>
                <select class="form-input" name="id_familia_FK" id="id_familia_FK" required>
                    <option value="">-- Selecciona una familia --</option>
                    <?php if(!empty($familias)): ?>
                        <?php foreach ($familias as $familia): ?>
                            <?php $idFamilia = (string) $familia['id_familia']; ?>
                            <option value="<?php echo htmlspecialchars($idFamilia); ?>"
                                <?php if($familiaSeleccionada === $idFamilia) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($familia['nom_familia']); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
                <small class="form-hint"><i class="bi bi-info-circle"></i> <?php echo $esEdicion ? 'Cambia la familia asociada a esta subfamilia.' : 'La subfamilia debe pertenecer a una familia.'; ?></small>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-action-primary">
                    <i class="bi bi-check-lg"></i> <?php echo $esEdicion ? 'Guardar Cambios' : 'Guardar Subfamilia'; ?>
                </button>
                <a href="sub_familia.php?accion=leer" class="btn-action-secondary">
                    <i class="bi bi-x-lg"></i> Cancelar
                </a>
            </div>
        </form>

        <script src="js/fk-autocomplete.js"></script>
        <script>
            (function () {
                if (!window.JoyeriaFkAutocomplete) {
                    return;
                }
                JoyeriaFkAutocomplete.initSelectAutocomplete({
                    selectId: 'id_familia_FK',
                    allowEmpty: false,
                    placeholder: 'Buscar familia...'
                });
            })();
        </script>
    <?php else: ?>
        <div class="alert-message error">
            <p><i class="bi bi-exclamation-triangle"></i> No se encontró la subfamilia. <a href="sub_familia.php?accion=leer">Volver al listado</a></p>
        </div>
    <?php endif; ?>
</div>