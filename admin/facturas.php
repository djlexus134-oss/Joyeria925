<?php
require_once __DIR__ . '/../sistema.class.php';
require_once __DIR__ . '/models/factura.php';

$app = new Factura();
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$accion = isset($_GET['accion']) ? htmlspecialchars((string) $_GET['accion']) : 'leer';
$mensaje = null;
$mensajeTipo = 'success';

require_once __DIR__ . '/views/header.php';
?>

<header class="admin-header">
    <h2>Facturas CFDI</h2>
</header>

<div class="admin-main">
<?php
switch ($accion) {
    case 'ver':
        if ($id > 0) {
            $factura = $app->leerUno($id);
            if (!$factura) {
                $mensaje = 'Factura no encontrada.';
                $facturas = $app->leer();
                require __DIR__ . '/views/facturas/index.php';
            } else {
                require __DIR__ . '/views/facturas/detalle.php';
            }
        } else {
            $mensaje = 'ID invalido.';
            $facturas = $app->leer();
            require __DIR__ . '/views/facturas/index.php';
        }
        break;

    case 'emitir':
        $idVenta = isset($_GET['id_venta']) ? (int) $_GET['id_venta'] : 0;
        if ($idVenta > 0) {
            $res = $app->emitirYEnviarParaVenta($idVenta, true);
            $mensaje = $res['ok']
                ? 'Factura emitida/enviada para venta #' . $idVenta
                : ('Error: ' . ($res['error'] ?? 'desconocido'));
            $mensajeTipo = $res['ok'] ? 'success' : 'error';
        }
        $facturas = $app->leer();
        require __DIR__ . '/views/facturas/index.php';
        break;

    case 'reenviar':
        if ($id > 0) {
            $app->enviarAlCliente($id);
            $mensaje = 'Reenvio procesado para factura #' . $id;
        }
        $factura = $id > 0 ? $app->leerUno($id) : null;
        if ($factura) {
            require __DIR__ . '/views/facturas/detalle.php';
        } else {
            $facturas = $app->leer();
            require __DIR__ . '/views/facturas/index.php';
        }
        break;

    case 'cancelar':
        if ($id > 0) {
            $res = $app->cancelar($id);
            $mensaje = $res['ok'] ? 'Factura cancelada.' : ('Error: ' . ($res['error'] ?? ''));
            $mensajeTipo = $res['ok'] ? 'success' : 'error';
        }
        $factura = $id > 0 ? $app->leerUno($id) : null;
        if ($factura) {
            require __DIR__ . '/views/facturas/detalle.php';
        } else {
            $facturas = $app->leer();
            require __DIR__ . '/views/facturas/index.php';
        }
        break;

    default:
        $facturas = $app->leer();
        require __DIR__ . '/views/facturas/index.php';
        break;
}
?>
</div>

<?php require_once __DIR__ . '/views/footer.php'; ?>
