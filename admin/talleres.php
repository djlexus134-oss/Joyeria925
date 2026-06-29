<?php
require_once (__DIR__ . "/../sistema.class.php");
require_once (__DIR__ . "/models/talleres.php");
require_once __DIR__ . '/includes/list_search.php';

$busqueda = joyeria_list_search_normalize(isset($_GET['q']) ? (string) $_GET['q'] : '');

$app = new Talleres();
$id = (isset($_GET['id'])) ? intval($_GET['id']) : null;
$accion = (isset($_GET['accion'])) ? htmlspecialchars($_GET['accion']) : null;
$mensaje = null;

require_once (__DIR__ . "/views/header.php");
?>

<header class="admin-header">
    <h2>Gestion de Talleres</h2>
</header>

<div class="admin-main">
    <?php
    switch ($accion) {
        case 'crear':
            if (isset($_POST['nombre']) && !empty(trim($_POST['nombre']))) {
                try {
                    $cantidad = $app->crear($_POST);
                    if ($cantidad) {
                        $mensaje = 'Taller creado correctamente';
                    }
                } catch (Exception $e) {
                    $mensaje = 'Error al crear el taller: ' . $e->getMessage();
                }
                $talleres = $app->leer($busqueda);
                require_once (__DIR__ . "/views/talleres/index.php");
            } else {
                if (!empty($_POST)) {
                    $mensaje = 'El nombre del taller es obligatorio.';
                }
                require_once (__DIR__ . "/views/talleres/formulario.php");
            }
            break;

        case 'actualizar':
            if (isset($_POST['nombre']) && !empty(trim($_POST['nombre']))) {
                try {
                    $cantidad = $app->actualizar($id, $_POST);
                    if ($cantidad) {
                        $mensaje = 'Taller actualizado correctamente';
                    } else {
                        $mensaje = 'No se realizaron cambios';
                    }
                } catch (Exception $e) {
                    $mensaje = 'Error al actualizar el taller: ' . $e->getMessage();
                }
                $talleres = $app->leer($busqueda);
                require_once (__DIR__ . "/views/talleres/index.php");
            } else {
                $taller = $app->leerUno($id);
                require_once (__DIR__ . "/views/talleres/formulario.php");
            }
            break;

        case 'borrar':
            if ($id) {
                try {
                    $cantidad = $app->borrar($id);
                    if ($cantidad) {
                        $mensaje = 'Taller dado de baja correctamente';
                    } else {
                        $mensaje = 'No se pudo dar de baja el taller';
                    }
                } catch (Exception $e) {
                    $mensaje = 'Error al dar de baja el taller: ' . $e->getMessage();
                }
            }
            $talleres = $app->leer($busqueda);
            require_once (__DIR__ . "/views/talleres/index.php");
            break;

        case 'leer':
        default:
            $talleres = $app->leer($busqueda);
            require_once (__DIR__ . "/views/talleres/index.php");
            break;
    }
    ?>
</div>

<?php require_once (__DIR__ . '/views/footer.php'); ?>
