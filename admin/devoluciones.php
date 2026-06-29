<?php

require_once __DIR__ . '/../sistema.class.php';
require_once __DIR__ . '/models/ventas.php';
require_once __DIR__ . '/models/devoluciones.php';
require_once __DIR__ . '/includes/auth.php';

$accion = isset($_GET['accion']) ? htmlspecialchars((string) $_GET['accion']) : 'leer';

$ventasApp = new Ventas();
$devolucionesApp = new Devoluciones();

$usuarioSesion = auth_user();
$idUsuarioSesion = is_array($usuarioSesion) ? (int) ($usuarioSesion['id_usuario'] ?? 0) : 0;
$idEmpleadoSesion = $idUsuarioSesion > 0 ? $ventasApp->obtenerEmpleadoIdPorUsuario($idUsuarioSesion) : null;

$catalogoClientes = $ventasApp->obtenerCatalogos()['clientes'] ?? [];
$formasPagoReembolso = $devolucionesApp->formasPagoParaReembolso();

$puedeCrear = auth_has_permission('DEVOLUCION_CREAR');
$puedeLeer = auth_has_permission('DEVOLUCION_LEER');
$puedeMonedero = $puedeCrear && auth_has_permission('DEVOLUCION_CREDITO_MONEDERO');
$puedeReembolso = $puedeCrear && auth_has_permission('DEVOLUCION_REEMBOLSO_EFECTIVO');

require_once __DIR__ . '/views/header.php';
?>

<header class="admin-header">
    <h2><i class="bi bi-arrow-return-left"></i> Devoluciones</h2>
</header>

<div class="admin-main">
    <?php
    switch ($accion) {
        case 'leer':
        default:
            require __DIR__ . '/views/devoluciones/index.php';
            break;
    }
    ?>
</div>

<?php require_once __DIR__ . '/views/footer.php'; ?>
