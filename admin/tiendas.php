<?php
require_once (__DIR__ . "/../sistema.class.php");
require_once (__DIR__ . "/models/tiendas.php");
require_once __DIR__ . '/includes/list_search.php';

$busqueda = joyeria_list_search_normalize(isset($_GET['q']) ? (string) $_GET['q'] : '');

$app = new Tiendas();
$id = (isset($_GET['id'])) ? intval($_GET['id']) : null;
$accion = (isset($_GET['accion'])) ? htmlspecialchars($_GET['accion']) : null;
$mensaje = null;
$tienda = null;

if ($accion === 'actualizar' && $id) {
    $tienda = $app->leerUno($id);
}

require_once (__DIR__ . "/views/header.php");
?>

<header class="admin-header">
    <h2>Gestion de Tiendas</h2>
</header>

<div class="admin-main">
    <?php
    switch ($accion) {
        case 'crear':
            if (
                isset($_POST['nom_tienda']) && trim((string) $_POST['nom_tienda']) !== '' &&
                isset($_POST['id_calle_FK']) && (int) $_POST['id_calle_FK'] > 0 &&
                isset($_POST['num_exterior']) && trim((string) $_POST['num_exterior']) !== '' && is_numeric($_POST['num_exterior']) &&
                (!isset($_POST['num_interior']) || trim((string) $_POST['num_interior']) === '' || is_numeric($_POST['num_interior']))
            ) {
                try {
                    $cantidad = $app->crear($_POST);
                    if ($cantidad) {
                        $mensaje = 'Tienda creada correctamente';
                    }
                } catch (Exception $e) {
                    $mensaje = 'Error al crear la tienda: ' . $e->getMessage();
                }
                $tiendas = $app->leer($busqueda);
                require_once (__DIR__ . "/views/tiendas/index.php");
            } else {
                if (!empty($_POST)) {
                    $mensaje = 'Nombre, calle y numero exterior son obligatorios. El numero interior debe ser numerico si se captura.';
                }
                require_once (__DIR__ . "/views/tiendas/formulario.php");
            }
            break;

        case 'actualizar':
            if (
                isset($_POST['nom_tienda']) && trim((string) $_POST['nom_tienda']) !== '' &&
                isset($_POST['id_calle_FK']) && (int) $_POST['id_calle_FK'] > 0 &&
                isset($_POST['num_exterior']) && trim((string) $_POST['num_exterior']) !== '' && is_numeric($_POST['num_exterior']) &&
                (!isset($_POST['num_interior']) || trim((string) $_POST['num_interior']) === '' || is_numeric($_POST['num_interior']))
            ) {
                try {
                    $cantidad = $app->actualizar($id, $_POST);
                    if ($cantidad) {
                        $mensaje = 'Tienda actualizada correctamente';
                    } else {
                        $mensaje = 'No se realizaron cambios';
                    }
                } catch (Exception $e) {
                    $mensaje = 'Error al actualizar la tienda: ' . $e->getMessage();
                }
                $tiendas = $app->leer($busqueda);
                require_once (__DIR__ . "/views/tiendas/index.php");
            } else {
                if ($id && !$tienda) {
                    $tienda = $app->leerUno($id);
                }
                if (!empty($_POST)) {
                    $mensaje = 'Nombre, calle y numero exterior son obligatorios. El numero interior debe ser numerico si se captura.';
                }
                require_once (__DIR__ . "/views/tiendas/formulario.php");
            }
            break;

        case 'borrar':
            if ($id) {
                try {
                    $idUsuarioActual = $_SESSION['id_usuario'] ?? null;
                    $cantidad = $app->borrar($id, $idUsuarioActual);
                    if ($cantidad) {
                        $mensaje = 'Tienda dada de baja correctamente';
                    } else {
                        $mensaje = 'No se pudo dar de baja la tienda';
                    }
                } catch (Exception $e) {
                    $mensaje = 'Error al dar de baja la tienda: ' . $e->getMessage();
                }
            }
            $tiendas = $app->leer($busqueda);
            require_once (__DIR__ . "/views/tiendas/index.php");
            break;

        case 'leer':
        default:
            $tiendas = $app->leer($busqueda);
            require_once (__DIR__ . "/views/tiendas/index.php");
            break;
    }
    ?>
</div>

<?php require_once (__DIR__ . '/views/footer.php'); ?>
