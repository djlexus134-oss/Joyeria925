<?php
require_once __DIR__ . '/../../includes/form_defaults.php';

$esEdicion = isset($gasto) && !empty($gasto);
$titulo = $esEdicion ? 'Editar Gasto' : 'Nuevo Gasto';
$accionForm = $esEdicion
    ? 'gastos.php?accion=actualizar&id=' . urlencode((string) $gasto['id_gasto'])
    : 'gastos.php?accion=crear';

$id_categoria_FK = $_POST['id_categoria_FK'] ?? ($esEdicion ? ($gasto['id_categoria_FK'] ?? '') : '');
$concepto = $_POST['concepto'] ?? ($esEdicion ? ($gasto['concepto'] ?? '') : '');
$monto = $_POST['monto'] ?? ($esEdicion ? ($gasto['monto'] ?? '') : '');
$fecha_gasto = joyeria_form_date_value(
    isset($_POST['fecha_gasto']) ? (string) $_POST['fecha_gasto'] : null,
    $esEdicion ? (string) ($gasto['fecha_gasto'] ?? '') : null,
    $esEdicion
);
$id_forma_pago_FK = $_POST['id_forma_pago_FK'] ?? ($esEdicion ? ($gasto['id_forma_pago_FK'] ?? '') : (!empty($idFormaPagoDefault) ? (string) (int) $idFormaPagoDefault : ''));
$id_empleado_FK = $_POST['id_empleado_FK'] ?? ($esEdicion ? ($gasto['id_empleado_FK'] ?? '') : '');
$afecta_caja = $_POST['afecta_caja'] ?? ($esEdicion ? ((int) ($gasto['afecta_caja'] ?? 1) === 1 ? '1' : '') : '1');
$observaciones = $_POST['observaciones'] ?? ($esEdicion ? ($gasto['observaciones'] ?? '') : '');
?>

