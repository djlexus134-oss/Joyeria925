<?php

require_once __DIR__ . '/../sistema.class.php';
require_once __DIR__ . '/models/cierre_caja.php';
require_once __DIR__ . '/includes/auth.php';

$app = new CierreCaja();
$accion = isset($_GET['accion']) ? htmlspecialchars((string) $_GET['accion']) : null;

$fechaHoy = joyeria_today_ymd();
$fechaRaw = isset($_GET['fecha']) ? trim((string) $_GET['fecha']) : $fechaHoy;
$errorResumen = null;
try {
    $fechaSeleccion = $app->validarFechaOperacion($fechaRaw);
} catch (InvalidArgumentException $e) {
    $fechaSeleccion = $fechaHoy;
    $errorResumen = $e->getMessage();
}

$guard = auth_current_access_guard();
if (!$guard['allowed']) {
    auth_set_flash((string) $guard['message'], 'error');
    if (!empty($guard['redirect'])) {
        header('Location: ' . $guard['redirect']);
        exit;
    }
    http_response_code(403);
    echo 'Acceso denegado.';
    exit;
}

joyeria_admin_csrf_require_for_post();

$usuarioSesion = auth_user();
$idUsuarioSesion = is_array($usuarioSesion) ? (int) ($usuarioSesion['id_usuario'] ?? 0) : 0;

// Redirecciones POST deben ejecutarse antes de cualquier salida HTML (header.php).
if ($accion === 'crear') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: cierre_caja.php?accion=leer&fecha=' . urlencode($fechaSeleccion));
        exit;
    }
    if (!auth_can_module_action('cierre_caja', 'CREAR')) {
        auth_set_flash('No tienes permiso para registrar cierres de caja.', 'error');
        header('Location: cierre_caja.php?accion=leer&fecha=' . urlencode($fechaSeleccion));
        exit;
    }
    if ($idUsuarioSesion <= 0) {
        auth_set_flash('Sesion invalida.', 'error');
        header('Location: cierre_caja.php?accion=leer');
        exit;
    }
    $fechaPost = isset($_POST['fecha_operacion']) ? trim((string) $_POST['fecha_operacion']) : '';
    try {
        $idNuevo = $app->crear($idUsuarioSesion, $_POST);
        auth_set_flash('Cierre registrado correctamente (ID #' . (int) $idNuevo . ').', 'success');
        header('Location: cierre_caja.php?accion=leer&fecha=' . urlencode($fechaPost !== '' ? $fechaPost : $fechaSeleccion));
        exit;
    } catch (Throwable $e) {
        auth_set_flash('No se pudo registrar el cierre: ' . $e->getMessage(), 'error');
        header('Location: cierre_caja.php?accion=leer&fecha=' . urlencode($fechaPost !== '' ? $fechaPost : $fechaSeleccion));
        exit;
    }
}

$mensaje = null;
$mensajeTipo = 'info';

$resumen = null;
$cierreGuardado = null;
$saldoSugerido = null;

if ($errorResumen === null && $app->tieneColumnaEsEfectivo()) {
    try {
        $saldoSugerido = $app->obtenerSaldoInicialSugerido($fechaSeleccion);
        $resumen = $app->calcularResumen($fechaSeleccion);
        $cierreGuardado = $app->leerPorFecha($fechaSeleccion);
    } catch (Throwable $e) {
        $errorResumen = $e->getMessage();
    }
} elseif ($errorResumen === null) {
    $errorResumen = 'Falta ejecutar la migracion SQL del modulo (forma_pago.es_efectivo y tabla cierre_caja).';
}

$historial = [];
try {
    $historial = $app->leerHistorial(90);
} catch (Throwable $e) {
    $historial = [];
}

require_once __DIR__ . '/views/header.php';
?>

<header class="admin-header">
    <h2>Cierre de caja</h2>
</header>

<div class="admin-main modulo-cierre-caja">
    <?php require __DIR__ . '/views/cierre_caja/index.php'; ?>
</div>

<?php require_once __DIR__ . '/views/footer.php'; ?>
