<?php
$esEdicion = isset($proveedor) && !empty($proveedor);
$titulo = $esEdicion ? 'Editar Proveedor' : 'Nuevo Proveedor';
$accionForm = $esEdicion
    ? 'proveedores.php?accion=actualizar&id=' . urlencode((string) $proveedor['id_proveedor'])
    : 'proveedores.php?accion=crear';

$razonSocial = $_POST['razon_social'] ?? ($esEdicion ? ($proveedor['razon_social'] ?? '') : '');
$nombreComercial = $_POST['nom_comercial'] ?? ($esEdicion ? ($proveedor['nom_comercial'] ?? '') : '');
$idDireccion = $_POST['id_direccion_FK'] ?? ($esEdicion ? ($proveedor['id_direccion_FK'] ?? '') : '');
$forzarDireccionProv = $esEdicion && !empty($proveedor['id_direccion_FK']);
$tieneProvDirBd = $forzarDireccionProv;

$incluir_direccion_prov = $_POST['incluir_direccion'] ?? ($tieneProvDirBd ? '1' : '0');

$modo_direccion_def = ($_POST['modo_direccion_prov'] ?? 'catalogo');
if ($modo_direccion_def !== 'rapida') {
    $modo_direccion_def = 'catalogo';
}
if ($tieneProvDirBd) {
    $modo_direccion_def = 'catalogo';
}

$rfc = $_POST['rfc'] ?? ($esEdicion ? ($proveedor['rfc'] ?? '') : '');
$tipoPersona = $_POST['tipo_persona'] ?? ($esEdicion ? ($proveedor['tipo_persona'] ?? '') : '');
$observaciones = $_POST['observaciones'] ?? ($esEdicion ? ($proveedor['observaciones'] ?? '') : '');

$dirProvRapida = [
    'id_pais_FK' => $_POST['rapida_id_pais_FK'] ?? '',
    'id_estado_FK' => $_POST['rapida_id_estado_FK'] ?? '',
    'id_municipio_FK' => $_POST['rapida_id_municipio_FK'] ?? '',
    'id_localidad_FK' => $_POST['rapida_id_localidad_FK'] ?? '',
    'id_codigo_postal_FK' => $_POST['rapida_id_codigo_postal_FK'] ?? '',
    'codigo_postal' => '',
    'id_colonia_FK' => $_POST['rapida_id_colonia_FK'] ?? '',
    'nom_colonia' => '',
    'id_calle_FK' => $_POST['rapida_id_calle_FK'] ?? '',
    'nom_calle' => '',
    'num_exterior' => $_POST['rapida_num_exterior'] ?? '',
    'num_interior' => $_POST['rapida_num_interior'] ?? '',
    'nom_pais' => '',
    'nom_estado' => '',
    'nom_municipio' => '',
    'nom_localidad' => '',
];

$dirOptsProvRapida = [
    'prefix' => 'rapida_',
    'root_id' => 'joyeria_dir_prov_rapida',
    'feedback_id' => 'prov_dir_feedback',
    'omit_fieldset' => true,
    'api_prefix' => './api/',
    'data_dir_req' => true,
    'num_exterior_id' => 'rapida_num_exterior',
    'num_interior_id' => 'rapida_num_interior',
];
?>

