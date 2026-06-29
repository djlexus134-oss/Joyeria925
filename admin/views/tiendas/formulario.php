<?php
$esEdicion = isset($tienda) && !empty($tienda);
$titulo = $esEdicion ? 'Editar Tienda' : 'Nueva Tienda';
$accionForm = $esEdicion
    ? 'tiendas.php?accion=actualizar&id=' . urlencode((string) $tienda['id_tienda'])
    : 'tiendas.php?accion=crear';

$nomTienda = $_POST['nom_tienda'] ?? ($esEdicion ? ($tienda['nom_tienda'] ?? '') : '');

$dir = [
    'id_pais_FK' => $_POST['id_pais_FK'] ?? ($esEdicion ? ($tienda['id_pais'] ?? '') : ''),
    'nom_pais' => $esEdicion ? ($tienda['nom_pais'] ?? '') : '',
    'id_estado_FK' => $_POST['id_estado_FK'] ?? ($esEdicion ? ($tienda['id_estado'] ?? '') : ''),
    'nom_estado' => $esEdicion ? ($tienda['nom_estado'] ?? '') : '',
    'id_municipio_FK' => $_POST['id_municipio_FK'] ?? ($esEdicion ? ($tienda['id_municipio'] ?? '') : ''),
    'nom_municipio' => $esEdicion ? ($tienda['nom_municipio'] ?? '') : '',
    'id_localidad_FK' => $_POST['id_localidad_FK'] ?? ($esEdicion ? ($tienda['id_localidad'] ?? '') : ''),
    'nom_localidad' => $esEdicion ? ($tienda['nom_localidad'] ?? '') : '',
    'id_codigo_postal_FK' => $_POST['id_codigo_postal_FK'] ?? ($esEdicion ? ($tienda['id_codigo_postal'] ?? '') : ''),
    'codigo_postal' => $esEdicion ? ($tienda['codigo_postal'] ?? '') : '',
    'id_colonia_FK' => $_POST['id_colonia_FK'] ?? ($esEdicion ? ($tienda['id_colonia'] ?? '') : ''),
    'nom_colonia' => $esEdicion ? ($tienda['nom_colonia'] ?? '') : '',
    'id_calle_FK' => $_POST['id_calle_FK'] ?? ($esEdicion ? ($tienda['id_calle_FK'] ?? '') : ''),
    'nom_calle' => $esEdicion ? ($tienda['nom_calle'] ?? '') : '',
    'num_exterior' => $_POST['num_exterior'] ?? ($esEdicion ? ($tienda['num_exterior'] ?? '') : ''),
    'num_interior' => $_POST['num_interior'] ?? ($esEdicion ? ($tienda['num_interior'] ?? '') : ''),
];

$dirOpts = [
    'prefix' => '',
    'feedback_id' => 'tienda_dir_feedback',
    'fieldset_title' => 'Direccion',
    'root_id' => 'joyeria_dir_tienda',
    'api_prefix' => './api/',
    'num_exterior_extra' => 'required data-dir-req',
];
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

    <?php if (!$esEdicion || !empty($tienda)): ?>
        <form action="<?php echo htmlspecialchars($accionForm); ?>" method="POST" class="admin-form">
            <fieldset class="form-fieldset">
                <legend><i class="bi bi-shop"></i> Datos de la tienda</legend>
                <div class="form-group">
                    <label for="nom_tienda">Nombre de la tienda:</label>
                    <input type="text" class="form-input" name="nom_tienda" id="nom_tienda" maxlength="30"
                           value="<?php echo htmlspecialchars((string) $nomTienda); ?>" required autofocus>
                </div>
            </fieldset>

            <?php require __DIR__ . '/../partials/direccion_form.php'; ?>

            <div class="form-actions">
                <button type="submit" class="btn-action-primary">
                    <i class="bi bi-check-lg"></i> <?php echo $esEdicion ? 'Guardar Cambios' : 'Guardar'; ?>
                </button>
                <a href="tiendas.php?accion=leer" class="btn-action-secondary">
                    <i class="bi bi-x-lg"></i> Cancelar
                </a>
            </div>
        </form>
    <?php else: ?>
        <div class="alert-message error">
            <p><i class="bi bi-exclamation-triangle"></i> No se encontró la tienda. <a href="tiendas.php?accion=leer">Volver al listado</a></p>
        </div>
    <?php endif; ?>
</div>
