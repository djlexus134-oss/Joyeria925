<?php
require_once __DIR__ . '/../sistema.class.php';
require_once __DIR__ . '/models/ordenes_taller.php';
require_once __DIR__ . '/models/configuracion_general.php';
require_once __DIR__ . '/includes/list_search.php';
require_once __DIR__ . '/includes/auth.php';

$busqueda = joyeria_list_search_normalize(isset($_GET['q']) ? (string) $_GET['q'] : '');

$app = new OrdenesTaller();
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$accion = isset($_GET['accion']) ? htmlspecialchars((string) $_GET['accion']) : null;
$mensaje = null;
$mensajeTipo = 'success';
$catalogos = $app->obtenerCatalogos();
$idFormaPagoDefault = (new ConfiguracionGeneral())->resolverIdFormaPagoDefault();

$usuarioSesion = function_exists('auth_user') ? auth_user() : null;
$idUsuarioSesion = is_array($usuarioSesion) ? (int) ($usuarioSesion['id_usuario'] ?? 0) : 0;

function ordenes_taller_redirect_listado(?string $flashMensaje = null, string $flashTipo = 'success', ?string $q = null): void
{
    if ($flashMensaje !== null && $flashMensaje !== '' && function_exists('auth_set_flash')) {
        auth_set_flash($flashMensaje, $flashTipo);
    }
    $url = 'ordenes_taller.php?accion=leer';
    if ($q !== null && $q !== '') {
        $url .= '&q=' . rawurlencode($q);
    }
    header('Location: ' . $url);
    exit;
}

function ordenes_taller_redirect_detalle(int $idOrden, ?string $flashMensaje = null, string $flashTipo = 'success'): void
{
    if ($flashMensaje !== null && $flashMensaje !== '' && function_exists('auth_set_flash')) {
        auth_set_flash($flashMensaje, $flashTipo);
    }
    header('Location: ordenes_taller.php?accion=actualizar&id=' . $idOrden);
    exit;
}

if ($accion === 'imprimir') {
    if (!auth_is_logged_in() || !auth_can_module_action('ordenes_taller', 'leer')) {
        auth_set_flash('No tienes permiso para imprimir ordenes de taller.', 'error');
        header('Location: login.php');
        exit;
    }
    if (!$id) {
        ordenes_taller_redirect_listado('Orden no especificada.', 'error', $busqueda);
    }
    $orden = $app->leerUno($id);
    if (!$orden) {
        ordenes_taller_redirect_listado('La orden no existe.', 'error', $busqueda);
    }
    $pagos = $app->leerPagos($id);
    $configNegocio = (new ConfiguracionGeneral())->leerPorClaves([
        'ticket_nombre_comercial',
        'ticket_horario',
        'ticket_mensaje_pie',
    ]);
    require __DIR__ . '/views/ordenes_taller/comprobante.php';
    exit;
}

require_once __DIR__ . '/views/header.php';
?>

<header class="admin-header">
    <h2><i class="bi bi-tools"></i> Ordenes de Taller</h2>
</header>

