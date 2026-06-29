<?php
require_once (__DIR__ . "/../sistema.class.php");
require_once (__DIR__ . "/models/rol_permiso.php");
require_once __DIR__ . '/includes/list_search.php';

$busqueda = joyeria_list_search_normalize(isset($_GET['q']) ? (string) $_GET['q'] : '');

$app = new RolPermiso();
$accion = (isset($_GET['accion'])) ? htmlspecialchars($_GET['accion']) : null;
$idRol = (isset($_GET['id_rol'])) ? intval($_GET['id_rol']) : null;
$idPermiso = (isset($_GET['id_permiso'])) ? intval($_GET['id_permiso']) : null;
$mensaje = null;

$roles = [];
$permisos = [];
try {
    $roles = $app->obtenerRolesActivos();
    $permisos = $app->obtenerPermisosActivos();
} catch (Exception $e) {
    $mensaje = 'No se pudieron cargar catalogos: ' . $e->getMessage();
}

require_once (__DIR__ . "/views/header.php");
?>

<header class="admin-header">
    <h2>Gestion de Rol - Permiso</h2>
</header>

<div class="admin-main">
    <?php
    switch ($accion) {
        case 'asignar':
            if (isset($_POST['id_rol_FK']) && (int) $_POST['id_rol_FK'] > 0
                && isset($_POST['id_permiso_FK']) && (int) $_POST['id_permiso_FK'] > 0) {
                try {
                    $cantidad = $app->asignar($_POST);
                    if ($cantidad) {
                        $mensaje = 'Permiso asignado correctamente al rol';
                    }
                } catch (Exception $e) {
                    $mensaje = 'Error al asignar el permiso: ' . $e->getMessage();
                }
            } else {
                $mensaje = 'Rol y permiso son obligatorios.';
            }
            $asignaciones = $app->leer($busqueda);
            require_once (__DIR__ . "/views/rol_permiso/index.php");
            break;

        case 'revocar':
            if ($idRol > 0 && $idPermiso > 0) {
                try {
                    $cantidad = $app->revocar($idRol, $idPermiso);
                    if ($cantidad) {
                        $mensaje = 'Relacion rol-permiso eliminada correctamente';
                    } else {
                        $mensaje = 'No se encontro la relacion a eliminar';
                    }
                } catch (Exception $e) {
                    $mensaje = 'Error al eliminar la relacion: ' . $e->getMessage();
                }
            }
            $asignaciones = $app->leer($busqueda);
            require_once (__DIR__ . "/views/rol_permiso/index.php");
            break;

        case 'leer':
        default:
            $asignaciones = $app->leer($busqueda);
            require_once (__DIR__ . "/views/rol_permiso/index.php");
            break;
    }
    ?>
</div>

<?php require_once (__DIR__ . '/views/footer.php'); ?>
