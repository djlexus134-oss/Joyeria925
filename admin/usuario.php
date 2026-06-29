<?php
require_once (__DIR__ . "/../sistema.class.php");
require_once (__DIR__ . "/models/usuario.php");
require_once __DIR__ . '/includes/list_search.php';

$busqueda = joyeria_list_search_normalize(isset($_GET['q']) ? (string) $_GET['q'] : '');

$app = new Usuario();
$id = (isset($_GET['id'])) ? intval($_GET['id']) : null;
$accion = (isset($_GET['accion'])) ? htmlspecialchars($_GET['accion']) : null;
$mensaje = null;

$direcciones = [];
try {
    $direcciones = $app->obtenerDirecciones();
} catch (Exception $e) {
    $mensaje = 'No se pudieron cargar las direcciones: ' . $e->getMessage();
}

require_once (__DIR__ . "/views/header.php");
?>

<header class="admin-header">
    <h2>Gestión de Usuarios</h2>
</header>

<div class="admin-main">
    <?php
    switch ($accion) {
        case 'crear':
            if (isset($_POST['nombre']) && trim((string) $_POST['nombre']) !== ''
                && isset($_POST['primer_apellido']) && trim((string) $_POST['primer_apellido']) !== ''
                && isset($_POST['correo']) && trim((string) $_POST['correo']) !== ''
                && isset($_POST['telefono']) && trim((string) $_POST['telefono']) !== ''
                && isset($_POST['contrasena']) && trim((string) $_POST['contrasena']) !== '') {
                try {
                    $id = $app->crear($_POST);
                    if ($id) {
                        $mensaje = 'Usuario creado correctamente';
                    }
                } catch (Exception $e) {
                    $mensaje = 'Error al crear el usuario: ' . $e->getMessage();
                }
                $usuarios = $app->leer($busqueda);
                require_once (__DIR__ . "/views/usuario/index.php");
            } else {
                if (!empty($_POST)) {
                    $mensaje = 'Los campos marcados con * son obligatorios.';
                }
                require_once (__DIR__ . "/views/usuario/formulario.php");
            }
            break;

        case 'actualizar':
            if (isset($_POST['nombre']) && trim((string) $_POST['nombre']) !== ''
                && isset($_POST['primer_apellido']) && trim((string) $_POST['primer_apellido']) !== ''
                && isset($_POST['correo']) && trim((string) $_POST['correo']) !== ''
                && isset($_POST['telefono']) && trim((string) $_POST['telefono']) !== '') {
                try {
                    $app->actualizar($id, $_POST);
                    $mensaje = 'Usuario actualizado correctamente';
                } catch (Exception $e) {
                    $mensaje = 'Error al actualizar el usuario: ' . $e->getMessage();
                }
                $usuarios = $app->leer($busqueda);
                require_once (__DIR__ . "/views/usuario/index.php");
            } else {
                $usuario = $app->leerUno($id);
                require_once (__DIR__ . "/views/usuario/formulario.php");
            }
            break;

        case 'borrar':
            if ($id) {
                try {
                    $app->eliminar($id);
                    $mensaje = 'Usuario dado de baja correctamente';
                } catch (Exception $e) {
                    $mensaje = 'Error al dar de baja el usuario: ' . $e->getMessage();
                }
            }
            $usuarios = $app->leer($busqueda);
            require_once (__DIR__ . "/views/usuario/index.php");
            break;

        case 'leer':
        default:
            $usuarios = $app->leer($busqueda);
            require_once (__DIR__ . "/views/usuario/index.php");
            break;
    }
    ?>
</div>

<?php require_once (__DIR__ . '/views/footer.php'); ?>
