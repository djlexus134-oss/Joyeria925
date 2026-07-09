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

    require_once __DIR__ . '/includes/ImpresionTicketHelper.php';
    $retorno = isset($_GET['retorno']) ? trim((string) $_GET['retorno']) : 'detalle';
    try {
        $idCola = joyeria_encolar_ticket_orden_taller($id);
        if ($idCola !== null && $idCola > 0) {
            $flash = 'Ticket encolado para impresion en la caja (cola #' . $idCola . ').';
            $flashTipo = 'success';
        } else {
            $flash = 'La impresion de tickets esta deshabilitada o no se pudo encolar. Revisa Configuracion > Impresion.';
            $flashTipo = 'error';
        }
    } catch (Throwable $e) {
        $flash = 'No se pudo encolar el ticket: ' . $e->getMessage();
        $flashTipo = 'error';
    }

    if ($retorno === 'leer') {
        ordenes_taller_redirect_listado($flash, $flashTipo, $busqueda);
    }
    ordenes_taller_redirect_detalle($id, $flash, $flashTipo);
}

$metodoHttp = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$accionesMutacionPreHeader = ['crear', 'actualizar', 'estado', 'abono', 'borrar'];
$requiereMutacionPreHeader = in_array((string) $accion, $accionesMutacionPreHeader, true)
    && (
        ($metodoHttp === 'POST' && in_array((string) $accion, ['crear', 'actualizar', 'estado', 'abono'], true))
        || ((string) $accion === 'borrar' && $id)
    );

if ($requiereMutacionPreHeader) {
    $guardMutacion = auth_current_access_guard();
    if (!$guardMutacion['allowed']) {
        auth_set_flash((string) $guardMutacion['message'], 'error');
        if (!empty($guardMutacion['redirect'])) {
            header('Location: ' . $guardMutacion['redirect']);
            exit;
        }
        http_response_code(403);
        echo 'Acceso denegado.';
        exit;
    }

    if ($metodoHttp === 'POST') {
        joyeria_admin_csrf_require_for_post();
    }

    switch ($accion) {
        case 'crear':
            if ($idUsuarioSesion <= 0) {
                auth_set_flash('Sesion no valida.', 'error');
                header('Location: ordenes_taller.php?accion=crear');
                exit;
            }
            if (!auth_can_module_action('ordenes_taller', 'CREAR')) {
                auth_set_flash('No tienes permiso para crear ordenes de taller.', 'error');
                header('Location: ordenes_taller.php?accion=leer');
                exit;
            }
            try {
                $idNuevo = $app->crear($_POST, $idUsuarioSesion);
                ordenes_taller_redirect_detalle($idNuevo, 'Orden de taller creada correctamente.');
            } catch (Throwable $e) {
                auth_set_flash('Error al crear la orden: ' . $e->getMessage(), 'error');
                header('Location: ordenes_taller.php?accion=crear');
                exit;
            }
            break;

        case 'actualizar':
            if (!$id) {
                ordenes_taller_redirect_listado('Orden no especificada.', 'error', $busqueda);
            }
            if ($idUsuarioSesion <= 0) {
                ordenes_taller_redirect_detalle($id, 'Sesion no valida.', 'error');
            }
            if (!auth_can_module_action('ordenes_taller', 'ACTUALIZAR')) {
                ordenes_taller_redirect_detalle($id, 'No tienes permiso para actualizar ordenes de taller.', 'error');
            }
            try {
                $cantidad = $app->actualizar($id, $_POST, $idUsuarioSesion);
                $flash = $cantidad ? 'Orden actualizada correctamente.' : 'No se realizaron cambios.';
                ordenes_taller_redirect_detalle($id, $flash, $cantidad ? 'success' : 'info');
            } catch (Throwable $e) {
                ordenes_taller_redirect_detalle($id, 'Error al actualizar: ' . $e->getMessage(), 'error');
            }
            break;

        case 'estado':
            if (!$id || empty($_POST['estado'])) {
                ordenes_taller_redirect_listado('Datos incompletos para cambiar estado.', 'error', $busqueda);
            }
            if ($idUsuarioSesion <= 0) {
                ordenes_taller_redirect_detalle($id, 'Sesion no valida.', 'error');
            }
            if (!auth_can_module_action('ordenes_taller', 'ACTUALIZAR')) {
                ordenes_taller_redirect_detalle($id, 'No tienes permiso para cambiar el estado.', 'error');
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
            if (!auth_can_module_action('ordenes_taller', 'ACTUALIZAR')) {
                ordenes_taller_redirect_detalle($id, 'No tienes permiso para registrar abonos.', 'error');
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
            if ($idUsuarioSesion <= 0) {
                ordenes_taller_redirect_listado('Sesion no valida.', 'error', $busqueda);
            }
            if (!auth_can_module_action('ordenes_taller', 'BORRAR')) {
                ordenes_taller_redirect_listado('No tienes permiso para dar de baja ordenes de taller.', 'error', $busqueda);
            }
            try {
                $cantidad = $app->borrar($id, $idUsuarioSesion);
                if ($cantidad) {
                    ordenes_taller_redirect_listado('Orden dada de baja correctamente.', 'success', $busqueda);
                }
                ordenes_taller_redirect_listado('No se pudo dar de baja la orden.', 'error', $busqueda);
            } catch (Throwable $e) {
                ordenes_taller_redirect_listado('Error al dar de baja: ' . $e->getMessage(), 'error', $busqueda);
            }
            break;
    }
}

// Redirecciones GET que no deben renderizar HTML (orden inexistente, accion invalida, etc.).
if ($metodoHttp === 'GET') {
    if ((string) $accion === 'actualizar') {
        if (!$id) {
            ordenes_taller_redirect_listado('Orden no especificada.', 'error', $busqueda);
        }
        if (!$app->leerUno($id)) {
            ordenes_taller_redirect_listado('La orden no existe.', 'error', $busqueda);
        }
    }
    if (in_array((string) $accion, ['estado', 'abono'], true)) {
        if ($id) {
            ordenes_taller_redirect_detalle($id);
        }
        ordenes_taller_redirect_listado('Accion no valida.', 'error', $busqueda);
    }
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
            require __DIR__ . '/views/ordenes_taller/formulario.php';
            break;

        case 'actualizar':
            $orden = $app->leerUno($id);
            $pagos = $app->leerPagos($id);
            $historial = $app->obtenerHistorial($id);
            require __DIR__ . '/views/ordenes_taller/formulario.php';
            break;

        case 'borrar':
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
