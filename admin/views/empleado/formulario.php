<?php
$esEdicion = isset($empleado) && !empty($empleado);
$titulo = $esEdicion ? 'Editar Empleado' : 'Nuevo Empleado';
$accionForm = $esEdicion
    ? 'empleado.php?accion=actualizar&id=' . urlencode((string) $empleado['id_empleado'])
    : 'empleado.php?accion=crear';

// Valores del formulario
$nombre = $_POST['nombre'] ?? ($esEdicion ? ($empleado['nombre'] ?? '') : '');
$primer_apellido = $_POST['primer_apellido'] ?? ($esEdicion ? ($empleado['primer_apellido'] ?? '') : '');
$segundo_apellido = $_POST['segundo_apellido'] ?? ($esEdicion ? ($empleado['segundo_apellido'] ?? '') : '');
$correo = $_POST['correo'] ?? ($esEdicion ? ($empleado['correo'] ?? '') : '');
$telefono = $_POST['telefono'] ?? ($esEdicion ? ($empleado['telefono'] ?? '') : '');
$contrasena = '';
$id_puesto_FK = $_POST['id_puesto_FK'] ?? ($esEdicion ? ($empleado['id_puesto_FK'] ?? '') : '');
$salario = $_POST['salario'] ?? ($esEdicion ? ($empleado['salario'] ?? '') : '');
$curp = $_POST['curp'] ?? ($esEdicion ? ($empleado['curp'] ?? '') : '');
$rfc = $_POST['rfc'] ?? ($esEdicion ? ($empleado['rfc'] ?? '') : '');
$nss = $_POST['nss'] ?? ($esEdicion ? ($empleado['nss'] ?? '') : '');
$num_exterior = $_POST['num_exterior'] ?? ($esEdicion ? ($empleado['num_exterior'] ?? '') : '');
$num_interior = $_POST['num_interior'] ?? ($esEdicion ? ($empleado['num_interior'] ?? '') : '');
$id_pais_FK = $_POST['id_pais_FK'] ?? ($esEdicion ? ($empleado['id_pais'] ?? '') : '');
$id_estado_FK = $_POST['id_estado_FK'] ?? ($esEdicion ? ($empleado['id_estado'] ?? '') : '');
$id_municipio_FK = $_POST['id_municipio_FK'] ?? ($esEdicion ? ($empleado['id_municipio'] ?? '') : '');
$id_localidad_FK = $_POST['id_localidad_FK'] ?? ($esEdicion ? ($empleado['id_localidad'] ?? '') : '');
$id_colonia_FK = $_POST['id_colonia_FK'] ?? ($esEdicion ? ($empleado['id_colonia'] ?? '') : '');
$id_codigo_postal_FK = $_POST['id_codigo_postal_FK'] ?? ($esEdicion ? ($empleado['id_codigo_postal'] ?? '') : '');
$id_calle_FK = $_POST['id_calle_FK'] ?? ($esEdicion ? ($empleado['id_calle'] ?? '') : '');
$nom_colonia_ini = $esEdicion ? ($empleado['nom_colonia'] ?? '') : '';
$nom_calle_ini = $esEdicion ? ($empleado['nom_calle'] ?? '') : '';

$idDirFkEmp = null;
if ($esEdicion && is_array($empleado ?? null)) {
    $idDirFkEmp = $empleado['id_direccion_FK'] ?? $empleado['id_direccion'] ?? null;
}
$forzarDireccionEmp = $esEdicion && $idDirFkEmp !== null && $idDirFkEmp !== '' && (int) $idDirFkEmp > 0;
$incluir_direccion_emp_val = $_POST['incluir_direccion'] ?? ($forzarDireccionEmp ? '1' : '0');

$nom_pais = $esEdicion ? ($empleado['nom_pais'] ?? '') : '';
$nom_estado = $esEdicion ? ($empleado['nom_estado'] ?? '') : '';
$nom_municipio = $esEdicion ? ($empleado['nom_municipio'] ?? '') : '';
$nom_localidad = $esEdicion ? ($empleado['nom_localidad'] ?? '') : '';

