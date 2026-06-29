<?php
$esEdicion = isset($tipo) && !empty($tipo);
$titulo = $esEdicion ? 'Editar tipo de variante' : 'Nuevo tipo de variante';
$accionForm = $esEdicion
    ? 'variantes.php?accion=actualizar&id=' . urlencode((string) $tipo['id_variante_tipo'])
    : 'variantes.php?accion=crear';

$nombre = $_POST['nombre'] ?? ($esEdicion ? ($tipo['nombre'] ?? '') : '');
$slug = $_POST['slug'] ?? ($esEdicion ? ($tipo['slug'] ?? '') : '');
$esTalla = isset($_POST['es_talla'])
    ? !empty($_POST['es_talla'])
    : ($esEdicion ? ((int) ($tipo['es_talla'] ?? 0) === 1) : false);
$valores = $valores ?? [];
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

    <form action="<?php echo htmlspecialchars($accionForm); ?>" method="POST" class="admin-form">
        <div class="form-row">
            <div class="form-group">
                <label for="nombre"><i class="bi bi-tag"></i> Nombre:</label>
                <input type="text" class="form-input" name="nombre" id="nombre" maxlength="50"
                       value="<?php echo htmlspecialchars((string) $nombre); ?>" required autofocus>
            </div>
            <div class="form-group">
                <label for="slug"><i class="bi bi-code-slash"></i> Identificador (slug):</label>
                <input type="text" class="form-input" name="slug" id="slug" maxlength="40"
                       value="<?php echo htmlspecialchars((string) $slug); ?>"
                       placeholder="Ej. material, largo">
            </div>
        </div>

        <div class="form-group">
            <label class="form-group">
                <input type="checkbox" name="es_talla" value="1" <?php echo $esTalla ? 'checked' : ''; ?>>
                Este tipo representa talla (anillos)
            </label>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn-action-primary">
                <i class="bi bi-check-lg"></i> Guardar tipo
            </button>
            <a href="variantes.php?accion=leer" class="btn-action-secondary">
                <i class="bi bi-x-lg"></i> Cancelar
            </a>
        </div>
    </form>

    <?php if ($esEdicion): ?>
        <hr style="margin:28px 0;">
        <h4><i class="bi bi-list-ul"></i> Valores de "<?php echo htmlspecialchars((string) $tipo['nombre']); ?>"</h4>

        <form action="variantes.php?accion=crear_valor&id=<?php echo (int) $tipo['id_variante_tipo']; ?>" method="POST" class="admin-form" style="margin-bottom:20px;">
            <div class="form-row">
                <div class="form-group">
                    <label for="valor"><i class="bi bi-plus-circle"></i> Nuevo valor:</label>
                    <input type="text" class="form-input" name="valor" id="valor" maxlength="40" required
                           placeholder="Ej. Rosa, 7, Oro laminado">
                </div>
                <div class="form-group" style="display:flex; align-items:flex-end;">
                    <button type="submit" class="btn-action-secondary">
                        <i class="bi bi-plus-lg"></i> Agregar valor
                    </button>
                </div>
            </div>
        </form>

        <?php if (!empty($valores)): ?>
            <div class="admin-table-wrapper">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Valor</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($valores as $valor): ?>
                            <tr>
                                <td><?php echo htmlspecialchars((string) $valor['valor']); ?></td>
                                <td class="actions-cell">
                                    <a href="variantes.php?accion=borrar_valor&id=<?php echo (int) $tipo['id_variante_tipo']; ?>&id_valor=<?php echo (int) $valor['id_variante_valor']; ?>"
                                       class="btn-action-danger"
                                       onclick="return confirm('¿Dar de baja este valor?');">
                                        <i class="bi bi-trash"></i> Eliminar
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert-message info">
                <p><i class="bi bi-info-circle"></i> Aun no hay valores para este tipo.</p>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
