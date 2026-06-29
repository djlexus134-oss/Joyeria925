<?php
require_once __DIR__ . '/../../includes/form_defaults.php';

$esEdicion = isset($historico) && !empty($historico);
$titulo = $esEdicion ? 'Editar Historico de Impuesto' : 'Nuevo Historico de Impuesto';
$accionForm = $esEdicion
    ? 'impuestos_historico.php?accion=actualizar&id=' . urlencode((string) $historico['id_impuesto_historico'])
    : 'impuestos_historico.php?accion=crear';

$idImpuesto = $_POST['id_impuesto_FK'] ?? ($esEdicion ? ($historico['id_impuesto_FK'] ?? '') : (!empty($idImpuestoDefault) ? (string) (int) $idImpuestoDefault : ''));
$porcentaje = $_POST['porcentaje'] ?? ($esEdicion ? ($historico['porcentaje'] ?? '') : '');
$fechaInicio = joyeria_form_date_value(
    isset($_POST['fecha_inicio']) ? (string) $_POST['fecha_inicio'] : null,
    $esEdicion ? (string) ($historico['fecha_inicio'] ?? '') : null,
    $esEdicion
);
$fechaFin = joyeria_form_date_value(
    isset($_POST['fecha_fin']) ? (string) $_POST['fecha_fin'] : null,
    $esEdicion ? (string) ($historico['fecha_fin'] ?? '') : null,
    $esEdicion,
    ''
);
$activo = $_POST['activo'] ?? ($esEdicion ? ((string)($historico['activo'] ?? '1')) : '1');
$observaciones = $_POST['observaciones'] ?? ($esEdicion ? ($historico['observaciones'] ?? '') : '');
?>

<div class="form-section">
    <h3><i class="bi <?php echo $esEdicion ? 'bi-pencil-square' : 'bi-plus-circle'; ?>"></i> <?php echo htmlspecialchars($titulo); ?></h3>

    <?php if(isset($mensaje) && !empty($mensaje)): ?>
        <div class="alert-message info"><p><i class="bi bi-info-circle"></i> <?php echo htmlspecialchars($mensaje); ?></p></div>
    <?php endif; ?>

    <form action="<?php echo htmlspecialchars($accionForm); ?>" method="POST" class="admin-form">
        <div class="form-row">
            <div class="form-group">
                <label for="id_impuesto_FK">Impuesto:</label>
                <select class="form-input" name="id_impuesto_FK" id="id_impuesto_FK" required>
                    <option value="">-- Selecciona impuesto --</option>
                    <?php foreach ($impuestosBase as $imp): ?>
                        <option value="<?php echo $imp['id_impuesto']; ?>" <?php echo ((string)$idImpuesto === (string)$imp['id_impuesto']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($imp['tipo_impuesto']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="porcentaje">Porcentaje:</label>
                <input type="number" class="form-input" name="porcentaje" id="porcentaje" step="0.01" min="0" max="100" value="<?php echo htmlspecialchars((string)$porcentaje); ?>" required>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="fecha_inicio">Fecha inicio:</label>
                <input type="date" class="form-input" name="fecha_inicio" id="fecha_inicio" value="<?php echo htmlspecialchars((string)$fechaInicio); ?>" required>
            </div>
            <div class="form-group">
                <label for="fecha_fin">Fecha fin:</label>
                <input type="date" class="form-input" name="fecha_fin" id="fecha_fin" value="<?php echo htmlspecialchars((string)$fechaFin); ?>">
            </div>
            <div class="form-group">
                <label for="activo">Activo:</label>
                <select class="form-input" name="activo" id="activo">
                    <option value="1" <?php echo $activo === '1' ? 'selected' : ''; ?>>Si</option>
                    <option value="0" <?php echo $activo === '0' ? 'selected' : ''; ?>>No</option>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label for="observaciones">Observaciones:</label>
            <textarea class="form-input" name="observaciones" id="observaciones" rows="4"><?php echo htmlspecialchars($observaciones); ?></textarea>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn-action-primary"><i class="bi bi-check-lg"></i> Guardar</button>
            <a href="impuestos_historico.php?accion=leer" class="btn-action-secondary"><i class="bi bi-x-lg"></i> Cancelar</a>
        </div>
    </form>
</div>
<script src="js/fk-autocomplete.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (!window.JoyeriaFkAutocomplete) return;
    JoyeriaFkAutocomplete.initSelectAutocomplete({ selectId: 'id_impuesto_FK', allowEmpty: false, placeholder: 'Buscar impuesto...' });
});
</script>
