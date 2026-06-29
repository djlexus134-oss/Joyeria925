<?php
/**
 * Bloque reutilizable de direccion (7 niveles combobox + numeros).
 *
 * Variables esperadas:
 * @var array $dir Valores iniciales (ids y textos de muestra).
 * @var array $dirOpts Opciones: prefix, feedback_id, fieldset_title, root_id, api_prefix,
 *                     style_fieldset (inline css opcional), num_exterior_extra (attrs HTML extras).
 */
if (!isset($dir) || !is_array($dir)) {
    $dir = [];
}
if (!isset($dirOpts) || !is_array($dirOpts)) {
    $dirOpts = [];
}

$pre = isset($dirOpts['prefix']) ? (string) $dirOpts['prefix'] : '';
$feedbackId = isset($dirOpts['feedback_id']) ? (string) $dirOpts['feedback_id'] : 'joyeria_dir_feedback';
$fieldsetTitle = isset($dirOpts['fieldset_title']) ? (string) $dirOpts['fieldset_title'] : 'Direccion';
$rootId = isset($dirOpts['root_id']) ? (string) $dirOpts['root_id'] : 'joyeria_direccion_root';
$apiPrefix = isset($dirOpts['api_prefix']) ? (string) $dirOpts['api_prefix'] : './api/';
$scriptPrefix = isset($dirOpts['script_prefix']) ? (string) $dirOpts['script_prefix'] : 'js/';
$styleFs = isset($dirOpts['style_fieldset']) ? (string) $dirOpts['style_fieldset'] : 'border:2px solid #c9a962;padding:1rem 1.25rem;';
$numExtExtra = isset($dirOpts['num_exterior_extra']) ? (string) $dirOpts['num_exterior_extra'] : '';
$dataDirReq = !empty($dirOpts['data_dir_req']);
$dirReqAttr = $dataDirReq ? ' data-dir-req=""' : '';
$idNumExt = isset($dirOpts['num_exterior_id']) ? (string) $dirOpts['num_exterior_id'] : ($rootId . '_num_ext');
$idNumInt = isset($dirOpts['num_interior_id']) ? (string) $dirOpts['num_interior_id'] : ($rootId . '_num_int');
$omitFieldset = !empty($dirOpts['omit_fieldset']);

