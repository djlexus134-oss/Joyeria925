<?php
$esEdicion = isset($metal) && !empty($metal);
$titulo = $esEdicion ? 'Editar Metal' : 'Nuevo Metal';
$accionForm = $esEdicion
    ? 'metales.php?accion=actualizar&id=' . urlencode((string) $metal['id_metal'])
    : 'metales.php?accion=crear';

$nombreMetal = $_POST['nom_metal'] ?? ($esEdicion ? ($metal['nom_metal'] ?? '') : '');
$precioTienda = $_POST['precio_tienda'] ?? ($esEdicion ? ($metal['precio_tienda'] ?? '') : '');
$precioMercado = $_POST['precio_mercado'] ?? ($esEdicion ? ($metal['precio_mercado'] ?? '') : '');
?>

<div class="form-section">
    <h3>
        <i class="bi <?php echo $esEdicion ? 'bi-pencil-square' : 'bi-plus-circle'; ?>"></i>
        <?php echo htmlspecialchars($titulo); ?>
    </h3>

    <?php if(isset($mensaje) && !empty($mensaje)): ?>
        <div class="alert-message info">
            <p><i class="bi bi-info-circle"></i> <?php echo htmlspecialchars($mensaje); ?></p>
        </div>
    <?php endif; ?>

    <?php if(!$esEdicion || !empty($metal)): ?>
        <form action="<?php echo htmlspecialchars($accionForm); ?>" method="POST" class="admin-form">
            <div class="form-row">
                <div class="form-group">
                    <label for="nom_metal">
                        <i class="bi bi-gem"></i> Nombre del Metal:
                    </label>
                    <input type="text"
                           class="form-input"
                           name="nom_metal"
                           id="nom_metal"
                           maxlength="25"
                           value="<?php echo htmlspecialchars($nombreMetal); ?>"
                           placeholder="Ej. Plata, Oro, Acero..."
                           required
                           autofocus>
                    <small class="form-hint"><i class="bi bi-info-circle"></i> Maximo 25 caracteres.</small>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="precio_tienda">
                        <i class="bi bi-cash-stack"></i> Precio Tienda:
                    </label>
                    <input type="number"
                           class="form-input"
                           name="precio_tienda"
                           id="precio_tienda"
                           step="0.01"
                           min="0"
                           value="<?php echo htmlspecialchars((string)$precioTienda); ?>"
                           placeholder="Ej. 520.50 (opcional)">
                </div>

                <div class="form-group">
                    <label for="precio_mercado">
                        <i class="bi bi-graph-up-arrow"></i> Precio Mercado:
                    </label>
                    <input type="number"
                           class="form-input"
                           name="precio_mercado"
                           id="precio_mercado"
                           step="0.01"
                           min="0"
                           value="<?php echo htmlspecialchars((string)$precioMercado); ?>"
                           placeholder="Ej. 500.00 (opcional)">
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-action-primary">
                    <i class="bi bi-check-lg"></i> <?php echo $esEdicion ? 'Guardar Cambios' : 'Guardar Metal'; ?>
                </button>
                <a href="metales.php?accion=leer" class="btn-action-secondary">
                    <i class="bi bi-x-lg"></i> Cancelar
                </a>
            </div>
        </form>
    <?php else: ?>
        <div class="alert-message error">
            <p><i class="bi bi-exclamation-triangle"></i> No se encontró el metal. <a href="metales.php?accion=leer">Volver al listado</a></p>
        </div>
    <?php endif; ?>
</div>
