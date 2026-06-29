<?php
$esEdicion = isset($contacto) && !empty($contacto);
$titulo = $esEdicion ? 'Editar Contacto de Proveedor' : 'Nuevo Contacto de Proveedor';
$accionForm = $esEdicion
    ? 'proveedor_contactos.php?accion=actualizar&id=' . urlencode((string) $contacto['id_contacto'])
    : 'proveedor_contactos.php?accion=crear';

$idProveedor = $_POST['id_proveedor_FK'] ?? ($esEdicion ? ($contacto['id_proveedor_FK'] ?? '') : '');
$nombre = $_POST['nombre'] ?? ($esEdicion ? ($contacto['nombre'] ?? '') : '');
$telefono = $_POST['telefono'] ?? ($esEdicion ? ($contacto['telefono'] ?? '') : '');
$correo = $_POST['correo'] ?? ($esEdicion ? ($contacto['correo'] ?? '') : '');
$puesto = $_POST['puesto'] ?? ($esEdicion ? ($contacto['puesto'] ?? '') : '');
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

    <?php if (!$esEdicion || !empty($contacto)): ?>
        <form action="<?php echo htmlspecialchars($accionForm); ?>" method="POST" class="admin-form">
            <div class="form-row">
                <div class="form-group">
                    <label for="id_proveedor_FK"><i class="bi bi-truck"></i> Proveedor:</label>
                    <select class="form-input" name="id_proveedor_FK" id="id_proveedor_FK" required>
                        <option value="">-- Selecciona un proveedor --</option>
                        <?php foreach (($proveedores ?? []) as $proveedor): ?>
                            <option value="<?php echo (int) $proveedor['id_proveedor']; ?>"
                                <?php echo ((string) $idProveedor === (string) $proveedor['id_proveedor']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars((string) $proveedor['razon_social']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="nombre"><i class="bi bi-person"></i> Nombre del Contacto:</label>
                    <input type="text" class="form-input" name="nombre" id="nombre" maxlength="100"
                           value="<?php echo htmlspecialchars((string) $nombre); ?>" required autofocus>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="telefono"><i class="bi bi-telephone"></i> Teléfono:</label>
                    <input type="text" class="form-input" name="telefono" id="telefono" maxlength="20"
                           value="<?php echo htmlspecialchars((string) $telefono); ?>">
                </div>

                <div class="form-group">
                    <label for="correo"><i class="bi bi-envelope"></i> Correo:</label>
                    <input type="email" class="form-input" name="correo" id="correo" maxlength="80"
                           value="<?php echo htmlspecialchars((string) $correo); ?>">
                </div>
            </div>

            <div class="form-group">
                <label for="puesto"><i class="bi bi-briefcase"></i> Puesto:</label>
                <input type="text" class="form-input" name="puesto" id="puesto" maxlength="50"
                       value="<?php echo htmlspecialchars((string) $puesto); ?>">
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-action-primary">
                    <i class="bi bi-check-lg"></i> <?php echo $esEdicion ? 'Guardar Cambios' : 'Guardar'; ?>
                </button>
                <a href="proveedor_contactos.php?accion=leer" class="btn-action-secondary">
                    <i class="bi bi-x-lg"></i> Cancelar
                </a>
            </div>
        </form>
    <?php else: ?>
        <div class="alert-message error">
            <p><i class="bi bi-exclamation-triangle"></i> No se encontró el contacto. <a href="proveedor_contactos.php?accion=leer">Volver al listado</a></p>
        </div>
    <?php endif; ?>
</div>
<script src="js/fk-autocomplete.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (!window.JoyeriaFkAutocomplete) return;
    JoyeriaFkAutocomplete.initSelectAutocomplete({ selectId: 'id_proveedor_FK', allowEmpty: false, placeholder: 'Buscar proveedor...' });
});
</script>
