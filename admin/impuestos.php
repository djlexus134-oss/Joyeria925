<?php
require_once (__DIR__ . "/../sistema.class.php");
require_once (__DIR__ . "/models/impuestos.php");
require_once __DIR__ . '/includes/list_search.php';

$busqueda = joyeria_list_search_normalize(isset($_GET['q']) ? (string) $_GET['q'] : '');

$app = new Impuestos();
$id = (isset($_GET['id'])) ? intval($_GET['id']) : null;
$accion = (isset($_GET['accion'])) ? htmlspecialchars($_GET['accion']) : null;
$mensaje = null;

require_once (__DIR__ . "/views/header.php");
?>

<header class="admin-header">
    <h2>Gestion de Impuestos</h2>
</header>

<div class="admin-main">
    <?php
    switch ($accion) {
        case 'crear':
            if (isset($_POST['tipo_impuesto']) && !empty(trim($_POST['tipo_impuesto']))) {
                try {
                    $cantidad = $app->crear($_POST);
                    if ($cantidad) {
                        $mensaje = 'Impuesto creado correctamente';
                    }
                } catch (Exception $e) {
                    $mensaje = 'Error al crear el impuesto: ' . $e->getMessage();
                }
                $impuestos = $app->leer($busqueda);
                require_once (__DIR__ . "/views/impuestos/index.php");
            } else {
                if (!empty($_POST)) {
                    $mensaje = 'El tipo de impuesto es obligatorio.';
                }
                require_once (__DIR__ . "/views/impuestos/formulario.php");
            }
            break;

        case 'actualizar':
            if (isset($_POST['tipo_impuesto']) && !empty(trim($_POST['tipo_impuesto']))) {
                try {
                    $cantidad = $app->actualizar($id, $_POST);
                    if ($cantidad) {
                        $mensaje = 'Impuesto actualizado correctamente';
                    } else {
                        $mensaje = 'No se realizaron cambios';
                    }
                } catch (Exception $e) {
                    $mensaje = 'Error al actualizar el impuesto: ' . $e->getMessage();
                }
                $impuestos = $app->leer($busqueda);
                require_once (__DIR__ . "/views/impuestos/index.php");
            } else {
                $impuesto = $app->leerUno($id);
                require_once (__DIR__ . "/views/impuestos/formulario.php");
            }
            break;

        case 'borrar':
            if ($id) {
                try {
                    $cantidad = $app->borrar($id);
                    if ($cantidad) {
                        $mensaje = 'Impuesto eliminado correctamente';
                    } else {
                        $mensaje = 'No se pudo eliminar el impuesto';
                    }
                } catch (Exception $e) {
                    $mensaje = 'Error al eliminar el impuesto: ' . $e->getMessage();
                }
            }
            $impuestos = $app->leer($busqueda);
            require_once (__DIR__ . "/views/impuestos/index.php");
            break;

        case 'leer':
        default:
            $impuestos = $app->leer($busqueda);
            require_once (__DIR__ . "/views/impuestos/index.php");
            break;
    }
    ?>
</div>

<?php require_once (__DIR__ . '/views/footer.php'); ?>
