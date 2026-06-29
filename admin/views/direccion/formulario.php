<?php
$esEdicion = isset($registro) && !empty($registro);
$titulo = $esEdicion
    ? 'Editar ' . $entidades[$entidad]['singular']
    : 'Nuevo ' . $entidades[$entidad]['singular'];
$accionForm = $esEdicion
    ? 'direccion.php?accion=actualizar&entidad=' . urlencode($entidad) . '&id=' . urlencode((string) $registro[$entidades[$entidad]['id']])
    : 'direccion.php?accion=crear&entidad=' . urlencode($entidad);

function valorCampo($registro, $campo, $default = '')
{
    if (isset($_POST[$campo])) {
        return $_POST[$campo];
    }
    if (isset($registro[$campo])) {
        return $registro[$campo];
    }
    return $default;
}
?>

<div class="form-section">
    <h3>
        <i class="bi <?php echo $esEdicion ? 'bi-pencil-square' : 'bi-plus-circle'; ?>"></i>
        <?php echo htmlspecialchars($titulo); ?>
    </h3>

    <?php if (isset($error) && !empty($error)): ?>
        <div class="alert-message error">
            <p><i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?></p>
        </div>
    <?php endif; ?>

    <form action="<?php echo htmlspecialchars($accionForm); ?>" method="POST" class="admin-form">

        <?php if ($entidad === 'paises'): ?>
            <div class="form-group">
                <label for="nom_pais">Nombre del pais:</label>
                <input type="text" class="form-input" name="nom_pais" id="nom_pais"
                       maxlength="100" required
                       value="<?php echo htmlspecialchars((string) valorCampo($registro ?? [], 'nom_pais')); ?>">
            </div>
        <?php elseif ($entidad === 'estados'): ?>
            <div class="form-group">
                <label for="nom_estado">Nombre del estado:</label>
                <input type="text" class="form-input" name="nom_estado" id="nom_estado"
                       maxlength="100" required
                       value="<?php echo htmlspecialchars((string) valorCampo($registro ?? [], 'nom_estado')); ?>">
            </div>
            <div class="form-group">
                <label for="id_pais_FK">Pais:</label>
                <select class="form-input" name="id_pais_FK" id="id_pais_FK" required>
                    <option value="">-- Selecciona un pais --</option>
                    <?php foreach ($listas['paises'] as $pais): ?>
                        <option value="<?php echo intval($pais['id_pais']); ?>"
                            <?php echo ((int) valorCampo($registro ?? [], 'id_pais_FK') === (int) $pais['id_pais']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($pais['nom_pais']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php elseif ($entidad === 'municipios'): ?>
            <div class="form-group">
                <label for="nom_municipio">Nombre del municipio:</label>
                <input type="text" class="form-input" name="nom_municipio" id="nom_municipio"
                       maxlength="100" required
                       value="<?php echo htmlspecialchars((string) valorCampo($registro ?? [], 'nom_municipio')); ?>">
            </div>
            <div class="form-group">
                <label for="id_estado_FK">Estado:</label>
                <select class="form-input" name="id_estado_FK" id="id_estado_FK" required>
                    <option value="">-- Selecciona un estado --</option>
                    <?php foreach ($listas['estados'] as $estado): ?>
                        <option value="<?php echo intval($estado['id_estado']); ?>"
                            <?php echo ((int) valorCampo($registro ?? [], 'id_estado_FK') === (int) $estado['id_estado']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($estado['nom_estado']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php elseif ($entidad === 'localidades'): ?>
            <div class="form-group">
                <label for="nom_localidad">Nombre de la localidad:</label>
                <input type="text" class="form-input" name="nom_localidad" id="nom_localidad"
                       maxlength="100" required
                       value="<?php echo htmlspecialchars((string) valorCampo($registro ?? [], 'nom_localidad')); ?>">
            </div>
            <div class="form-group">
                <label for="id_municipio_FK">Municipio:</label>
                <select class="form-input" name="id_municipio_FK" id="id_municipio_FK" required>
                    <option value="">-- Selecciona un municipio --</option>
                    <?php foreach ($listas['municipios'] as $municipio): ?>
                        <option value="<?php echo intval($municipio['id_municipio']); ?>"
                            <?php echo ((int) valorCampo($registro ?? [], 'id_municipio_FK') === (int) $municipio['id_municipio']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($municipio['nom_municipio']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php elseif ($entidad === 'codigos_postales'): ?>
            <div class="form-group">
                <label for="codigo_postal">Código postal:</label>
                <input type="text" class="form-input" name="codigo_postal" id="codigo_postal"
                       maxlength="10" required
                       value="<?php echo htmlspecialchars((string) valorCampo($registro ?? [], 'codigo_postal')); ?>">
            </div>
        <?php elseif ($entidad === 'colonias'): ?>
            <div class="form-group">
                <label for="nom_colonia">Nombre de la colonia:</label>
                <input type="text" class="form-input" name="nom_colonia" id="nom_colonia"
                       maxlength="100" required
                       value="<?php echo htmlspecialchars((string) valorCampo($registro ?? [], 'nom_colonia')); ?>">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="id_localidad_FK">Localidad:</label>
                    <select class="form-input" name="id_localidad_FK" id="id_localidad_FK" required>
                        <option value="">-- Selecciona una localidad --</option>
                        <?php foreach ($listas['localidades'] as $localidad): ?>
                            <option value="<?php echo intval($localidad['id_localidad']); ?>"
                                <?php echo ((int) valorCampo($registro ?? [], 'id_localidad_FK') === (int) $localidad['id_localidad']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($localidad['nom_localidad']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="id_codigo_postal_FK">Código postal:</label>
                    <select class="form-input" name="id_codigo_postal_FK" id="id_codigo_postal_FK" required>
                        <option value="">-- Selecciona un codigo postal --</option>
                        <?php foreach ($listas['codigos_postales'] as $cp): ?>
                            <option value="<?php echo intval($cp['id_codigo_postal']); ?>"
                                <?php echo ((int) valorCampo($registro ?? [], 'id_codigo_postal_FK') === (int) $cp['id_codigo_postal']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cp['codigo_postal']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        <?php elseif ($entidad === 'calles'): ?>
            <div class="form-group">
                <label for="nom_calle">Nombre de la calle:</label>
                <input type="text" class="form-input" name="nom_calle" id="nom_calle"
                       maxlength="100" required
                       value="<?php echo htmlspecialchars((string) valorCampo($registro ?? [], 'nom_calle')); ?>">
            </div>
            <div class="form-group">
                <label for="id_colonia_FK">Colonia:</label>
                <select class="form-input" name="id_colonia_FK" id="id_colonia_FK" required>
                    <option value="">-- Selecciona una colonia --</option>
                    <?php foreach ($listas['colonias'] as $colonia): ?>
                        <option value="<?php echo intval($colonia['id_colonia']); ?>"
                            <?php echo ((int) valorCampo($registro ?? [], 'id_colonia_FK') === (int) $colonia['id_colonia']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($colonia['nom_colonia']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php elseif ($entidad === 'direcciones'): ?>
            <div class="form-row">
                <div class="form-group">
                    <label for="num_exterior">Número exterior:</label>
                    <input type="number" class="form-input" name="num_exterior" id="num_exterior"
                           min="1" required
                           value="<?php echo htmlspecialchars((string) valorCampo($registro ?? [], 'num_exterior')); ?>">
                </div>
                <div class="form-group">
                    <label for="num_interior">Número interior (opcional):</label>
                    <input type="number" class="form-input" name="num_interior" id="num_interior"
                           min="0"
                           value="<?php echo htmlspecialchars((string) valorCampo($registro ?? [], 'num_interior')); ?>">
                </div>
            </div>
            <div class="form-group">
                <label for="id_calle_FK">Calle:</label>
                <select class="form-input" name="id_calle_FK" id="id_calle_FK" required>
                    <option value="">-- Selecciona una calle --</option>
                    <?php foreach ($listas['calles'] as $calle): ?>
                        <option value="<?php echo intval($calle['id_calle']); ?>"
                            <?php echo ((int) valorCampo($registro ?? [], 'id_calle_FK') === (int) $calle['id_calle']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($calle['nom_calle']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>

        <div class="form-actions">
            <button type="submit" class="btn-action-primary">
                <i class="bi bi-check-lg"></i> <?php echo $esEdicion ? 'Guardar Cambios' : 'Guardar'; ?>
            </button>
            <a href="direccion.php?accion=leer&entidad=<?php echo urlencode($entidad); ?>" class="btn-action-secondary">
                <i class="bi bi-x-lg"></i> Cancelar
            </a>
        </div>
    </form>
</div>
