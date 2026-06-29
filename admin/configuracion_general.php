<?php
require_once __DIR__ . '/../sistema.class.php';
require_once __DIR__ . '/models/configuracion_general.php';
require_once __DIR__ . '/includes/ConfiguracionGeneralPanelController.php';
require_once __DIR__ . '/includes/list_search.php';
require_once __DIR__ . '/includes/auth.php';

$accionRaw = isset($_GET['accion']) ? (string) $_GET['accion'] : 'panel';
$accion = htmlspecialchars($accionRaw);

if (in_array($accionRaw, ['avanzado', 'crear', 'actualizar', 'borrar'], true)) {
    $modoAvanzado = true;
    $busqueda = joyeria_list_search_normalize(isset($_GET['q']) ? (string) $_GET['q'] : '');
    $app = new ConfiguracionGeneral();
    $id = isset($_GET['id']) ? intval($_GET['id']) : null;
    $tipos = $app->tipos();
    $mensaje = null;
    $accionCrud = $accionRaw === 'avanzado' ? 'leer' : $accionRaw;

    require_once __DIR__ . '/views/header.php';
    ?>
    <header class="admin-header">
        <h2>Configuracion — modo avanzado</h2>
        <a href="configuracion_general.php" class="btn-action-secondary"><i class="bi bi-arrow-left"></i> Volver al panel</a>
    </header>
    <div class="admin-main">
    <?php
    switch ($accionCrud) {
        case 'crear':
            if (isset($_POST['valor']) && trim((string) $_POST['valor']) !== '' && isset($_POST['tipo']) && trim((string) $_POST['tipo']) !== '') {
                try {
                    if ($app->crear($_POST)) {
                        $mensaje = 'Configuracion creada correctamente';
                    }
                } catch (Exception $e) {
                    $mensaje = 'Error al crear: ' . $e->getMessage();
                }
                $configuraciones = $app->leer($busqueda);
                require __DIR__ . '/views/configuracion_general/index.php';
            } else {
                if (!empty($_POST)) {
                    $mensaje = 'Faltan campos obligatorios.';
                }
                require __DIR__ . '/views/configuracion_general/formulario.php';
            }
            break;

        case 'actualizar':
            if (isset($_POST['valor']) && trim((string) $_POST['valor']) !== '' && isset($_POST['tipo']) && trim((string) $_POST['tipo']) !== '') {
                try {
                    $cantidad = $app->actualizar($id, $_POST);
                    $mensaje = $cantidad ? 'Configuracion actualizada' : 'Sin cambios';
                } catch (Exception $e) {
                    $mensaje = 'Error: ' . $e->getMessage();
                }
                $configuraciones = $app->leer($busqueda);
                require __DIR__ . '/views/configuracion_general/index.php';
            } else {
                $configuracion = $app->leerUno($id);
                require __DIR__ . '/views/configuracion_general/formulario.php';
            }
            break;

        case 'borrar':
            if ($id) {
                try {
                    $mensaje = $app->borrar($id) ? 'Eliminada' : 'No se pudo eliminar';
                } catch (Exception $e) {
                    $mensaje = 'Error: ' . $e->getMessage();
                }
            }
            $configuraciones = $app->leer($busqueda);
            require __DIR__ . '/views/configuracion_general/index.php';
            break;

        case 'leer':
        default:
            $configuraciones = $app->leer($busqueda);
            require __DIR__ . '/views/configuracion_general/index.php';
            break;
    }
    ?>
    </div>
    <?php
    require_once __DIR__ . '/views/footer.php';
    exit;
}

$panel = new ConfiguracionGeneralPanelController();
$seccion = $panel->seccionActiva($_GET['seccion'] ?? null);
$mensaje = null;
$tipoMensaje = 'success';
$valores = $panel->valoresActuales();
$catalogos = $panel->catalogos();
$vistaPrevia = $panel->vistaPreviaTicket($valores);
$opcionesBarcode = $panel->opcionesTipoCodigoBarras();

if (isset($_GET['accion']) && (string) $_GET['accion'] === 'guardar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    joyeria_admin_csrf_require_for_post();
    $guardConfig = auth_current_access_guard();
    if (!$guardConfig['allowed']) {
        auth_set_flash((string) $guardConfig['message'], 'error');
        header('Location: ' . ($guardConfig['redirect'] ?? 'configuracion_general.php'));
        exit;
    }
    try {
        $panel->guardar($_POST);
        $valores = $panel->valoresActuales();
        $vistaPrevia = $panel->vistaPreviaTicket($valores);
        $seccion = $panel->seccionActiva($_POST['seccion_activa'] ?? $seccion);
        $mensaje = 'Configuracion guardada correctamente.';
    } catch (Throwable $e) {
        $mensaje = 'Error al guardar: ' . $e->getMessage();
        $tipoMensaje = 'error';
        $valores = array_merge($valores, array_intersect_key($_POST, $valores));
    }
}

require_once __DIR__ . '/views/header.php';
?>
<header class="admin-header config-hub-header">
    <div>
        <h2>Configuracion del sistema</h2>
        <p class="config-hub-subtitle">Negocio, punto de venta, impresion, etiquetas y contratos en un solo lugar.</p>
    </div>
    <a href="configuracion_general.php?accion=avanzado" class="btn-action-secondary config-hub-advanced-link">
        <i class="bi bi-code-slash"></i> Modo avanzado
    </a>
</header>

<div class="admin-main">
    <?php require __DIR__ . '/views/configuracion_general/panel.php'; ?>
</div>

<?php require_once __DIR__ . '/views/footer.php'; ?>