$dir = [
    'id_pais_FK' => $id_pais_FK,
    'nom_pais' => $nom_pais,
    'id_estado_FK' => $id_estado_FK,
    'nom_estado' => $nom_estado,
    'id_municipio_FK' => $id_municipio_FK,
    'nom_municipio' => $nom_municipio,
    'id_localidad_FK' => $id_localidad_FK,
    'nom_localidad' => $nom_localidad,
    'id_codigo_postal_FK' => $id_codigo_postal_FK,
    'codigo_postal' => $esEdicion ? ($empleado['codigo_postal'] ?? '') : '',
    'id_colonia_FK' => $id_colonia_FK,
    'nom_colonia' => $nom_colonia_ini,
    'id_calle_FK' => $id_calle_FK,
    'nom_calle' => $nom_calle_ini,
    'num_exterior' => $num_exterior,
    'num_interior' => $num_interior,
];
$dirOpts = [
    'omit_fieldset' => true,
    'prefix' => '',
    'feedback_id' => 'direccion_accion_feedback',
    'fieldset_title' => 'Direccion',
    'root_id' => 'joyeria_dir_empleado',
    'api_prefix' => './api/',
    'style_fieldset' => 'margin-top:12px;padding-top:12px;border-top:1px solid #e8e4dc;',
    'data_dir_req' => true,
    'num_exterior_id' => 'num_exterior',
    'num_interior_id' => 'num_interior',
];
?>

