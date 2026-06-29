<?php
$esEdicion = isset($articulo) && !empty($articulo);
$titulo = $esEdicion ? 'Editar artículo de catálogo' : 'Nuevo Artículo de catálogo';
$accionForm = $esEdicion
    ? 'catalogo_compra.php?accion=actualizar&id=' . urlencode((string) $articulo['id_articulo_compra'])
    : 'catalogo_compra.php?accion=crear';

$tipo = $_POST['tipo'] ?? ($esEdicion ? ($articulo['tipo'] ?? '') : '');
$descripcion = $_POST['descripcion'] ?? ($esEdicion ? ($articulo['descripcion'] ?? '') : '');
$idFamilia = $_POST['id_familia_FK'] ?? ($esEdicion ? ($articulo['id_familia_FK'] ?? '') : '');
$idSubFamilia = $_POST['id_sub_familia_FK'] ?? ($esEdicion ? ($articulo['id_sub_familia_FK'] ?? '') : '');
$idMetal = $_POST['id_metal_FK'] ?? ($esEdicion ? ($articulo['id_metal_FK'] ?? '') : '');
$observaciones = $_POST['observaciones'] ?? ($esEdicion ? ($articulo['observaciones'] ?? '') : '');

$familias = $catalogos['familias'] ?? [];
$subfamilias = $catalogos['subfamilias'] ?? [];
$metales = $catalogos['metales'] ?? [];
?>

<div class="form-section">
    <h3>
        <i class="bi <?php echo $esEdicion ? 'bi-pencil-square' : 'bi-plus-circle'; ?>"></i>
        <?php echo htmlspecialchars($titulo); ?>
    </h3>

    <?php if (isset($mensaje) && !empty($mensaje)): ?>
        <div class="alert-message info">
            <p><i class="bi bi-info-circle"></i> <?php echo htmlspecialchars($mensaje); ?></p>
        </div>
    <?php endif; ?>

    <?php if (!$esEdicion || !empty($articulo)): ?>
        <form action="<?php echo htmlspecialchars($accionForm); ?>" method="POST" class="admin-form">
            <div class="form-row">
                <div class="form-group">
                    <label for="tipo"><i class="bi bi-tags"></i> Tipo:</label>
                    <select class="form-input" name="tipo" id="tipo" required>
                        <option value="">-- Selecciona --</option>
                        <option value="pieza" <?php echo $tipo === 'pieza' ? 'selected' : ''; ?>>Pieza</option>
                        <option value="metal" <?php echo $tipo === 'metal' ? 'selected' : ''; ?>>Metal</option>
                        <option value="insumo" <?php echo $tipo === 'insumo' ? 'selected' : ''; ?>>Insumo</option>
                        <option value="servicio" <?php echo $tipo === 'servicio' ? 'selected' : ''; ?>>Servicio</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="descripcion"><i class="bi bi-card-text"></i> Descripción:</label>
                    <input type="text" class="form-input" name="descripcion" id="descripcion" maxlength="255"
                           value="<?php echo htmlspecialchars((string) $descripcion); ?>" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="id_familia_FK"><i class="bi bi-collection"></i> Familia:</label>
                    <select class="form-input" name="id_familia_FK" id="id_familia_FK">
                        <option value="">-- Opcional --</option>
                        <?php foreach ($familias as $familia): ?>
                            <option value="<?php echo (int) $familia['id_familia']; ?>"
                                <?php echo ((string) $idFamilia === (string) $familia['id_familia']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars((string) $familia['nom_familia']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="id_sub_familia_FK"><i class="bi bi-diagram-3"></i> Subfamilia:</label>
                    <select class="form-input" name="id_sub_familia_FK" id="id_sub_familia_FK">
                        <option value="">-- Opcional --</option>
                        <?php foreach ($subfamilias as $subfamilia): ?>
                            <option value="<?php echo (int) $subfamilia['id_sub_familia']; ?>"
                                <?php echo ((string) $idSubFamilia === (string) $subfamilia['id_sub_familia']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars((string) $subfamilia['nom_sub_familia']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="id_metal_FK"><i class="bi bi-gem"></i> Metal:</label>
                    <select class="form-input" name="id_metal_FK" id="id_metal_FK">
                        <option value="">-- Opcional --</option>
                        <?php foreach ($metales as $metal): ?>
                            <option value="<?php echo (int) $metal['id_metal']; ?>"
                                <?php echo ((string) $idMetal === (string) $metal['id_metal']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars((string) $metal['nom_metal']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="observaciones"><i class="bi bi-chat-left-text"></i> Observaciones:</label>
                    <textarea class="form-input" name="observaciones" id="observaciones" rows="3"><?php echo htmlspecialchars((string) $observaciones); ?></textarea>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-action-primary">
                    <i class="bi bi-check-lg"></i> <?php echo $esEdicion ? 'Guardar Cambios' : 'Guardar'; ?>
                </button>
                <a href="catalogo_compra.php?accion=leer" class="btn-action-secondary">
                    <i class="bi bi-x-lg"></i> Cancelar
                </a>
            </div>
        </form>
    <?php else: ?>
        <div class="alert-message error">
            <p><i class="bi bi-exclamation-triangle"></i> No se encontró el artículo. <a href="catalogo_compra.php?accion=leer">Volver al listado</a></p>
        </div>
    <?php endif; ?>
</div>
<script src="js/fk-autocomplete.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (!window.JoyeriaFkAutocomplete) return;
    JoyeriaFkAutocomplete.initSelectAutocomplete({ selectId: 'id_familia_FK', allowEmpty: true, placeholder: 'Buscar familia...' });
    JoyeriaFkAutocomplete.initSelectAutocomplete({ selectId: 'id_sub_familia_FK', allowEmpty: true, placeholder: 'Buscar subfamilia...' });
    JoyeriaFkAutocomplete.initSelectAutocomplete({ selectId: 'id_metal_FK', allowEmpty: true, placeholder: 'Buscar metal...' });
});
</script>
