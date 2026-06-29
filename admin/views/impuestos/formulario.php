<?php
$esEdicion = isset($impuesto) && !empty($impuesto);
$titulo = $esEdicion ? 'Editar Impuesto' : 'Nuevo Impuesto';
$accionForm = $esEdicion
    ? 'impuestos.php?accion=actualizar&id=' . urlencode((string) $impuesto['id_impuesto'])
    : 'impuestos.php?accion=crear';

$tipo = $_POST['tipo_impuesto'] ?? ($esEdicion ? ($impuesto['tipo_impuesto'] ?? '') : '');
$porcentaje = $_POST['porcentaje'] ?? ($esEdicion ? ($impuesto['porcentaje'] ?? '') : '');
?>

<div class="form-section">
    <h3><i class="bi <?php echo $esEdicion ? 'bi-pencil-square' : 'bi-plus-circle'; ?>"></i> <?php echo htmlspecialchars($titulo); ?></h3>

    <?php if(isset($mensaje) && !empty($mensaje)): ?>
        <div class="alert-message info"><p><i class="bi bi-info-circle"></i> <?php echo htmlspecialchars($mensaje); ?></p></div>
    <?php endif; ?>

    <form action="<?php echo htmlspecialchars($accionForm); ?>" method="POST" class="admin-form">
        <div class="form-row">
            <div class="form-group">
                <label for="tipo_impuesto"><i class="bi bi-receipt"></i> Tipo de Impuesto:</label>
                <input type="text" class="form-input" name="tipo_impuesto" id="tipo_impuesto" maxlength="40" value="<?php echo htmlspecialchars($tipo); ?>" required autofocus>
            </div>
            <div class="form-group">
                <label for="porcentaje"><i class="bi bi-percent"></i> Porcentaje:</label>
                <input type="number" class="form-input" name="porcentaje" id="porcentaje" min="0" max="100" step="1" value="<?php echo htmlspecialchars((string)$porcentaje); ?>">
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn-action-primary"><i class="bi bi-check-lg"></i> Guardar</button>
            <a href="impuestos.php?accion=leer" class="btn-action-secondary"><i class="bi bi-x-lg"></i> Cancelar</a>
        </div>
    </form>
</div>