<div class="form-section">
    <style>
        .wizard-steps {
            display: flex;
            gap: 10px;
            margin: 10px 0 16px;
            flex-wrap: wrap;
        }

        .wizard-step {
            border: 1px solid #ced4da;
            border-radius: 999px;
            padding: 6px 12px;
            font-size: 0.9rem;
            background: #f8f9fa;
            color: #4a5568;
        }

        .wizard-step.active {
            background: #0d6efd;
            border-color: #0d6efd;
            color: #ffffff;
            font-weight: 600;
        }

        .inline-create {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .inline-create .form-input {
            flex: 1;
        }

        .inline-create-feedback {
            margin-top: 8px;
            font-size: 0.9rem;
            color: #0b5ed7;
        }

        .joyeria-dir-combobox-wrap {
            position: relative;
        }

        .joyeria-dir-combobox-dd {
            position: absolute;
            left: 0;
            right: 0;
            z-index: 50;
            max-height: 220px;
            overflow-y: auto;
            margin: 2px 0 0;
            padding: 4px 0;
            list-style: none;
            background: #fff;
            border: 1px solid #ced4da;
            border-radius: 4px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .joyeria-dir-combobox-item {
            padding: 6px 10px;
            cursor: pointer;
        }

        .joyeria-dir-combobox-item:hover {
            background: rgba(11, 94, 215, 0.08);
        }
    </style>

    <h3>
        <i class="bi <?php echo $esEdicion ? 'bi-pencil-square' : 'bi-plus-circle'; ?>"></i>
        <?php echo htmlspecialchars($titulo); ?>
    </h3>

    <div class="wizard-steps" id="empleadoWizardSteps">
        <div class="wizard-step active" data-step="1">1. Datos</div>
        <div class="wizard-step" data-step="2">2. Direccion</div>
        <div class="wizard-step" data-step="3">3. Confirmar</div>
    </div>

    <?php if (!empty($mensaje)): ?>
        <div class="alert-message info">
            <p><i class="bi bi-info-circle"></i> <?php echo htmlspecialchars($mensaje); ?></p>
        </div>
    <?php endif; ?>

    <?php if (!$esEdicion || !empty($empleado)): ?>
        <form action="<?php echo htmlspecialchars($accionForm); ?>" method="POST" class="admin-form" id="empleadoFormWizard">

            <div class="wizard-panel" data-panel="1">
            
            <!-- SECCIÓN: INFORMACIÓN PERSONAL -->
            <fieldset class="form-fieldset">
                <legend><i class="bi bi-person"></i> Información Personal</legend>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="nombre">
                            <i class="bi bi-person-badge"></i> Nombre:
                        </label>
                        <input type="text"
                            class="form-input"
                            name="nombre"
                            id="nombre"
                            maxlength="50"
                            value="<?php echo htmlspecialchars($nombre); ?>"
                            placeholder="Ej. Juan"
                            required
                            autofocus>
                        <small class="form-hint"><i class="bi bi-info-circle"></i> Máximo 50 caracteres.</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="primer_apellido">
                            <i class="bi bi-person-badge"></i> Primer Apellido:
                        </label>
                        <input type="text"
                            class="form-input"
                            name="primer_apellido"
                            id="primer_apellido"
                            maxlength="25"
                            value="<?php echo htmlspecialchars($primer_apellido); ?>"
                            placeholder="Ej. García"
                            required>
                        <small class="form-hint"><i class="bi bi-info-circle"></i> Máximo 25 caracteres.</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="segundo_apellido">
                            <i class="bi bi-person-badge"></i> Segundo Apellido:
                        </label>
                        <input type="text"
                            class="form-input"
                            name="segundo_apellido"
                            id="segundo_apellido"
                            maxlength="25"
                            value="<?php echo htmlspecialchars($segundo_apellido); ?>"
                            placeholder="Ej. López (opcional)">
                        <small class="form-hint"><i class="bi bi-info-circle"></i> Máximo 25 caracteres.</small>
                    </div>
                </div>
            </fieldset>

            <!-- SECCIÓN: INFORMACIÓN LABORAL -->
            <fieldset class="form-fieldset">
                <legend><i class="bi bi-briefcase"></i> Información Laboral</legend>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="id_puesto_FK">
                            <i class="bi bi-briefcase"></i> Puesto:
                        </label>
                        <select class="form-input"
                            name="id_puesto_FK"
                            id="id_puesto_FK"
                            required>
                            <option value="">-- Selecciona un puesto --</option>
                            <?php if (!empty($puestos)): ?>
                                <?php foreach ($puestos as $puesto): ?>
                                    <option value="<?php echo $puesto['id_puesto']; ?>"
                                        <?php echo ($id_puesto_FK == $puesto['id_puesto']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($puesto['nombre_puesto']); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                        <small class="form-hint"><i class="bi bi-info-circle"></i> Selecciona el puesto del empleado.</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="salario">
                            <i class="bi bi-currency-dollar"></i> Salario:
                        </label>
                        <input type="number"
                            class="form-input"
                            name="salario"
                            id="salario"
                            step="0.01"
                            min="0"
                            value="<?php echo htmlspecialchars($salario); ?>"
                            placeholder="Ej. 15000.00"
                            required>
                        <small class="form-hint"><i class="bi bi-info-circle"></i> Ingresa el salario mencionado.</small>
                    </div>
                </div>
            </fieldset>

            <!-- SECCIÓN: DOCUMENTOS -->
            <fieldset class="form-fieldset">
                <legend><i class="bi bi-file-earmark-text"></i> Documentos Oficiales</legend>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="curp">
                            <i class="bi bi-card-text"></i> CURP:
                        </label>
                        <input type="text"
                            class="form-input"
                            name="curp"
                            id="curp"
                            maxlength="18"
                            value="<?php echo htmlspecialchars($curp); ?>"
                            placeholder="Ej. GARL000101HDFRRL09"
                            required>
                        <small class="form-hint"><i class="bi bi-info-circle"></i> Máximo 18 caracteres.</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="rfc">
                            <i class="bi bi-card-text"></i> RFC:
                        </label>
                        <input type="text"
                            class="form-input"
                            name="rfc"
                            id="rfc"
                            maxlength="13"
                            value="<?php echo htmlspecialchars($rfc); ?>"
                            placeholder="Ej. GARL000101ABC"
                            required>
                        <small class="form-hint"><i class="bi bi-info-circle"></i> Máximo 13 caracteres.</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="nss">
                            <i class="bi bi-shield-check"></i> NSS (Seguro Social):
                        </label>
                        <input type="text"
                            class="form-input"
                            name="nss"
                            id="nss"
                            maxlength="11"
                            value="<?php echo htmlspecialchars($nss); ?>"
                            placeholder="Ej. 12345678901 (opcional)">
                        <small class="form-hint"><i class="bi bi-info-circle"></i> Máximo 11 caracteres.</small>
                    </div>
                </div>
            </fieldset>

            <!-- SECCIÓN: CONTACTO -->
            <fieldset class="form-fieldset">
                <legend><i class="bi bi-telephone"></i> Información de Contacto</legend>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="correo">
                            <i class="bi bi-envelope"></i> Correo Electrónico:
                        </label>
                        <input type="email"
                            class="form-input"
                            name="correo"
                            id="correo"
                            maxlength="80"
                            value="<?php echo htmlspecialchars($correo); ?>"
                            placeholder="Ej. empleado@example.com"
                            required>
                        <small class="form-hint"><i class="bi bi-info-circle"></i> Máximo 80 caracteres.</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="telefono">
                            <i class="bi bi-telephone"></i> Teléfono:
                        </label>
                        <input type="tel"
                            class="form-input"
                            name="telefono"
                            id="telefono"
                            maxlength="15"
                            value="<?php echo htmlspecialchars($telefono); ?>"
                            placeholder="Ej. +52-1234567890"
                            required>
                        <small class="form-hint"><i class="bi bi-info-circle"></i> Máximo 15 caracteres.</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="contrasena">
                            <i class="bi bi-lock"></i> Contraseña<?php echo $esEdicion ? ' (dejar en blanco para mantener actual)' : ':'; ?>
                        </label>
                        <input type="password"
                            class="form-input"
                            name="contrasena"
                            id="contrasena"
                            maxlength="255"
                            value=""
                            placeholder="Ingresa la contraseña"
                            <?php echo !$esEdicion ? 'required' : ''; ?>>
                        <small class="form-hint"><i class="bi bi-info-circle"></i> <?php echo $esEdicion ? 'Déjalo en blanco para mantener la contraseña actual.' : 'Ingresa una contraseña segura.'; ?></small>
                    </div>
                </div>
            </fieldset>

            <div class="form-actions">
                <button type="button" class="btn-action-primary" onclick="irPaso(2)">
                    <i class="bi bi-arrow-right"></i> Siguiente: Direccion
                </button>
            </div>
            </div>

            <div class="wizard-panel" data-panel="2" style="display:none;">

            <!-- SECCIÓN: DIRECCIÓN COMPLETA -->
            <fieldset class="form-fieldset" style="border:2px solid #c9a962;padding:1rem 1.25rem;">
                <legend><i class="bi bi-geo-alt"></i> Dirección (opcional)</legend>

                <?php if ($forzarDireccionEmp): ?>
                    <input type="hidden" name="incluir_direccion" value="1">
                    <p class="form-hint" style="font-weight:600;"><i class="bi bi-info-circle"></i> Este empleado ya tiene dirección; completa todos los campos de domicilio.</p>
                <?php else: ?>
                    <div class="form-group" style="margin-bottom:1rem;">
                        <label for="incluir_direccion_emp_select" style="font-weight:600;display:block;margin-bottom:8px;">
                            <i class="bi bi-question-circle"></i> Desea registrar dirección?
                        </label>
                        <select class="form-input" name="incluir_direccion" id="incluir_direccion_emp_select" style="max-width:340px;"
                                onchange="sincronizarDireccionEmpleadoWizard()">
                            <option value="0" <?php echo ((string) $incluir_direccion_emp_val !== '1') ? 'selected' : ''; ?>>No</option>
                            <option value="1" <?php echo ((string) $incluir_direccion_emp_val === '1') ? 'selected' : ''; ?>>Sí, registrar dirección</option>
                        </select>
                    </div>
                <?php endif; ?>

                <details class="form-row" style="margin-bottom:16px;">
                    <summary><strong>Jerarquía de dirección</strong> (búsqueda y alta en línea)</summary>
                    <small class="form-hint"><i class="bi bi-info-circle"></i> Código postal y colonia aceleran la captura; puedes crear catálogos al escribir.</small>
                </details>

                <?php require __DIR__ . '/../partials/direccion_form.php'; ?>
            </fieldset>

            <div class="form-actions">
                <button type="button" class="btn-action-secondary" onclick="irPaso(1)">
                    <i class="bi bi-arrow-left"></i> Volver
                </button>
                <button type="button" class="btn-action-primary" onclick="irPaso(3)">
                    <i class="bi bi-arrow-right"></i> Siguiente: Confirmar
                </button>
            </div>
            </div>

            <div class="wizard-panel" data-panel="3" style="display:none;">
                <fieldset class="form-fieldset">
                    <legend><i class="bi bi-check2-circle"></i> Confirmacion final</legend>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Empleado:</label>
                            <input type="text" class="form-input" id="confirm_nombre" readonly>
                        </div>
                        <div class="form-group">
                            <label>Contacto:</label>
                            <input type="text" class="form-input" id="confirm_contacto" readonly>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Puesto y salario:</label>
                            <input type="text" class="form-input" id="confirm_laboral" readonly>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Direccion:</label>
                            <input type="text" class="form-input" id="confirm_direccion" readonly>
                        </div>
                    </div>
                </fieldset>

            <!-- SECCIÓN: BOTONES -->
            <div class="form-actions">
                <button type="button" class="btn-action-secondary" onclick="irPaso(2)">
                    <i class="bi bi-arrow-left"></i> Volver
                </button>
                <button type="submit" class="btn-action-primary">
                    <i class="bi <?php echo $esEdicion ? 'bi-pencil' : 'bi-plus-lg'; ?>"></i>
                    <?php echo $esEdicion ? 'Actualizar Empleado' : 'Crear Empleado'; ?>
                </button>
                <a href="empleado.php?accion=leer" class="btn-action-secondary">
                    <i class="bi bi-arrow-left"></i> Cancelar
                </a>
            </div>
            </div>
        </form>
    <?php endif; ?>
</div>

<script>
function setDireccionFeedback(mensaje, esError = false) {
    const nodo = document.getElementById('direccion_accion_feedback');
    if (!nodo) {
        return;
    }
    nodo.textContent = mensaje || '';
    nodo.style.color = esError ? '#b02a37' : '#0b5ed7';
}

function empleadoDireccionObligatoria() {
    const hidden = document.querySelector('input[type="hidden"][name="incluir_direccion"]');
    if (hidden && hidden.value === '1') {
        return true;
    }
    const sel = document.getElementById('incluir_direccion_emp_select');
    if (sel) {
        return String(sel.value) === '1';
    }
    return true;
}

function sincronizarDireccionEmpleadoWizard() {
    const on = empleadoDireccionObligatoria();
    const panel = document.querySelector('.wizard-panel[data-panel="2"]');
    if (!panel) {
        return;
    }
    const fieldset = panel.querySelector('fieldset.form-fieldset');
    if (fieldset) {
        fieldset.style.opacity = on ? '1' : '0.55';
    }
    panel.querySelectorAll('input, select, textarea').forEach((el) => {
        if (el.name === 'incluir_direccion' || el.id === 'incluir_direccion_emp_select') {
            return;
        }
        if (!el.hasAttribute('data-dir-req')) {
            return;
        }
        el.disabled = !on;
        if (on) {
            el.setAttribute('required', 'required');
        } else {
            el.removeAttribute('required');
        }
    });
}

function irPaso(numeroPaso) {
    if (numeroPaso > 1 && !validarPaso(numeroPaso - 1)) {
        return;
    }

    if (numeroPaso === 3) {
        construirConfirmacion();
    }

    document.querySelectorAll('.wizard-panel').forEach((panel) => {
        panel.style.display = panel.dataset.panel === String(numeroPaso) ? '' : 'none';
    });

    document.querySelectorAll('.wizard-step').forEach((step) => {
        step.classList.toggle('active', step.dataset.step === String(numeroPaso));
    });

    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function validarPaso(paso) {
    const panel = document.querySelector(`.wizard-panel[data-panel="${paso}"]`);
    if (!panel) {
        return true;
    }

    const campos = panel.querySelectorAll('input, select, textarea');
    for (const campo of campos) {
        if (campo.offsetParent === null || campo.disabled) {
            continue;
        }
        if (!campo.checkValidity()) {
            campo.reportValidity();
            campo.focus();
            return false;
        }
    }

    return true;
}

function construirConfirmacion() {
    const nombre = document.getElementById('nombre').value.trim();
    const ap1 = document.getElementById('primer_apellido').value.trim();
    const ap2 = document.getElementById('segundo_apellido').value.trim();
    const correo = document.getElementById('correo').value.trim();
    const telefono = document.getElementById('telefono').value.trim();
    const puesto = document.getElementById('id_puesto_FK');
    const puestoTexto = puesto.options[puesto.selectedIndex] ? puesto.options[puesto.selectedIndex].text : '';
    const salario = document.getElementById('salario').value.trim();
    let dirTxt = '(Sin direccion registrada)';
    if (empleadoDireccionObligatoria()) {
        const calleTexto = document.getElementById('id_calle_FK_display').value.trim();
        const coloniaTexto = document.getElementById('id_colonia_FK_display').value.trim();
        const cpDisp = document.getElementById('id_codigo_postal_FK_display');
        const cpTexto = cpDisp ? cpDisp.value.trim() : '';
        const numExt = document.getElementById('num_exterior').value.trim();
        const numInt = document.getElementById('num_interior').value.trim();
        dirTxt = `${calleTexto}, Col. ${coloniaTexto}, CP ${cpTexto}, #${numExt}${numInt ? ' Int. ' + numInt : ''}`;
    }

    document.getElementById('confirm_nombre').value = `${nombre} ${ap1} ${ap2}`.trim();
    document.getElementById('confirm_contacto').value = `${correo} | ${telefono}`;
    document.getElementById('confirm_laboral').value = `${puestoTexto} | $${salario}`;
    document.getElementById('confirm_direccion').value = dirTxt;
}

document.addEventListener('DOMContentLoaded', () => {
    sincronizarDireccionEmpleadoWizard();
});
</script>

<script src="js/fk-autocomplete.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (!window.JoyeriaFkAutocomplete) return;
    JoyeriaFkAutocomplete.initSelectAutocomplete({ selectId: 'id_puesto_FK', allowEmpty: false, placeholder: 'Buscar puesto...' });
});
</script>