<div class="form-section">
    <h3><i class="bi <?php echo $esEdicion ? 'bi-pencil-square' : 'bi-plus-circle'; ?>"></i> <?php echo htmlspecialchars($titulo); ?></h3>

    <?php if (isset($mensaje) && !empty($mensaje)): ?>
        <?php
        $tipoAlertaForm = $mensajeTipo ?? 'error';
        if (!in_array($tipoAlertaForm, ['success', 'error', 'info'], true)) {
            $tipoAlertaForm = 'error';
        }
        $iconoAlertaForm = $tipoAlertaForm === 'success' ? 'bi-check-circle' : ($tipoAlertaForm === 'info' ? 'bi-info-circle' : 'bi-exclamation-triangle');
        ?>
        <div class="alert-message <?php echo htmlspecialchars($tipoAlertaForm); ?>">
            <p><i class="bi <?php echo htmlspecialchars($iconoAlertaForm); ?>"></i> <?php echo htmlspecialchars($mensaje); ?></p>
        </div>
    <?php endif; ?>

    <?php if (!$esEdicion || !empty($gasto)): ?>
        <form action="<?php echo htmlspecialchars($accionForm); ?>" method="POST" class="admin-form">
            <div class="form-row">
                <div class="form-group">
                    <label for="id_categoria_FK"><i class="bi bi-tags"></i> Categoria:</label>
                    <div style="display:flex;gap:10px;align-items:flex-end;">
                        <div style="flex:1 1 auto;min-width:220px;">
                            <select class="form-input" name="id_categoria_FK" id="id_categoria_FK" required>
                                <option value="">-- Selecciona categoria --</option>
                                <?php foreach (($catalogos['categorias'] ?? []) as $categoria): ?>
                                    <option value="<?php echo (int) $categoria['id_categoria_gasto']; ?>" <?php echo ((string) $id_categoria_FK === (string) $categoria['id_categoria_gasto']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars((string) $categoria['nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="button" class="btn-action-secondary" id="btn-nueva-categoria-gasto" style="white-space:nowrap;">
                            <i class="bi bi-plus-circle"></i> Nueva
                        </button>
                    </div>
                    <small class="form-hint"><i class="bi bi-info-circle"></i> Escribe para buscar; si no existe, crea una nueva.</small>
                </div>

                <div class="form-group">
                    <label><i class="bi bi-person-badge"></i> Empleado:</label>
                    <input type="text" class="form-input" value="<?php echo htmlspecialchars((string) ($authUser['nombre_completo'] ?? 'Usuario actual')); ?>" readonly>
                    <small class="form-hint"><i class="bi bi-info-circle"></i> Se asigna automaticamente desde la sesion activa.</small>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="concepto"><i class="bi bi-card-text"></i> Concepto:</label>
                    <input type="text" class="form-input" name="concepto" id="concepto" maxlength="150" value="<?php echo htmlspecialchars((string) $concepto); ?>" required autofocus>
                </div>

                <div class="form-group">
                    <label for="monto"><i class="bi bi-currency-dollar"></i> Monto:</label>
                    <input type="number" class="form-input" name="monto" id="monto" step="0.01" min="0.01" value="<?php echo htmlspecialchars((string) $monto); ?>" required>
                </div>

                <div class="form-group">
                    <label for="fecha_gasto"><i class="bi bi-calendar-date"></i> Fecha:</label>
                    <input type="date" class="form-input" name="fecha_gasto" id="fecha_gasto" value="<?php echo htmlspecialchars((string) $fecha_gasto); ?>" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="id_forma_pago_FK"><i class="bi bi-credit-card"></i> Forma de Pago:</label>
                    <select class="form-input" name="id_forma_pago_FK" id="id_forma_pago_FK">
                        <option value="">-- Sin forma de pago --</option>
                        <?php foreach (($catalogos['formas_pago'] ?? []) as $formaPago): ?>
                            <option value="<?php echo (int) $formaPago['id_forma_pago']; ?>" <?php echo ((string) $id_forma_pago_FK === (string) $formaPago['id_forma_pago']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars((string) $formaPago['forma_pago']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" style="display:flex;align-items:center;gap:10px;padding-top:26px;">
                    <input type="checkbox" name="afecta_caja" id="afecta_caja" value="1" <?php echo ((string) $afecta_caja === '1') ? 'checked' : ''; ?>>
                    <label for="afecta_caja" style="margin:0;"><i class="bi bi-cash-coin"></i> Afecta caja</label>
                </div>
            </div>

            <div class="form-group">
                <label for="observaciones"><i class="bi bi-chat-text"></i> Observaciones:</label>
                <textarea class="form-input" name="observaciones" id="observaciones" rows="4"><?php echo htmlspecialchars((string) $observaciones); ?></textarea>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-action-primary"><i class="bi bi-check-lg"></i> Guardar</button>
                <a href="gastos.php?accion=leer" class="btn-action-secondary"><i class="bi bi-x-lg"></i> Cancelar</a>
            </div>
        </form>
    <?php else: ?>
        <div class="alert-message error">
            <p><i class="bi bi-exclamation-triangle"></i> No se encontró el gasto. <a href="gastos.php?accion=leer">Volver al listado</a></p>
        </div>
    <?php endif; ?>
</div>

<script src="js/fk-autocomplete.js"></script>

<script>
    (function () {
        var categoriaAuto = null;
        if (window.JoyeriaFkAutocomplete) {
            JoyeriaFkAutocomplete.initSelectAutocomplete({
                selectId: 'id_forma_pago_FK',
                allowEmpty: true,
                placeholder: 'Buscar forma de pago...'
            });
            categoriaAuto = JoyeriaFkAutocomplete.initSelectAutocomplete({
                selectId: 'id_categoria_FK',
                allowEmpty: false,
                placeholder: 'Buscar categoria...'
            });
        }

        var categoriaSelect = document.getElementById('id_categoria_FK');
        var btnNuevaCategoria = document.getElementById('btn-nueva-categoria-gasto');
        if (!categoriaSelect) {
            return;
        }

        function abrirNuevaCategoria() {
            var nombre = prompt('Nombre de la nueva categoria de gasto:');
            if (!nombre) {
                return;
            }

            fetch('api/gastos_categorias.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ nombre: nombre })
            })
                .then(function (r) { return r.json(); })
                .then(function (json) {
                    if (!json || json.success !== true || !json.data) {
                        alert('No se pudo crear la categoria de gasto.');
                        return;
                    }
                    var opt = document.createElement('option');
                    opt.value = String(json.data.id_categoria_gasto);
                    opt.textContent = String(json.data.nombre || nombre);
                    categoriaSelect.appendChild(opt);
                    categoriaSelect.value = String(json.data.id_categoria_gasto);
                    if (categoriaAuto) {
                        categoriaAuto.refresh();
                    }
                })
                .catch(function () {
                    alert('No se pudo crear la categoria de gasto.');
                });
        }

        if (btnNuevaCategoria) {
            btnNuevaCategoria.addEventListener('click', abrirNuevaCategoria);
        }
    })();
</script>
