<?php
require_once (__DIR__ . "/../sistema.class.php");
require_once (__DIR__ . "/models/usuario_notificacion.php");
require_once __DIR__ . '/includes/list_search.php';

$busqueda = joyeria_list_search_normalize(isset($_GET['q']) ? (string) $_GET['q'] : '');

$app = new UsuarioNotificacion();
$accion = (isset($_GET['accion'])) ? htmlspecialchars($_GET['accion']) : null;
$idUsuario = (isset($_GET['id_usuario'])) ? intval($_GET['id_usuario']) : null;
$idNotificacion = (isset($_GET['id_notificacion'])) ? intval($_GET['id_notificacion']) : null;
$mensaje = null;

$usuarios = [];
$notificaciones = [];
try {
    $usuarios = $app->obtenerUsuariosActivos();
    $notificaciones = $app->obtenerNotificaciones();
} catch (Exception $e) {
    $mensaje = 'No se pudieron cargar catalogos: ' . $e->getMessage();
}

require_once (__DIR__ . "/views/header.php");
?>

<header class="admin-header">
    <h2>Gestion de Usuario - Notificacion</h2>
</header>

<div class="admin-main">
    <?php
    switch ($accion) {
        case 'asignar':
            if (isset($_POST['id_usuario_FK']) && (int) $_POST['id_usuario_FK'] > 0
                && isset($_POST['id_notificacion_FK']) && (int) $_POST['id_notificacion_FK'] > 0) {
                try {
                    $cantidad = $app->asignar($_POST);
                    if ($cantidad) {
                        $mensaje = 'Notificacion asignada correctamente al usuario';
                    }
                } catch (Exception $e) {
                    $mensaje = 'Error al asignar la notificacion: ' . $e->getMessage();
                }
            } else {
                $mensaje = 'Usuario y notificacion son obligatorios.';
            }
            $asignaciones = $app->leer($busqueda);
            require_once (__DIR__ . "/views/usuario_notificacion/index.php");
            break;

        case 'desvincular':
            if ($idUsuario > 0 && $idNotificacion > 0) {
                try {
                    $cantidad = $app->desvincular($idUsuario, $idNotificacion);
                    if ($cantidad) {
                        $mensaje = 'Relacion usuario-notificacion eliminada correctamente';
                    } else {
                        $mensaje = 'No se encontro la relacion a eliminar';
                    }
                } catch (Exception $e) {
                    $mensaje = 'Error al eliminar la relacion: ' . $e->getMessage();
                }
            }
            $asignaciones = $app->leer($busqueda);
            require_once (__DIR__ . "/views/usuario_notificacion/index.php");
            break;

        case 'leer':
        default:
            $asignaciones = $app->leer($busqueda);
            require_once (__DIR__ . "/views/usuario_notificacion/index.php");
            break;
    }
    ?>
</div>

<?php require_once (__DIR__ . '/views/footer.php'); ?>
