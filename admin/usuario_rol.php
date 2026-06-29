<?php
require_once (__DIR__ . "/../sistema.class.php");
require_once (__DIR__ . "/models/usuario_rol.php");
require_once __DIR__ . '/includes/list_search.php';

$busqueda = joyeria_list_search_normalize(isset($_GET['q']) ? (string) $_GET['q'] : '');

$app = new UsuarioRol();
$accion = (isset($_GET['accion'])) ? htmlspecialchars($_GET['accion']) : null;
$idUsuario = (isset($_GET['id_usuario'])) ? intval($_GET['id_usuario']) : null;
$idRol = (isset($_GET['id_rol'])) ? intval($_GET['id_rol']) : null;
$mensaje = null;

$usuarios = [];
$roles = [];
try {
    $usuarios = $app->obtenerUsuariosActivos();
    $roles = $app->obtenerRolesActivos();
} catch (Exception $e) {
    $mensaje = 'No se pudieron cargar catalogos: ' . $e->getMessage();
}

require_once (__DIR__ . "/views/header.php");
?>

<header class="admin-header">
    <h2>Gestion de Usuario - Rol</h2>
</header>

<div class="admin-main">
    <?php
    switch ($accion) {
        case 'asignar':
            if (isset($_POST['id_usuario_FK']) && (int) $_POST['id_usuario_FK'] > 0
                && isset($_POST['id_rol_FK']) && (int) $_POST['id_rol_FK'] > 0) {
                try {
                    $cantidad = $app->asignar($_POST);
                    if ($cantidad) {
                        $mensaje = 'Rol asignado correctamente al usuario';
                    }
                } catch (Exception $e) {
                    $mensaje = 'Error al asignar el rol: ' . $e->getMessage();
                }
            } else {
                $mensaje = 'Usuario y rol son obligatorios.';
            }
            $asignaciones = $app->leer($busqueda);
            require_once (__DIR__ . "/views/usuario_rol/index.php");
            break;

        case 'revocar':
            if ($idUsuario > 0 && $idRol > 0) {
                try {
                    $cantidad = $app->revocar($idUsuario, $idRol);
                    if ($cantidad) {
                        $mensaje = 'Relacion usuario-rol eliminada correctamente';
                    } else {
                        $mensaje = 'No se encontro la relacion a eliminar';
                    }
                } catch (Exception $e) {
                    $mensaje = 'Error al eliminar la relacion: ' . $e->getMessage();
                }
            }
            $asignaciones = $app->leer($busqueda);
            require_once (__DIR__ . "/views/usuario_rol/index.php");
            break;

        case 'leer':
        default:
            $asignaciones = $app->leer($busqueda);
            require_once (__DIR__ . "/views/usuario_rol/index.php");
            break;
    }
    ?>
</div>

<?php require_once (__DIR__ . '/views/footer.php'); ?>
