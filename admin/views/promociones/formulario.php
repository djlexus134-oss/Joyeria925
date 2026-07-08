<?php
require_once __DIR__ . '/../../includes/form_defaults.php';
require_once __DIR__ . '/../../../includes/promociones_tienda_publica.php';

$esEdicion = isset($promocion) && !empty($promocion);
$titulo = $esEdicion ? 'Editar Promoción' : 'Nueva Promoción';
$accionForm = $esEdicion
    ? 'promociones.php?accion=actualizar&id=' . urlencode((string) $promocion['id_promocion'])
    : 'promociones.php?accion=crear';

$nombre = $_POST['nombre'] ?? ($esEdicion ? ($promocion['nombre'] ?? '') : '');
$porcentaje = $_POST['porcentaje_descuento'] ?? ($esEdicion ? ($promocion['porcentaje_descuento'] ?? '') : '');
$fechaInicio = joyeria_form_date_value(
    isset($_POST['fecha_inicio']) ? (string) $_POST['fecha_inicio'] : null,
    $esEdicion ? (string) ($promocion['fecha_inicio'] ?? '') : null,
    $esEdicion
);
$fechaFin = joyeria_form_date_value(
    isset($_POST['fecha_fin']) ? (string) $_POST['fecha_fin'] : null,
    $esEdicion ? (string) ($promocion['fecha_fin'] ?? '') : null,
    $esEdicion
);
$idPieza = $_POST['id_pieza_FK'] ?? ($esEdicion ? ($promocion['id_pieza_FK'] ?? '') : '');
$idSubfamilia = $_POST['id_subfamilia_FK'] ?? ($esEdicion ? ($promocion['id_subfamilia_FK'] ?? '') : '');
$idFamilia = $_POST['id_familia_FK'] ?? ($esEdicion ? ($promocion['id_familia_FK'] ?? '') : '');
$idMetal = $_POST['id_metal_FK'] ?? ($esEdicion ? ($promocion['id_metal_FK'] ?? '') : '');
$aplicaTodasFamilias = isset($_POST['aplica_todas_familias'])
    ? ((string) $_POST['aplica_todas_familias'] !== '' && (string) $_POST['aplica_todas_familias'] !== '0')
    : ($esEdicion && !empty($promocion['aplica_todas_familias']));
