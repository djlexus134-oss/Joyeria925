<?php
require_once (__DIR__ . "/../sistema.class.php");
require_once (__DIR__ . "/models/forma_pago.php");
require_once __DIR__ . '/includes/list_search.php';

$busqueda = joyeria_list_search_normalize(isset($_GET['q']) ? (string) $_GET['q'] : '');

$app = new FormaPago();
$mostrarEsEfectivoCaja = $app->tieneCampoEsEfectivo();
$mostrarClaveSat = $app->tieneCampoClaveSat();
$id = (isset($_GET['id'])) ? intval($_GET['id']) : null;
$accion = (isset($_GET['accion'])) ? htmlspecialchars($_GET['accion']) : null;
$mensaje = null;

require_once (__DIR__ . "/views/header.php");
?>

<header class="admin-header">
    <h2>Gestion de Formas de Pago</h2>
</header>

<div class="admin-main">
    <?php
    switch ($accion) {
        case 'crear':
            if (isset($_POST['forma_pago']) && !empty(trim($_POST['forma_pago']))) {
                try {
                    $cantidad = $app->crear($_POST);
                    if ($cantidad) {
                        $mensaje = 'Forma de pago creada correctamente';
                    }
                } catch (Exception $e) {
                    $mensaje = 'Error al crear la forma de pago: ' . $e->getMessage();
                }
                $formasPago = $app->leer($busqueda);
                require_once (__DIR__ . "/views/forma_pago/index.php");
            } else {
                if (!empty($_POST)) {
                    $mensaje = 'La forma de pago es obligatoria.';
                }
                require_once (__DIR__ . "/views/forma_pago/formulario.php");
            }
            break;

        case 'actualizar':
            if (isset($_POST['forma_pago']) && !empty(trim($_POST['forma_pago']))) {
                try {
                    $cantidad = $app->actualizar($id, $_POST);
                    if ($cantidad) {
                        $mensaje = 'Forma de pago actualizada correctamente';
                    } else {
                        $mensaje = 'No se realizaron cambios';
                    }
                } catch (Exception $e) {
                    $mensaje = 'Error al actualizar la forma de pago: ' . $e->getMessage();
                }
                $formasPago = $app->leer($busqueda);
                require_once (__DIR__ . "/views/forma_pago/index.php");
            } else {
                $formaPago = $app->leerUno($id);
                require_once (__DIR__ . "/views/forma_pago/formulario.php");
            }
            break;

        case 'borrar':
            if ($id) {
                try {
                    $idUsuarioActual = $_SESSION['id_usuario'] ?? null;
                    $cantidad = $app->borrar($id, $idUsuarioActual);
                    if ($cantidad) {
                        $mensaje = 'Forma de pago dada de baja correctamente';
                    } else {
                        $mensaje = 'No se pudo dar de baja la forma de pago';
                    }
                } catch (Exception $e) {
                    $mensaje = 'Error al dar de baja la forma de pago: ' . $e->getMessage();
                }
            }
            $formasPago = $app->leer($busqueda);
            require_once (__DIR__ . "/views/forma_pago/index.php");
            break;

        case 'leer':
        default:
            $formasPago = $app->leer($busqueda);
            require_once (__DIR__ . "/views/forma_pago/index.php");
            break;
    }
    ?>
</div>

<?php require_once (__DIR__ . '/views/footer.php'); ?>