$fn = static function (array $d, string $key, $default = '') {
    return isset($d[$key]) ? $d[$key] : $default;
};
?>
<style>
    .joyeria-dir-fieldset .inline-create-feedback { margin-top: 8px; font-size: 0.9rem; color: #0b5ed7; }
    .joyeria-dir-fieldset .joyeria-dir-combobox-wrap { position: relative; }
    .joyeria-dir-fieldset .joyeria-dir-combobox-dd {
        position: absolute; left: 0; right: 0; z-index: 50; max-height: 220px; overflow-y: auto;
        margin: 2px 0 0; padding: 4px 0; list-style: none; background: #fff;
        border: 1px solid #ced4da; border-radius: 4px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }
    .joyeria-dir-fieldset .joyeria-dir-combobox-item { padding: 6px 10px; cursor: pointer; }
    .joyeria-dir-fieldset .joyeria-dir-combobox-item:hover { background: rgba(11, 94, 215, 0.08); }
</style>

<?php if (!$omitFieldset): ?>
<fieldset class="form-fieldset joyeria-dir-fieldset" style="<?php echo htmlspecialchars($styleFs); ?>">
    <legend><i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($fieldsetTitle); ?></legend>
<?php endif; ?>

    <div
        id="<?php echo htmlspecialchars($rootId); ?>"
        class="joyeria-direccion-root<?php echo $omitFieldset ? ' joyeria-dir-fieldset' : ''; ?>"
        style="<?php echo $omitFieldset ? htmlspecialchars($styleFs) : ''; ?>"
        data-api-prefix="<?php echo htmlspecialchars($apiPrefix); ?>"
        data-feedback-id="<?php echo htmlspecialchars($feedbackId); ?>"
        data-prefix="<?php echo htmlspecialchars($pre); ?>"
    >

        <div class="form-row">
            <div class="joyeria-field form-group" data-entity="pais">
                <label><i class="bi bi-globe"></i> Pais</label>
                <input type="hidden" class="joyeria-fk" name="<?php echo htmlspecialchars($pre); ?>id_pais_FK"<?php echo ($pre === '' ? ' id="id_pais_FK"' : ''); ?>
                       value="<?php echo htmlspecialchars((string) $fn($dir, 'id_pais_FK')); ?>"<?php echo $dirReqAttr; ?>>
                <div class="joyeria-dir-combobox-wrap">
                    <input type="text" class="form-input joyeria-display" autocomplete="off"
                           value="<?php echo htmlspecialchars((string) $fn($dir, 'nom_pais')); ?>"
                           maxlength="100" placeholder="Buscar o crear pais..."<?php echo $dirReqAttr; ?>>
                </div>
            </div>
            <div class="joyeria-field form-group" data-entity="estado">
                <label><i class="bi bi-map"></i> Estado</label>
                <input type="hidden" class="joyeria-fk" name="<?php echo htmlspecialchars($pre); ?>id_estado_FK"<?php echo ($pre === '' ? ' id="id_estado_FK"' : ''); ?>
                       value="<?php echo htmlspecialchars((string) $fn($dir, 'id_estado_FK')); ?>"<?php echo $dirReqAttr; ?>>
                <div class="joyeria-dir-combobox-wrap">
                    <input type="text" class="form-input joyeria-display" autocomplete="off"
                           value="<?php echo htmlspecialchars((string) $fn($dir, 'nom_estado')); ?>"
                           maxlength="100" placeholder="Buscar o crear estado..."<?php echo $dirReqAttr; ?>>
                </div>
            </div>
        </div>

        <div class="form-row">
            <div class="joyeria-field form-group" data-entity="municipio">
                <label><i class="bi bi-pin-map"></i> Municipio</label>
                <input type="hidden" class="joyeria-fk" name="<?php echo htmlspecialchars($pre); ?>id_municipio_FK"<?php echo ($pre === '' ? ' id="id_municipio_FK"' : ''); ?>
                       value="<?php echo htmlspecialchars((string) $fn($dir, 'id_municipio_FK')); ?>"<?php echo $dirReqAttr; ?>>
                <div class="joyeria-dir-combobox-wrap">
                    <input type="text" class="form-input joyeria-display" autocomplete="off"
                           value="<?php echo htmlspecialchars((string) $fn($dir, 'nom_municipio')); ?>"
                           maxlength="100" placeholder="Buscar o crear municipio..."<?php echo $dirReqAttr; ?>>
                </div>
            </div>
            <div class="joyeria-field form-group" data-entity="localidad">
                <label><i class="bi bi-building"></i> Localidad</label>
                <input type="hidden" class="joyeria-fk" name="<?php echo htmlspecialchars($pre); ?>id_localidad_FK"<?php echo ($pre === '' ? ' id="id_localidad_FK"' : ''); ?>
                       value="<?php echo htmlspecialchars((string) $fn($dir, 'id_localidad_FK')); ?>"<?php echo $dirReqAttr; ?>>
                <div class="joyeria-dir-combobox-wrap">
                    <input type="text" class="form-input joyeria-display" autocomplete="off"
                           value="<?php echo htmlspecialchars((string) $fn($dir, 'nom_localidad')); ?>"
                           maxlength="100" placeholder="Buscar o crear localidad..."<?php echo $dirReqAttr; ?>>
                </div>
            </div>
        </div>

        <div class="form-row">
            <div class="joyeria-field form-group" data-entity="cp">
                <label><i class="bi bi-mailbox"></i> Código postal</label>
                <input type="hidden" class="joyeria-fk" name="<?php echo htmlspecialchars($pre); ?>id_codigo_postal_FK"<?php echo ($pre === '' ? ' id="id_codigo_postal_FK"' : ''); ?>
                       value="<?php echo htmlspecialchars((string) $fn($dir, 'id_codigo_postal_FK')); ?>"<?php echo $dirReqAttr; ?>>
                <div class="joyeria-dir-combobox-wrap">
                    <input type="text" class="form-input joyeria-display" autocomplete="off"
                           value="<?php echo htmlspecialchars((string) $fn($dir, 'codigo_postal')); ?>"
                           maxlength="10" placeholder="Buscar o crear CP..."<?php echo $dirReqAttr; ?>
                           <?php echo ($pre === '' ? 'id="id_codigo_postal_FK_display"' : ''); ?>>
                </div>
            </div>
            <div class="joyeria-field form-group" data-entity="colonia">
                <label><i class="bi bi-houses"></i> Colonia</label>
                <input type="hidden" class="joyeria-fk" name="<?php echo htmlspecialchars($pre); ?>id_colonia_FK"<?php echo ($pre === '' ? ' id="id_colonia_FK"' : ''); ?>
                       value="<?php echo htmlspecialchars((string) $fn($dir, 'id_colonia_FK')); ?>"<?php echo $dirReqAttr; ?>>
                <div class="joyeria-dir-combobox-wrap">
                    <input type="text" class="form-input joyeria-display" autocomplete="off"
                           value="<?php echo htmlspecialchars((string) $fn($dir, 'nom_colonia')); ?>"
                           maxlength="100" placeholder="Buscar o crear colonia..."<?php echo $dirReqAttr; ?>
                           <?php echo ($pre === '' ? 'id="id_colonia_FK_display"' : ''); ?>>
                </div>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Estado (resumen)</label>
                <input type="text" class="form-input joyeria-resumen" data-resumen="estado" readonly
                    <?php echo ($pre === '' ? ' id="resumen_estado"' : ''); ?>
                       value="<?php echo htmlspecialchars((string) $fn($dir, 'resumen_estado', $fn($dir, 'nom_estado'))); ?>">
            </div>
            <div class="form-group">
                <label>Municipio (resumen)</label>
                <input type="text" class="form-input joyeria-resumen" data-resumen="municipio" readonly
                    <?php echo ($pre === '' ? ' id="resumen_municipio"' : ''); ?>
                       value="<?php echo htmlspecialchars((string) $fn($dir, 'resumen_municipio', $fn($dir, 'nom_municipio'))); ?>">
            </div>
            <div class="form-group">
                <label>Localidad (resumen)</label>
                <input type="text" class="form-input joyeria-resumen" data-resumen="localidad" readonly
                    <?php echo ($pre === '' ? ' id="resumen_localidad"' : ''); ?>
                       value="<?php echo htmlspecialchars((string) $fn($dir, 'resumen_localidad', $fn($dir, 'nom_localidad'))); ?>">
            </div>
        </div>

        <div class="form-row">
            <div class="joyeria-field form-group" data-entity="calle">
                <label><i class="bi bi-signpost-2"></i> Calle</label>
                <input type="hidden" class="joyeria-fk" name="<?php echo htmlspecialchars($pre); ?>id_calle_FK"<?php echo ($pre === '' ? ' id="id_calle_FK"' : ''); ?>
                       value="<?php echo htmlspecialchars((string) $fn($dir, 'id_calle_FK')); ?>"<?php echo $dirReqAttr; ?>>
                <div class="joyeria-dir-combobox-wrap">
                    <input type="text" class="form-input joyeria-display" autocomplete="off"
                           value="<?php echo htmlspecialchars((string) $fn($dir, 'nom_calle')); ?>"
                           maxlength="120" placeholder="Buscar o crear calle..."<?php echo $dirReqAttr; ?>
                           <?php echo ($pre === '' ? 'id="id_calle_FK_display"' : ''); ?>>
                </div>
            </div>
        </div>

        <div id="<?php echo htmlspecialchars($feedbackId); ?>" class="inline-create-feedback" aria-live="polite"></div>

        <div class="form-row joyeria-dir-numeros">
            <div class="form-group">
                <label for="<?php echo htmlspecialchars($idNumExt); ?>"><i class="bi bi-123"></i> Número exterior</label>
                <input type="text" class="form-input" name="<?php echo htmlspecialchars($pre); ?>num_exterior"
                       id="<?php echo htmlspecialchars($idNumExt); ?>"
                       inputmode="numeric" pattern="[0-9]+"
                       value="<?php echo htmlspecialchars((string) $fn($dir, 'num_exterior')); ?>"
                       placeholder="Ej. 123" <?php echo $numExtExtra; ?><?php echo $dirReqAttr; ?>>
            </div>
            <div class="form-group">
                <label for="<?php echo htmlspecialchars($idNumInt); ?>"><i class="bi bi-123"></i> Número interior</label>
                <input type="text" class="form-input" name="<?php echo htmlspecialchars($pre); ?>num_interior"
                       id="<?php echo htmlspecialchars($idNumInt); ?>"
                       inputmode="numeric" pattern="[0-9]*"
                       value="<?php echo htmlspecialchars((string) $fn($dir, 'num_interior')); ?>"
                       placeholder="Opcional">
            </div>
        </div>
    </div>

<?php if (!$omitFieldset): ?>
</fieldset>
<?php endif; ?>

<script src="<?php echo htmlspecialchars($scriptPrefix); ?>direccion-combobox.js"></script>
<script src="<?php echo htmlspecialchars($scriptPrefix); ?>direccion-form.js"></script>
<script>
(function () {
    var root = document.getElementById(<?php echo json_encode($rootId); ?>);
    if (!root || typeof JoyeriaDireccionForm === 'undefined') {
        return;
    }
    var api = JoyeriaDireccionForm.init({
        root: root,
        apiPrefix: <?php echo json_encode($apiPrefix); ?>,
        feedbackId: <?php echo json_encode($feedbackId); ?>
    });
    if (!api) {
        return;
    }
})();
</script>