<div class="admin-main">
    <?php
    switch ($accion) {
        case 'crear':
            if (!empty($_POST)) {
                if ($idUsuarioSesion <= 0) {
                    $mensaje = 'Sesion no valida.';
                    $mensajeTipo = 'error';
                    require __DIR__ . '/views/ordenes_taller/formulario.php';
                    break;
                }
                try {
                    $idNuevo = $app->crear($_POST, $idUsuarioSesion);
                    ordenes_taller_redirect_detalle($idNuevo, 'Orden de taller creada correctamente.');
                } catch (Throwable $e) {
                    $mensaje = 'Error al crear la orden: ' . $e->getMessage();
                    $mensajeTipo = 'error';
                    require __DIR__ . '/views/ordenes_taller/formulario.php';
                }
            } else {
                require __DIR__ . '/views/ordenes_taller/formulario.php';
            }
            break;

        case 'actualizar':
            if (!$id) {
                ordenes_taller_redirect_listado('Orden no especificada.', 'error', $busqueda);
            }
            if (!empty($_POST)) {
                if ($idUsuarioSesion <= 0) {
                    $mensaje = 'Sesion no valida.';
                    $mensajeTipo = 'error';
                    $orden = $app->leerUno($id);
                    $pagos = $app->leerPagos($id);
                    $historial = $app->obtenerHistorial($id);
                    require __DIR__ . '/views/ordenes_taller/formulario.php';
                    break;
                }
                try {
                    $cantidad = $app->actualizar($id, $_POST, $idUsuarioSesion);
                    $flash = $cantidad ? 'Orden actualizada correctamente.' : 'No se realizaron cambios.';
                    ordenes_taller_redirect_detalle($id, $flash, $cantidad ? 'success' : 'info');
                } catch (Throwable $e) {
                    $mensaje = 'Error al actualizar: ' . $e->getMessage();
                    $mensajeTipo = 'error';
                    $orden = $app->leerUno($id);
                    $pagos = $app->leerPagos($id);
                    $historial = $app->obtenerHistorial($id);
                    require __DIR__ . '/views/ordenes_taller/formulario.php';
                }
            } else {
                $orden = $app->leerUno($id);
                if (!$orden) {
                    ordenes_taller_redirect_listado('La orden no existe.', 'error', $busqueda);
                }
                $pagos = $app->leerPagos($id);
                $historial = $app->obtenerHistorial($id);
                require __DIR__ . '/views/ordenes_taller/formulario.php';
            }
            break;

        case 'estado':
            if (!$id || empty($_POST['estado'])) {
                ordenes_taller_redirect_listado('Datos incompletos para cambiar estado.', 'error', $busqueda);
            }
            if ($idUsuarioSesion <= 0) {
                ordenes_taller_redirect_detalle($id, 'Sesion no valida.', 'error');
            }
            try {
                $nota = isset($_POST['nota']) ? trim((string) $_POST['nota']) : null;
                $app->cambiarEstado($id, (string) $_POST['estado'], $nota, $idUsuarioSesion);
                ordenes_taller_redirect_detalle($id, 'Estado actualizado correctamente.');
            } catch (Throwable $e) {
                ordenes_taller_redirect_detalle($id, 'Error al cambiar estado: ' . $e->getMessage(), 'error');
            }
            break;

        case 'abono':
            if (!$id || !isset($_POST['monto']) || !isset($_POST['id_forma_pago_FK'])) {
                ordenes_taller_redirect_listado('Datos incompletos para registrar abono.', 'error', $busqueda);
            }
            if ($idUsuarioSesion <= 0) {
                ordenes_taller_redirect_detalle($id, 'Sesion no valida.', 'error');
            }
            try {
                $monto = (float) $_POST['monto'];
                $idForma = (int) $_POST['id_forma_pago_FK'];
                $app->registrarAbono($id, $monto, $idForma, $idUsuarioSesion);
                ordenes_taller_redirect_detalle($id, 'Abono registrado correctamente.');
            } catch (Throwable $e) {
                ordenes_taller_redirect_detalle($id, 'Error al registrar abono: ' . $e->getMessage(), 'error');
            }
            break;

        case 'borrar':
            if ($id && $idUsuarioSesion > 0) {
                try {
                    $cantidad = $app->borrar($id, $idUsuarioSesion);
                    if ($cantidad) {
                        ordenes_taller_redirect_listado('Orden dada de baja correctamente.', 'success', $busqueda);
                    }
                    ordenes_taller_redirect_listado('No se pudo dar de baja la orden.', 'error', $busqueda);
                } catch (Throwable $e) {
                    ordenes_taller_redirect_listado('Error al dar de baja: ' . $e->getMessage(), 'error', $busqueda);
                }
            }
            $ordenes = $app->leer($busqueda);
            require __DIR__ . '/views/ordenes_taller/index.php';
            break;

        case 'leer':
        default:
            $ordenes = $app->leer($busqueda);
            require __DIR__ . '/views/ordenes_taller/index.php';
            break;
    }
    ?>
</div>

<?php require_once __DIR__ . '/views/footer.php'; ?>