<div class="form-section">
    <style>
        .prov-dir-fieldset{border:2px solid #c9a962;padding:1rem 1.25rem;border-radius:6px;margin-bottom:1rem;background:#fdfcf9}
        .prov-dir-mode{display:flex;gap:20px;flex-wrap:wrap;margin:10px 0}
        #bloque_dir_catalogo_prov, #bloque_dir_rapida_prov{margin-top:12px;padding-top:12px;border-top:1px solid #e8e4dc}
        .inline-create{display:flex;gap:8px;align-items:center}
    </style>

    <h3>
        <i class="bi <?php echo $esEdicion ? 'bi-pencil-square' : 'bi-plus-circle'; ?>"></i>
        <?php echo htmlspecialchars($titulo); ?>
    </h3>

    <?php if (isset($mensaje) && !empty($mensaje)): ?>
        <div class="alert-message info">
            <p><i class="bi bi-info-circle"></i> <?php echo htmlspecialchars($mensaje); ?></p>
        </div>
    <?php endif; ?>

    <?php if (!$esEdicion || !empty($proveedor)): ?>
        <form action="<?php echo htmlspecialchars($accionForm); ?>" method="POST" class="admin-form" id="formProveedor">
            <div class="form-row">
                <div class="form-group">
                    <label for="razon_social"><i class="bi bi-building"></i> Razon Social:</label>
                    <input type="text" class="form-input" name="razon_social" id="razon_social" maxlength="100"
                           value="<?php echo htmlspecialchars($razonSocial); ?>" placeholder="Ej. Plata Fina SA de CV" required autofocus>
                </div>

                <div class="form-group">
                    <label for="nom_comercial"><i class="bi bi-shop"></i> Nombre Comercial:</label>
                    <input type="text" class="form-input" name="nom_comercial" id="nom_comercial" maxlength="100"
                           value="<?php echo htmlspecialchars($nombreComercial); ?>" placeholder="Opcional">
                </div>
            </div>

            <fieldset class="prov-dir-fieldset">
                <legend><i class="bi bi-geo-alt"></i> Direccion</legend>

                <?php if ($forzarDireccionProv): ?>
                    <input type="hidden" name="incluir_direccion" value="1">
                    <input type="hidden" name="modo_direccion_prov" value="catalogo">
                    <input type="hidden" name="nueva_direccion_rapida" value="0">
                    <p class="form-hint" style="font-weight:600;"><i class="bi bi-info-circle"></i> Este proveedor ya tiene direccion. Debes mantener una opcion del catalogo.</p>
                    <div class="form-group">
                        <label for="id_direccion_FK"><i class="bi bi-geo-alt"></i> Direccion del catalogo:</label>
                        <select class="form-input" name="id_direccion_FK" id="id_direccion_FK" required>
                            <option value="">-- Selecciona --</option>
                            <?php foreach (($direcciones ?? []) as $direccion): ?>
                                <?php
                                $texto = ($direccion['nom_calle'] ?? '') . ' #' . ($direccion['num_exterior'] ?? '');
                                if (!empty($direccion['num_interior'])) {
                                    $texto .= ' Int. ' . $direccion['num_interior'];
                                }
                                $texto .= ', ' . ($direccion['nom_colonia'] ?? '') . ', CP ' . ($direccion['codigo_postal'] ?? '');
                                ?>
                                <option value="<?php echo (int) $direccion['id_direccion']; ?>"
                                    <?php echo ((string) $idDireccion === (string) $direccion['id_direccion']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($texto); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-hint">Para alta de direccion nueva usa el módulo de direcciones o da de alta desde otro formulario primero.</small>
                    </div>
                <?php else: ?>

                    <div class="form-group">
                        <label for="prov_incluir_direccion"><strong>Desea agregar dirección del proveedor?</strong></label>
                        <select class="form-input" id="prov_incluir_direccion" name="incluir_direccion" style="max-width:340px;"
                                onchange="provSincronizarDireccionUi()">
                            <option value="0" <?php echo ((string) $incluir_direccion_prov !== '1') ? 'selected' : ''; ?>>No</option>
                            <option value="1" <?php echo ((string) $incluir_direccion_prov === '1') ? 'selected' : ''; ?>>Si</option>
                        </select>
                    </div>

                    <div id="prov_modo_wrap">
                        <p class="form-hint"><i class="bi bi-info-circle"></i> Elija usar una direccion existente o crear una nueva (rapida).</p>
                        <div class="prov-dir-mode">
                            <label>
                                <input type="radio" name="modo_direccion_prov" id="prov_modo_catalogo" value="catalogo"
                                       <?php echo ($modo_direccion_def === 'catalogo') ? 'checked' : ''; ?> onchange="provSincronizarDireccionUi()">
                                Direccion existente (catalogo)
                            </label>
                            <label>
                                <input type="radio" name="modo_direccion_prov" id="prov_modo_rapida" value="rapida"
                                       <?php echo ($modo_direccion_def === 'rapida') ? 'checked' : ''; ?> onchange="provSincronizarDireccionUi()">
                                Nueva direccion (captura rapida)
                            </label>
                        </div>

                        <div id="bloque_dir_catalogo_prov">
                            <div class="form-group">
                                <label for="id_direccion_FK"><i class="bi bi-list-ul"></i> Seleccionar direccion:</label>
                                <select class="form-input" name="id_direccion_FK" id="id_direccion_FK">
                                    <option value="">-- Selecciona --</option>
                                    <?php foreach (($direcciones ?? []) as $direccion): ?>
                                        <?php
                                        $texto = ($direccion['nom_calle'] ?? '') . ' #' . ($direccion['num_exterior'] ?? '');
                                        if (!empty($direccion['num_interior'])) {
                                            $texto .= ' Int. ' . $direccion['num_interior'];
                                        }
                                        $texto .= ', ' . ($direccion['nom_colonia'] ?? '') . ', CP ' . ($direccion['codigo_postal'] ?? '');
                                        ?>
                                        <option value="<?php echo (int) $direccion['id_direccion']; ?>"
                                            <?php echo ((string) $idDireccion === (string) $direccion['id_direccion']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($texto); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div id="bloque_dir_rapida_prov">
                            <?php
                            $dir = $dirProvRapida;
                            $dirOpts = $dirOptsProvRapida;
                            require __DIR__ . '/../partials/direccion_form.php';
                            ?>
                        </div>
                    </div>
                <?php endif; ?>
            </fieldset>

            <div class="form-row">
                <div class="form-group">
                    <label for="rfc"><i class="bi bi-card-text"></i> RFC:</label>
                    <input type="text" class="form-input" name="rfc" id="rfc" maxlength="13"
                           value="<?php echo htmlspecialchars($rfc); ?>" placeholder="Opcional">
                </div>

                <div class="form-group">
                    <label for="tipo_persona"><i class="bi bi-person-vcard"></i> Tipo de Persona:</label>
                    <select class="form-input" name="tipo_persona" id="tipo_persona">
                        <option value="">-- Opcional --</option>
                        <option value="Fisica" <?php echo $tipoPersona === 'Fisica' ? 'selected' : ''; ?>>Fisica</option>
                        <option value="Moral" <?php echo $tipoPersona === 'Moral' ? 'selected' : ''; ?>>Moral</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="observaciones"><i class="bi bi-chat-left-text"></i> Observaciones:</label>
                <textarea class="form-input" name="observaciones" id="observaciones" rows="3"><?php echo htmlspecialchars($observaciones); ?></textarea>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-action-primary">
                    <i class="bi bi-check-lg"></i> <?php echo $esEdicion ? 'Guardar Cambios' : 'Guardar'; ?>
                </button>
                <a href="proveedores.php?accion=leer" class="btn-action-secondary">
                    <i class="bi bi-x-lg"></i> Cancelar
                </a>
            </div>
        </form>
    <?php else: ?>
        <div class="alert-message error">
            <p><i class="bi bi-exclamation-triangle"></i> No se encontró el proveedor. <a href="proveedores.php?accion=leer">Volver al listado</a></p>
        </div>
    <?php endif; ?>
</div>

<script src="js/fk-autocomplete.js"></script>
<script>
function provIncluirOn() {
    var s = document.getElementById('prov_incluir_direccion');
    return !s || String(s.value) === '1';
}

function provModoRapida() {
    var r = document.getElementById('prov_modo_rapida');
    return r && r.checked;
}

function provSincronizarDireccionUi() {
    var incluir = provIncluirOn();
    var wrap = document.getElementById('prov_modo_wrap');
    if (wrap) {
        wrap.style.display = incluir ? '' : 'none';
        wrap.style.opacity = incluir ? '1' : '0.5';
        if (!incluir) {
            wrap.querySelectorAll('input, select, textarea').forEach(function (el) {
                if (el.name === 'incluir_direccion') return;
                if (el.type === 'radio') return;
                el.removeAttribute('required');
            });
        }
    }
    var bloqCat = document.getElementById('bloque_dir_catalogo_prov');
    var bloqRap = document.getElementById('bloque_dir_rapida_prov');
    var selCat = document.getElementById('id_direccion_FK');

    var rapida = incluir && provModoRapida();
    var catalogo = incluir && !provModoRapida();

    if (bloqCat) bloqCat.style.display = catalogo ? '' : 'none';
    if (bloqRap) bloqRap.style.display = rapida ? '' : 'none';

    if (selCat) {
        if (catalogo && incluir) {
            selCat.removeAttribute('disabled');
            selCat.setAttribute('required', 'required');
        } else {
            selCat.removeAttribute('required');
            selCat.setAttribute('disabled', 'disabled');
            selCat.value = '';
        }
    }
    document.querySelectorAll('#bloque_dir_rapida_prov [data-dir-req]').forEach(function (el) {
        if (!rapida) {
            el.removeAttribute('required');
            el.setAttribute('disabled', 'disabled');
        } else {
            el.removeAttribute('disabled');
            el.setAttribute('required', 'required');
        }
    });
    document.querySelectorAll('input[name="modo_direccion_prov"]').forEach(function (r) {
        r.disabled = !incluir;
    });
}

document.addEventListener('DOMContentLoaded', function () {
    if (window.JoyeriaFkAutocomplete) {
        JoyeriaFkAutocomplete.initSelectAutocomplete({
            selectId: 'id_direccion_FK',
            allowEmpty: true,
            placeholder: 'Buscar direccion...'
        });
    }
    provSincronizarDireccionUi();
});
</script>
