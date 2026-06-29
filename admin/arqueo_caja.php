<?php

require_once __DIR__ . '/../sistema.class.php';
require_once __DIR__ . '/models/cierre_caja.php';
require_once __DIR__ . '/includes/auth.php';

$app = new CierreCaja();

$fechaHoy = joyeria_today_ymd();
$fechaRaw = isset($_REQUEST['fecha']) ? trim((string) $_REQUEST['fecha']) : $fechaHoy;
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

$saldoInicialReq = null;
if (isset($_REQUEST['saldo_inicial']) && trim((string) $_REQUEST['saldo_inicial']) !== '') {
    if (!is_numeric($_REQUEST['saldo_inicial'])) {
        $errorResumen = 'El saldo inicial debe ser un numero valido.';
    } else {
        $saldoInicialReq = (float) $_REQUEST['saldo_inicial'];
    }
}

$efectivoContadoReq = null;
if (isset($_REQUEST['efectivo_contado']) && trim((string) $_REQUEST['efectivo_contado']) !== '') {
    if (!is_numeric($_REQUEST['efectivo_contado'])) {
        $errorResumen = 'El efectivo contado debe ser un numero valido.';
    } else {
        $efectivoContadoReq = (float) $_REQUEST['efectivo_contado'];
    }
}

$arqueo = null;
$saldoSugerido = null;
$cierreGuardado = null;

if ($errorResumen === null && $app->tieneColumnaEsEfectivo()) {
    try {
        $saldoSugerido = $app->obtenerSaldoInicialSugerido($fechaSeleccion);
        $arqueo = $app->calcularArqueo($fechaSeleccion, $saldoInicialReq, $efectivoContadoReq);
        $cierreGuardado = $app->leerPorFecha($fechaSeleccion);
    } catch (Throwable $e) {
        $errorResumen = $e->getMessage();
    }
} elseif ($errorResumen === null) {
    $errorResumen = 'Falta ejecutar la migracion SQL del modulo (forma_pago.es_efectivo).';
}

require_once __DIR__ . '/views/header.php';
?>

<header class="admin-header">
    <h2>Arqueo y descuadre de caja</h2>
</header>

<div class="admin-main">
    <?php require __DIR__ . '/views/arqueo_caja/index.php'; ?>
</div>

<?php require_once __DIR__ . '/views/footer.php'; ?>
