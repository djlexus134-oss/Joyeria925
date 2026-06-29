<?php
require_once __DIR__ . "/../sistema.class.php";
require_once __DIR__ . "/models/ventas.php";
require_once __DIR__ . '/models/devoluciones.php';
require_once __DIR__ . '/models/factura.php';
require_once __DIR__ . '/includes/list_search.php';
require_once __DIR__ . '/includes/list_filters.php';

$busqueda = joyeria_list_search_normalize(isset($_GET['q']) ? (string) $_GET['q'] : '');
$filtrosVentas = joyeria_ventas_filtros_desde_get($_GET);

$app = new Ventas();
$devolucionesApp = new Devoluciones();
$facturaApp = new Factura();
$id = isset($_GET['id']) ? intval($_GET['id']) : null;
$accion = isset($_GET['accion']) ? htmlspecialchars($_GET['accion']) : null;
$mensaje = null;
$catalogos = $app->obtenerCatalogos();
$idImpuestoDefault = $app->obtenerIdImpuestoDefault();

require_once __DIR__ . "/views/header.php";
?>

<header class="admin-header">
    <h2>Gestion de Ventas</h2>
</header>

<div class="admin-main">
    <?php
    switch ($accion) {
        case 'crear':
            if (isset($_POST['id_cliente_FK']) && (int) $_POST['id_cliente_FK'] > 0
                && isset($_POST['id_empleado_FK']) && (int) $_POST['id_empleado_FK'] > 0
                && isset($_POST['id_impuesto_FK']) && (int) $_POST['id_impuesto_FK'] > 0
                && isset($_POST['total']) && trim((string) $_POST['total']) !== ''
                && isset($_POST['impuesto_porcentaje']) && trim((string) $_POST['impuesto_porcentaje']) !== ''
                && isset($_POST['impuesto_monto']) && trim((string) $_POST['impuesto_monto']) !== '') {

                try {
                    $idGenerado = $app->crear($_POST);
                    $mensaje = $idGenerado > 0 ? 'Venta creada correctamente' : 'No se pudo crear la venta.';
                    $ventas = $app->leer($busqueda, $filtrosVentas);
                    require __DIR__ . "/views/ventas/index.php";
                } catch (Exception $e) {
                    $mensaje = 'Error al crear la venta: ' . $e->getMessage();
                    require __DIR__ . "/views/ventas/formulario.php";
                }
            } else {
                if (!empty($_POST)) {
                    $mensaje = 'Faltan campos obligatorios para crear la venta.';
                }
                require __DIR__ . "/views/ventas/formulario.php";
            }
            break;

        case 'ver':
            if ($id && (int) $id > 0) {
                $venta = $app->leerUno($id);
                if (!$venta) {
                    $mensaje = 'Venta no encontrada.';
                    $ventas = $app->leer($busqueda, $filtrosVentas);
                    require __DIR__ . '/views/ventas/index.php';
                } else {
                    $venta['pagos'] = $app->leerPagosVenta((int) $id);
                    $venta['devoluciones'] = $devolucionesApp->listarPorVenta((int) $id);
                    $venta['canje_creditos'] = $devolucionesApp->listarCanjeEnVentaDestino((int) $id);
                    $facturaCfdi = $facturaApp->leerPorVenta((int) $id);
                    $facturacionHabilitada = $facturaApp->facturacionHabilitada();
                    require __DIR__ . '/views/ventas/detalle.php';
                }
            } else {
                $mensaje = 'ID de venta invalido.';
                $ventas = $app->leer($busqueda, $filtrosVentas);
                require __DIR__ . '/views/ventas/index.php';
            }
            break;

        case 'actualizar':
            if ($id
                && isset($_POST['id_cliente_FK']) && (int) $_POST['id_cliente_FK'] > 0
                && isset($_POST['id_empleado_FK']) && (int) $_POST['id_empleado_FK'] > 0
                && isset($_POST['id_impuesto_FK']) && (int) $_POST['id_impuesto_FK'] > 0
                && isset($_POST['total']) && trim((string) $_POST['total']) !== ''
                && isset($_POST['impuesto_porcentaje']) && trim((string) $_POST['impuesto_porcentaje']) !== ''
                && isset($_POST['impuesto_monto']) && trim((string) $_POST['impuesto_monto']) !== '') {

                try {
                    $cantidad = $app->actualizar($id, $_POST);
                    $mensaje = $cantidad ? 'Venta actualizada correctamente' : 'No se realizaron cambios';
                    $ventas = $app->leer($busqueda, $filtrosVentas);
                    require __DIR__ . "/views/ventas/index.php";
                } catch (Exception $e) {
                    $mensaje = 'Error al actualizar la venta: ' . $e->getMessage();
                    $venta = $app->leerUno($id);
                    require __DIR__ . "/views/ventas/formulario.php";
                }
            } else {
                if (!empty($_POST)) {
                    $mensaje = 'Faltan campos obligatorios para actualizar la venta.';
                }
                $venta = $app->leerUno($id);
                require __DIR__ . "/views/ventas/formulario.php";
            }
            break;

        case 'borrar':
            if ($id) {
                try {
                    $cantidad = $app->borrar($id);
                    $mensaje = $cantidad ? 'Venta cancelada correctamente' : 'No se pudo cancelar la venta';
                } catch (Exception $e) {
                    $mensaje = 'Error al cancelar la venta: ' . $e->getMessage();
                }
            }
            $ventas = $app->leer($busqueda, $filtrosVentas);
            require __DIR__ . "/views/ventas/index.php";
            break;

        case 'leer':
        default:
            $ventas = $app->leer($busqueda, $filtrosVentas);
            require __DIR__ . "/views/ventas/index.php";
            break;
    }
    ?>
</div>

<?php require_once __DIR__ . '/views/footer.php'; ?>
