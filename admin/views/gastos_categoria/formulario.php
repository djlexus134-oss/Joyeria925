<?php
$esEdicion = isset($categoria) && !empty($categoria);
$titulo = $esEdicion ? 'Editar Categoria de Gasto' : 'Nueva Categoria de Gasto';
$accionForm = $esEdicion
    ? 'gastos_categoria.php?accion=actualizar&id=' . urlencode((string) $categoria['id_categoria_gasto'])
    : 'gastos_categoria.php?accion=crear';

$nombre = $_POST['nombre'] ?? ($esEdicion ? ($categoria['nombre'] ?? '') : '');
$descripcion = $_POST['descripcion'] ?? ($esEdicion ? ($categoria['descripcion'] ?? '') : '');
?>

<div class="form-section">
    <h3><i class="bi <?php echo $esEdicion ? 'bi-pencil-square' : 'bi-plus-circle'; ?>"></i> <?php echo htmlspecialchars($titulo); ?></h3>

    <?php if(isset($mensaje) && !empty($mensaje)): ?>
        <div class="alert-message info"><p><i class="bi bi-info-circle"></i> <?php echo htmlspecialchars($mensaje); ?></p></div>
    <?php endif; ?>

    <form action="<?php echo htmlspecialchars($accionForm); ?>" method="POST" class="admin-form">
        <div class="form-group">
            <label for="nombre"><i class="bi bi-tag"></i> Nombre:</label>
            <input type="text" class="form-input" name="nombre" id="nombre" maxlength="50" value="<?php echo htmlspecialchars($nombre); ?>" required autofocus>
        </div>

        <div class="form-group">
            <label for="descripcion"><i class="bi bi-card-text"></i> Descripción:</label>
            <textarea class="form-input" name="descripcion" id="descripcion" rows="4"><?php echo htmlspecialchars($descripcion); ?></textarea>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn-action-primary"><i class="bi bi-check-lg"></i> Guardar</button>
            <a href="gastos_categoria.php?accion=leer" class="btn-action-secondary"><i class="bi bi-x-lg"></i> Cancelar</a>
        </div>
    </form>
</div>