$observaciones = $_POST['observaciones'] ?? ($esEdicion ? ($promocion['observaciones'] ?? '') : '');
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

    <?php if (!$esEdicion || !empty($promocion)): ?>
        <div class="alert-message info mb-3">
            <p><i class="bi bi-shop"></i> Las promociones <strong>activas y vigentes</strong> se publican y aplican automáticamente en la tienda en línea (catálogo, carrito y checkout).</p>
        </div>
        <form action="<?php echo htmlspecialchars($accionForm); ?>" method="POST" class="admin-form">
            <div class="form-row">
                <div class="form-group">
                    <label for="nombre"><i class="bi bi-tag"></i> Nombre de la Promoción:<span class="required">*</span></label>
                    <input type="text" class="form-input" name="nombre" id="nombre" maxlength="100"
                           value="<?php echo htmlspecialchars((string) $nombre); ?>" required autofocus>
                </div>

                <div class="form-group">
                    <label for="porcentaje_descuento"><i class="bi bi-percent"></i> Porcentaje de Descuento:<span class="required">*</span></label>
                    <input type="number" class="form-input" name="porcentaje_descuento" id="porcentaje_descuento"
                           min="0" max="100" step="0.01" value="<?php echo htmlspecialchars((string) $porcentaje); ?>" required>
                    <small class="form-text text-muted">Hasta 2 decimales.</small>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="fecha_inicio"><i class="bi bi-calendar-event"></i> Fecha de Inicio:<span class="required">*</span></label>
                    <input type="date" class="form-input" name="fecha_inicio" id="fecha_inicio"
                           value="<?php echo htmlspecialchars((string) $fechaInicio); ?>" required>
                </div>

                <div class="form-group">
                    <label for="fecha_fin"><i class="bi bi-calendar-event"></i> Fecha de Fin:<span class="required">*</span></label>
                    <input type="date" class="form-input" name="fecha_fin" id="fecha_fin"
                           value="<?php echo htmlspecialchars((string) $fechaFin); ?>" required>
                </div>
            </div>

            <div class="form-divider">
                <p><strong>Alcance de la promoción:</strong></p>
            </div>

            <div class="form-group mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="aplica_todas_familias" id="aplica_todas_familias" value="1"
                        <?php echo $aplicaTodasFamilias ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="aplica_todas_familias">
                        <strong>Todas las familias</strong> — aplica el descuento a todo el catálogo en línea
                    </label>
                </div>
                <small class="form-text text-muted d-block mt-1">
                    Si activas esta opción, no necesitas elegir pieza, subfamilia ni familia. Las promociones más específicas (pieza, subfamilia o una familia) siguen teniendo prioridad sobre esta.
                </small>
            </div>

            <div id="promo-alcance-especifico" class="<?php echo $aplicaTodasFamilias ? 'opacity-50' : ''; ?>">
            <div class="form-row">
                <div class="form-group">
                    <label for="id_pieza_FK"><i class="bi bi-gem"></i> Pieza específica:</label>
                    <select class="form-input promo-alcance-select" name="id_pieza_FK" id="id_pieza_FK" <?php echo $aplicaTodasFamilias ? 'disabled' : ''; ?>>
                        <option value="">-- Sin pieza específica --</option>
                        <?php foreach (($catalogos['piezas'] ?? []) as $pieza): ?>
                            <option value="<?php echo (int) $pieza['id_pieza']; ?>"
                                <?php echo ((string) $idPieza === (string) $pieza['id_pieza']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars((string) $pieza['desc_pieza'] . ' - ' . $pieza['nom_sub_familia'] . ' (' . $pieza['nom_metal'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="id_subfamilia_FK"><i class="bi bi-diagram-3"></i> Subfamilia:</label>
                    <select class="form-input promo-alcance-select" name="id_subfamilia_FK" id="id_subfamilia_FK" <?php echo $aplicaTodasFamilias ? 'disabled' : ''; ?>>
                        <option value="">-- Sin subfamilia específica --</option>
                        <?php foreach (($catalogos['subfamilias'] ?? []) as $subfamilia): ?>
                            <option value="<?php echo (int) $subfamilia['id_sub_familia']; ?>"
                                <?php echo ((string) $idSubfamilia === (string) $subfamilia['id_sub_familia']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars((string) $subfamilia['nom_sub_familia']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="id_familia_FK"><i class="bi bi-diagram-2"></i> Familia:</label>
                    <select class="form-input promo-alcance-select" name="id_familia_FK" id="id_familia_FK" <?php echo $aplicaTodasFamilias ? 'disabled' : ''; ?>>
                        <option value="">-- Sin familia específica --</option>
                        <?php foreach (($catalogos['familias'] ?? []) as $familia): ?>
                            <option value="<?php echo (int) $familia['id_familia']; ?>"
                                <?php echo ((string) $idFamilia === (string) $familia['id_familia']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars((string) $familia['nom_familia']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="id_metal_FK"><i class="bi bi-gem"></i> Metal:</label>
                    <select class="form-input promo-alcance-select" name="id_metal_FK" id="id_metal_FK" <?php echo $aplicaTodasFamilias ? 'disabled' : ''; ?>>
                        <option value="">-- Sin metal específico --</option>
                        <?php foreach (($catalogos['metales'] ?? []) as $metal): ?>
                            <option value="<?php echo (int) $metal['id_metal']; ?>"
                                <?php echo ((string) $idMetal === (string) $metal['id_metal']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars((string) $metal['nom_metal']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            </div>

            <div class="form-group">
                <label for="observaciones"><i class="bi bi-chat-left-text"></i> Observaciones:</label>
                <textarea class="form-input" name="observaciones" id="observaciones" rows="4" maxlength="65535"
                          ><?php echo htmlspecialchars((string) $observaciones); ?></textarea>
            </div>

            <?php
            $previewPromo = null;
            if (trim((string) $nombre) !== '' && is_numeric($porcentaje)) {
                $previewRow = [
                    'nombre' => (string) $nombre,
                    'porcentaje_descuento' => (float) $porcentaje,
                    'fecha_inicio' => (string) $fechaInicio,
                    'fecha_fin' => (string) $fechaFin,
                    'observaciones' => (string) $observaciones,
                    'aplica_todas_familias' => $aplicaTodasFamilias ? 1 : 0,
                    'id_pieza_FK' => $aplicaTodasFamilias ? null : ($idPieza !== '' ? (int) $idPieza : null),
                    'id_subfamilia_FK' => $aplicaTodasFamilias ? null : ($idSubfamilia !== '' ? (int) $idSubfamilia : null),
                    'id_familia_FK' => $aplicaTodasFamilias ? null : ($idFamilia !== '' ? (int) $idFamilia : null),
                    'desc_pieza' => '',
                    'nom_sub_familia' => '',
                    'nom_familia' => '',
                ];
                foreach (($catalogos['piezas'] ?? []) as $pz) {
                    if ((int) ($pz['id_pieza'] ?? 0) === (int) $idPieza) {
                        $previewRow['desc_pieza'] = (string) ($pz['desc_pieza'] ?? '');
                        break;
                    }
                }
                foreach (($catalogos['subfamilias'] ?? []) as $sf) {
                    if ((int) ($sf['id_sub_familia'] ?? 0) === (int) $idSubfamilia) {
                        $previewRow['nom_sub_familia'] = (string) ($sf['nom_sub_familia'] ?? '');
                        break;
                    }
                }
                foreach (($catalogos['familias'] ?? []) as $fm) {
                    if ((int) ($fm['id_familia'] ?? 0) === (int) $idFamilia) {
                        $previewRow['nom_familia'] = (string) ($fm['nom_familia'] ?? '');
                        break;
                    }
                }
                $previewPromo = joyeria_promocion_a_stripe_catalogo($previewRow);
            }
            ?>
            <?php if (is_array($previewPromo)): ?>
            <div class="form-group">
                <label><i class="bi bi-eye"></i> Vista previa en tienda en línea</label>
                <div class="border rounded p-3 bg-light small">
                    <p class="mb-1 text-muted"><?php echo htmlspecialchars((string) ($previewPromo['eyebrow'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
                    <p class="mb-1 fw-semibold"><?php echo htmlspecialchars((string) ($previewPromo['titulo'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
                    <p class="mb-0"><?php echo htmlspecialchars((string) ($previewPromo['texto'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
            </div>
            <?php endif; ?>

            <div class="form-actions">
                <button type="submit" class="btn-action-primary">
                    <i class="bi bi-check-lg"></i> <?php echo $esEdicion ? 'Guardar Cambios' : 'Guardar'; ?>
                </button>
                <a href="promociones.php?accion=leer" class="btn-action-secondary">
                    <i class="bi bi-x-lg"></i> Cancelar
                </a>
            </div>
        </form>
    <?php else: ?>
        <div class="alert-message error">
            <p><i class="bi bi-exclamation-triangle"></i> No se encontró la promoción. <a href="promociones.php?accion=leer">Volver al listado</a></p>
        </div>
    <?php endif; ?>
</div>
<script src="js/fk-autocomplete.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var chkTodas = document.getElementById('aplica_todas_familias');
    var wrapEsp = document.getElementById('promo-alcance-especifico');
    function syncAlcancePromo() {
        var on = chkTodas && chkTodas.checked;
        if (wrapEsp) {
            wrapEsp.classList.toggle('opacity-50', on);
        }
        document.querySelectorAll('.promo-alcance-select').forEach(function (sel) {
            sel.disabled = on;
            if (on) {
                sel.value = '';
            }
        });
    }
    if (chkTodas) {
        chkTodas.addEventListener('change', syncAlcancePromo);
        syncAlcancePromo();
    }

    if (!window.JoyeriaFkAutocomplete) return;
    JoyeriaFkAutocomplete.initSelectAutocomplete({ selectId: 'id_pieza_FK', allowEmpty: true, placeholder: 'Buscar pieza...' });
    JoyeriaFkAutocomplete.initSelectAutocomplete({ selectId: 'id_subfamilia_FK', allowEmpty: true, placeholder: 'Buscar subfamilia...' });
    JoyeriaFkAutocomplete.initSelectAutocomplete({ selectId: 'id_familia_FK', allowEmpty: true, placeholder: 'Buscar familia...' });
});
</script>
