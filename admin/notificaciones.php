<?php
require_once (__DIR__ . "/../sistema.class.php");
require_once (__DIR__ . "/models/notificaciones.php");
require_once __DIR__ . '/includes/list_search.php';

$busqueda = joyeria_list_search_normalize(isset($_GET['q']) ? (string) $_GET['q'] : '');

$app = new Notificaciones();
$id = (isset($_GET['id'])) ? intval($_GET['id']) : null;
$accion = (isset($_GET['accion'])) ? htmlspecialchars($_GET['accion']) : null;
$mensaje = null;

require_once (__DIR__ . "/views/header.php");
?>

<header class="admin-header">
    <h2>Gestion de Notificaciones</h2>
</header>

<div class="admin-main">
    <?php
    switch ($accion) {
        case 'crear':
            if (isset($_POST['mensaje']) && trim((string) $_POST['mensaje']) !== '') {
                try {
                    $cantidad = $app->crear($_POST);
                    if ($cantidad) {
                        $mensaje = 'Notificacion creada correctamente';
                    }
                } catch (Exception $e) {
                    $mensaje = 'Error al crear la notificacion: ' . $e->getMessage();
                }
                $notificaciones = $app->leer($busqueda);
                require_once (__DIR__ . "/views/notificaciones/index.php");
            } else {
                if (!empty($_POST)) {
                    $mensaje = 'El mensaje es obligatorio.';
                }
                require_once (__DIR__ . "/views/notificaciones/formulario.php");
            }
            break;

        case 'actualizar':
            if (isset($_POST['mensaje']) && trim((string) $_POST['mensaje']) !== '') {
                try {
                    $cantidad = $app->actualizar($id, $_POST);
                    if ($cantidad) {
                        $mensaje = 'Notificacion actualizada correctamente';
                    } else {
                        $mensaje = 'No se realizaron cambios';
                    }
                } catch (Exception $e) {
                    $mensaje = 'Error al actualizar la notificacion: ' . $e->getMessage();
                }
                $notificaciones = $app->leer($busqueda);
                require_once (__DIR__ . "/views/notificaciones/index.php");
            } else {
                $notificacion = $app->leerUno($id);
                require_once (__DIR__ . "/views/notificaciones/formulario.php");
            }
            break;

        case 'borrar':
            if ($id) {
                try {
                    $cantidad = $app->borrar($id);
                    if ($cantidad) {
                        $mensaje = 'Notificacion eliminada correctamente';
                    } else {
                        $mensaje = 'No se pudo eliminar la notificacion';
                    }
                } catch (Exception $e) {
                    $mensaje = 'Error al eliminar la notificacion: ' . $e->getMessage();
                }
            }
            $notificaciones = $app->leer($busqueda);
            require_once (__DIR__ . "/views/notificaciones/index.php");
            break;

        case 'leer':
        default:
            $notificaciones = $app->leer($busqueda);
            require_once (__DIR__ . "/views/notificaciones/index.php");
            break;
    }
    ?>
</div>

<?php require_once (__DIR__ . '/views/footer.php'); ?>
