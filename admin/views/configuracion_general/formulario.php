<?php
$esEdicion = isset($configuracion) && !empty($configuracion);
$titulo = $esEdicion ? 'Editar Configuracion' : 'Nueva Configuracion';
$accionForm = $esEdicion
    ? 'configuracion_general.php?accion=actualizar&id=' . urlencode((string) $configuracion['id_configuracion_global'])
    : 'configuracion_general.php?accion=crear';

$clave = $_POST['clave'] ?? ($esEdicion ? ($configuracion['clave'] ?? '') : '');
$valor = $_POST['valor'] ?? ($esEdicion ? ($configuracion['valor'] ?? '') : '');
$tipo = $_POST['tipo'] ?? ($esEdicion ? ($configuracion['tipo'] ?? 'STRING') : 'STRING');
$descripcion = $_POST['descripcion'] ?? ($esEdicion ? ($configuracion['descripcion'] ?? '') : '');
?>

<div class="form-section">
    <h3><i class="bi <?php echo $esEdicion ? 'bi-pencil-square' : 'bi-plus-circle'; ?>"></i> <?php echo htmlspecialchars($titulo); ?></h3>

    <?php if(isset($mensaje) && !empty($mensaje)): ?>
        <div class="alert-message info"><p><i class="bi bi-info-circle"></i> <?php echo htmlspecialchars($mensaje); ?></p></div>
    <?php endif; ?>

    <form action="<?php echo htmlspecialchars($accionForm); ?>" method="POST" class="admin-form">
        <div class="form-row">
            <div class="form-group">
                <label for="clave">Clave:</label>
                <input type="text" class="form-input" name="clave" id="clave" maxlength="50" value="<?php echo htmlspecialchars($clave); ?>" autofocus>
            </div>
            <div class="form-group">
                <label for="tipo">Tipo:</label>
                <select class="form-input" name="tipo" id="tipo" required>
                    <?php foreach ($tipos as $t): ?>
                        <option value="<?php echo htmlspecialchars($t); ?>" <?php echo $tipo === $t ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($t); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label for="valor">Valor:</label>
            <input type="text" class="form-input" name="valor" id="valor" maxlength="255" value="<?php echo htmlspecialchars($valor); ?>" required>
        </div>

        <div class="form-group">
            <label for="descripcion">Descripción:</label>
            <textarea class="form-input" name="descripcion" id="descripcion" rows="4"><?php echo htmlspecialchars($descripcion); ?></textarea>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn-action-primary"><i class="bi bi-check-lg"></i> Guardar</button>
            <a href="configuracion_general.php?accion=leer" class="btn-action-secondary"><i class="bi bi-x-lg"></i> Cancelar</a>
        </div>
    </form>
</div>
