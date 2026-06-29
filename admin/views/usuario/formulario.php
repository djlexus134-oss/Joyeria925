<?php
$esEdicion = isset($usuario) && !empty($usuario);
$titulo = $esEdicion ? 'Editar Usuario' : 'Nuevo Usuario';
$accionForm = $esEdicion
    ? 'usuario.php?accion=actualizar&id=' . urlencode((string) $usuario['id_usuario'])
    : 'usuario.php?accion=crear';

$nombre = $_POST['nombre'] ?? ($esEdicion ? ($usuario['nombre'] ?? '') : '');
$primerApellido = $_POST['primer_apellido'] ?? ($esEdicion ? ($usuario['primer_apellido'] ?? '') : '');
$segundoApellido = $_POST['segundo_apellido'] ?? ($esEdicion ? ($usuario['segundo_apellido'] ?? '') : '');
$correo = $_POST['correo'] ?? ($esEdicion ? ($usuario['correo'] ?? '') : '');
$telefono = $_POST['telefono'] ?? ($esEdicion ? ($usuario['telefono'] ?? '') : '');
$idDireccion = $_POST['id_direccion_FK'] ?? ($esEdicion ? ($usuario['id_direccion_FK'] ?? '') : '');
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

    <?php if (!$esEdicion || !empty($usuario)): ?>
        <form action="<?php echo htmlspecialchars($accionForm); ?>" method="POST" class="admin-form">
            <div class="form-row">
                <div class="form-group">
                    <label for="nombre"><i class="bi bi-person"></i> Nombre:<span class="required">*</span></label>
                    <input type="text" class="form-input" name="nombre" id="nombre" maxlength="50"
                           value="<?php echo htmlspecialchars((string) $nombre); ?>" required autofocus>
                </div>

                <div class="form-group">
                    <label for="primer_apellido"><i class="bi bi-person"></i> Primer Apellido:<span class="required">*</span></label>
                    <input type="text" class="form-input" name="primer_apellido" id="primer_apellido" maxlength="25"
                           value="<?php echo htmlspecialchars((string) $primerApellido); ?>" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="segundo_apellido"><i class="bi bi-person"></i> Segundo Apellido:</label>
                    <input type="text" class="form-input" name="segundo_apellido" id="segundo_apellido" maxlength="25"
                           value="<?php echo htmlspecialchars((string) $segundoApellido); ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="correo"><i class="bi bi-envelope"></i> Correo Electrónico:<span class="required">*</span></label>
                    <input type="email" class="form-input" name="correo" id="correo" maxlength="80"
                           value="<?php echo htmlspecialchars((string) $correo); ?>" required>
                </div>

                <div class="form-group">
                    <label for="telefono"><i class="bi bi-telephone"></i> Teléfono:<span class="required">*</span></label>
                    <input type="text" class="form-input" name="telefono" id="telefono" maxlength="15"
                           value="<?php echo htmlspecialchars((string) $telefono); ?>" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="contrasena"><i class="bi bi-lock"></i> Contraseña:<?php echo !$esEdicion ? '<span class="required">*</span>' : '<small>(dejar vacío para no cambiar)</small>'; ?></label>
                    <input type="password" class="form-input" name="contrasena" id="contrasena"
                           <?php echo !$esEdicion ? 'required' : ''; ?>>
                </div>
            </div>

            <div class="form-group">
                <label for="id_direccion_FK"><i class="bi bi-geo-alt"></i> Dirección (Opcional):</label>
                <select class="form-input" name="id_direccion_FK" id="id_direccion_FK">
                    <option value="">-- Sin dirección registrada --</option>
                    <?php foreach (($direcciones ?? []) as $direccion): ?>
                        <option value="<?php echo (int) $direccion['id_direccion']; ?>"
                            <?php echo ((string) $idDireccion === (string) $direccion['id_direccion']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars((string) $direccion['direccion_completa']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-action-primary">
                    <i class="bi bi-check-lg"></i> <?php echo $esEdicion ? 'Guardar Cambios' : 'Guardar'; ?>
                </button>
                <a href="usuario.php?accion=leer" class="btn-action-secondary">
                    <i class="bi bi-x-lg"></i> Cancelar
                </a>
            </div>
        </form>
    <?php else: ?>
        <div class="alert-message error">
            <p><i class="bi bi-exclamation-triangle"></i> No se encontró el usuario. <a href="usuario.php?accion=leer">Volver al listado</a></p>
        </div>
    <?php endif; ?>
</div>
<script src="js/fk-autocomplete.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (!window.JoyeriaFkAutocomplete) return;
    JoyeriaFkAutocomplete.initSelectAutocomplete({ selectId: 'id_direccion_FK', allowEmpty: true, placeholder: 'Buscar direccion...' });
});
</script>
