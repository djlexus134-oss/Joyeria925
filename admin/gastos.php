<?php
require_once __DIR__ . "/../sistema.class.php";
require_once __DIR__ . "/models/gastos.php";
require_once __DIR__ . '/models/configuracion_general.php';
require_once __DIR__ . '/includes/list_search.php';

$busqueda = joyeria_list_search_normalize(isset($_GET['q']) ? (string) $_GET['q'] : '');

$app = new Gastos();
$id = isset($_GET['id']) ? intval($_GET['id']) : null;
$accion = isset($_GET['accion']) ? htmlspecialchars($_GET['accion']) : null;
$mensaje = null;
$mensajeTipo = 'success';
$catalogos = $app->obtenerCatalogos();
$idFormaPagoDefault = (new ConfiguracionGeneral())->resolverIdFormaPagoDefault();

/**
 * Redirige al listado (POST-redirect-GET) con flash opcional.
 */
function gastos_redirect_listado(?string $flashMensaje = null, string $flashTipo = 'success', ?string $q = null): void
{
    if ($flashMensaje !== null && $flashMensaje !== '' && function_exists('auth_set_flash')) {
        auth_set_flash($flashMensaje, $flashTipo);
    }
    $url = 'gastos.php?accion=leer';
    if ($q !== null && $q !== '') {
        $url .= '&q=' . rawurlencode($q);
    }
    header('Location: ' . $url);
    exit;
}

require_once __DIR__ . "/views/header.php";
?>

<header class="admin-header">
    <h2>Gestion de Gastos</h2>
</header>

<div class="admin-main">
    <?php
    $usuarioSesion = function_exists('auth_user') ? auth_user() : null;
    $idUsuarioSesion = is_array($usuarioSesion) ? (int) ($usuarioSesion['id_usuario'] ?? 0) : 0;
    $idEmpleadoSesion = $idUsuarioSesion > 0 ? $app->obtenerIdEmpleadoPorUsuario($idUsuarioSesion) : null;

    switch ($accion) {
        case 'crear':
            if (isset($_POST['id_categoria_FK']) && (int) $_POST['id_categoria_FK'] > 0
                && isset($_POST['concepto']) && !empty(trim($_POST['concepto']))
                && isset($_POST['monto']) && trim((string) $_POST['monto']) !== ''
                && isset($_POST['fecha_gasto']) && !empty(trim($_POST['fecha_gasto']))
                && $idEmpleadoSesion !== null) {

                try {
                    $_POST['id_empleado_FK'] = (string) $idEmpleadoSesion;
                    $idGenerado = $app->crear($_POST);
                    if ($idGenerado <= 0) {
                        throw new RuntimeException('No se obtuvo el identificador del gasto creado.');
                    }
                    gastos_redirect_listado('Gasto creado correctamente.', 'success', $busqueda);
                } catch (Throwable $e) {
                    $mensaje = 'Error al crear el gasto: ' . $e->getMessage();
                    $mensajeTipo = 'error';
                    require __DIR__ . "/views/gastos/formulario.php";
                }
            } else {
                if (!empty($_POST)) {
                    $mensaje = $idEmpleadoSesion === null
                        ? 'Tu usuario no tiene un empleado activo vinculado. No se puede registrar el gasto.'
                        : 'Faltan campos obligatorios para crear el gasto.';
                    $mensajeTipo = 'error';
                }
                require __DIR__ . "/views/gastos/formulario.php";
            }
            break;

        case 'actualizar':
            if ($id
                && isset($_POST['id_categoria_FK']) && (int) $_POST['id_categoria_FK'] > 0
                && isset($_POST['concepto']) && !empty(trim($_POST['concepto']))
                && isset($_POST['monto']) && trim((string) $_POST['monto']) !== ''
                && isset($_POST['fecha_gasto']) && !empty(trim($_POST['fecha_gasto']))
                && $idEmpleadoSesion !== null) {

                try {
                    $_POST['id_empleado_FK'] = (string) $idEmpleadoSesion;
                    $cantidad = $app->actualizar($id, $_POST);
                    $flash = $cantidad
                        ? 'Gasto actualizado correctamente.'
                        : 'No se realizaron cambios en el gasto.';
                    gastos_redirect_listado($flash, $cantidad ? 'success' : 'info', $busqueda);
                } catch (Throwable $e) {
                    $mensaje = 'Error al actualizar el gasto: ' . $e->getMessage();
                    $mensajeTipo = 'error';
                    $gasto = $app->leerUno($id);
                    require __DIR__ . "/views/gastos/formulario.php";
                }
            } else {
                if (!empty($_POST)) {
                    $mensaje = $idEmpleadoSesion === null
                        ? 'Tu usuario no tiene un empleado activo vinculado. No se puede actualizar el gasto.'
                        : 'Faltan campos obligatorios para actualizar el gasto.';
                    $mensajeTipo = 'error';
                }
                $gasto = $app->leerUno($id);
                require __DIR__ . "/views/gastos/formulario.php";
            }
            break;

        case 'borrar':
            if ($id) {
                try {
                    $cantidad = $app->borrar($id);
                    if ($cantidad) {
                        gastos_redirect_listado('Gasto eliminado correctamente.', 'success', $busqueda);
                    }
                    gastos_redirect_listado('No se pudo eliminar el gasto.', 'error', $busqueda);
                } catch (Throwable $e) {
                    gastos_redirect_listado('Error al eliminar el gasto: ' . $e->getMessage(), 'error', $busqueda);
                }
            }
            $gastos = $app->leer($busqueda);
            require __DIR__ . "/views/gastos/index.php";
            break;

        case 'leer':
        default:
            $gastos = $app->leer($busqueda);
            require __DIR__ . "/views/gastos/index.php";
            break;
    }
    ?>
</div>

<?php require_once __DIR__ . '/views/footer.php'; ?>
