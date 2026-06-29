<?php

require_once __DIR__ . '/../sistema.class.php';
require_once __DIR__ . '/models/apartado_gestion.php';
require_once __DIR__ . '/models/ventas.php';
require_once __DIR__ . '/models/configuracion_general.php';

$accion = isset($_GET['accion']) ? htmlspecialchars((string) $_GET['accion']) : 'leer';

$app = new ApartadoGestion();
$ventasApp = new Ventas();

$usuarioSesion = function_exists('auth_user') ? auth_user() : null;
$idUsuarioSesion = is_array($usuarioSesion) ? (int) ($usuarioSesion['id_usuario'] ?? 0) : 0;
$idEmpleadoSesion = $idUsuarioSesion > 0 ? $ventasApp->obtenerEmpleadoIdPorUsuario($idUsuarioSesion) : null;

$catalogos = $ventasApp->obtenerCatalogos();
$catalogoClientes = $catalogos['clientes'] ?? [];
$catalogoImpuestos = $catalogos['impuestos'] ?? [];
$descuentoGeneralMostrador = $ventasApp->obtenerDescuentoGeneralMostrador();
$formasPago = $app->formasPagoAbono($app->getDb());
$configGeneral = new ConfiguracionGeneral();
$idFormaPagoDefault = $configGeneral->resolverIdFormaPagoDefault();
$idImpuestoDefault = $ventasApp->obtenerIdImpuestoDefault();
$fechaVencimientoDefecto = ApartadoGestion::fechaVencimientoUnMesDesdeHoy();

require_once __DIR__ . '/views/header.php';
?>

<header class="admin-header">
    <h2><i class="bi bi-plus-circle"></i> Apartados: alta (multilinea)</h2>
</header>

<div class="admin-main">
    <?php
    switch ($accion) {
        case 'leer':
        default:
            require __DIR__ . '/views/apartados_alta/index.php';
            break;
    }
    ?>
</div>

<?php require_once __DIR__ . '/views/footer.php'; ?>
