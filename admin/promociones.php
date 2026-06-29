<?php
require_once (__DIR__ . "/../sistema.class.php");
require_once (__DIR__ . "/models/promociones.php");
require_once __DIR__ . '/includes/list_search.php';

$busqueda = joyeria_list_search_normalize(isset($_GET['q']) ? (string) $_GET['q'] : '');

$app = new Promociones();
$id = (isset($_GET['id'])) ? intval($_GET['id']) : null;
$accion = (isset($_GET['accion'])) ? htmlspecialchars($_GET['accion']) : null;
$mensaje = null;

$catalogos = [];
try {
    $catalogos = $app->obtenerCatalogos();
} catch (Exception $e) {
    $mensaje = 'No se pudieron cargar los catálogos: ' . $e->getMessage();
}

require_once (__DIR__ . "/views/header.php");
?>

<header class="admin-header">
    <h2>Gestión de Promociones</h2>
</header>

<div class="admin-main">
    <?php
    switch ($accion) {
        case 'crear':
            if (isset($_POST['nombre']) && trim((string) $_POST['nombre']) !== ''
                && isset($_POST['porcentaje_descuento']) && is_numeric($_POST['porcentaje_descuento'])
                && isset($_POST['fecha_inicio']) && trim((string) $_POST['fecha_inicio']) !== ''
                && isset($_POST['fecha_fin']) && trim((string) $_POST['fecha_fin']) !== '') {
                try {
                    $id = $app->crear($_POST);
                    if ($id) {
                        $mensaje = 'Promoción creada correctamente';
                    }
                } catch (Exception $e) {
                    $mensaje = 'Error al crear la promoción: ' . $e->getMessage();
                }
                $promociones = $app->leer($busqueda);
                require_once (__DIR__ . "/views/promociones/index.php");
            } else {
                if (!empty($_POST)) {
                    $mensaje = 'Los campos marcados con * son obligatorios.';
                }
                require_once (__DIR__ . "/views/promociones/formulario.php");
            }
            break;

        case 'actualizar':
            if (isset($_POST['nombre']) && trim((string) $_POST['nombre']) !== ''
                && isset($_POST['porcentaje_descuento']) && is_numeric($_POST['porcentaje_descuento'])
                && isset($_POST['fecha_inicio']) && trim((string) $_POST['fecha_inicio']) !== ''
                && isset($_POST['fecha_fin']) && trim((string) $_POST['fecha_fin']) !== '') {
                try {
                    $app->actualizar($id, $_POST);
                    $mensaje = 'Promoción actualizada correctamente';
                } catch (Exception $e) {
                    $mensaje = 'Error al actualizar la promoción: ' . $e->getMessage();
                }
                $promociones = $app->leer($busqueda);
                require_once (__DIR__ . "/views/promociones/index.php");
            } else {
                $promocion = $app->leerUno($id);
                require_once (__DIR__ . "/views/promociones/formulario.php");
            }
            break;

        case 'borrar':
            if ($id) {
                try {
                    $app->eliminar($id);
                    $mensaje = 'Promoción desactivada correctamente';
                } catch (Exception $e) {
                    $mensaje = 'Error al desactivar la promoción: ' . $e->getMessage();
                }
            }
            $promociones = $app->leer($busqueda);
            require_once (__DIR__ . "/views/promociones/index.php");
            break;

        case 'leer':
        default:
            $promociones = $app->leer($busqueda);
            require_once (__DIR__ . "/views/promociones/index.php");
            break;
    }
    ?>
</div>

<?php require_once (__DIR__ . '/views/footer.php'); ?>
