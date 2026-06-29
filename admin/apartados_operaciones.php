<?php

require_once __DIR__ . '/../sistema.class.php';
require_once __DIR__ . '/models/apartado_gestion.php';
require_once __DIR__ . '/models/ventas.php';
require_once __DIR__ . '/models/configuracion_general.php';

$accion = isset($_GET['accion']) ? htmlspecialchars((string) $_GET['accion']) : 'leer';

$apartadoGestion = new ApartadoGestion();
$ventasApp = new Ventas();

$usuarioSesion = function_exists('auth_user') ? auth_user() : null;
$idUsuarioSesion = is_array($usuarioSesion) ? (int) ($usuarioSesion['id_usuario'] ?? 0) : 0;
$idEmpleadoSesion = $idUsuarioSesion > 0 ? $ventasApp->obtenerEmpleadoIdPorUsuario($idUsuarioSesion) : null;

$formasPago = $apartadoGestion->formasPagoAbono($apartadoGestion->getDb());
$idFormaPagoDefault = (new ConfiguracionGeneral())->resolverIdFormaPagoDefault();
$catalogoClientes = $ventasApp->obtenerCatalogos()['clientes'] ?? [];
$listaApartados = $apartadoGestion->listarApartados(150, 'activo', null);
$idApartadoUrl = isset($_GET['id_apartado']) ? (int) $_GET['id_apartado'] : 0;
$destinoRaw = isset($_GET['destino']) ? strtolower(trim((string) $_GET['destino'])) : '';
$destinosValidos = ['abono', 'quitar', 'agregar'];
$prefillDestino = in_array($destinoRaw, $destinosValidos, true) ? $destinoRaw : 'abono';

require_once __DIR__ . '/views/header.php';
?>

<header class="admin-header">
    <h2><i class="bi bi-grid-3x2-gap"></i> Apartados activos: abonos y gestion de piezas</h2>
</header>

<div class="admin-main">
    <?php
    switch ($accion) {
        case 'leer':
        default:
            $aoView = __DIR__ . '/apartados_operaciones.view.php';
            if (!is_file($aoView)) {
                http_response_code(500);
                echo 'Falta la vista del modulo: sube el archivo admin/apartados_operaciones.view.php (misma carpeta que apartados_operaciones.php).';
                exit;
            }
            require $aoView;
            break;
    }
    ?>
</div>

<?php require_once __DIR__ . '/views/footer.php'; ?>
